<?php
/* Copyright (C) 2026 Aohzan (fork: https://github.com/Aohzan/dolibarr-micro-entreprise-compta)
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
 * \file    automicroent/class/declarationperiods.class.php
 * \ingroup automicroent
 * \brief   URSSAF declaration periods and deadlines.
 */

/**
 * Declaration periods of a year, with their URSSAF deadline.
 *
 * Quarterly: the quarter is declared by the last day of the following
 * month (30 April, 31 July, 31 October, 31 January). Monthly: by the last
 * day of the following month.
 *
 * @phpstan-type Period array{index: int, months: list<int>, start: string, end: string, deadline: string}
 */
class AmeDeclarationPeriods
{
	const QUARTERLY = 'quarterly';
	const MONTHLY = 'monthly';

	/** The period has not started. */
	const STATUS_UPCOMING = 'upcoming';
	/** The period is running. */
	const STATUS_CURRENT = 'current';
	/** The period is over and its deadline not passed: to declare. */
	const STATUS_DUE = 'due';
	/** The deadline has passed. */
	const STATUS_PAST = 'past';

	/**
	 * Periods of a year.
	 *
	 * @param int    $year        Year
	 * @param string $periodicity QUARTERLY or MONTHLY
	 * @return list<Period>
	 */
	public static function forYear(int $year, string $periodicity): array
	{
		$length = ($periodicity === self::MONTHLY) ? 1 : 3;
		$periods = array();
		for ($first = 1, $index = 1; $first <= 12; $first += $length, $index++) {
			$last = $first + $length - 1;
			$periods[] = array(
				'index' => $index,
				'months' => range($first, $last),
				'start' => sprintf('%04d-%02d-01', $year, $first),
				'end' => self::lastDay($year, $last),
				'deadline' => ($last === 12) ? self::lastDay($year + 1, 1) : self::lastDay($year, $last + 1),
			);
		}
		return $periods;
	}

	/**
	 * Status of a period on a given day.
	 *
	 * @param Period $period Period
	 * @param string $today  Day as Y-m-d
	 * @return string One of the STATUS_* constants
	 */
	public static function status(array $period, string $today): string
	{
		if ($today < $period['start']) {
			return self::STATUS_UPCOMING;
		}
		if ($today <= $period['end']) {
			return self::STATUS_CURRENT;
		}
		if ($today <= $period['deadline']) {
			return self::STATUS_DUE;
		}
		return self::STATUS_PAST;
	}

	/**
	 * Period to show first: the one to declare, else the running one, else the last one.
	 *
	 * @param list<Period> $periods Periods of a year
	 * @param string       $today   Day as Y-m-d
	 * @return int Index of the period
	 */
	public static function focused(array $periods, string $today): int
	{
		foreach (array(self::STATUS_DUE, self::STATUS_CURRENT) as $wanted) {
			foreach ($periods as $period) {
				if (self::status($period, $today) === $wanted) {
					return $period['index'];
				}
			}
		}
		$first = reset($periods);
		$last = end($periods);
		return ($today < $first['start']) ? $first['index'] : $last['index'];
	}

	/**
	 * Last day of a month.
	 *
	 * @param int $year  Year
	 * @param int $month Month (1-12)
	 * @return string Y-m-d
	 */
	private static function lastDay(int $year, int $month): string
	{
		return sprintf('%04d-%02d-%02d', $year, $month, cal_days_in_month(CAL_GREGORIAN, $month, $year));
	}
}
