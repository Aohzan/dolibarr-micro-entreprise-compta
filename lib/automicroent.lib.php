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
 * \file    automicroent/lib/automicroent.lib.php
 * \ingroup automicroent
 * \brief   Helpers shared by the module pages.
 */

require_once __DIR__.'/../class/turnovercalculator.class.php';
require_once __DIR__.'/../class/contributioncalculator.class.php';
require_once __DIR__.'/../class/declarationperiods.class.php';
require_once __DIR__.'/../class/taxcatalog.class.php';
require_once __DIR__.'/../class/raterepository.class.php';
require_once __DIR__.'/../class/paymentrepository.class.php';

/**
 * Stop with "access forbidden" unless the user has a right of the module.
 *
 * @param string $right Right name: use or configure
 * @return void
 */
function ameRequireRight(string $right): void
{
	global $user;
	if (!isModEnabled('automicroent') || !$user->hasRight('automicroent', $right)) {
		accessforbidden();
	}
}

/**
 * Activity profile chosen in the module setup.
 *
 * @return string One of the AmeTaxCatalog::PROFILE_* constants, '' if not chosen
 */
function ameProfile(): string
{
	return getDolGlobalString('AUTOMICROENT_PROFILE');
}

/**
 * Declaration periodicity chosen in the module setup.
 *
 * @return string AmeDeclarationPeriods::QUARTERLY or MONTHLY
 */
function amePeriodicity(): string
{
	return getDolGlobalString('AUTOMICROENT_PERIODICITY') === AmeDeclarationPeriods::MONTHLY
		? AmeDeclarationPeriods::MONTHLY
		: AmeDeclarationPeriods::QUARTERLY;
}

/**
 * Whether the user pays income tax in full discharge (versement libératoire).
 *
 * @return bool
 */
function ameIncomeTax(): bool
{
	return getDolGlobalInt('AUTOMICROENT_INCOME_TAX') === 1;
}

/**
 * Rate repository of the current entity.
 *
 * @return AmeRateRepository
 */
function ameRates(): AmeRateRepository
{
	global $db, $conf;
	return new AmeRateRepository($db, (int) $conf->entity);
}

/**
 * Payment repository of the current entity.
 *
 * @return AmePaymentRepository
 */
function amePayments(): AmePaymentRepository
{
	global $db;
	return new AmePaymentRepository($db, getEntity('invoice'));
}

/**
 * Turnover calculator used by the pages.
 *
 * @return AmeTurnoverCalculator
 */
function ameTurnoverCalculator(): AmeTurnoverCalculator
{
	// Dolibarr types every deposit line as goods: use the activity of the profile.
	$profile = ameProfile();
	$deposit = ($profile === '') ? null : (($profile === AmeTaxCatalog::PROFILE_SALES) ? AmeTurnoverCalculator::SALE : AmeTurnoverCalculator::SERVICE);
	return new AmeTurnoverCalculator(null, $deposit);
}

/**
 * Today as Y-m-d, in the user's time zone.
 *
 * @return string
 */
function ameToday(): string
{
	return dol_print_date(dol_now(), '%Y-%m-%d', 'tzuserrel');
}

/**
 * Date of a Y-m-d string, for display.
 *
 * @param string $date Y-m-d
 * @return string
 */
function ameDate(string $date): string
{
	// Parsed and printed in the server time zone, so the day never shifts.
	return dol_print_date(dol_stringtotime($date, 0), 'day', 'tzserver');
}

/**
 * Write a CSV line (';' separated), PHP 7.2 to 8.5.
 *
 * @param resource           $out    Output stream
 * @param array<int, scalar> $fields Values
 * @return void
 */
function ameCsvLine($out, array $fields): void
{
	// An empty escape character, the RFC 4180 behaviour, needs PHP 7.4.
	if (PHP_VERSION_ID >= 70400) {
		fputcsv($out, $fields, ';', '"', '');
	} else {
		fputcsv($out, $fields, ';', '"');
	}
}

/**
 * Year to show by default: the previous one while its last period is still to declare (January).
 *
 * @param string $today Day as Y-m-d
 * @return int
 */
function ameDefaultYear(string $today): int
{
	$year = (int) substr($today, 0, 4);
	$previous = AmeDeclarationPeriods::forYear($year - 1, amePeriodicity());
	return (AmeDeclarationPeriods::status(end($previous), $today) === AmeDeclarationPeriods::STATUS_DUE) ? $year - 1 : $year;
}

/**
 * Label of a category.
 *
 * @param int $level Category
 * @return string
 */
function ameLevelLabel(int $level): string
{
	global $langs;
	return $langs->trans('AME_Level'.$level);
}

/**
 * Label of a base.
 *
 * @param int $type Base
 * @return string
 */
function ameBaseLabel(int $type): string
{
	global $langs;
	return $langs->trans('AME_Base'.$type);
}

/**
 * Label of an activity, as on the URSSAF declaration.
 *
 * @param string $activity Activity
 * @return string
 */
function ameActivityLabel(string $activity): string
{
	global $langs;
	if ($activity === AmeTurnoverCalculator::SERVICE) {
		$bnc = in_array(ameProfile(), array(AmeTaxCatalog::PROFILE_BNC, AmeTaxCatalog::PROFILE_CIPAV), true);
		return $langs->trans($bnc ? 'AME_ActivityServiceBnc' : 'AME_ActivityServiceBic');
	}
	return $langs->trans('AME_Activity_'.$activity);
}

/**
 * Label of a declaration period.
 *
 * @param array{index: int, months: list<int>} $period Period
 * @param int                                 $year   Year
 * @return string
 */
function amePeriodLabel(array $period, int $year): string
{
	global $langs;
	if (count($period['months']) === 1) {
		return ucfirst(dol_print_date(dol_mktime(12, 0, 0, $period['months'][0], 1, $year), '%B %Y'));
	}
	return $langs->trans('AME_QuarterShort', $period['index']).' '.$year;
}

/**
 * Badge of a period status.
 *
 * @param string $status One of the AmeDeclarationPeriods::STATUS_* constants
 * @return string HTML
 */
function ameStatusBadge(string $status): string
{
	global $langs;
	$types = array(
		AmeDeclarationPeriods::STATUS_UPCOMING => 'status0',
		AmeDeclarationPeriods::STATUS_CURRENT => 'status1',
		AmeDeclarationPeriods::STATUS_DUE => 'status3',
		AmeDeclarationPeriods::STATUS_PAST => 'status4',
	);
	$label = $langs->trans('AME_Status_'.$status);
	return dolGetStatus($label, $label, '', $types[$status], 5);
}

/**
 * Amount in the company currency.
 *
 * @param float|int $amount   Amount
 * @param bool      $decimals Show the cents
 * @return string HTML
 */
function ameMoney($amount, bool $decimals = true): string
{
	global $langs, $conf;
	return price($decimals ? price2num($amount, 'MT') : round($amount), 0, $langs, 1, $decimals ? -1 : 0, $decimals ? -1 : 0, $conf->currency);
}

/**
 * Rate as "12,3 %".
 *
 * @param float $rate Rate
 * @return string
 */
function ameRate(float $rate): string
{
	global $langs;
	return price($rate, 0, $langs, 1, -1, -1).' %';
}

/**
 * Previous / next year navigation.
 *
 * @param int    $year   Current year
 * @param string $url    Page URL, the year is appended
 * @return string HTML
 */
function ameYearNav(int $year, string $url): string
{
	global $langs;
	$sep = (strpos($url, '?') === false) ? '?' : '&';
	$out = '<a class="paddingright" href="'.dol_escape_htmltag($url.$sep.'year='.($year - 1)).'" title="'.$langs->trans('Previous').'">'.img_previous().' '.($year - 1).'</a>';
	$out .= ' <span class="paddingleft paddingright">|</span> ';
	$out .= '<a class="paddingleft" href="'.dol_escape_htmltag($url.$sep.'year='.($year + 1)).'" title="'.$langs->trans('Next').'">'.($year + 1).' '.img_next().'</a>';
	return $out;
}

/**
 * Turnover and contributions of every period of a year.
 *
 * @param int                                                                $year     Year
 * @param list<array{date: string, activities: array<string, float>}>        $receipts Receipts of the year
 * @param list<array{name: string, level: int, type: int, rate: float}>      $rates    Rates of the year
 * @return list<array{period: array{index: int, months: list<int>, start: string, end: string, deadline: string}, turnover: array<string, float>, estimate: array{declared: array<string, int>, lines: list<array{name: string, level: int, type: int, rate: float, base: int, amount: float}>, levels: array<int, int>, total: int}}>
 */
function ameYearPeriods(int $year, array $receipts, array $rates): array
{
	$monthly = AmeTurnoverCalculator::monthly($receipts, $year);
	$out = array();
	foreach (AmeDeclarationPeriods::forYear($year, amePeriodicity()) as $period) {
		$turnover = AmeTurnoverCalculator::sumMonths($monthly, $period['months']);
		$out[] = array(
			'period' => $period,
			'turnover' => $turnover,
			'estimate' => AmeContributionCalculator::estimate($turnover, $rates, $year),
		);
	}
	return $out;
}

/**
 * Turnover of an activity group: sales, services or all.
 *
 * @param array<string, float|int> $amounts Activity => amount
 * @param int                      $base    One of the AmeContributionCalculator::BASE_* constants
 * @return float
 */
function ameSum(array $amounts, int $base): float
{
	$sum = 0.0;
	foreach ($amounts as $activity => $amount) {
		$isSale = ($activity === AmeTurnoverCalculator::SALE);
		if ($base === AmeContributionCalculator::BASE_ALL || ($base === AmeContributionCalculator::BASE_SALES) === $isSale) {
			$sum += $amount;
		}
	}
	return $sum;
}

/**
 * Activity profiles for a select.
 *
 * @return array<string, string>
 */
function ameProfileOptions(): array
{
	global $langs;
	$options = array();
	foreach (AmeTaxCatalog::profiles() as $profile) {
		$options[$profile] = $langs->trans('AME_Profile_'.$profile);
	}
	return $options;
}

/**
 * Order activities as on the URSSAF form: sales first.
 *
 * @param array<string, int|float> $amounts Activity => amount
 * @return array<string, int|float>
 */
function ameSortActivities(array $amounts): array
{
	uksort($amounts, function ($a, $b) {
		return array($a !== AmeTurnoverCalculator::SALE, $a) <=> array($b !== AmeTurnoverCalculator::SALE, $b);
	});
	return $amounts;
}
