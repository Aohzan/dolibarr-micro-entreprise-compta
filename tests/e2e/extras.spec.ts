import { test, expect, modulePage } from './fixtures';

test('the home page shows the declaration widget', async ({ page }) => {
  await page.goto('/index.php?mainmenu=home');
  const box = page.locator('.box', { hasText: 'Micro-entreprise : déclaration en cours' });
  await expect(box).toBeVisible();
  await expect(box).toContainText('CA à déclarer');
  await expect(box).toContainText('Échéance');
});

test('official ceilings are added and the declaration warns above them', async ({ page }) => {
  await page.goto(modulePage('rates.php'));
  const add = page.getByRole('link', { name: 'Ajouter les plafonds officiels' });
  if (await add.count()) {
    await add.click();
  }
  await expect(page.locator('#ame-rates')).toContainText('Plafonds et seuils (€)');
  await expect(page.locator('#ame-rates tr:has(td:text-is("Franchise de TVA (Services)")) input[name$="[2026]"]')).toHaveValue('37500');

  // The seed collects about 41,000 EUR of services in 2026: above the VAT franchise.
  await page.goto(modulePage('declaration.php', { year: 2026 }));
  await expect(page.locator('#ame-thresholds tr', { hasText: 'Franchise de TVA (Services)' })).toHaveAttribute('data-state', 'over');
  await expect(page.locator('#ame-thresholds tr', { hasText: 'Plafond micro-entreprise (Services)' })).toHaveAttribute('data-state', 'ok');
  await expect(page.getByText('dépasse un plafond')).toBeVisible();
});

test('the setup page shows the reminder and category settings', async ({ page }) => {
  await page.goto('/custom/automicroent/admin/setup.php');
  await expect(page.locator('input[name="reminder_days"]')).toHaveValue('7');
  await expect(page.locator('select[name="category_bnc"]')).toHaveCount(1);
  await expect(page.locator('input[name="threshold_alert"]')).toHaveValue('80');
});
