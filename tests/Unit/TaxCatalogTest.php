<?php
/* Copyright (C) 2026 Aohzan (fork: https://github.com/Aohzan/dolibarr-micro-entreprise-compta)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../class/taxcatalog.class.php';

/**
 * Official rates catalog.
 */
final class TaxCatalogTest extends TestCase
{
	/**
	 * @return void
	 */
	public function testEveryProfileHasSocialContributionsAndTraining(): void
	{
		foreach (AmeTaxCatalog::profiles() as $profile) {
			foreach (array(2025, 2026) as $year) {
				$levels = array_column(AmeTaxCatalog::lines($profile, $year, true), 'level');
				$this->assertContains(AmeContributionCalculator::LEVEL_SOCIAL, $levels, $profile.' '.$year);
				$this->assertContains(AmeContributionCalculator::LEVEL_TRAINING, $levels, $profile.' '.$year);
				$this->assertContains(AmeContributionCalculator::LEVEL_INCOME_TAX, $levels, $profile.' '.$year);
			}
		}
	}

	/**
	 * @return void
	 */
	public function testYearsInheritTheClosestEarlierYear(): void
	{
		$this->assertSame(2026, AmeTaxCatalog::sourceYear(2030, AmeTaxCatalog::PROFILE_BNC));
		$this->assertSame(2025, AmeTaxCatalog::sourceYear(2026, AmeTaxCatalog::PROFILE_SALES));
		$this->assertSame(2025, AmeTaxCatalog::sourceYear(2020, AmeTaxCatalog::PROFILE_BNC));
		$this->assertSame(25.6, AmeTaxCatalog::lines(AmeTaxCatalog::PROFILE_BNC, 2027, false)[0]['rate']);
		$this->assertSame(24.6, AmeTaxCatalog::lines(AmeTaxCatalog::PROFILE_BNC, 2025, false)[0]['rate']);
	}

	/**
	 * @return void
	 */
	public function testIncomeTaxOnlyWhenChosen(): void
	{
		$levels = array_column(AmeTaxCatalog::lines(AmeTaxCatalog::PROFILE_CRAFT, 2026, false), 'level');
		$this->assertNotContains(AmeContributionCalculator::LEVEL_INCOME_TAX, $levels);
		$this->assertSame(array(), AmeTaxCatalog::lines('unknown', 2026, true));
	}
}
