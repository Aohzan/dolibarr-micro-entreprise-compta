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
 * \file    automicroent/class/contributioncalculator.class.php
 * \ingroup automicroent
 * \brief   Estimated contributions of a declaration period.
 */

require_once __DIR__.'/turnovercalculator.class.php';

/**
 * Estimates the contributions due on a declared turnover.
 *
 * The declared turnover is rounded to the euro, as on the URSSAF form. Each
 * rate applies to the turnover of its base (sales, services or both), and
 * the results are summed and rounded per category.
 *
 * @phpstan-type Rate array{name: string, level: int, type: int, rate: float}
 * @phpstan-type Line array{name: string, level: int, type: int, rate: float, base: int, amount: float}
 */
class AmeContributionCalculator
{
	/** Categories (column "level" of llx_ame_rates). */
	const LEVEL_SOCIAL = 1;
	const LEVEL_CHAMBER = 2;
	const LEVEL_TRAINING = 3;
	const LEVEL_OTHER = 4;
	const LEVEL_INCOME_TAX = 5;
	/** Not a rate: a turnover ceiling in euros (see AmeThresholdCheck). */
	const LEVEL_THRESHOLD = 10;

	/** Bases (column "type" of llx_ame_rates). */
	const BASE_SALES = 0;
	const BASE_SERVICES = 1;
	const BASE_ALL = 2;
	const BASE_SERVICES_BIC = 3;
	const BASE_SERVICES_BNC = 4;

	/**
	 * Categories, in display order; the last one holds the ceilings.
	 *
	 * @return list<int>
	 */
	public static function levels(): array
	{
		return array(self::LEVEL_SOCIAL, self::LEVEL_CHAMBER, self::LEVEL_TRAINING, self::LEVEL_INCOME_TAX, self::LEVEL_OTHER, self::LEVEL_THRESHOLD);
	}

	/**
	 * Bases, in display order.
	 *
	 * @return list<int>
	 */
	public static function bases(): array
	{
		return array(self::BASE_SALES, self::BASE_SERVICES, self::BASE_SERVICES_BIC, self::BASE_SERVICES_BNC, self::BASE_ALL);
	}

	/**
	 * Turnover to declare, rounded to the euro per activity.
	 *
	 * @param array<string, float> $turnover Activity => amount
	 * @return array<string, int>
	 */
	public static function declared(array $turnover): array
	{
		$declared = array();
		foreach ($turnover as $activity => $amount) {
			$declared[$activity] = (int) round($amount, 0, PHP_ROUND_HALF_UP);
		}
		return $declared;
	}

	/**
	 * Declared turnover a base applies to.
	 *
	 * @param array<string, int> $declared Activity => declared amount
	 * @param int                $base     One of the BASE_* constants
	 * @return int
	 */
	public static function baseAmount(array $declared, int $base): int
	{
		$sum = 0;
		foreach ($declared as $activity => $amount) {
			if (self::inBase((string) $activity, $base)) {
				$sum += $amount;
			}
		}
		return $sum;
	}

	/**
	 * Whether an activity belongs to a base.
	 *
	 * @param string $activity Activity
	 * @param int    $base     One of the BASE_* constants
	 * @return bool
	 */
	public static function inBase(string $activity, int $base): bool
	{
		switch ($base) {
			case self::BASE_ALL:
				return true;
			case self::BASE_SALES:
				return $activity === AmeTurnoverCalculator::SALE;
			case self::BASE_SERVICES:
				return $activity !== AmeTurnoverCalculator::SALE;
			case self::BASE_SERVICES_BIC:
				return $activity === AmeTurnoverCalculator::SERVICE_BIC;
			case self::BASE_SERVICES_BNC:
				return $activity === AmeTurnoverCalculator::SERVICE_BNC;
			default:
				return false;
		}
	}

	/**
	 * Round a category total the way URSSAF does for that year.
	 *
	 * Until 2015 the social contributions, the chamber fees and the other
	 * contributions were rounded down; since 2016 every category is rounded to the nearest euro.
	 *
	 * @param float $amount Sum of the category
	 * @param int   $level  Category
	 * @param int   $year   Year of the period
	 * @return int
	 */
	public static function roundLevel(float $amount, int $level, int $year): int
	{
		if ($year < 2016 && in_array($level, array(self::LEVEL_SOCIAL, self::LEVEL_CHAMBER, self::LEVEL_OTHER), true)) {
			return (int) floor($amount + 1e-9);
		}
		return (int) round($amount, 0, PHP_ROUND_HALF_UP);
	}

	/**
	 * Estimate the contributions of a period.
	 *
	 * @param array<string, float> $turnover Activity => turnover of the period
	 * @param list<Rate>           $rates    Rates of the year (not applicable rates left out)
	 * @param int                  $year     Year of the period
	 * @return array{declared: array<string, int>, lines: list<Line>, levels: array<int, int>, total: int}
	 */
	public static function estimate(array $turnover, array $rates, int $year): array
	{
		$declared = self::declared($turnover);
		$lines = array();
		$sums = array();
		foreach ($rates as $rate) {
			$base = self::baseAmount($declared, (int) $rate['type']);
			$amount = $base * (float) $rate['rate'] / 100;
			$lines[] = array(
				'name' => $rate['name'],
				'level' => (int) $rate['level'],
				'type' => (int) $rate['type'],
				'rate' => (float) $rate['rate'],
				'base' => $base,
				'amount' => round($amount, 2),
			);
			$sums[(int) $rate['level']] = ($sums[(int) $rate['level']] ?? 0) + $amount;
		}

		$levels = array();
		foreach ($sums as $level => $sum) {
			$levels[$level] = self::roundLevel($sum, $level, $year);
		}

		return array(
			'declared' => $declared,
			'lines' => $lines,
			'levels' => $levels,
			'total' => array_sum($levels),
		);
	}
}
