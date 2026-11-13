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
 * \file    automicroent/admin/setup.php
 * \ingroup automicroent
 * \brief   Module setup: activity, declaration periodicity, income tax option.
 */

// Load Dolibarr: htdocs/automicroent or htdocs/custom/automicroent.
$res = 0;
if (!empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
	$res = @include $_SERVER['CONTEXT_DOCUMENT_ROOT'].'/main.inc.php';
}
foreach (array('../../main.inc.php', '../../../main.inc.php') as $main) {
	if (!$res && file_exists($main)) {
		$res = @include $main;
	}
}
if (!$res) {
	die('Include of main fails');
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once __DIR__.'/../lib/automicroent.lib.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */

$langs->loadLangs(array('admin', 'automicroent@automicroent'));
ameRequireRight('configure');

$action = GETPOST('action', 'aZ09');
$form = new Form($db);

if ($action === 'update') {
	$profile = GETPOST('profile', 'aZ09');
	if ($profile !== '' && !in_array($profile, AmeTaxCatalog::profiles(), true)) {
		$profile = '';
	}
	$periodicity = GETPOST('periodicity', 'aZ09') === AmeDeclarationPeriods::MONTHLY ? AmeDeclarationPeriods::MONTHLY : AmeDeclarationPeriods::QUARTERLY;
	dolibarr_set_const($db, 'AUTOMICROENT_PROFILE', $profile, 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'AUTOMICROENT_PERIODICITY', $periodicity, 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'AUTOMICROENT_INCOME_TAX', GETPOST('income_tax', 'alpha') ? 1 : 0, 'chaine', 0, '', $conf->entity);
	// The fields only show with the Categories module: keep the mapping otherwise.
	if (GETPOSTISSET('category_bic')) {
		dolibarr_set_const($db, 'AUTOMICROENT_CATEGORY_BIC', max(0, GETPOSTINT('category_bic')), 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, 'AUTOMICROENT_CATEGORY_BNC', max(0, GETPOSTINT('category_bnc')), 'chaine', 0, '', $conf->entity);
	}
	dolibarr_set_const($db, 'AUTOMICROENT_THRESHOLD_ALERT', max(1, min(100, GETPOSTINT('threshold_alert') ?: 80)), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'AUTOMICROENT_REMINDER_DAYS', max(0, min(60, GETPOSTINT('reminder_days'))), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'AUTOMICROENT_REMINDER_USER', max(0, GETPOSTINT('reminder_user')), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'AUTOMICROENT_REMINDER_EMAIL', GETPOST('reminder_email', 'alpha') ? 1 : 0, 'chaine', 0, '', $conf->entity);
	setEventMessages($langs->trans('SetupSaved'), null);
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

llxHeader('', $langs->trans('AME_SetupTitle'), '', '', 0, 0, '', '', '', 'mod-automicroent page-admin-setup');

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans('AME_SetupTitle'), $linkback, 'title_setup');

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans('Parameter').'</th><th>'.$langs->trans('Value').'</th></tr>';

print '<tr class="oddeven"><td class="titlefield">'.$form->textwithpicto($langs->trans('AME_Profile'), $langs->trans('AME_ProfileHelp')).'</td><td>';
print $form->selectarray('profile', ameProfileOptions(), ameProfile(), 1);
print '</td></tr>';

print '<tr class="oddeven"><td>'.$langs->trans('AME_Periodicity').'</td><td>';
print $form->selectarray('periodicity', array(
	AmeDeclarationPeriods::QUARTERLY => $langs->trans('AME_Periodicity_'.AmeDeclarationPeriods::QUARTERLY),
	AmeDeclarationPeriods::MONTHLY => $langs->trans('AME_Periodicity_'.AmeDeclarationPeriods::MONTHLY),
), amePeriodicity());
print '</td></tr>';

print '<tr class="oddeven"><td>'.$langs->trans('AME_IncomeTax').'</td><td>';
print '<input type="checkbox" id="income_tax" name="income_tax" value="1"'.(ameIncomeTax() ? ' checked' : '').'> ';
print '<label for="income_tax">'.$langs->trans('AME_IncomeTaxHelp').'</label>';
print '</td></tr>';

if (isModEnabled('categorie')) {
	print '<tr class="oddeven"><td>'.$form->textwithpicto($langs->trans('AME_CategoryBic'), $langs->trans('AME_CategoryHelp')).'</td><td>';
	print img_picto('', 'category', 'class="pictofixedwidth"').$form->select_all_categories('product', getDolGlobalString('AUTOMICROENT_CATEGORY_BIC'), 'category_bic', 64, 0, 0, 0, 'minwidth300');
	print '</td></tr>';
	print '<tr class="oddeven"><td>'.$form->textwithpicto($langs->trans('AME_CategoryBnc'), $langs->trans('AME_CategoryHelp')).'</td><td>';
	print img_picto('', 'category', 'class="pictofixedwidth"').$form->select_all_categories('product', getDolGlobalString('AUTOMICROENT_CATEGORY_BNC'), 'category_bnc', 64, 0, 0, 0, 'minwidth300');
	print '</td></tr>';
}

print '<tr class="oddeven"><td>'.$langs->trans('AME_ThresholdAlert').'</td><td>';
print '<input type="number" class="width50 right" name="threshold_alert" min="1" max="100" value="'.ameThresholdAlert().'"> %</td></tr>';

print '<tr class="liste_titre"><th colspan="2">'.$langs->trans('AME_Reminder').'</th></tr>';
print '<tr class="oddeven"><td>'.$form->textwithpicto($langs->trans('AME_ReminderDays'), $langs->trans('AME_ReminderHelp')).'</td><td>';
print '<input type="number" class="width50 right" name="reminder_days" min="0" max="60" value="'.getDolGlobalInt('AUTOMICROENT_REMINDER_DAYS', 7).'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('AME_ReminderUser').'</td><td>';
print $form->select_dolusers(getDolGlobalInt('AUTOMICROENT_REMINDER_USER') ?: (int) $user->id, 'reminder_user', 0, null, 0, '', '', '0', 0, 0, '', 0, '', 'minwidth200');
print '</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('AME_ReminderEmail').'</td><td>';
print '<input type="checkbox" id="reminder_email" name="reminder_email" value="1"'.(getDolGlobalInt('AUTOMICROENT_REMINDER_EMAIL') ? ' checked' : '').'></td></tr>';

print '</table>';
print '<div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"></div>';
print '</form>';

print '<br><div class="info">'.$langs->trans('AME_SetupRatesLink', dol_buildpath('/automicroent/rates.php', 1)).'</div>';

llxFooter();
$db->close();
