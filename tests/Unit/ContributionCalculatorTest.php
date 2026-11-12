<?php
/* Copyright (C) 2026 Aohzan (fork: https://github.com/Aohzan/dolibarr-micro-entreprise-compta)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../class/contributioncalculator.class.php';

/**
 * Estimating the contributions of a period.
 */
final class ContributionCalculatorTest extends TestCase
{
	/**
	 * @return void
	 */
	public function testRatesApplyToTheirBaseAndRoundPerCategory(): void
	{
		$rates = array(
			array('name' => 'Social sales', 'level' => 1, 'type' => 0, 'rate' => 12.3),
			array('name' => 'Social services', 'level' => 1, 'type' => 1, 'rate' => 25.6),
			array('name' => 'CFP', 'level' => 3, 'type' => 2, 'rate' => 0.2),
		);
		$estimate = AmeContributionCalculator::estimate(array('sale' => 1000.4, 'service' => 2999.5), $rates, 2026);

		$this->assertSame(array('sale' => 1000, 'service' => 3000), $estimate['declared']);
		$this->assertSame(1000, $estimate['lines'][0]['base']);
		$this->assertSame(123.0, $estimate['lines'][0]['amount']);
		$this->assertSame(768.0, $estimate['lines'][1]['amount']);
		$this->assertSame(4000, $estimate['lines'][2]['base']);
		$this->assertSame(array(1 => 891, 3 => 8), $estimate['levels']);
		$this->assertSame(899, $estimate['total']);
	}

	/**
	 * @return void
	 */
	public function testServicesBaseIncludesEveryServiceActivity(): void
	{
		$declared = array('sale' => 10, 'service_bic' => 20, 'service_bnc' => 30);
		$this->assertSame(50, AmeContributionCalculator::baseAmount($declared, AmeContributionCalculator::BASE_SERVICES));
		$this->assertSame(10, AmeContributionCalculator::baseAmount($declared, AmeContributionCalculator::BASE_SALES));
		$this->assertSame(60, AmeContributionCalculator::baseAmount($declared, AmeContributionCalculator::BASE_ALL));
	}

	/**
	 * @return void
	 */
	public function testRoundingRulesChangedIn2016(): void
	{
		$this->assertSame(10, AmeContributionCalculator::roundLevel(10.9, AmeContributionCalculator::LEVEL_SOCIAL, 2015));
		$this->assertSame(11, AmeContributionCalculator::roundLevel(10.5, AmeContributionCalculator::LEVEL_TRAINING, 2015));
		$this->assertSame(11, AmeContributionCalculator::roundLevel(10.5, AmeContributionCalculator::LEVEL_SOCIAL, 2016));
		$this->assertSame(10, AmeContributionCalculator::roundLevel(10.4, AmeContributionCalculator::LEVEL_SOCIAL, 2016));
	}

	/**
	 * @return void
	 */
	public function testNoTurnoverNoContribution(): void
	{
		$estimate = AmeContributionCalculator::estimate(array(), array(array('name' => 'Social', 'level' => 1, 'type' => 2, 'rate' => 21.2)), 2026);
		$this->assertSame(0, $estimate['total']);
	}
}
