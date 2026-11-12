import type { Page } from '@playwright/test';
import { test, expect, modulePage } from './fixtures';

// These tests change the rates: they run in order and restore what they change.
test.describe.configure({ mode: 'serial' });

const cell = (row: string, year: number) => `#ame-rates tr:has(td:text-is("${row}")) input[name$="[${year}]"]`;

/** Form creating or editing a tax row. */
const rowForm = (page: Page) => page.locator('form:has(input[name="action"][value="saverow"])');

// Put the seeded rate back even when a test fails halfway.
test.afterAll(async ({ browser }) => {
  const page = await browser.newPage();
  await page.goto(modulePage('rates.php'));
  await page.locator(cell('Cotisations sociales BNC', 2025)).fill('24.6');
  await page.getByRole('button', { name: 'Enregistrer' }).click();
  await page.close();
});

test('a rate is changed in place', async ({ page }) => {
  await page.goto(modulePage('rates.php'));
  await expect(page.locator(cell('Cotisations sociales BNC', 2025))).toHaveValue('24.6');
  await page.locator(cell('Cotisations sociales BNC', 2025)).fill('24.7');
  await page.getByRole('button', { name: 'Enregistrer' }).click();
  await expect(page.locator('.jnotify-container')).toContainText('1 taux modifié');
  await expect(page.locator(cell('Cotisations sociales BNC', 2025))).toHaveValue('24.7');

  await page.locator(cell('Cotisations sociales BNC', 2025)).fill('24.6');
  await page.getByRole('button', { name: 'Enregistrer' }).click();
  await expect(page.locator(cell('Cotisations sociales BNC', 2025))).toHaveValue('24.6');
});

test('a new year copies the previous one, then is deleted', async ({ page }) => {
  await page.goto(modulePage('rates.php'));
  await page.locator('a.btnTitle[title="Ajouter une année"]').click();
  await page.locator('input[name="year"]').fill('2027');
  await page.getByLabel('Recopier l\'année précédente').check();
  await page.getByRole('button', { name: 'Ajouter' }).click();
  await expect(page.locator('#ame-rates th', { hasText: '2027' })).toBeVisible();
  await expect(page.locator(cell('Cotisations sociales BNC', 2027))).toHaveValue('25.6');

  await page.locator('#ame-rates th', { hasText: '2027' }).getByRole('link').click();
  await page.getByRole('button', { name: 'Oui' }).click();
  await expect(page.locator('#ame-rates th', { hasText: '2027' })).toHaveCount(0);
});

test('a new year can start from the official catalog', async ({ page }) => {
  await page.goto(modulePage('rates.php'));
  await page.locator('a.btnTitle[title="Ajouter une année"]').click();
  await page.locator('input[name="year"]').fill('2028');
  await page.getByLabel('Repartir du catalogue officiel URSSAF').check();
  await page.getByRole('button', { name: 'Ajouter' }).click();
  await expect(page.locator('.jnotify-container')).toContainText('Taux du catalogue 2026');
  // Profile of the seed: non-regulated liberal profession.
  await expect(page.locator(cell('Cotisations sociales', 2028))).toHaveValue('25.6');
  await expect(page.locator(cell('Contribution à la formation professionnelle', 2028))).toHaveValue('0.2');

  await page.locator('#ame-rates th', { hasText: '2028' }).getByRole('link').click();
  await page.getByRole('button', { name: 'Oui' }).click();
  await expect(page.locator('#ame-rates th', { hasText: '2028' })).toHaveCount(0);
  for (const row of ['Cotisations sociales', 'Contribution à la formation professionnelle']) {
    await expect(page.locator(`#ame-rates td:text-is("${row}")`)).toHaveCount(0);
  }
});

test('a tax row is added, renamed and deleted', async ({ page }) => {
  await page.goto(modulePage('rates.php'));
  await page.locator('a.btnTitle[title="Ajouter une taxe"]').click();
  await page.locator('input[name="name"]').fill('Taxe test');
  await page.locator('select[name="level"]').selectOption('4', { force: true });
  await page.locator('select[name="type"]').selectOption('2', { force: true });
  await rowForm(page).getByRole('button', { name: 'Enregistrer' }).click();
  await expect(page.locator('#ame-rates td', { hasText: 'Taxe test' })).toBeVisible();

  await page.locator('#ame-rates tr', { hasText: 'Taxe test' }).locator('a[title="Modifier"]').click();
  await page.locator('input[name="name"]').fill('Taxe test renommée');
  await rowForm(page).getByRole('button', { name: 'Enregistrer' }).click();
  await expect(page.locator('#ame-rates td', { hasText: 'Taxe test renommée' })).toBeVisible();

  await page.locator('#ame-rates tr', { hasText: 'Taxe test renommée' }).locator('a[title="Supprimer"]').click();
  await page.getByRole('button', { name: 'Oui' }).click();
  await expect(page.locator('#ame-rates td', { hasText: 'Taxe test' })).toHaveCount(0);
});

test('renaming a tax like another one merges them when they share no year', async ({ page }) => {
  // Two rows of the same tax, named differently in 2023 and 2024, as the legacy module allowed.
  await page.goto(modulePage('rates.php'));
  for (const [name, year] of [['Fusion A', 2023], ['Fusion B', 2024]] as const) {
    await page.locator('a.btnTitle[title="Ajouter une taxe"]').click();
    await rowForm(page).locator('input[name="name"]').fill(name);
    await rowForm(page).locator('select[name="level"]').selectOption('4', { force: true });
    await rowForm(page).getByRole('button', { name: 'Enregistrer' }).click();
    await page.locator(cell(name, year)).fill('1');
    await page.locator(cell(name, 2026)).fill('');
    await page.getByRole('button', { name: 'Enregistrer' }).click();
  }

  await page.locator('#ame-rates tr', { hasText: 'Fusion B' }).locator('a[title="Modifier"]').click();
  await rowForm(page).locator('input[name="name"]').fill('Fusion A');
  await rowForm(page).getByRole('button', { name: 'Enregistrer' }).click();
  await expect(page.locator('#ame-rates td:text-is("Fusion B")')).toHaveCount(0);
  await expect(page.locator(cell('Fusion A', 2023))).toHaveValue('1');
  await expect(page.locator(cell('Fusion A', 2024))).toHaveValue('1');

  await page.locator('#ame-rates tr', { hasText: 'Fusion A' }).locator('a[title="Supprimer"]').click();
  await page.getByRole('button', { name: 'Oui' }).click();
  await expect(page.locator('#ame-rates td:text-is("Fusion A")')).toHaveCount(0);
});
