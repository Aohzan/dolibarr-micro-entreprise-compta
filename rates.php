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
 * \file    automicroent/rates.php
 * \ingroup automicroent
 * \brief   Contribution rates: one column per year, one row per rate.
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

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once __DIR__.'/lib/automicroent.lib.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */

$langs->loadLangs(array('admin', 'automicroent@automicroent'));
ameRequireRight('configure');

$action = GETPOST('action', 'aZ09');
$rowKey = GETPOST('row', 'aZ09');
$year = GETPOSTINT('year');

$repository = ameRates();
$form = new Form($db);
$self = $_SERVER['PHP_SELF'];

/*
 * Actions
 */

try {
	if ($action === 'save') {
		$values = GETPOST('rate', 'array');
		$result = $repository->saveRates($values);
		setEventMessages($langs->trans('AME_RatesSaved', $result['changed']), null);
		if ($result['invalid']) {
			setEventMessages($langs->trans('AME_RatesInvalid', implode(', ', $result['invalid'])), null, 'errors');
		}
		header('Location: '.$self);
		exit;
	}

	// Action names start with "add": Dolibarr then requires a CSRF token, even in GET.
	if ($action === 'addfirstyear' || $action === 'addyear') {
		$source = GETPOST('source', 'aZ09');
		if ($action === 'addfirstyear') {
			// Only on first use: it would replace the rates of the current year.
			if ($repository->years()) {
				throw new InvalidArgumentException('AME_ErrorYearExists');
			}
			$profile = GETPOST('profile', 'aZ09');
			if (!in_array($profile, AmeTaxCatalog::profiles(), true)) {
				throw new InvalidArgumentException('AME_ErrorChooseProfile');
			}
			dolibarr_set_const($db, 'AUTOMICROENT_PROFILE', $profile, 'chaine', 0, '', $conf->entity);
			dolibarr_set_const($db, 'AUTOMICROENT_INCOME_TAX', GETPOST('income_tax', 'alpha') ? 1 : 0, 'chaine', 0, '', $conf->entity);
			$source = AmeRateRepository::SOURCE_CATALOG;
			$year = (int) dol_print_date(dol_now(), '%Y');
		}
		if ($year < 2000 || $year > 2100) {
			throw new InvalidArgumentException('AME_ErrorYear');
		}
		if (!in_array($source, array(AmeRateRepository::SOURCE_PREVIOUS, AmeRateRepository::SOURCE_CATALOG, AmeRateRepository::SOURCE_EMPTY), true)) {
			$source = AmeRateRepository::SOURCE_EMPTY;
		}
		if ($source === AmeRateRepository::SOURCE_CATALOG && ameProfile() === '') {
			throw new InvalidArgumentException('AME_ErrorChooseProfile');
		}
		$count = $repository->addYear($year, $source, ameProfile(), ameIncomeTax(), $langs);
		setEventMessages($langs->trans('AME_YearAdded', $year, $count), null);
		if ($source === AmeRateRepository::SOURCE_CATALOG) {
			setEventMessages($langs->trans('AME_CheckCatalogRates', AmeTaxCatalog::sourceYear($year, ameProfile())), null, 'warnings');
		}
		header('Location: '.$self);
		exit;
	}

	if ($action === 'addthresholds') {
		if (ameProfile() === '') {
			throw new InvalidArgumentException('AME_ErrorChooseProfile');
		}
		$count = $repository->addCatalogThresholds(ameProfile(), $langs);
		setEventMessages($langs->trans('AME_ThresholdsAdded', $count), null);
		header('Location: '.$self);
		exit;
	}

	if ($action === 'saverow') {
		$name = trim(GETPOST('name', 'alphanohtml'));
		$level = GETPOSTINT('level');
		$type = GETPOSTINT('type');
		if ($name === '' || !in_array($level, AmeContributionCalculator::levels(), true) || !in_array($type, AmeContributionCalculator::bases(), true)) {
			throw new InvalidArgumentException('AME_ErrorRowFields');
		}
		$repository->saveRow($rowKey !== '' ? $rowKey : null, $level, $type, $name, trim(GETPOST('comment', 'alphanohtml')));
		setEventMessages($langs->trans('RecordSaved'), null);
		header('Location: '.$self);
		exit;
	}

	if ($action === 'confirm_deleterow' && GETPOST('confirm', 'alpha') === 'yes') {
		$repository->deleteRow($rowKey);
		setEventMessages($langs->trans('RecordDeleted'), null);
		header('Location: '.$self);
		exit;
	}

	if ($action === 'confirm_deleteyear' && GETPOST('confirm', 'alpha') === 'yes') {
		$repository->deleteYear($year);
		setEventMessages($langs->trans('AME_YearDeleted', $year), null);
		header('Location: '.$self);
		exit;
	}
} catch (InvalidArgumentException $e) {
	setEventMessages($langs->trans($e->getMessage()), null, 'errors');
	$action = ($action === 'saverow') ? 'editrow' : (($action === 'addyear') ? 'addyearform' : '');
} catch (RuntimeException $e) {
	setEventMessages($langs->trans('AME_ErrorDatabase', $e->getMessage()), null, 'errors');
	$action = '';
}

/*
 * View
 */

try {
	$years = $repository->years();
	$rows = $repository->matrix();
	$dbError = '';
} catch (RuntimeException $e) {
	$years = array();
	$rows = array();
	$dbError = $e->getMessage();
}

llxHeader('', $langs->trans('AME_RatesTitle'), '', '', 0, 0, '', '', '', 'mod-automicroent page-rates');

$buttons = '';
if ($years) {
	$buttons .= dolGetButtonTitle($langs->trans('AME_AddYear'), '', 'fa fa-calendar-plus', $self.'?action=addyearform&token='.newToken());
	$buttons .= dolGetButtonTitle($langs->trans('AME_AddRow'), '', 'fa fa-plus-circle', $self.'?action=editrow&token='.newToken());
}
print load_fiche_titre($langs->trans('AME_RatesTitle'), $buttons, 'fa-percent');

if ($dbError !== '') {
	print info_admin($langs->trans('AME_ErrorReactivate', $dbError), 0, 0, '1', 'error');
	llxFooter();
	$db->close();
	exit;
}

// Confirmations
if ($action === 'deleterow' && isset($rows[$rowKey])) {
	print $form->formconfirm($self.'?row='.urlencode($rowKey), $langs->trans('AME_DeleteRow'), $langs->trans('AME_ConfirmDeleteRow', $rows[$rowKey]['name']), 'confirm_deleterow', '', 0, 1);
}
if ($action === 'deleteyear' && in_array($year, $years, true)) {
	print $form->formconfirm($self.'?year='.$year, $langs->trans('AME_DeleteYear', $year), $langs->trans('AME_ConfirmDeleteYear', $year), 'confirm_deleteyear', '', 0, 1);
}

// First use: start from the catalog.
if (!$years) {
	print '<div class="info">'.$langs->trans('AME_NoRateYet').'</div>';
	print '<form method="POST" action="'.$self.'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="addfirstyear">';
	print '<table class="noborder centpercent"><tr class="liste_titre"><th colspan="2">'.$langs->trans('AME_StartFromCatalog').'</th></tr>';
	print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('AME_Profile').'</td><td>';
	print $form->selectarray('profile', ameProfileOptions(), ameProfile(), 1);
	print '</td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('AME_IncomeTax').'</td><td>';
	print '<input type="checkbox" id="income_tax" name="income_tax" value="1"'.(ameIncomeTax() ? ' checked' : '').'> ';
	print '<label for="income_tax">'.$langs->trans('AME_IncomeTaxHelp').'</label></td></tr>';
	print '</table>';
	print '<div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('AME_CreateYearFromCatalog', dol_print_date(dol_now(), '%Y')).'"></div>';
	print '</form>';
	llxFooter();
	$db->close();
	exit;
}

// Add a year
if ($action === 'addyearform') {
	$next = max($years) + 1;
	print '<form method="POST" action="'.$self.'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="addyear">';
	print '<table class="noborder centpercent"><tr class="liste_titre"><th colspan="2">'.$langs->trans('AME_AddYear').'</th></tr>';
	print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('Year').'</td><td>';
	print '<input type="number" class="width75" name="year" min="2000" max="2100" value="'.($year ?: $next).'"></td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('AME_YearSource').'</td><td>';
	$sources = array(
		AmeRateRepository::SOURCE_PREVIOUS => $langs->trans('AME_SourcePrevious'),
		AmeRateRepository::SOURCE_CATALOG => $langs->trans('AME_SourceCatalog'),
		AmeRateRepository::SOURCE_EMPTY => $langs->trans('AME_SourceEmpty'),
	);
	foreach ($sources as $value => $label) {
		$disabled = ($value === AmeRateRepository::SOURCE_CATALOG && ameProfile() === '') ? ' disabled' : '';
		print '<div><input type="radio" id="source_'.$value.'" name="source" value="'.$value.'"'.($value === AmeRateRepository::SOURCE_PREVIOUS ? ' checked' : '').$disabled.'> ';
		print '<label for="source_'.$value.'">'.$label.'</label></div>';
	}
	if (ameProfile() === '') {
		print '<span class="opacitymedium">'.$langs->trans('AME_CatalogNeedsProfile', dol_buildpath('/automicroent/admin/setup.php', 1)).'</span>';
	}
	print '</td></tr></table>';
	print '<div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('Add').'"> ';
	print '<a class="button button-cancel" href="'.$self.'">'.$langs->trans('Cancel').'</a></div>';
	print '</form><br>';
}

// Create or edit a row
if ($action === 'editrow') {
	$row = $rows[$rowKey] ?? array('key' => '', 'level' => GETPOSTINT('level') ?: 1, 'type' => GETPOSTINT('type'), 'name' => GETPOST('name', 'alphanohtml'), 'comment' => GETPOST('comment', 'alphanohtml'));
	print '<form method="POST" action="'.$self.'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="saverow">';
	print '<input type="hidden" name="row" value="'.dol_escape_htmltag($row['key']).'">';
	print '<table class="noborder centpercent"><tr class="liste_titre"><th colspan="2">'.$langs->trans($row['key'] ? 'AME_EditRow' : 'AME_AddRow').'</th></tr>';
	print '<tr class="oddeven"><td class="titlefield fieldrequired">'.$langs->trans('Label').'</td><td>';
	print '<input type="text" class="minwidth300" name="name" maxlength="48" value="'.dol_escape_htmltag($row['name'] === AmeRateRepository::LEGACY_UNNAMED ? '' : $row['name']).'" autofocus></td></tr>';
	print '<tr class="oddeven"><td class="fieldrequired">'.$langs->trans('AME_Category').'</td><td>';
	$levels = array();
	foreach (AmeContributionCalculator::levels() as $level) {
		$levels[$level] = ameLevelLabel($level);
	}
	print $form->selectarray('level', $levels, $row['level']);
	print '</td></tr>';
	print '<tr class="oddeven"><td class="fieldrequired">'.$langs->trans('AME_Base').'</td><td>';
	$bases = array();
	foreach (AmeContributionCalculator::bases() as $base) {
		$bases[$base] = ameBaseLabel($base);
	}
	print $form->selectarray('type', $bases, $row['type']);
	print '</td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('Comment').'</td><td>';
	print '<input type="text" class="minwidth300" name="comment" maxlength="255" value="'.dol_escape_htmltag($row['comment']).'"></td></tr>';
	print '</table>';
	print '<div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"> ';
	print '<a class="button button-cancel" href="'.$self.'">'.$langs->trans('Cancel').'</a></div>';
	print '</form><br>';
}

// The table: years in columns, rates in rows.
print '<form method="POST" action="'.$self.'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save">';
print '<div class="div-table-responsive">';
print '<table class="noborder centpercent ame-rates" id="ame-rates">';
print '<tr class="liste_titre">';
print '<th>'.$langs->trans('AME_Tax').'</th>';
print '<th>'.$langs->trans('AME_Base').'</th>';
foreach ($years as $y) {
	$delete = '<a class="paddingleft" href="'.$self.'?action=deleteyear&year='.$y.'&token='.newToken().'" title="'.dol_escape_htmltag($langs->trans('AME_DeleteYear', $y)).'">'.img_delete().'</a>';
	print '<th class="center nowraponall">'.$y.$delete.'</th>';
}
print '<th></th>';
print '</tr>';

$colspan = count($years) + 3;
if (!$rows) {
	print '<tr class="oddeven"><td colspan="'.$colspan.'"><span class="opacitymedium">'.$langs->trans('AME_NoRateRow').'</span></td></tr>';
}
$currentLevel = null;
foreach ($rows as $row) {
	if ($row['level'] !== $currentLevel) {
		$currentLevel = $row['level'];
		print '<tr class="liste_titre_sub"><td colspan="'.$colspan.'"><strong>'.dol_escape_htmltag(ameLevelLabel($currentLevel)).'</strong></td></tr>';
	}
	$name = ($row['name'] === AmeRateRepository::LEGACY_UNNAMED) ? '<span class="opacitymedium">'.$langs->trans('AME_Unnamed').'</span>' : dol_escape_htmltag($row['name']);
	print '<tr class="oddeven" data-row="'.dol_escape_htmltag($row['key']).'">';
	print '<td class="tdoverflowmax300">'.$name;
	if ($row['comment'] !== '') {
		print ' '.$form->textwithpicto('', dol_escape_htmltag($row['comment']));
	}
	print '</td>';
	print '<td>'.ameBaseLabel($row['type']).'</td>';
	foreach ($years as $y) {
		$rate = $row['cells'][$y]['rate'] ?? null;
		$value = ($rate === null) ? '' : rtrim(rtrim(number_format($rate, 4, '.', ''), '0'), '.');
		$label = dol_escape_htmltag($row['name'].' '.$y);
		$isThreshold = ($row['level'] === AmeContributionCalculator::LEVEL_THRESHOLD);
		print '<td class="center nowraponall">';
		print '<input type="number" step="any" min="0"'.($isThreshold ? '' : ' max="100"').' class="flat '.($isThreshold ? 'width100' : 'width75').' right" name="rate['.$row['key'].']['.$y.']" value="'.$value.'" aria-label="'.$label.'" placeholder="–"> '.($isThreshold ? '€' : '%');
		print '</td>';
	}
	print '<td class="right nowraponall">';
	print '<a class="editfielda paddingright" href="'.$self.'?action=editrow&row='.urlencode($row['key']).'&token='.newToken().'" title="'.$langs->trans('Modify').'">'.img_edit().'</a>';
	print '<a href="'.$self.'?action=deleterow&row='.urlencode($row['key']).'&token='.newToken().'" title="'.$langs->trans('Delete').'">'.img_delete().'</a>';
	print '</td>';
	print '</tr>';
}
print '</table>';
print '</div>';
print '<div class="opacitymedium small">'.$langs->trans('AME_EmptyRateHelp').'</div>';
$hasThresholds = false;
foreach ($rows as $row) {
	$hasThresholds = $hasThresholds || $row['level'] === AmeContributionCalculator::LEVEL_THRESHOLD;
}
if (!$hasThresholds && ameProfile() !== '') {
	print '<div class="info">'.$langs->trans('AME_NoThreshold').' ';
	print '<a class="button smallpaddingimp" href="'.$self.'?action=addthresholds&token='.newToken().'">'.$langs->trans('AME_AddThresholds').'</a></div>';
}
if ($rows) {
	print '<div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"></div>';
}
print '</form>';

llxFooter();
$db->close();
