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
 * \file    automicroent/class/paymentrepository.class.php
 * \ingroup automicroent
 * \brief   Customer payments read for the turnover.
 */

require_once __DIR__.'/turnovercalculator.class.php';

/**
 * Reads customer payments and the invoices they settle.
 */
class AmePaymentRepository
{
	/**
	 * @var DoliDB Database handler
	 */
	private $db;

	/**
	 * @var string SQL list of the entities whose invoices are read
	 */
	private $entities;

	/**
	 * @param DoliDB $db       Database handler
	 * @param string $entities SQL list of entities, from getEntity('invoice')
	 */
	public function __construct($db, string $entities)
	{
		$this->db = $db;
		$this->entities = $entities;
	}

	/**
	 * Receipts of payments received between two years (included).
	 *
	 * @param int                 $fromYear First year
	 * @param int                 $toYear   Last year
	 * @param AmeTurnoverCalculator $calculator Calculator
	 * @return list<array{payment_id: int, date: string, invoice_id: int, invoice_ref: string, amount: float, partial: bool, activities: array<string, float>}>
	 */
	public function receipts(int $fromYear, int $toYear, AmeTurnoverCalculator $calculator): array
	{
		$from = sprintf('%04d-01-01 00:00:00', $fromYear);
		$to = sprintf('%04d-01-01 00:00:00', $toYear + 1);

		// Invoices paid in the window...
		$sql = 'SELECT DISTINCT pf.fk_facture AS invoice_id';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'paiement_facture AS pf';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'paiement AS p ON p.rowid = pf.fk_paiement';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'facture AS f ON f.rowid = pf.fk_facture';
		$sql .= ' WHERE f.entity IN ('.$this->entities.')';
		$sql .= " AND p.datep >= '".$this->db->escape($from)."' AND p.datep < '".$this->db->escape($to)."'";
		$ids = array();
		$resql = $this->query($sql);
		while ($obj = $this->db->fetch_object($resql)) {
			$ids[] = (int) $obj->invoice_id;
		}
		if (!$ids) {
			return array();
		}

		$invoices = array();
		$allocations = array();
		foreach (array_chunk($ids, 500) as $chunk) {
			$in = implode(',', $chunk);

			$resql = $this->query('SELECT rowid, ref, type, total_ttc FROM '.MAIN_DB_PREFIX.'facture WHERE rowid IN ('.$in.')');
			while ($obj = $this->db->fetch_object($resql)) {
				$invoices[(int) $obj->rowid] = array('ref' => (string) $obj->ref, 'type' => (int) $obj->type, 'total_ttc' => (float) $obj->total_ttc, 'lines' => array());
			}

			$sql = 'SELECT fk_facture, product_type, fk_product, total_ht, total_ttc, info_bits, fk_remise_except FROM '.MAIN_DB_PREFIX.'facturedet';
			$sql .= ' WHERE fk_facture IN ('.$in.') ORDER BY fk_facture, rang, rowid';
			$resql = $this->query($sql);
			while ($obj = $this->db->fetch_object($resql)) {
				$invoices[(int) $obj->fk_facture]['lines'][] = array(
					'product_type' => (int) $obj->product_type,
					'fk_product' => (int) $obj->fk_product,
					'total_ht' => (float) $obj->total_ht,
					'total_ttc' => (float) $obj->total_ttc,
					// A deposit or a credit note deducted from this invoice.
					'discount' => ((int) $obj->fk_remise_except > 0 || ((int) $obj->info_bits & 2)),
				);
			}

			// ...with all their payments: earlier ones decide what a later one settles.
			$sql = 'SELECT p.rowid AS payment_id, p.datep, pf.fk_facture AS invoice_id, pf.amount';
			$sql .= ' FROM '.MAIN_DB_PREFIX.'paiement_facture AS pf';
			$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'paiement AS p ON p.rowid = pf.fk_paiement';
			$sql .= ' WHERE pf.fk_facture IN ('.$in.')';
			$resql = $this->query($sql);
			while ($obj = $this->db->fetch_object($resql)) {
				$allocations[] = array(
					'payment_id' => (int) $obj->payment_id,
					// The date as stored, like the legacy module: no time zone shift.
					'date' => substr((string) $obj->datep, 0, 10),
					'invoice_id' => (int) $obj->invoice_id,
					'amount' => (float) $obj->amount,
				);
			}
		}

		$receipts = array();
		foreach ($calculator->receipts($invoices, $allocations) as $receipt) {
			if ($receipt['date'] >= substr($from, 0, 10) && $receipt['date'] < substr($to, 0, 10)) {
				$receipts[] = $receipt;
			}
		}
		return $receipts;
	}

	/**
	 * First year with a customer payment.
	 *
	 * @return int|null
	 */
	public function firstYear(): ?int
	{
		$sql = 'SELECT MIN(p.datep) AS first FROM '.MAIN_DB_PREFIX.'paiement AS p';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'paiement_facture AS pf ON pf.fk_paiement = p.rowid';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'facture AS f ON f.rowid = pf.fk_facture';
		$sql .= ' WHERE f.entity IN ('.$this->entities.')';
		$obj = $this->db->fetch_object($this->query($sql));
		return ($obj && $obj->first) ? (int) substr((string) $obj->first, 0, 4) : null;
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
			throw new RuntimeException($this->db->lasterror());
		}
		return $resql;
	}
}
