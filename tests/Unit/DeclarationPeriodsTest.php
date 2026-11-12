<?php
/* Copyright (C) 2026 Aohzan (fork: https://github.com/Aohzan/dolibarr-micro-entreprise-compta)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../class/declarationperiods.class.php';

/**
 * Declaration periods, deadlines and statuses.
 */
final class DeclarationPeriodsTest extends TestCase
{
	/**
	 * @return void
	 */
	public function testQuarterlyDeadlines(): void
	{
		$periods = AmeDeclarationPeriods::forYear(2026, AmeDeclarationPeriods::QUARTERLY);

		$this->assertCount(4, $periods);
		$this->assertSame(array(4, 5, 6), $periods[1]['months']);
		$this->assertSame(array('2026-04-30', '2026-07-31', '2026-10-31', '2027-01-31'), array_column($periods, 'deadline'));
		$this->assertSame('2026-09-30', $periods[2]['end']);
	}

	/**
	 * @return void
	 */
	public function testMonthlyDeadlines(): void
	{
		$periods = AmeDeclarationPeriods::forYear(2024, AmeDeclarationPeriods::MONTHLY);

		$this->assertCount(12, $periods);
		$this->assertSame('2024-03-31', $periods[1]['deadline']);
		$this->assertSame('2024-02-29', $periods[1]['end']);
		$this->assertSame('2025-01-31', $periods[11]['deadline']);
	}

	/**
	 * @return void
	 */
	public function testStatusAndFocus(): void
	{
		$periods = AmeDeclarationPeriods::forYear(2026, AmeDeclarationPeriods::QUARTERLY);

		$this->assertSame(AmeDeclarationPeriods::STATUS_PAST, AmeDeclarationPeriods::status($periods[1], '2026-11-12'));
		$this->assertSame(AmeDeclarationPeriods::STATUS_CURRENT, AmeDeclarationPeriods::status($periods[3], '2026-11-12'));
		$this->assertSame(AmeDeclarationPeriods::STATUS_DUE, AmeDeclarationPeriods::status($periods[2], '2026-10-31'));
		$this->assertSame(AmeDeclarationPeriods::STATUS_UPCOMING, AmeDeclarationPeriods::status($periods[3], '2026-09-30'));

		$this->assertSame(3, AmeDeclarationPeriods::focused($periods, '2026-10-10'));
		$this->assertSame(4, AmeDeclarationPeriods::focused($periods, '2026-11-12'));
		$this->assertSame(4, AmeDeclarationPeriods::focused($periods, '2027-06-01'));
		$this->assertSame(1, AmeDeclarationPeriods::focused($periods, '2025-06-01'));
	}
}
