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
require_once __DIR__.'/../class/thresholdcheck.class.php';

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
	$default = ameDefaultServiceActivity();
	$bic = ameCategoryProducts(getDolGlobalInt('AUTOMICROENT_CATEGORY_BIC'));
	$bnc = ameCategoryProducts(getDolGlobalInt('AUTOMICROENT_CATEGORY_BNC'));
	$resolver = function (array $line) use ($default, $bic, $bnc): ?string {
		switch ((int) $line['product_type']) {
			case 0:
				return AmeTurnoverCalculator::SALE;
			case 1:
				if (isset($bnc[(int) $line['fk_product']])) {
					return AmeTurnoverCalculator::SERVICE_BNC;
				}
				if (isset($bic[(int) $line['fk_product']])) {
					return AmeTurnoverCalculator::SERVICE_BIC;
				}
				return $default;
			default:
				return null;
		}
	};
	// Dolibarr types every deposit line as goods: use the activity of the profile.
	$profile = ameProfile();
	$deposit = ($profile === '') ? null : (($profile === AmeTaxCatalog::PROFILE_SALES) ? AmeTurnoverCalculator::SALE : $default);
	return new AmeTurnoverCalculator($resolver, $deposit);
}

/**
 * Activity of the services that are in no mapped category: BNC for the
 * liberal professions, BIC for traders and craftsmen, unknown without profile.
 *
 * @return string
 */
function ameDefaultServiceActivity(): string
{
	switch (ameProfile()) {
		case AmeTaxCatalog::PROFILE_BNC:
		case AmeTaxCatalog::PROFILE_CIPAV:
			return AmeTurnoverCalculator::SERVICE_BNC;
		case AmeTaxCatalog::PROFILE_BIC_SERVICES:
		case AmeTaxCatalog::PROFILE_CRAFT:
		case AmeTaxCatalog::PROFILE_SALES:
			return AmeTurnoverCalculator::SERVICE_BIC;
		default:
			return AmeTurnoverCalculator::SERVICE;
	}
}

/**
 * Products and services of a category and its sub-categories.
 *
 * @param int $categoryId Category id, 0 for none
 * @return array<int, true> Product id => true
 */
function ameCategoryProducts(int $categoryId): array
{
	global $db;
	if ($categoryId <= 0) {
		return array();
	}
	// One query for the tree; the visited set protects against parent loops.
	$children = array();
	$resql = $db->query('SELECT rowid, fk_parent FROM '.MAIN_DB_PREFIX.'categorie WHERE type = 0');
	while ($resql && ($obj = $db->fetch_object($resql))) {
		$children[(int) $obj->fk_parent][] = (int) $obj->rowid;
	}
	$ids = array();
	$queue = array($categoryId);
	while ($queue) {
		$id = array_shift($queue);
		if (isset($ids[$id])) {
			continue;
		}
		$ids[$id] = true;
		foreach ($children[$id] ?? array() as $child) {
			$queue[] = $child;
		}
	}
	$products = array();
	$sql = 'SELECT fk_product FROM '.MAIN_DB_PREFIX.'categorie_product WHERE fk_categorie IN ('.implode(',', array_keys($ids)).')';
	$resql = $db->query($sql);
	while ($resql && ($obj = $db->fetch_object($resql))) {
		$products[(int) $obj->fk_product] = true;
	}
	return $products;
}

/**
 * Main activity of the setup: sales for a trader selling goods, else its services.
 *
 * @return string
 */
function ameDefaultActivity(): string
{
	return (ameProfile() === AmeTaxCatalog::PROFILE_SALES) ? AmeTurnoverCalculator::SALE : ameDefaultServiceActivity();
}

/**
 * Alert percentage of the ceilings.
 *
 * @return int
 */
function ameThresholdAlert(): int
{
	return max(1, min(100, getDolGlobalInt('AUTOMICROENT_THRESHOLD_ALERT', 80)));
}

/**
 * Gauge of a ceiling: amount, percentage and colour.
 *
 * @param array{amount: float, limit: float, ratio: float, state: string} $check Check
 * @return string HTML
 */
function ameThresholdGauge(array $check): string
{
	$colors = array(AmeThresholdCheck::STATE_OK => 'ok', AmeThresholdCheck::STATE_WARNING => 'warning', AmeThresholdCheck::STATE_OVER => 'error');
	$percent = (int) floor($check['ratio'] * 100);
	$out = '<span class="ame-gauge ame-gauge-'.$check['state'].'" title="'.dol_escape_htmltag(ameMoney($check['amount'], false).' / '.ameMoney($check['limit'], false)).'">';
	$out .= '<progress max="100" value="'.min(100, $percent).'"></progress> ';
	$out .= '<span class="'.$colors[$check['state']].'">'.$percent.' %</span></span>';
	return $out;
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
		if (AmeContributionCalculator::inBase((string) $activity, $base)) {
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
