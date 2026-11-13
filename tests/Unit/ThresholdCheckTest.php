<?php
/* Copyright (C) 2026 Aohzan (fork: https://github.com/Aohzan/dolibarr-micro-entreprise-compta)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../class/thresholdcheck.class.php';

/**
 * Turnover against the ceilings.
 */
final class ThresholdCheckTest extends TestCase
{
	/**
	 * @return void
	 */
	public function testSalesCeilingCapsTheWholeTurnoverAndServicesOnlyServices(): void
	{
		$thresholds = array(
			array('name' => 'Micro sales', 'type' => 0, 'rate' => 203100.0),
			array('name' => 'Micro services', 'type' => 1, 'rate' => 83600.0),
			array('name' => 'VAT services', 'type' => 1, 'rate' => 37500.0),
		);
		$checks = AmeThresholdCheck::check(10000, 35000, $thresholds, 80);

		$this->assertSame(45000.0, $checks[0]['amount']);
		$this->assertSame(AmeThresholdCheck::STATE_OK, $checks[0]['state']);
		$this->assertSame(35000.0, $checks[1]['amount']);
		$this->assertSame(AmeThresholdCheck::STATE_WARNING, $checks[2]['state']);
		$this->assertSame(AmeThresholdCheck::STATE_WARNING, AmeThresholdCheck::worst($checks));
	}

	/**
	 * @return void
	 */
	public function testOverAndIgnoredThresholds(): void
	{
		$thresholds = array(
			array('name' => 'VAT services', 'type' => 1, 'rate' => 37500.0),
			array('name' => 'Empty', 'type' => 1, 'rate' => 0.0),
		);
		$checks = AmeThresholdCheck::check(0, 37500.01, $thresholds, 80);

		$this->assertCount(1, $checks);
		$this->assertSame(AmeThresholdCheck::STATE_OVER, AmeThresholdCheck::worst($checks));
		$this->assertSame(AmeThresholdCheck::STATE_OK, AmeThresholdCheck::worst(array()));
	}
}
