<?php
/* Copyright (C) 2026 Aohzan (fork: https://github.com/Aohzan/dolibarr-micro-entreprise-compta)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../class/deadlinereminder.class.php';

/**
 * Which deadlines get a reminder.
 */
final class DeadlineReminderTest extends TestCase
{
	/**
	 * @param string $today Day
	 * @param int    $days  Days before
	 * @param string $periodicity Periodicity
	 * @return list<string> Year and index of each due period
	 */
	private static function due(string $today, int $days, string $periodicity = AmeDeclarationPeriods::QUARTERLY): array
	{
		return array_map(function ($item) {
			return $item['year'].'-'.$item['period']['index'];
		}, AmeDeadlineReminder::duePeriods($today, $days, $periodicity));
	}

	/**
	 * @return void
	 */
	public function testReminderStartsSomeDaysBeforeTheDeadline(): void
	{
		$this->assertSame(array(), self::due('2026-10-23', 7));
		$this->assertSame(array('2026-3'), self::due('2026-10-24', 7));
		$this->assertSame(array('2026-3'), self::due('2026-10-31', 7));
		$this->assertSame(array(), self::due('2026-11-01', 7));
	}

	/**
	 * @return void
	 */
	public function testLastQuarterIsDeclaredInJanuary(): void
	{
		$this->assertSame(array('2026-4'), self::due('2027-01-25', 7));
	}

	/**
	 * @return void
	 */
	public function testMonthly(): void
	{
		$this->assertSame(array('2026-10'), self::due('2026-11-30', 0, AmeDeclarationPeriods::MONTHLY));
		$this->assertSame(array('2026-10'), self::due('2026-11-01', 30, AmeDeclarationPeriods::MONTHLY));
	}
}
