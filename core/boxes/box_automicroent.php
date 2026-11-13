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
 * \file    automicroent/core/boxes/box_automicroent.php
 * \ingroup automicroent
 * \brief   Home page widget: the period to declare and the ceilings.
 */

include_once DOL_DOCUMENT_ROOT.'/core/boxes/modules_boxes.php';
require_once __DIR__.'/../../lib/automicroent.lib.php';

/**
 * Widget showing the turnover and estimated contributions of the current
 * declaration period, its deadline and the ceilings of the year.
 */
class box_automicroent extends ModeleBoxes
{
	/** @var string */
	public $boxcode = 'automicroent';
	/** @var string */
	public $boximg = 'fa-file-invoice';
	/** @var string */
	public $boxlabel = 'AME_BoxTitle';
	/** @var string[] */
	public $depends = array('automicroent');

	/**
	 * @param DoliDB $db    Database handler
	 * @param string $param More parameters
	 */
	public function __construct($db, $param = '')
	{
		global $user;
		parent::__construct($db, $param);
		$this->hidden = !$user->hasRight('automicroent', 'use');
	}

	/**
	 * Load the widget content.
	 *
	 * @param int $max Unused
	 * @return void
	 */
	public function loadBox($max = 5)
	{
		global $langs;
		$langs->load('automicroent@automicroent');

		$url = dol_buildpath('/automicroent/declaration.php', 1);
		$this->info_box_head = array('text' => $langs->trans('AME_BoxTitle'), 'limit' => 0);

		$today = ameToday();
		$year = (int) substr($today, 0, 4);
		$periods = AmeDeclarationPeriods::forYear($year, amePeriodicity());
		$focused = AmeDeclarationPeriods::focused($periods, $today);
		// In January, the period to declare is the last one of the year before.
		$previousYear = AmeDeclarationPeriods::forYear($year - 1, amePeriodicity());
		$last = end($previousYear);
		if (AmeDeclarationPeriods::status($last, $today) === AmeDeclarationPeriods::STATUS_DUE) {
			$year--;
			$focused = $last['index'];
		}

		try {
			$receipts = amePayments()->receipts($year, $year, ameTurnoverCalculator());
			$repository = ameRates();
			$rows = ameYearPeriods($year, $receipts, $repository->rates($year));
			$thresholds = $repository->thresholds($year);
		} catch (RuntimeException $e) {
			$this->info_box_contents[0][0] = array('td' => '', 'text' => $langs->trans('AME_ErrorReactivate', $e->getMessage()));
			return;
		}

		$row = $rows[$focused - 1];
		$period = $row['period'];
		$status = AmeDeclarationPeriods::status($period, $today);
		$line = 0;

		$this->info_box_contents[$line][] = array(
			'td' => 'class="tdoverflowmax200"',
			'text' => '<a href="'.$url.'?year='.$year.'&period='.$period['index'].'">'.amePeriodLabel($period, $year).'</a>',
			'asis' => 1,
		);
		$this->info_box_contents[$line][] = array('td' => 'class="right"', 'text' => ameStatusBadge($status), 'asis' => 1);
		$line++;

		$items = array(
			$langs->trans('AME_TurnoverToDeclare') => ameMoney(ameSum($row['estimate']['declared'], AmeContributionCalculator::BASE_ALL), false),
			$langs->trans('AME_EstimatedContributions') => ameMoney($row['estimate']['total'], false),
			$langs->trans('AME_Deadline') => ameDate($period['deadline']),
		);
		foreach ($items as $label => $value) {
			$this->info_box_contents[$line][] = array('td' => '', 'text' => $label);
			$this->info_box_contents[$line][] = array('td' => 'class="right nowraponall amount"', 'text' => $value, 'asis' => 1);
			$line++;
		}

		$monthly = AmeTurnoverCalculator::monthly($receipts, $year);
		$turnover = AmeTurnoverCalculator::sumMonths($monthly, range(1, 12));
		$checks = AmeThresholdCheck::check(
			ameSum($turnover, AmeContributionCalculator::BASE_SALES),
			ameSum($turnover, AmeContributionCalculator::BASE_SERVICES),
			$thresholds,
			ameThresholdAlert()
		);
		foreach ($checks as $check) {
			$this->info_box_contents[$line][] = array('td' => '', 'text' => $check['name']);
			$this->info_box_contents[$line][] = array('td' => 'class="right nowraponall"', 'text' => ameThresholdGauge($check), 'asis' => 1);
			$line++;
		}
	}

	/**
	 * Show the widget.
	 *
	 * @param array<string, mixed>|null $head     Unused
	 * @param array<mixed>|null         $contents Unused
	 * @param int<0, 1>                 $nooutput No print, only return the string
	 * @return string
	 */
	public function showBox($head = null, $contents = null, $nooutput = 0)
	{
		return parent::showBox($this->info_box_head, $this->info_box_contents, $nooutput);
	}
}
