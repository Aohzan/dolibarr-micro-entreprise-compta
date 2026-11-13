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
 * \file    automicroent/class/deadlinereminder.class.php
 * \ingroup automicroent
 * \brief   Scheduled job: an agenda event before each URSSAF deadline.
 */

require_once __DIR__.'/../lib/automicroent.lib.php';

/**
 * Creates an agenda event, and optionally sends an email, some days before
 * each declaration deadline. Run daily by the Dolibarr scheduled jobs.
 */
class AmeDeadlineReminder
{
	/** Prefix of the external reference of the events, which keeps them unique. */
	const REF_PREFIX = 'automicroent-';

	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * @var string Output shown in the scheduled job history
	 */
	public $output = '';

	/**
	 * @var string Error message
	 */
	public $error = '';

	/**
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Periods whose reminder is due on a given day: the deadline is within
	 * the next $days days and not passed.
	 *
	 * @param string $today       Day as Y-m-d
	 * @param int    $days        Days before the deadline
	 * @param string $periodicity AmeDeclarationPeriods::QUARTERLY or MONTHLY
	 * @return list<array{year: int, period: array{index: int, months: list<int>, start: string, end: string, deadline: string}}>
	 */
	public static function duePeriods(string $today, int $days, string $periodicity): array
	{
		$limit = date('Y-m-d', strtotime($today.' +'.max(0, $days).' days'));
		$due = array();
		$year = (int) substr($today, 0, 4);
		foreach (array($year - 1, $year) as $y) {
			foreach (AmeDeclarationPeriods::forYear($y, $periodicity) as $period) {
				if ($period['end'] < $today && $period['deadline'] >= $today && $period['deadline'] <= $limit) {
					$due[] = array('year' => $y, 'period' => $period);
				}
			}
		}
		return $due;
	}

	/**
	 * Scheduled job entry point.
	 *
	 * @return int 0 if OK, < 0 if KO
	 */
	public function createDeadlineEvents(): int
	{
		global $conf, $langs, $user;

		if (!isModEnabled('agenda')) {
			$this->error = 'The Agenda module must be enabled.';
			return -1;
		}
		require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
		// Scheduled jobs run without main.inc.php: load what the pages get from it.
		require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
		$langs->loadLangs(array('main', 'agenda', 'automicroent@automicroent'));

		$owner = new User($this->db);
		$ownerId = getDolGlobalInt('AUTOMICROENT_REMINDER_USER') ?: (int) $user->id;
		if ($owner->fetch($ownerId) <= 0) {
			$this->error = 'Reminder user '.$ownerId.' not found.';
			return -1;
		}

		$created = 0;
		$warnings = '';
		$due = self::duePeriods(ameToday(), getDolGlobalInt('AUTOMICROENT_REMINDER_DAYS', 7), amePeriodicity());
		foreach ($due as $item) {
			$ref = self::REF_PREFIX.amePeriodicity().'-'.$item['year'].'-'.$item['period']['index'];
			if ($this->eventExists($ref, (int) $conf->entity)) {
				continue;
			}

			$label = $langs->transnoentitiesnoconv('AME_ReminderLabel', amePeriodLabel($item['period'], $item['year']));
			$deadline = dol_stringtotime($item['period']['deadline'], 0);
			$note = $langs->transnoentitiesnoconv('AME_ReminderNote', ameDate($item['period']['deadline']), dol_buildpath('/automicroent/declaration.php', 2).'?year='.$item['year'].'&period='.$item['period']['index']);

			$event = new ActionComm($this->db);
			$event->type_code = 'AC_OTH';
			$event->code = 'AC_OTH';
			$event->label = $label;
			$event->note_private = $note;
			$event->datep = $deadline;
			$event->datef = $deadline;
			$event->fulldayevent = 1;
			$event->percentage = 0;
			$event->userownerid = $owner->id;
			$event->ref_ext = $ref;
			if ($event->create($owner) <= 0) {
				$this->error = $event->error;
				return -1;
			}
			$created++;

			if (getDolGlobalInt('AUTOMICROENT_REMINDER_EMAIL') && $owner->email) {
				$warnings .= $this->sendEmail($owner->email, $label, $note);
			}
		}

		$this->output = $created.' reminder(s) created.'.$warnings;
		return 0;
	}

	/**
	 * Whether the reminder event of a period exists.
	 *
	 * @param string $ref    External reference
	 * @param int    $entity Entity
	 * @return bool
	 */
	private function eventExists(string $ref, int $entity): bool
	{
		$sql = 'SELECT id FROM '.MAIN_DB_PREFIX."actioncomm WHERE ref_ext = '".$this->db->escape($ref)."' AND entity = ".$entity;
		$resql = $this->db->query($sql);
		return $resql && $this->db->num_rows($resql) > 0;
	}

	/**
	 * Send the reminder by email.
	 *
	 * @param string $to      Recipient
	 * @param string $subject Subject
	 * @param string $body    Text
	 * @return string Warning to show in the job output, '' when sent
	 */
	private function sendEmail(string $to, string $subject, string $body): string
	{
		require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
		$from = getDolGlobalString('MAIN_MAIL_EMAIL_FROM', getDolGlobalString('MAIN_INFO_SOCIETE_MAIL'));
		$mail = new CMailFile($subject, $to, $from, $body);
		return $mail->sendfile() ? '' : ' Email to '.$to.' not sent: '.$mail->error;
	}
}
