import { test, expect, modulePage } from './fixtures';

test('the accountancy menu has a Micro-entreprise section', async ({ page }) => {
  await page.goto('/compta/index.php?mainmenu=accountancy&leftmenu=');
  const menu = page.locator('#id-left');
  await expect(menu.getByRole('link', { name: 'Micro-entreprise' })).toBeVisible();
  await page.goto(modulePage('declaration.php'));
  for (const name of ['Configuration', 'Déclarations', 'Statistiques']) {
    await expect(menu.getByRole('link', { name, exact: true })).toBeVisible();
  }
});

test('the former pages redirect to the new ones', async ({ page }) => {
  await page.goto('/custom/automicroent/report/index.php');
  await expect(page).toHaveURL(/\/automicroent\/declaration\.php/);
  await page.goto('/custom/automicroent/admin/index.php');
  await expect(page).toHaveURL(/\/automicroent\/admin\/setup\.php/);
});

test('the module setup saves the declaration frequency', async ({ page }) => {
  await page.goto('/custom/automicroent/admin/setup.php');
  await page.locator('select[name="periodicity"]').selectOption('monthly', { force: true });
  await page.getByRole('button', { name: 'Enregistrer' }).click();
  await page.goto(modulePage('declaration.php', { year: 2025 }));
  await expect(page.locator('#ame-periods tr[data-period]')).toHaveCount(12);

  await page.goto('/custom/automicroent/admin/setup.php');
  await page.locator('select[name="periodicity"]').selectOption('quarterly', { force: true });
  await page.getByRole('button', { name: 'Enregistrer' }).click();
  await page.goto(modulePage('declaration.php', { year: 2025 }));
  await expect(page.locator('#ame-periods tr[data-period]')).toHaveCount(4);
});
