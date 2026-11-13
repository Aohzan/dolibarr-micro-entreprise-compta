<?php
/* Copyright (C) 2026 Aohzan (fork: https://github.com/Aohzan/dolibarr-micro-entreprise-compta)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../class/turnovercalculator.class.php';

/**
 * Splitting payments into turnover per activity.
 */
final class TurnoverCalculatorTest extends TestCase
{
	/**
	 * Invoice line.
	 *
	 * @param int   $type 0 goods, 1 service, 9 title
	 * @param float $ht   Total excluding VAT
	 * @param float $ttc  Total including VAT
	 * @return array{product_type: int, fk_product: int, total_ht: float, total_ttc: float}
	 */
	private static function line(int $type, float $ht, ?float $ttc = null): array
	{
		return array('product_type' => $type, 'fk_product' => 0, 'total_ht' => $ht, 'total_ttc' => $ttc ?? $ht);
	}

	/**
	 * Payment allocation.
	 *
	 * @param int    $id      Payment id
	 * @param string $date    Y-m-d
	 * @param float  $amount  Amount
	 * @param int    $invoice Invoice id
	 * @return array{payment_id: int, date: string, invoice_id: int, amount: float}
	 */
	private static function pay(int $id, string $date, float $amount, int $invoice = 1): array
	{
		return array('payment_id' => $id, 'date' => $date, 'invoice_id' => $invoice, 'amount' => $amount);
	}

	/**
	 * Invoice.
	 *
	 * @param list<array<string, mixed>> $lines Lines
	 * @return array{ref: string, total_ttc: float, lines: list<array<string, mixed>>}
	 */
	private static function invoice(array $lines): array
	{
		return array('ref' => 'FA1', 'total_ttc' => array_sum(array_column($lines, 'total_ttc')), 'lines' => $lines);
	}

	/**
	 * @return void
	 */
	public function testFullPaymentCountsEachActivity(): void
	{
		$invoices = array(1 => self::invoice(array(self::line(0, 100), self::line(1, 400), self::line(9, 0))));
		$receipts = (new AmeTurnoverCalculator())->receipts($invoices, array(self::pay(1, '2026-03-15', 500)));

		$this->assertCount(1, $receipts);
		$this->assertSame(array('sale' => 100.0, 'service' => 400.0), $receipts[0]['activities']);
		$this->assertFalse($receipts[0]['partial']);
	}

	/**
	 * @return void
	 */
	public function testPartialPaymentsSettleGoodsFirstInTheirOwnMonth(): void
	{
		$invoices = array(1 => self::invoice(array(self::line(1, 400), self::line(0, 100))));
		$receipts = (new AmeTurnoverCalculator())->receipts($invoices, array(
			self::pay(2, '2026-04-02', 350),
			self::pay(1, '2026-03-30', 150),
		));

		$this->assertSame('2026-03-30', $receipts[0]['date']);
		$this->assertSame(array('sale' => 100.0, 'service' => 50.0), $receipts[0]['activities']);
		$this->assertSame(array('service' => 350.0), $receipts[1]['activities']);
		$this->assertTrue($receipts[0]['partial']);

		$monthly = AmeTurnoverCalculator::monthly($receipts, 2026);
		$this->assertSame(array('sale' => 100.0, 'service' => 50.0), $monthly[3]);
		$this->assertSame(array('service' => 350.0), $monthly[4]);
		$this->assertSame(array('sale' => 100.0, 'service' => 400.0), AmeTurnoverCalculator::sumMonths($monthly, array(1, 2, 3, 4)));
	}

	/**
	 * @return void
	 */
	public function testOverpaymentCountsAsServices(): void
	{
		$invoices = array(1 => self::invoice(array(self::line(0, 100))));
		$receipts = (new AmeTurnoverCalculator())->receipts($invoices, array(self::pay(1, '2026-01-10', 110)));

		$this->assertSame(array('sale' => 110.0), $receipts[0]['activities']);

		$invoices = array(1 => self::invoice(array(self::line(0, 100), self::line(1, 50))));
		$receipts = (new AmeTurnoverCalculator())->receipts($invoices, array(self::pay(1, '2026-01-10', 160)));
		$this->assertSame(array('sale' => 100.0, 'service' => 60.0), $receipts[0]['activities']);
	}

	/**
	 * @return void
	 */
	public function testVatIsLeftOut(): void
	{
		$invoices = array(1 => self::invoice(array(self::line(1, 1000, 1200))));
		$receipts = (new AmeTurnoverCalculator())->receipts($invoices, array(
			self::pay(1, '2026-02-01', 600),
			self::pay(2, '2026-05-01', 600),
		));

		$this->assertSame(array('service' => 500.0), $receipts[0]['activities']);
		$this->assertSame(array('service' => 500.0), $receipts[1]['activities']);
	}

	/**
	 * @return void
	 */
	public function testRefundOfACreditNoteLowersTheTurnover(): void
	{
		$invoices = array(7 => array('ref' => 'AV1', 'total_ttc' => -120.0, 'lines' => array(self::line(1, -120))));
		$receipts = (new AmeTurnoverCalculator())->receipts($invoices, array(self::pay(3, '2026-06-01', -120, 7)));

		$this->assertSame(array('service' => -120.0), $receipts[0]['activities']);
		$this->assertSame(7, $receipts[0]['invoice_id']);
	}

	/**
	 * @return void
	 */
	public function testDiscountLargerThanItsActivityIsSpreadProportionally(): void
	{
		$invoices = array(1 => self::invoice(array(self::line(0, -50), self::line(1, 250))));
		$receipts = (new AmeTurnoverCalculator())->receipts($invoices, array(self::pay(1, '2026-01-10', 100)));

		$this->assertSame(array('sale' => -25.0, 'service' => 125.0), $receipts[0]['activities']);
	}

	/**
	 * @return void
	 */
	public function testCustomActivityResolver(): void
	{
		$resolver = function (array $line): ?string {
			return $line['product_type'] == 1 ? 'service_bnc' : null;
		};
		$invoices = array(1 => self::invoice(array(self::line(1, 300), self::line(0, 20))));
		$receipts = (new AmeTurnoverCalculator($resolver))->receipts($invoices, array(self::pay(1, '2026-01-10', 300)));

		$this->assertSame(array('service_bnc' => 300.0), $receipts[0]['activities']);
	}

	/**
	 * @return void
	 */
	public function testRefundUnwindsServicesFirst(): void
	{
		$invoices = array(1 => self::invoice(array(self::line(0, 500), self::line(1, 500))));
		$receipts = (new AmeTurnoverCalculator())->receipts($invoices, array(
			self::pay(1, '2026-01-10', 700),
			self::pay(2, '2026-02-10', -300),
		));

		$this->assertSame(array('sale' => 500.0, 'service' => 200.0), $receipts[0]['activities']);
		$this->assertSame(array('sale' => -100.0, 'service' => -200.0), $receipts[1]['activities']);
	}

	/**
	 * @return void
	 */
	public function testRefundBeforeAnyPaymentIsSpreadAndDoesNotEatTheNextPayment(): void
	{
		$invoices = array(1 => self::invoice(array(self::line(0, 100), self::line(1, 300))));
		$receipts = (new AmeTurnoverCalculator())->receipts($invoices, array(
			self::pay(1, '2026-01-10', -40),
			self::pay(2, '2026-02-10', 440),
		));

		$this->assertSame(array('sale' => -10.0, 'service' => -30.0), $receipts[0]['activities']);
		$this->assertSame(array('sale' => 110.0, 'service' => 330.0), $receipts[1]['activities']);
	}

	/**
	 * @return void
	 */
	public function testDepositAndItsDeductionKeepTheRealActivity(): void
	{
		$calculator = new AmeTurnoverCalculator(null, AmeTurnoverCalculator::SERVICE);
		$deposit = array('ref' => 'AC1', 'total_ttc' => 300.0, 'type' => AmeTurnoverCalculator::INVOICE_DEPOSIT, 'lines' => array(self::line(0, 300)));
		$final = self::invoice(array(self::line(1, 1000), self::line(0, -300) + array('discount' => true)));
		$receipts = $calculator->receipts(array(1 => $deposit, 2 => $final), array(
			self::pay(1, '2026-02-10', 300, 1),
			self::pay(2, '2026-05-10', 700, 2),
		));

		$this->assertSame(array('service' => 300.0), $receipts[0]['activities']);
		$this->assertSame(array('service' => 700.0), $receipts[1]['activities']);
	}

	/**
	 * @return void
	 */
	public function testDiscountLineLowersTheGoodsFirst(): void
	{
		$invoices = array(1 => self::invoice(array(self::line(0, 200), self::line(1, 800), self::line(1, -100) + array('discount' => true))));
		$receipts = (new AmeTurnoverCalculator())->receipts($invoices, array(self::pay(1, '2026-01-10', 900)));

		$this->assertSame(array('sale' => 100.0, 'service' => 800.0), $receipts[0]['activities']);
	}

	/**
	 * @return void
	 */
	public function testDepositSettlesTheGoodsOfItsProposalFirst(): void
	{
		// Deposit of 2,500 on a proposal of 6,555 of goods and 180 of services,
		// then the final invoice deducting it (case found on real data).
		$calculator = new AmeTurnoverCalculator(null, AmeTurnoverCalculator::SERVICE);
		$deposit = array(
			'ref' => 'AC1', 'total_ttc' => 2500.0, 'type' => AmeTurnoverCalculator::INVOICE_DEPOSIT,
			'lines' => array(self::line(1, 2500)),
			'source_lines' => array(self::line(0, 6555), self::line(1, 180)),
		);
		$final = self::invoice(array(self::line(0, 6555), self::line(1, 180), self::line(0, -2500) + array('discount' => true)));
		$receipts = $calculator->receipts(array(1 => $deposit, 2 => $final), array(
			self::pay(1, '2023-10-23', 2500, 1),
			self::pay(2, '2023-12-19', 4235, 2),
		));

		$this->assertSame(array('sale' => 2500.0), $receipts[0]['activities']);
		$this->assertSame(array('sale' => 4055.0, 'service' => 180.0), $receipts[1]['activities']);
	}

	/**
	 * @return void
	 */
	public function testDepositAboveItsProposalGoodsCountsTheRestAsServices(): void
	{
		$deposit = array(
			'ref' => 'AC1', 'total_ttc' => 500.0, 'type' => AmeTurnoverCalculator::INVOICE_DEPOSIT,
			'lines' => array(self::line(0, 500)),
			'source_lines' => array(self::line(0, 300), self::line(1, 1000)),
		);
		$receipts = (new AmeTurnoverCalculator())->receipts(array(1 => $deposit), array(self::pay(1, '2026-01-10', 500)));

		$this->assertSame(array('sale' => 300.0, 'service' => 200.0), $receipts[0]['activities']);
	}

	/**
	 * @return void
	 */
	public function testAllocationsOfUnknownInvoicesAreIgnored(): void
	{
		$receipts = (new AmeTurnoverCalculator())->receipts(array(), array(self::pay(1, '2026-01-10', 300, 42)));
		$this->assertSame(array(), $receipts);
	}
}
