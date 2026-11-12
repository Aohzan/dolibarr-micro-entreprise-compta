<?php
/* Copyright (C) 2013-2018 Fabrice Delliaux <netbox253@gmail.com>
 * Copyright (C) 2026 Aohzan (fork: https://github.com/Aohzan/dolibarr-micro-entreprise-compta)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    automicroent/class/raterepository.class.php
 * \ingroup automicroent
 * \brief   Storage of the contribution rates (table llx_ame_rates).
 */

require_once __DIR__.'/contributioncalculator.class.php';
require_once __DIR__.'/taxcatalog.class.php';

/**
 * Contribution rates per year, stored in llx_ame_rates.
 *
 * A rate row is identified across years by its category (level), base
 * (type) and name. A record with level 0 marks a year that has no rate yet.
 * A NULL rate means "not applicable this year". The legacy columns first,
 * second and accuracy are kept at 0 so that sql/data.sql never migrates a
 * record twice.
 *
 * @phpstan-type Row array{key: string, level: int, type: int, name: string, comment: string, cells: array<int, array{id: int, rate: ?float}>}
 */
class AmeRateRepository
{
	/** Name given by the legacy module to unnamed rates. */
	const LEGACY_UNNAMED = 'AME_UPDATE';

	/** Previous year as the source of a new year. */
	const SOURCE_PREVIOUS = 'previous';
	/** Official catalog as the source of a new year. */
	const SOURCE_CATALOG = 'catalog';
	/** No rate for a new year. */
	const SOURCE_EMPTY = 'empty';

	/**
	 * @var DoliDB Database handler
	 */
	private $db;

	/**
	 * @var int Entity
	 */
	private $entity;

	/**
	 * @var string Table name
	 */
	private $table;

	/**
	 * @param DoliDB $db     Database handler
	 * @param int    $entity Current entity
	 */
	public function __construct($db, int $entity)
	{
		$this->db = $db;
		$this->entity = $entity;
		$this->table = MAIN_DB_PREFIX.'ame_rates';
	}

	/**
	 * Key identifying a rate row across years.
	 *
	 * @param int    $level Category
	 * @param int    $type  Base
	 * @param string $name  Name
	 * @return string
	 */
	public static function rowKey(int $level, int $type, string $name): string
	{
		return $level.'-'.$type.'-'.substr(md5($name), 0, 12);
	}

	/**
	 * Years that have rates or a year marker, most recent first.
	 *
	 * @return list<int>
	 */
	public function years(): array
	{
		$years = array();
		$resql = $this->query('SELECT DISTINCT year FROM '.$this->table.' WHERE entity = '.$this->entity.' ORDER BY year DESC');
		while ($obj = $this->db->fetch_object($resql)) {
			$years[] = (int) $obj->year;
		}
		return $years;
	}

	/**
	 * Applicable rates of a year.
	 *
	 * @param int $year Year
	 * @return list<array{name: string, level: int, type: int, rate: float}>
	 */
	public function rates(int $year): array
	{
		$rates = array();
		$sql = 'SELECT name, level, type, rate FROM '.$this->table;
		$sql .= ' WHERE entity = '.$this->entity.' AND year = '.$year.' AND level > 0 AND rate IS NOT NULL';
		$sql .= ' ORDER BY level, position, rowid';
		$resql = $this->query($sql);
		while ($obj = $this->db->fetch_object($resql)) {
			$rates[] = array('name' => $obj->name, 'level' => (int) $obj->level, 'type' => (int) $obj->type, 'rate' => (float) $obj->rate);
		}
		return $rates;
	}

	/**
	 * Every rate row with its value per year, ordered by category.
	 *
	 * @return array<string, Row> Rows keyed by row key
	 */
	public function matrix(): array
	{
		$rows = array();
		$sql = 'SELECT rowid, year, name, comment, level, type, rate FROM '.$this->table;
		$sql .= ' WHERE entity = '.$this->entity.' AND level > 0';
		$sql .= ' ORDER BY level, position, type, name, year';
		$resql = $this->query($sql);
		while ($obj = $this->db->fetch_object($resql)) {
			$key = self::rowKey((int) $obj->level, (int) $obj->type, $obj->name);
			// The legacy module allowed duplicates: show each as its own row so that it can be fixed.
			while (isset($rows[$key]['cells'][(int) $obj->year])) {
				$key .= '-'.(int) $obj->rowid;
			}
			if (!isset($rows[$key])) {
				$rows[$key] = array(
					'key' => $key,
					'level' => (int) $obj->level,
					'type' => (int) $obj->type,
					'name' => $obj->name,
					'comment' => (string) $obj->comment,
					'cells' => array(),
				);
			}
			if ((string) $obj->comment !== '') {
				$rows[$key]['comment'] = (string) $obj->comment;
			}
			$rows[$key]['cells'][(int) $obj->year] = array(
				'id' => (int) $obj->rowid,
				'rate' => $obj->rate === null ? null : (float) $obj->rate,
			);
		}
		return $rows;
	}

	/**
	 * Save the values of the configuration table.
	 *
	 * @param array<mixed> $values Row key => year => rate ('' when not applicable), as posted
	 * @return array{changed: int, invalid: list<string>} Number of changed cells, and the cells left
	 *                                                    unchanged because their value is not a rate
	 */
	public function saveRates(array $values): array
	{
		$rows = $this->matrix();
		$years = $this->years();
		$changed = 0;
		$invalid = array();

		$this->db->begin();
		foreach ($values as $key => $cells) {
			if (!is_string($key) || !isset($rows[$key]) || !is_array($cells)) {
				continue;
			}
			$row = $rows[$key];
			foreach ($cells as $year => $value) {
				$year = (int) $year;
				if (!in_array($year, $years, true)) {
					continue;
				}
				$value = is_string($value) ? trim($value) : '';
				$rate = self::parseRate($value);
				if ($value !== '' && $rate === null) {
					$invalid[] = $row['name'].' '.$year;
					continue;
				}
				$current = $row['cells'][$year] ?? null;
				if ($current !== null) {
					if ($current['rate'] === $rate) {
						continue;
					}
					$this->query('UPDATE '.$this->table.' SET rate = '.self::sqlRate($rate).', first = 0, second = 0 WHERE rowid = '.$current['id']);
					$changed++;
				} elseif ($rate !== null) {
					$this->insert($year, $row['level'], $row['type'], $row['name'], $row['comment'], $rate);
					$changed++;
				}
			}
		}
		$this->db->commit();
		return array('changed' => $changed, 'invalid' => $invalid);
	}

	/**
	 * Create a year.
	 *
	 * @param int       $year      Year to create
	 * @param string    $source    One of the SOURCE_* constants
	 * @param string    $profile   Activity profile (catalog source)
	 * @param bool      $incomeTax Income tax in full discharge (catalog source)
	 * @param Translate $langs     Translations, for the names of catalog rates
	 * @return int Number of rates created
	 */
	public function addYear(int $year, string $source, string $profile, bool $incomeTax, $langs): int
	{
		if (in_array($year, $this->years(), true)) {
			throw new InvalidArgumentException('AME_ErrorYearExists');
		}
		$lines = array();
		if ($source === self::SOURCE_CATALOG) {
			foreach (AmeTaxCatalog::lines($profile, $year, $incomeTax) as $line) {
				$lines[] = array('level' => $line['level'], 'type' => $line['type'], 'name' => $langs->transnoentitiesnoconv($line['key']), 'comment' => '', 'rate' => $line['rate']);
			}
		} elseif ($source === self::SOURCE_PREVIOUS) {
			$previous = null;
			foreach ($this->years() as $candidate) {
				if ($candidate < $year) {
					$previous = $candidate;
					break;
				}
			}
			foreach ($this->matrix() as $row) {
				$rate = ($previous !== null) ? ($row['cells'][$previous]['rate'] ?? null) : null;
				if ($rate !== null) {
					$lines[] = array('level' => $row['level'], 'type' => $row['type'], 'name' => $row['name'], 'comment' => $row['comment'], 'rate' => $rate);
				}
			}
		}

		$this->db->begin();
		$this->insert($year, 0, 0, self::LEGACY_UNNAMED, '', null);
		foreach ($lines as $line) {
			$this->insert($year, $line['level'], $line['type'], $line['name'], $line['comment'], $line['rate']);
		}
		$this->db->commit();
		return count($lines);
	}

	/**
	 * Delete a year and all its rates.
	 *
	 * @param int $year Year
	 * @return void
	 */
	public function deleteYear(int $year): void
	{
		$this->deleteYearRecords($year);
	}

	/**
	 * Create a rate row, or change one in every year.
	 *
	 * @param string|null $key     Key of the row to change, null to create one
	 * @param int         $level   Category
	 * @param int         $type    Base
	 * @param string      $name    Name
	 * @param string      $comment Comment
	 * @return string Key of the saved row
	 */
	public function saveRow(?string $key, int $level, int $type, string $name, string $comment): string
	{
		$rows = $this->matrix();
		$newKey = self::rowKey($level, $type, $name);
		if ($newKey !== $key && isset($rows[$newKey])) {
			throw new InvalidArgumentException('AME_ErrorRowExists');
		}

		$this->db->begin();
		if ($key !== null && isset($rows[$key])) {
			$ids = array_column($rows[$key]['cells'], 'id');
			$sql = 'UPDATE '.$this->table.' SET level = '.$level.', type = '.$type;
			$sql .= ", name = '".$this->db->escape($name)."', comment = ".$this->sqlComment($comment);
			$sql .= ' WHERE rowid IN ('.implode(',', array_map('intval', $ids)).')';
			$this->query($sql);
		} else {
			$years = $this->years();
			if (!$years) {
				$years = array((int) dol_print_date(dol_now(), '%Y'));
				$this->insert($years[0], 0, 0, self::LEGACY_UNNAMED, '', null);
			}
			// The row needs a record to exist: not applicable in the latest year.
			$this->insert($years[0], $level, $type, $name, $comment, null);
		}
		$this->db->commit();
		return $newKey;
	}

	/**
	 * Delete a rate row in every year.
	 *
	 * @param string $key Row key
	 * @return void
	 */
	public function deleteRow(string $key): void
	{
		$rows = $this->matrix();
		if (!isset($rows[$key])) {
			return;
		}
		$ids = array_map('intval', array_column($rows[$key]['cells'], 'id'));
		$this->query('DELETE FROM '.$this->table.' WHERE entity = '.$this->entity.' AND rowid IN ('.implode(',', $ids).')');
	}

	/**
	 * Parse a rate typed by the user (comma or dot, "%" allowed).
	 *
	 * @param string $value Typed value
	 * @return float|null Rate, null when empty or invalid
	 */
	public static function parseRate(string $value): ?float
	{
		$value = trim(str_replace(array(',', '%', ' '), array('.', '', ''), $value));
		if ($value === '' || !is_numeric($value)) {
			return null;
		}
		$rate = round((float) $value, 4);
		return ($rate < 0 || $rate > 100) ? null : $rate;
	}

	/**
	 * Insert a record.
	 *
	 * @param int        $year    Year
	 * @param int        $level   Category, 0 for a year marker
	 * @param int        $type    Base
	 * @param string     $name    Name
	 * @param string     $comment Comment
	 * @param float|null $rate    Rate, null when not applicable
	 * @return void
	 */
	private function insert(int $year, int $level, int $type, string $name, string $comment, ?float $rate): void
	{
		$sql = 'INSERT INTO '.$this->table.' (entity, year, level, type, name, comment, rate, first, second, accuracy)';
		$sql .= ' VALUES ('.$this->entity.', '.$year.', '.$level.', '.$type.", '".$this->db->escape($name)."', ";
		$sql .= $this->sqlComment($comment).', '.self::sqlRate($rate).', 0, 0, 2)';
		$this->query($sql);
	}

	/**
	 * Delete every record of a year.
	 *
	 * @param int $year Year
	 * @return void
	 */
	private function deleteYearRecords(int $year): void
	{
		$this->query('DELETE FROM '.$this->table.' WHERE entity = '.$this->entity.' AND year = '.$year);
	}

	/**
	 * SQL literal of a comment.
	 *
	 * @param string $comment Comment
	 * @return string
	 */
	private function sqlComment(string $comment): string
	{
		return ($comment === '') ? 'NULL' : "'".$this->db->escape($comment)."'";
	}

	/**
	 * SQL literal of a rate.
	 *
	 * @param float|null $rate Rate
	 * @return string
	 */
	private static function sqlRate(?float $rate): string
	{
		return ($rate === null) ? 'NULL' : number_format($rate, 4, '.', '');
	}

	/**
	 * Run a query, or throw with the database error.
	 *
	 * @param string $sql SQL
	 * @return resource|object Result
	 */
	private function query(string $sql)
	{
		$resql = $this->db->query($sql);
		if (!$resql) {
			if ($this->db->transaction_opened) {
				$this->db->rollback();
			}
			throw new RuntimeException($this->db->lasterror());
		}
		return $resql;
	}
}
