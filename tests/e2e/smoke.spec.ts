import { test, expect } from './fixtures';

test('the module menu is in the accountancy menu', async ({ page }) => {
  await page.goto('/compta/index.php?mainmenu=accountancy&leftmenu=');
  await expect(page.locator('#id-left').getByRole('link', { name: 'Auto Micro Entreprise' })).toBeVisible();
});

test('the setup page lists the rates of a year', async ({ page }) => {
  await page.goto('/custom/automicroent/admin/index.php');
  await page.locator('select[name="year"]').selectOption('2025');
  await expect(page).toHaveURL(/year=2025/);
  await expect(page.getByText('Cotisations sociales BNC')).toBeVisible();
  await expect(page.getByText('24.60 %')).toBeVisible();
});

test('the report page shows the turnover', async ({ page }) => {
  await page.goto('/custom/automicroent/report/index.php?year_start=2024');
  await expect(page.locator('table.noborder').first()).toContainText('2026');
});
