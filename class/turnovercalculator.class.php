<?php
/* Copyright (C) 2013-2018 Fabrice Delliaux <netbox253@gmail.com>
 * Copyright (C) 2026 Aohzan (fork: https://github.com/Aohzan/dolibarr-micro-entreprise-compta)
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
 * \file    automicroent/class/turnovercalculator.class.php
 * \ingroup automicroent
 * \brief   Turnover collected per payment, month and activity (cash basis).
 */

/**
 * Splits customer payments into turnover per activity, excluding VAT.
 *
 * A micro-entreprise declares what it collects: each payment counts in the
 * month it was received. When an invoice is paid in several times, the
 * payments settle the goods (sales) first, then the services, as the module
 * always did. Amounts above the invoice total count as services.
 *
 * Pure PHP: the data comes from AmePaymentRepository, so it is unit tested.
 *
 * @phpstan-type InvoiceLine array{product_type: int, fk_product: int, total_ht: float, total_ttc: float}
 * @phpstan-type Invoice array{ref: string, total_ttc: float, lines: list<InvoiceLine>}
 * @phpstan-type Allocation array{payment_id: int, date: string, invoice_id: int, amount: float}
 * @phpstan-type Receipt array{payment_id: int, date: string, invoice_id: int, invoice_ref: string, amount: float, partial: bool, activities: array<string, float>}
 */
class AmeTurnoverCalculator
{
	/** Goods sold (Dolibarr product type 0). */
	const SALE = 'sale';

	/** Services (Dolibarr product type 1). */
	const SERVICE = 'service';

	/**
	 * @var callable(array{product_type: int, fk_product: int}): ?string Activity of an invoice line, null to ignore it
	 */
	private $activityOf;

	/**
	 * @param callable(array{product_type: int, fk_product: int}): ?string|null $activityOf Activity of an invoice line, null to ignore the line. Default: by product type.
	 */
	public function __construct(?callable $activityOf = null)
	{
		$this->activityOf = $activityOf ?? array(self::class, 'activityByProductType');
	}

	/**
	 * Default activity of an invoice line: goods are sales, services are services.
	 * Other lines (titles, subtotals) carry no turnover.
	 *
	 * @param array{product_type: int, fk_product: int} $line Invoice line
	 * @return string|null
	 */
	public static function activityByProductType(array $line): ?string
	{
		switch ((int) $line['product_type']) {
			case 0:
				return self::SALE;
			case 1:
				return self::SERVICE;
			default:
				return null;
		}
	}

	/**
	 * Split every payment allocation into turnover per activity.
	 *
	 * @param array<int, Invoice> $invoices    Invoices keyed by id
	 * @param list<Allocation>    $allocations Every allocation of these invoices, whatever its date,
	 *                                         because earlier payments decide what a later one settles
	 * @return list<Receipt> One receipt per allocation, in date order
	 */
	public function receipts(array $invoices, array $allocations): array
	{
		usort($allocations, function ($a, $b) {
			return array($a['date'], $a['payment_id']) <=> array($b['date'], $b['payment_id']);
		});

		$byInvoice = array();
		foreach ($allocations as $allocation) {
			$byInvoice[$allocation['invoice_id']][] = $allocation;
		}

		$receipts = array();
		foreach ($byInvoice as $invoiceId => $invoiceAllocations) {
			if (!isset($invoices[$invoiceId])) {
				continue;
			}
			foreach ($this->invoiceReceipts($invoices[$invoiceId], $invoiceAllocations) as $receipt) {
				$receipt['invoice_id'] = $invoiceId;
				$receipts[] = $receipt;
			}
		}

		usort($receipts, function ($a, $b) {
			return array($a['date'], $a['payment_id'], $a['invoice_id']) <=> array($b['date'], $b['payment_id'], $b['invoice_id']);
		});
		return $receipts;
	}

	/**
	 * Sum receipts per month of a year and per activity.
	 *
	 * @param list<array{date: string, activities: array<string, float>}> $receipts Receipts
	 * @param int           $year     Year
	 * @return array<int, array<string, float>> Month (1-12) => activity => amount
	 */
	public static function monthly(array $receipts, int $year): array
	{
		$months = array_fill(1, 12, array());
		foreach ($receipts as $receipt) {
			if ((int) substr($receipt['date'], 0, 4) !== $year) {
				continue;
			}
			$month = (int) substr($receipt['date'], 5, 2);
			foreach ($receipt['activities'] as $activity => $amount) {
				$months[$month][$activity] = round(($months[$month][$activity] ?? 0) + $amount, 2);
			}
		}
		return $months;
	}

	/**
	 * Sum per activity the months of a period.
	 *
	 * @param array<int, array<string, float>> $monthly Output of monthly()
	 * @param list<int>                        $months  Months of the period
	 * @return array<string, float>
	 */
	public static function sumMonths(array $monthly, array $months): array
	{
		$sum = array();
		foreach ($months as $month) {
			foreach ($monthly[$month] ?? array() as $activity => $amount) {
				$sum[$activity] = round(($sum[$activity] ?? 0) + $amount, 2);
			}
		}
		return $sum;
	}

	/**
	 * Receipts of one invoice.
	 *
	 * @param Invoice          $invoice     Invoice
	 * @param list<Allocation> $allocations Its allocations, in date order
	 * @return list<array{payment_id: int, date: string, invoice_ref: string, amount: float, partial: bool, activities: array<string, float>}>
	 */
	private function invoiceReceipts(array $invoice, array $allocations): array
	{
		// Group lines per activity, goods first: they are settled first.
		$groups = array();
		foreach ($invoice['lines'] as $line) {
			$activity = call_user_func($this->activityOf, $line);
			if ($activity === null) {
				continue;
			}
			$groups[$activity]['ht'] = ($groups[$activity]['ht'] ?? 0) + (float) $line['total_ht'];
			$groups[$activity]['ttc'] = ($groups[$activity]['ttc'] ?? 0) + (float) $line['total_ttc'];
		}
		uksort($groups, function ($a, $b) {
			return array($a !== self::SALE, $a) <=> array($b !== self::SALE, $b);
		});

		$total = 0.0;
		foreach ($groups as $group) {
			$total += $group['ttc'];
		}

		$receipts = array();
		if (abs($total) < 0.005) {
			return $receipts;
		}

		// Work on magnitudes so that credit notes (negative totals) follow the same rules.
		$sign = $total < 0 ? -1 : 1;
		$proportional = false;
		foreach ($groups as $group) {
			if ($group['ttc'] * $sign < 0) {
				$proportional = true; // a discount line larger than its activity
			}
		}

		$paid = 0.0;
		$single = count($allocations) === 1;
		foreach ($allocations as $allocation) {
			$amount = (float) $allocation['amount'] * $sign;
			$shares = ($proportional || $amount < 0)
				? $this->proportionalShares($groups, $total, $amount)
				: $this->settleInOrder($groups, $sign, $paid, $amount);
			$paid += $amount;

			$activities = array();
			foreach ($shares as $activity => $share) {
				$group = $groups[$activity] ?? null;
				$ratio = ($group && abs($group['ttc']) >= 0.005) ? $group['ht'] / $group['ttc'] : 1.0;
				$activities[$activity] = round(($activities[$activity] ?? 0) + $share * $ratio * $sign, 2);
			}

			$receipts[] = array(
				'payment_id' => (int) $allocation['payment_id'],
				'date' => $allocation['date'],
				'invoice_ref' => $invoice['ref'],
				'amount' => round((float) $allocation['amount'], 2),
				'partial' => !$single || abs($amount - $total * $sign) >= 0.005,
				'activities' => $activities,
			);
		}
		return $receipts;
	}

	/**
	 * Settle the activities in order, from what was already paid.
	 *
	 * @param array<string, array{ht: float, ttc: float}> $groups Activities of the invoice
	 * @param int                                         $sign   1 for an invoice, -1 for a credit note
	 * @param float                                       $paid   Magnitude already paid before this payment
	 * @param float                                       $amount Magnitude of this payment (positive)
	 * @return array<string, float> Activity => magnitude including VAT
	 */
	private function settleInOrder(array $groups, int $sign, float $paid, float $amount): array
	{
		$shares = array();
		$start = 0.0;
		$from = $paid;
		$to = $paid + $amount;
		foreach ($groups as $activity => $group) {
			$end = $start + $group['ttc'] * $sign;
			$share = max(0.0, min($to, $end) - max($from, $start));
			if ($share > 0) {
				$shares[$activity] = $share;
			}
			$start = $end;
		}
		// Paid above the invoice total.
		$extra = $to - max($from, $start);
		if ($extra >= 0.005) {
			$activity = isset($groups[self::SERVICE]) ? self::SERVICE : (string) key(array_slice($groups, -1, 1, true));
			$shares[$activity] = ($shares[$activity] ?? 0) + $extra;
		}
		return $shares;
	}

	/**
	 * Spread a payment over the activities in proportion to their amounts.
	 *
	 * @param array<string, array{ht: float, ttc: float}> $groups Activities of the invoice
	 * @param float                                       $total  Invoice total including VAT
	 * @param float                                       $amount Magnitude of this payment
	 * @return array<string, float> Activity => magnitude including VAT
	 */
	private function proportionalShares(array $groups, float $total, float $amount): array
	{
		$shares = array();
		foreach ($groups as $activity => $group) {
			$shares[$activity] = $amount * $group['ttc'] / $total;
		}
		return $shares;
	}
}
