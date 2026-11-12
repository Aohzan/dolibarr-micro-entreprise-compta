import { test, expect, modulePage } from './fixtures';

/** Parse "36 451 €" (French format, narrow no-break spaces) into a number. */
function euros(text: string): number {
  return Number(text.replace(/[^\d,-]/g, '').replace(',', '.'));
}

// Sum of the seeded customer payments per year (SELECT SUM(amount) FROM llx_paiement),
// rounded to the euro: the seed has no VAT, so it is the turnover to declare.
const seededTurnover: Record<number, number> = { 2023: 37377, 2024: 39790, 2025: 36451 };

test('a past year shows four quarters whose turnover adds up to the payments', async ({ page }) => {
  await page.goto(modulePage('declaration.php', { year: 2025, period: 2 }));
  const rows = page.locator('#ame-periods tr[data-period]');
  await expect(rows).toHaveCount(4);
  await expect(rows.first()).toContainText('T1 2025');
  await expect(rows.first()).toContainText('30/04/2025');
  await expect(rows.last()).toContainText('31/01/2026');
  await expect(rows.first()).toContainText('Échéance passée');

  let sum = 0;
  for (const cell of await rows.locator('td:nth-child(5)').allTextContents()) sum += euros(cell);
  // Each quarter is rounded to the euro.
  expect(Math.abs(sum - seededTurnover[2025])).toBeLessThanOrEqual(4);

  await expect(page.locator('#ame-period')).toContainText('T2 2025 : à déclarer sur autoentrepreneur.urssaf.fr');
  await expect(page.locator('#ame-declare')).toContainText('Prestations de services (BNC)');
  await expect(page.locator('#ame-estimate')).toContainText('24,60 %');
  await expect(page.locator('#ame-receipts tr').nth(1).getByRole('link')).toHaveAttribute('href', /compta\/facture\/card\.php\?facid=\d+/);
});

test('the estimate applies each rate to its base', async ({ page }) => {
  await page.goto(modulePage('declaration.php', { year: 2025, period: 1 }));
  const social = page.locator('#ame-estimate tr', { hasText: 'Cotisations sociales BNC' });
  const base = euros(await social.locator('td').nth(1).innerText());
  const amount = euros(await social.locator('td').nth(3).innerText());
  expect(amount).toBeCloseTo(base * 0.246, 1);
});

test('the yearly turnover for the income tax return', async ({ page }) => {
  for (const [year, turnover] of Object.entries(seededTurnover)) {
    await page.goto(modulePage('declaration.php', { year }));
    let sum = 0;
    for (const cell of await page.locator('#ame-yearly td.amount').allTextContents()) sum += euros(cell);
    expect(Math.abs(sum - turnover), year).toBeLessThanOrEqual(1);
  }
});

test('the periods export as CSV', async ({ page }) => {
  await page.goto(modulePage('declaration.php', { year: 2025 }));
  const href = await page.locator('a.btnTitle[title="Export CSV"]').getAttribute('href');
  const response = await page.request.get(href ?? '');
  expect(response.headers()['content-disposition']).toContain('declarations-2025.csv');
  const lines = (await response.text()).trim().split('\n');
  expect(lines).toHaveLength(5);
  expect(lines[1]).toContain('2025-01-01;2025-03-31;2025-04-30');
});

test('statistics show every year with its turnover', async ({ page }) => {
  await page.goto(modulePage('stats.php', { range: 'all' }));
  await expect(page.locator('canvas')).toHaveCount(3);
  for (const [year, turnover] of Object.entries(seededTurnover)) {
    const row = page.locator('#ame-stats tr', { hasText: year });
    expect(euros(await row.locator('td').nth(3).innerText()), year).toBe(turnover);
  }
});
