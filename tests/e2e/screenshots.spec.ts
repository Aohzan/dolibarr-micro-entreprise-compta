import type { Page } from '@playwright/test';
import { test, expect, modulePage } from './fixtures';

/**
 * README screenshots (npm run screenshots), from the seeded fictitious data.
 * Run against a fresh `mise run dev`, after `mise run e2e` or not.
 */
/**
 * Screenshot of an element. The window first grows to the page height: a
 * full-page screenshot would resize it during the capture, which redraws
 * the charts from zero, and the sticky top menu would cover the element.
 */
async function shot(page: Page, selector: string, name: string): Promise<void> {
  const height = await page.evaluate(() => document.documentElement.scrollHeight);
  await page.setViewportSize({ width: 1280, height });
  // Charts redraw on resize: let them finish.
  await page.waitForTimeout(selector === '#id-right' ? 2500 : 500);
  const box = await page.locator(selector).first().boundingBox();
  if (!box) throw new Error(`${selector} not found`);
  await page.screenshot({ path: `docs/screenshots/${name}.png`, animations: 'disabled', clip: box });
}

test.beforeAll(async ({ browser }) => {
  // The ceilings are not in the legacy seed: add the official ones once.
  const page = await browser.newPage();
  await page.goto(modulePage('rates.php'));
  const add = page.getByRole('link', { name: 'Ajouter les plafonds officiels' });
  if (await add.count()) await add.click();
  await page.close();
});

test('declarations', async ({ page }) => {
  await page.goto(modulePage('declaration.php', { year: 2026, period: 3 }));
  await expect(page.locator('#ame-periods')).toBeVisible();
  await shot(page, '#id-right', 'declarations');
});

test('rates', async ({ page }) => {
  await page.goto(modulePage('rates.php'));
  await shot(page, '#id-right', 'rates');
});

test('statistics', async ({ page }) => {
  await page.goto(modulePage('stats.php'));
  await expect(page.locator('canvas')).toHaveCount(3);
  await shot(page, '#id-right', 'statistics');
});

test('setup', async ({ page }) => {
  await page.goto('/custom/automicroent/admin/setup.php?mainmenu=home');
  await shot(page, '#id-right', 'setup');
});

test('widget', async ({ page }) => {
  await page.goto('/index.php?mainmenu=home');
  await shot(page, '.box:has-text("Micro-entreprise : déclaration en cours")', 'widget');
});
