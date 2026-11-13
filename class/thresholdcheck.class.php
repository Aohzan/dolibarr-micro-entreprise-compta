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
 * \file    automicroent/class/thresholdcheck.class.php
 * \ingroup automicroent
 * \brief   Turnover compared with the micro-entreprise ceilings and the VAT thresholds.
 */

require_once __DIR__.'/contributioncalculator.class.php';

/**
 * Compares the turnover of a year with its ceilings.
 *
 * Thresholds are stored as rows of llx_ame_rates with the category
 * LEVEL_THRESHOLD: the base says which turnover they apply to (sales:
 * sales and services together, services: services only, as the law
 * counts them), the value is an amount in euros.
 *
 * @phpstan-type Threshold array{name: string, type: int, rate: float}
 * @phpstan-type Check array{name: string, type: int, amount: float, limit: float, ratio: float, state: string}
 */
class AmeThresholdCheck
{
	/** Below the alert percentage. */
	const STATE_OK = 'ok';
	/** At or above the alert percentage. */
	const STATE_WARNING = 'warning';
	/** Above the threshold. */
	const STATE_OVER = 'over';

	/**
	 * Compare a turnover with thresholds.
	 *
	 * @param float           $sales      Turnover of sales
	 * @param float           $services   Turnover of services
	 * @param list<Threshold> $thresholds Thresholds of the year
	 * @param int             $alert      Alert percentage (e.g. 80)
	 * @return list<Check>
	 */
	public static function check(float $sales, float $services, array $thresholds, int $alert): array
	{
		$checks = array();
		foreach ($thresholds as $threshold) {
			$limit = (float) $threshold['rate'];
			if ($limit <= 0) {
				continue;
			}
			// A sales ceiling caps the whole turnover; a services one, the services only.
			$amount = ((int) $threshold['type'] === AmeContributionCalculator::BASE_SERVICES) ? $services : $sales + $services;
			$ratio = $amount / $limit;
			if ($amount > $limit) {
				$state = self::STATE_OVER;
			} elseif ($ratio * 100 >= $alert) {
				$state = self::STATE_WARNING;
			} else {
				$state = self::STATE_OK;
			}
			$checks[] = array(
				'name' => $threshold['name'],
				'type' => (int) $threshold['type'],
				'amount' => round($amount, 2),
				'limit' => $limit,
				'ratio' => $ratio,
				'state' => $state,
			);
		}
		return $checks;
	}

	/**
	 * Worst state of a list of checks.
	 *
	 * @param list<Check> $checks Checks
	 * @return string One of the STATE_* constants
	 */
	public static function worst(array $checks): string
	{
		$states = array_column($checks, 'state');
		if (in_array(self::STATE_OVER, $states, true)) {
			return self::STATE_OVER;
		}
		return in_array(self::STATE_WARNING, $states, true) ? self::STATE_WARNING : self::STATE_OK;
	}
}
