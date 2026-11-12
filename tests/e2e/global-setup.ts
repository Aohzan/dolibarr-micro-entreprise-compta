import { chromium, type FullConfig } from '@playwright/test';

/** Log in once as the seeded admin and save the session for every test. */
export default async function globalSetup(config: FullConfig): Promise<void> {
  const { baseURL, storageState, locale } = config.projects[0].use;
  const browser = await chromium.launch();
  const page = await browser.newPage({ baseURL, locale });
  await page.goto('/');
  await page.locator('#username').fill('admin');
  await page.locator('#password').fill('admin');
  await page.locator('#password').press('Enter');
  // The top menu only exists once logged in.
  await page.locator('#mainmenua_home').waitFor();
  await page.context().storageState({ path: storageState as string });
  await browser.close();
}
