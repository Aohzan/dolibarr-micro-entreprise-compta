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
 * \file    automicroent/class/taxcatalog.class.php
 * \ingroup automicroent
 * \brief   Official micro-entrepreneur rates, per year and activity.
 */

require_once __DIR__.'/contributioncalculator.class.php';

/**
 * Official rates a user can start from, per activity profile and year.
 *
 * Sources (checked on 2026-11-12):
 * - Social contributions: service-public.gouv.fr F37353, checked 2026-02-21
 *   (2026: sales 12.3 %, BIC services 21.2 %, non-regulated liberal
 *   professions 25.6 %, Cipav 23.2 %); 2025 non-regulated liberal
 *   professions 24.6 % (décret 2024-484).
 * - Income tax paid in full discharge (versement libératoire):
 *   service-public.gouv.fr F23267 (1 %, 1.7 %, 2.2 %).
 * - Vocational training (CFP): traders 0.1 %, craftsmen 0.3 %, liberal
 *   professions 0.2 % (URSSAF).
 * - Chamber fees: CCI sales 0.015 %, CCI services 0.044 %, CMA services
 *   0.48 % (URSSAF).
 *
 * - Ceilings: micro-entreprise 188,700 / 77,700 EUR until 2025, 203,100 /
 *   83,600 EUR from 2026; VAT franchise 85,000 (increased 93,500) EUR for
 *   sales and 37,500 (41,250) EUR for services in 2025 and 2026
 *   (economie.gouv.fr, impots.gouv.fr, BOI-TVA-DECLA-40-10-10).
 *
 * Users must check the rates of their own situation: the catalog only
 * fills the configuration, it can be edited afterwards.
 *
 */
class AmeTaxCatalog
{
	/** Trader selling goods. */
	const PROFILE_SALES = 'sales';
	/** Trader providing commercial services (BIC). */
	const PROFILE_BIC_SERVICES = 'bic_services';
	/** Craftsman providing services (BIC, CMA). */
	const PROFILE_CRAFT = 'craft';
	/** Non-regulated liberal profession (BNC, SSI). */
	const PROFILE_BNC = 'bnc';
	/** Regulated liberal profession affiliated to the Cipav (BNC). */
	const PROFILE_CIPAV = 'cipav';

	/**
	 * Rates per year: profile => list of [level, type, translation key, rate].
	 * A year inherits the lines of the closest earlier year it does not override.
	 */
	const RATES = array(
		2025 => array(
			self::PROFILE_SALES => array(
				array(AmeContributionCalculator::LEVEL_SOCIAL, AmeContributionCalculator::BASE_SALES, 'AME_CatalogSocial', 12.3),
				array(AmeContributionCalculator::LEVEL_CHAMBER, AmeContributionCalculator::BASE_SALES, 'AME_CatalogChamberCci', 0.015),
				array(AmeContributionCalculator::LEVEL_TRAINING, AmeContributionCalculator::BASE_ALL, 'AME_CatalogTraining', 0.1),
				array(AmeContributionCalculator::LEVEL_INCOME_TAX, AmeContributionCalculator::BASE_SALES, 'AME_CatalogIncomeTax', 1.0),
			),
			self::PROFILE_BIC_SERVICES => array(
				array(AmeContributionCalculator::LEVEL_SOCIAL, AmeContributionCalculator::BASE_SERVICES, 'AME_CatalogSocial', 21.2),
				array(AmeContributionCalculator::LEVEL_CHAMBER, AmeContributionCalculator::BASE_SERVICES, 'AME_CatalogChamberCci', 0.044),
				array(AmeContributionCalculator::LEVEL_TRAINING, AmeContributionCalculator::BASE_ALL, 'AME_CatalogTraining', 0.1),
				array(AmeContributionCalculator::LEVEL_INCOME_TAX, AmeContributionCalculator::BASE_SERVICES, 'AME_CatalogIncomeTax', 1.7),
			),
			self::PROFILE_CRAFT => array(
				array(AmeContributionCalculator::LEVEL_SOCIAL, AmeContributionCalculator::BASE_SERVICES, 'AME_CatalogSocial', 21.2),
				array(AmeContributionCalculator::LEVEL_CHAMBER, AmeContributionCalculator::BASE_SERVICES, 'AME_CatalogChamberCma', 0.48),
				array(AmeContributionCalculator::LEVEL_TRAINING, AmeContributionCalculator::BASE_ALL, 'AME_CatalogTraining', 0.3),
				array(AmeContributionCalculator::LEVEL_INCOME_TAX, AmeContributionCalculator::BASE_SERVICES, 'AME_CatalogIncomeTax', 1.7),
			),
			self::PROFILE_BNC => array(
				array(AmeContributionCalculator::LEVEL_SOCIAL, AmeContributionCalculator::BASE_SERVICES, 'AME_CatalogSocial', 24.6),
				array(AmeContributionCalculator::LEVEL_TRAINING, AmeContributionCalculator::BASE_ALL, 'AME_CatalogTraining', 0.2),
				array(AmeContributionCalculator::LEVEL_INCOME_TAX, AmeContributionCalculator::BASE_SERVICES, 'AME_CatalogIncomeTax', 2.2),
			),
			self::PROFILE_CIPAV => array(
				array(AmeContributionCalculator::LEVEL_SOCIAL, AmeContributionCalculator::BASE_SERVICES, 'AME_CatalogSocial', 23.2),
				array(AmeContributionCalculator::LEVEL_TRAINING, AmeContributionCalculator::BASE_ALL, 'AME_CatalogTraining', 0.2),
				array(AmeContributionCalculator::LEVEL_INCOME_TAX, AmeContributionCalculator::BASE_SERVICES, 'AME_CatalogIncomeTax', 2.2),
			),
		),
		2026 => array(
			self::PROFILE_BNC => array(
				array(AmeContributionCalculator::LEVEL_SOCIAL, AmeContributionCalculator::BASE_SERVICES, 'AME_CatalogSocial', 25.6),
				array(AmeContributionCalculator::LEVEL_TRAINING, AmeContributionCalculator::BASE_ALL, 'AME_CatalogTraining', 0.2),
				array(AmeContributionCalculator::LEVEL_INCOME_TAX, AmeContributionCalculator::BASE_SERVICES, 'AME_CatalogIncomeTax', 2.2),
			),
		),
	);

	/**
	 * Ceilings per year: [base, translation key, amount in euros]. A sales
	 * ceiling caps the whole turnover, a services one the services only.
	 */
	const THRESHOLDS = array(
		2025 => array(
			array(AmeContributionCalculator::BASE_SALES, 'AME_CatalogMicroCeiling', 188700),
			array(AmeContributionCalculator::BASE_SERVICES, 'AME_CatalogMicroCeiling', 77700),
			array(AmeContributionCalculator::BASE_SALES, 'AME_CatalogVatThreshold', 85000),
			array(AmeContributionCalculator::BASE_SALES, 'AME_CatalogVatThresholdIncreased', 93500),
			array(AmeContributionCalculator::BASE_SERVICES, 'AME_CatalogVatThreshold', 37500),
			array(AmeContributionCalculator::BASE_SERVICES, 'AME_CatalogVatThresholdIncreased', 41250),
		),
		2026 => array(
			array(AmeContributionCalculator::BASE_SALES, 'AME_CatalogMicroCeiling', 203100),
			array(AmeContributionCalculator::BASE_SERVICES, 'AME_CatalogMicroCeiling', 83600),
			array(AmeContributionCalculator::BASE_SALES, 'AME_CatalogVatThreshold', 85000),
			array(AmeContributionCalculator::BASE_SALES, 'AME_CatalogVatThresholdIncreased', 93500),
			array(AmeContributionCalculator::BASE_SERVICES, 'AME_CatalogVatThreshold', 37500),
			array(AmeContributionCalculator::BASE_SERVICES, 'AME_CatalogVatThresholdIncreased', 41250),
		),
	);

	/**
	 * Ceilings of a profile for a year. Every profile gets both families:
	 * a services business can also sell goods, and the other way round.
	 *
	 * @param string $profile Activity profile
	 * @param int    $year    Year (from 2025: earlier ceilings are not in the catalog)
	 * @return list<array{type: int, key: string, amount: int}>
	 */
	public static function thresholds(string $profile, int $year): array
	{
		if ($year < 2025 || !in_array($profile, self::profiles(), true)) {
			return array();
		}
		$source = 2025;
		foreach (array_keys(self::THRESHOLDS) as $candidate) {
			if ($candidate <= $year) {
				$source = $candidate;
			}
		}
		$lines = array();
		foreach (self::THRESHOLDS[$source] as $line) {
			$lines[] = array('type' => $line[0], 'key' => $line[1], 'amount' => $line[2]);
		}
		return $lines;
	}

	/**
	 * Activity profiles, in display order.
	 *
	 * @return list<string>
	 */
	public static function profiles(): array
	{
		return array(self::PROFILE_SALES, self::PROFILE_BIC_SERVICES, self::PROFILE_CRAFT, self::PROFILE_BNC, self::PROFILE_CIPAV);
	}

	/**
	 * Catalog year used for a year: the closest earlier one, or the first.
	 *
	 * @param int    $year    Wanted year
	 * @param string $profile Activity profile
	 * @return int
	 */
	public static function sourceYear(int $year, string $profile): int
	{
		$years = array_keys(self::RATES);
		$source = $years[0];
		foreach ($years as $candidate) {
			if ($candidate <= $year && isset(self::RATES[$candidate][$profile])) {
				$source = $candidate;
			}
		}
		return $source;
	}

	/**
	 * Catalog lines of a profile for a year.
	 *
	 * @param string $profile   Activity profile
	 * @param int    $year      Year
	 * @param bool   $incomeTax Whether the user pays income tax in full discharge (versement libératoire)
	 * @return list<array{level: int, type: int, key: string, rate: float}>
	 */
	public static function lines(string $profile, int $year, bool $incomeTax): array
	{
		if (!in_array($profile, self::profiles(), true)) {
			return array();
		}
		$lines = array();
		foreach (self::RATES[self::sourceYear($year, $profile)][$profile] as $line) {
			list($level, $type, $key, $rate) = $line;
			if ($level === AmeContributionCalculator::LEVEL_INCOME_TAX && !$incomeTax) {
				continue;
			}
			$lines[] = array('level' => $level, 'type' => $type, 'key' => $key, 'rate' => $rate);
		}
		return $lines;
	}
}
