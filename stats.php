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
 * \file    automicroent/stats.php
 * \ingroup automicroent
 * \brief   Turnover and contributions over the years.
 */

// Load Dolibarr: htdocs/automicroent or htdocs/custom/automicroent.
$res = 0;
if (!empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
	$res = @include $_SERVER['CONTEXT_DOCUMENT_ROOT'].'/main.inc.php';
}
foreach (array('../main.inc.php', '../../main.inc.php') as $main) {
	if (!$res && file_exists($main)) {
		$res = @include $main;
	}
}
if (!$res) {
	die('Include of main fails');
}

require_once DOL_DOCUMENT_ROOT.'/core/class/dolgraph.class.php';
require_once __DIR__.'/lib/automicroent.lib.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */

$langs->loadLangs(array('bills', 'automicroent@automicroent'));
ameRequireRight('use');

$self = $_SERVER['PHP_SELF'];
$today = ameToday();
$lastYear = (int) substr($today, 0, 4);
$range = GETPOST('range', 'aZ09') === 'all' ? 'all' : '5';

$error = '';
$years = array();
$stats = array();
try {
	$firstYear = amePayments()->firstYear() ?? $lastYear;
	if ($range !== 'all') {
		$firstYear = max($firstYear, $lastYear - 4);
	}
	$years = range($firstYear, $lastYear);
	$receipts = amePayments()->receipts($firstYear, $lastYear, ameTurnoverCalculator());
	$rateRepository = ameRates();
	foreach ($years as $year) {
		$yearReceipts = array_values(array_filter($receipts, function ($r) use ($year) {
			return (int) substr($r['date'], 0, 4) === $year;
		}));
		$monthly = AmeTurnoverCalculator::monthly($yearReceipts, $year);
		$contributions = 0;
		foreach (ameYearPeriods($year, $yearReceipts, $rateRepository->rates($year)) as $row) {
			$contributions += $row['estimate']['total'];
		}
		$stats[$year] = array(
			'monthly' => $monthly,
			// Turnover until the current month, to compare the running year fairly.
			'toDate' => ameSum(AmeTurnoverCalculator::sumMonths($monthly, range(1, (int) substr($today, 5, 2))), AmeContributionCalculator::BASE_ALL),
			'sales' => ameSum(AmeTurnoverCalculator::sumMonths($monthly, range(1, 12)), AmeContributionCalculator::BASE_SALES),
			'services' => ameSum(AmeTurnoverCalculator::sumMonths($monthly, range(1, 12)), AmeContributionCalculator::BASE_SERVICES),
			'contributions' => $contributions,
			'receipts' => count($yearReceipts),
		);
	}
} catch (RuntimeException $e) {
	$error = $e->getMessage();
}

llxHeader('', $langs->trans('AME_StatsTitle'), '', '', 0, 0, '', '', '', 'mod-automicroent page-stats');

$rangeLinks = '<a class="paddingright'.($range === '5' ? ' bold' : '').'" href="'.$self.'">'.$langs->trans('AME_LastYears', 5).'</a>';
$rangeLinks .= ' | <a class="paddingleft'.($range === 'all' ? ' bold' : '').'" href="'.$self.'?range=all">'.$langs->trans('AME_AllYears').'</a>';
print load_fiche_titre($langs->trans('AME_StatsTitle'), $rangeLinks, 'fa-chart-line');

if ($error !== '') {
	print info_admin($langs->trans('AME_ErrorReactivate', $error), 0, 0, '1', 'error');
	llxFooter();
	$db->close();
	exit;
}

$width = DolGraph::getDefaultGraphSizeForStats('width');
$height = DolGraph::getDefaultGraphSizeForStats('height');
$monthLabels = array();
for ($m = 1; $m <= 12; $m++) {
	$monthLabels[$m] = dol_print_date(dol_mktime(12, 0, 0, $m, 1, 2000), '%b');
}

// 1. Yearly turnover and contributions
$data = array();
foreach ($stats as $year => $s) {
	$data[] = array((string) $year, $s['sales'], $s['services'], $s['contributions']);
}
$graphs = array();
$graphs[] = ameGraph('ame_yearly', $langs->trans('AME_GraphYearly'), $data, array($langs->trans('AME_Activity_sale'), $langs->trans('AME_Services'), $langs->trans('AME_EstimatedContributions')), array('bars', 'bars', 'bars'), $width, $height);

// 2. Monthly turnover, one line per year
$data = array();
$cumulative = array();
$running = array_fill_keys($years, 0.0);
for ($m = 1; $m <= 12; $m++) {
	$point = array($monthLabels[$m]);
	$cumulativePoint = array($monthLabels[$m]);
	foreach ($years as $year) {
		$amount = ameSum($stats[$year]['monthly'][$m], AmeContributionCalculator::BASE_ALL);
		$running[$year] += $amount;
		$future = ($year === $lastYear && $m > (int) substr($today, 5, 2));
		$point[] = $future ? null : round($amount, 2);
		$cumulativePoint[] = $future ? null : round($running[$year], 2);
	}
	$data[] = $point;
	$cumulative[] = $cumulativePoint;
}
$legend = array_map('strval', $years);
$types = array_fill(0, count($years), 'lines');
$graphs[] = ameGraph('ame_monthly', $langs->trans('AME_GraphMonthly'), $data, $legend, $types, $width, $height);

// 3. Cumulated turnover since January
$graphs[] = ameGraph('ame_cumulative', $langs->trans('AME_GraphCumulative'), $cumulative, $legend, $types, $width, $height);

print '<div class="fichecenter ame-graphs">';
foreach ($graphs as $i => $graph) {
	print '<div class="'.($i % 2 ? 'fichehalfright' : 'fichehalfleft').'"><div class="ame-graph">'.$graph.'</div></div>';
	if ($i % 2) {
		print '<div class="clearboth"></div>';
	}
}
print '</div><div class="clearboth"></div>';

// Table per year
print '<div class="div-table-responsive">';
print '<table class="noborder centpercent" id="ame-stats">';
print '<tr class="liste_titre">';
print '<th>'.$langs->trans('Year').'</th>';
print '<th class="right">'.$langs->trans('AME_Activity_sale').'</th>';
print '<th class="right">'.$langs->trans('AME_Services').'</th>';
print '<th class="right">'.$langs->trans('Total').'</th>';
print '<th class="right">'.$langs->trans('AME_Evolution').'</th>';
print '<th class="right">'.$langs->trans('AME_EstimatedContributions').'</th>';
print '<th class="right">'.$langs->trans('AME_EffectiveRate').'</th>';
print '<th class="right">'.$langs->trans('AME_Receipts').'</th>';
print '<th class="right">'.$langs->trans('AME_AverageReceipt').'</th>';
print '</tr>';
$previousYear = null;
foreach ($stats as $year => $s) {
	$total = $s['sales'] + $s['services'];
	// The running year is compared with the same months of the year before.
	$current = ($year === $lastYear) ? $s['toDate'] : $total;
	$previous = ($previousYear === null) ? null : (($year === $lastYear) ? $previousYear['toDate'] : $previousYear['sales'] + $previousYear['services']);
	$evolution = ($previous !== null && $previous > 0) ? ($current - $previous) / $previous * 100 : null;
	print '<tr class="oddeven">';
	print '<td><a href="'.dol_buildpath('/automicroent/declaration.php', 1).'?year='.$year.'">'.$year.'</a>'.($year === $lastYear ? ' <span class="opacitymedium">('.$langs->trans('AME_YearToDate').')</span>' : '').'</td>';
	print '<td class="right amount">'.ameMoney($s['sales'], false).'</td>';
	print '<td class="right amount">'.ameMoney($s['services'], false).'</td>';
	print '<td class="right amount"><strong>'.ameMoney($total, false).'</strong></td>';
	print '<td class="right">'.($evolution === null ? '-' : '<span class="'.($evolution < 0 ? 'error' : 'ok').'">'.($evolution > 0 ? '+' : '').price(round($evolution, 1), 0, $langs, 1, 1, 1).' %</span>').'</td>';
	print '<td class="right amount">'.ameMoney($s['contributions'], false).'</td>';
	print '<td class="right">'.($total > 0 ? ameRate(round($s['contributions'] / $total * 100, 1)) : '-').'</td>';
	print '<td class="right">'.$s['receipts'].'</td>';
	print '<td class="right amount">'.($s['receipts'] ? ameMoney($total / $s['receipts'], false) : '-').'</td>';
	print '</tr>';
	$previousYear = $s;
}
print '</table>';
print '</div>';

llxFooter();
$db->close();

/**
 * Render a chart.
 *
 * @param string                              $id     Chart id
 * @param string                              $title  Title
 * @param list<array<int, string|float|null>> $data   Rows: label, then one value per series
 * @param list<string>                        $legend Series names
 * @param list<string>                        $types  Series types (bars, lines)
 * @param int                                 $width  Width
 * @param int                                 $height Height
 * @return string HTML
 */
function ameGraph(string $id, string $title, array $data, array $legend, array $types, $width, $height): string
{
	$graph = new DolGraph();
	$error = $graph->isGraphKo();
	if ($error) {
		return $error;
	}
	$graph->SetData($data);
	$graph->SetLegend($legend);
	$graph->SetType($types);
	$graph->SetTitle($title);
	$graph->SetWidth($width);
	$graph->SetHeight($height);
	$graph->SetMinValue(0);
	$graph->setShowLegend(2);
	$graph->draw($id.'.png');
	return $graph->show();
}
