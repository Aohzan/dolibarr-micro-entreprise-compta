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
 * \file    automicroent/declaration.php
 * \ingroup automicroent
 * \brief   Declarations of a year: turnover to declare and estimated contributions per period.
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

require_once __DIR__.'/lib/automicroent.lib.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */

$langs->loadLangs(array('bills', 'automicroent@automicroent'));
ameRequireRight('use');

$today = ameToday();
$year = GETPOSTINT('year') ?: (int) substr($today, 0, 4);
$action = GETPOST('action', 'aZ09');
$self = $_SERVER['PHP_SELF'];

$error = '';
try {
	$rates = ameRates()->rates($year);
	$receipts = amePayments()->receipts($year, $year, ameTurnoverCalculator());
} catch (RuntimeException $e) {
	$rates = array();
	$receipts = array();
	$error = $e->getMessage();
}
$periods = ameYearPeriods($year, $receipts, $rates);

$selected = GETPOSTINT('period') ?: AmeDeclarationPeriods::focused(array_column($periods, 'period'), $today);
$selected = max(1, min(count($periods), $selected));

/*
 * CSV export of the periods
 */

if ($action === 'csv') {
	$filename = 'declarations-'.$year.'.csv';
	header('Content-Type: text/csv; charset=UTF-8');
	header('Content-Disposition: attachment; filename="'.$filename.'"');
	$out = fopen('php://output', 'w');
	fwrite($out, "\xEF\xBB\xBF");
	fputcsv($out, array(
		$langs->transnoentitiesnoconv('AME_Period'), $langs->transnoentitiesnoconv('DateStart'), $langs->transnoentitiesnoconv('DateEnd'),
		$langs->transnoentitiesnoconv('AME_Deadline'), $langs->transnoentitiesnoconv('AME_Activity_sale'), $langs->transnoentitiesnoconv('AME_Services'),
		$langs->transnoentitiesnoconv('Total'), $langs->transnoentitiesnoconv('AME_EstimatedContributions'), $langs->transnoentitiesnoconv('Status'),
	), ';', '"', '');
	foreach ($periods as $row) {
		$declared = $row['estimate']['declared'];
		fputcsv($out, array(
			amePeriodLabel($row['period'], $year), $row['period']['start'], $row['period']['end'], $row['period']['deadline'],
			(int) ameSum($declared, AmeContributionCalculator::BASE_SALES), (int) ameSum($declared, AmeContributionCalculator::BASE_SERVICES),
			(int) ameSum($declared, AmeContributionCalculator::BASE_ALL), $row['estimate']['total'],
			$langs->transnoentitiesnoconv('AME_Status_'.AmeDeclarationPeriods::status($row['period'], $today)),
		), ';', '"', '');
	}
	fclose($out);
	exit;
}

/*
 * View
 */

llxHeader('', $langs->trans('AME_DeclarationsTitle', $year), '', '', 0, 0, '', '', '', 'mod-automicroent page-declaration');

$morehtmlright = ameYearNav($year, $self);
$morehtmlright .= dolGetButtonTitle($langs->trans('AME_ExportCsv'), '', 'fa fa-file-csv', $self.'?year='.$year.'&action=csv&token='.newToken());
$morehtmlright .= dolGetButtonTitle($langs->trans('Print'), '', 'fa fa-print', $self.'?year='.$year.'&period='.$selected.'&optioncss=print', '', 1, array('attr' => array('target' => '_blank')));
print load_fiche_titre($langs->trans('AME_DeclarationsTitle', $year), $morehtmlright, 'fa-file-invoice');

if ($error !== '') {
	print info_admin($langs->trans('AME_ErrorReactivate', $error), 0, 0, '1', 'error');
}
if (!$rates) {
	print info_admin($langs->trans('AME_NoRateForYear', $year, dol_buildpath('/automicroent/rates.php', 1)), 0, 0, '1', 'warning');
}

// Key figures of the year
$yearTurnover = 0.0;
$yearContributions = 0;
$toDateContributions = 0;
foreach ($periods as $row) {
	$yearTurnover += ameSum($row['turnover'], AmeContributionCalculator::BASE_ALL);
	$yearContributions += $row['estimate']['total'];
}
print '<div class="box-flex-container kanban ame-kpis">';
print ameKpi('fa-coins', $langs->trans('AME_TurnoverCollected'), ameMoney($yearTurnover, false));
print ameKpi('fa-landmark', $langs->trans('AME_EstimatedContributions'), ameMoney($yearContributions, false));
$effective = ($yearTurnover > 0) ? ameRate(round($yearContributions / $yearTurnover * 100, 1)) : '-';
print ameKpi('fa-percent', $langs->trans('AME_EffectiveRate'), $effective);
print '</div>';

// Periods
print '<div class="div-table-responsive">';
print '<table class="noborder centpercent" id="ame-periods">';
print '<tr class="liste_titre">';
print '<th>'.$langs->trans('AME_Period').'</th>';
print '<th class="center">'.$langs->trans('AME_Deadline').'</th>';
print '<th class="right">'.$langs->trans('AME_Activity_sale').'</th>';
print '<th class="right">'.$langs->trans('AME_Services').'</th>';
print '<th class="right">'.$langs->trans('AME_TurnoverToDeclare').'</th>';
print '<th class="right">'.$langs->trans('AME_EstimatedContributions').'</th>';
print '<th class="center">'.$langs->trans('Status').'</th>';
print '</tr>';
foreach ($periods as $row) {
	$period = $row['period'];
	$declared = $row['estimate']['declared'];
	$status = AmeDeclarationPeriods::status($period, $today);
	$isSelected = ($period['index'] === $selected);
	$empty = ($status === AmeDeclarationPeriods::STATUS_UPCOMING);
	print '<tr class="oddeven'.($isSelected ? ' ame-selected' : '').'" data-period="'.$period['index'].'">';
	print '<td><a href="'.$self.'?year='.$year.'&period='.$period['index'].'#ame-period">'.($isSelected ? img_picto('', 'fa-caret-right', 'class="paddingright"') : '').amePeriodLabel($period, $year).'</a></td>';
	print '<td class="center">'.dol_print_date(dol_stringtotime($period['deadline']), 'day').'</td>';
	print '<td class="right amount">'.($empty ? '-' : ameMoney(ameSum($declared, AmeContributionCalculator::BASE_SALES), false)).'</td>';
	print '<td class="right amount">'.($empty ? '-' : ameMoney(ameSum($declared, AmeContributionCalculator::BASE_SERVICES), false)).'</td>';
	print '<td class="right amount"><strong>'.($empty ? '-' : ameMoney(ameSum($declared, AmeContributionCalculator::BASE_ALL), false)).'</strong></td>';
	print '<td class="right amount">'.($empty ? '-' : ameMoney($row['estimate']['total'], false)).'</td>';
	print '<td class="center">'.ameStatusBadge($status).'</td>';
	print '</tr>';
}
print '</table>';
print '</div>';

// Selected period: what to declare and how the estimate is computed.
$row = $periods[$selected - 1];
$period = $row['period'];
$estimate = $row['estimate'];
print '<div id="ame-period" class="ame-period">';
print load_fiche_titre($langs->trans('AME_ToDeclare', amePeriodLabel($period, $year)), '', 'fa-paper-plane');
print '<div class="opacitymedium">'.$langs->trans('AME_ToDeclareHelp', dol_print_date(dol_stringtotime($period['deadline']), 'day')).'</div>';

print '<div class="fichecenter"><div class="fichehalfleft">';
print '<table class="noborder centpercent" id="ame-declare">';
print '<tr class="liste_titre"><th>'.$langs->trans('AME_UrssafLine').'</th><th class="right">'.$langs->trans('AME_AmountToDeclare').'</th></tr>';
$activities = ameSortActivities($estimate['declared'] ?: array(AmeTurnoverCalculator::SERVICE => 0));
foreach ($activities as $activity => $amount) {
	print '<tr class="oddeven"><td>'.ameActivityLabel($activity).'</td><td class="right amount"><strong>'.ameMoney($amount, false).'</strong></td></tr>';
}
print '</table>';
print '</div><div class="fichehalfright">';
print '<table class="noborder centpercent" id="ame-estimate">';
print '<tr class="liste_titre"><th>'.$langs->trans('AME_Tax').'</th><th class="right">'.$langs->trans('AME_BaseAmount').'</th><th class="right">'.$langs->trans('AME_Rate').'</th><th class="right">'.$langs->trans('Amount').'</th></tr>';
foreach ($estimate['lines'] as $line) {
	print '<tr class="oddeven"><td>'.dol_escape_htmltag($line['name']).' <span class="opacitymedium">'.ameLevelLabel($line['level']).'</span></td>';
	print '<td class="right amount">'.ameMoney($line['base'], false).'</td><td class="right nowraponall">'.ameRate($line['rate']).'</td><td class="right amount">'.ameMoney($line['amount']).'</td></tr>';
}
foreach ($estimate['levels'] as $level => $amount) {
	print '<tr class="liste_total"><td colspan="3">'.$langs->trans('AME_LevelTotal', ameLevelLabel($level)).'</td><td class="right amount">'.ameMoney($amount, false).'</td></tr>';
}
print '<tr class="liste_total"><td colspan="3"><strong>'.$langs->trans('AME_EstimatedContributions').'</strong></td><td class="right amount"><strong>'.ameMoney($estimate['total'], false).'</strong></td></tr>';
print '</table>';
print '</div></div>';
print '<div class="clearboth"></div>';

// Payments of the period
$periodReceipts = array_values(array_filter($receipts, function ($r) use ($period) {
	return $r['date'] >= $period['start'] && $r['date'] <= $period['end'];
}));
print load_fiche_titre($langs->trans('AME_PeriodReceipts', count($periodReceipts)), '', 'fa-money-check-alt');
print '<div class="div-table-responsive">';
print '<table class="noborder centpercent" id="ame-receipts">';
print '<tr class="liste_titre"><th>'.$langs->trans('DatePayment').'</th><th>'.$langs->trans('Invoice').'</th>';
print '<th class="right">'.$langs->trans('AME_PaymentAmount').'</th><th class="right">'.$langs->trans('AME_Activity_sale').'</th><th class="right">'.$langs->trans('AME_Services').'</th><th></th></tr>';
if (!$periodReceipts) {
	print '<tr class="oddeven"><td colspan="6"><span class="opacitymedium">'.$langs->trans('AME_NoReceipt').'</span></td></tr>';
}
foreach ($periodReceipts as $receipt) {
	$url = DOL_URL_ROOT.'/compta/facture/card.php?facid='.$receipt['invoice_id'];
	print '<tr class="oddeven">';
	print '<td>'.dol_print_date(dol_stringtotime($receipt['date']), 'day').'</td>';
	print '<td><a href="'.$url.'">'.img_picto('', 'bill', 'class="pictofixedwidth"').dol_escape_htmltag($receipt['invoice_ref']).'</a></td>';
	print '<td class="right amount">'.ameMoney($receipt['amount']).'</td>';
	print '<td class="right amount">'.ameMoney(ameSum($receipt['activities'], AmeContributionCalculator::BASE_SALES)).'</td>';
	print '<td class="right amount">'.ameMoney(ameSum($receipt['activities'], AmeContributionCalculator::BASE_SERVICES)).'</td>';
	print '<td class="center">'.($receipt['partial'] ? dolGetBadge($langs->trans('AME_PartialPayment'), '', 'warning', '', '', array('attr' => array('class' => 'classfortooltip', 'title' => $langs->trans('AME_PartialPaymentHelp')))) : '').'</td>';
	print '</tr>';
}
print '</table>';
print '</div>';
print '</div>';

// Yearly turnover, for the income tax return
print load_fiche_titre($langs->trans('AME_IncomeTaxReturn', $year), '', 'fa-file-signature');
print '<div class="opacitymedium">'.$langs->trans('AME_IncomeTaxReturnHelp').'</div>';
$monthly = AmeTurnoverCalculator::monthly($receipts, $year);
$yearDeclared = AmeContributionCalculator::declared(AmeTurnoverCalculator::sumMonths($monthly, range(1, 12)));
print '<table class="noborder centpercent" id="ame-yearly">';
print '<tr class="liste_titre"><th>'.$langs->trans('AME_UrssafLine').'</th><th class="right">'.$langs->trans('AME_YearTurnover').'</th></tr>';
foreach (ameSortActivities($yearDeclared ?: array(AmeTurnoverCalculator::SERVICE => 0)) as $activity => $amount) {
	print '<tr class="oddeven"><td>'.ameActivityLabel($activity).'</td><td class="right amount"><strong>'.ameMoney($amount, false).'</strong></td></tr>';
}
print '</table>';

llxFooter();
$db->close();

/**
 * Key figure tile.
 *
 * @param string $icon  Font Awesome icon
 * @param string $label Label
 * @param string $value Value (HTML)
 * @return string HTML
 */
function ameKpi(string $icon, string $label, string $value): string
{
	$out = '<div class="box-flex-item box-flex-grow-zero">';
	$out .= '<div class="info-box info-box-sm">';
	$out .= '<span class="info-box-icon bg-infobox-facture"><span class="fa '.$icon.'"></span></span>';
	$out .= '<div class="info-box-content">';
	$out .= '<div class="info-box-title">'.dol_escape_htmltag($label).'</div>';
	$out .= '<div class="info-box-lines"><div class="info-box-line"><span class="amount">'.$value.'</span></div></div>';
	$out .= '</div></div></div>';
	return $out;
}
