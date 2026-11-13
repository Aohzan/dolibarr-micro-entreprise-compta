import { defineConfig, devices } from '@playwright/test';

/**
 * End-to-end tests against the docker compose environment (mise run dev).
 * The seed (dev/docker-init.d/50-seed.php) provides deterministic data.
 */
export default defineConfig({
  testDir: 'tests/e2e',
  outputDir: '.cache/playwright/results',
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  workers: 1,
  reporter: process.env.CI ? [['github'], ['html', { open: 'never', outputFolder: '.cache/playwright/report' }]] : 'list',
  globalSetup: './tests/e2e/global-setup.ts',
  use: {
    baseURL: process.env.DOLIBARR_URL ?? `http://localhost:${process.env.DOLIBARR_PORT ?? '8080'}`,
    storageState: '.cache/playwright/admin.json',
    locale: 'fr-FR',
    timezoneId: 'Europe/Paris',
    trace: 'retain-on-failure',
  },
  projects: [
    {
      name: 'e2e',
      testIgnore: /screenshots\.spec\.ts/,
      use: { ...devices['Desktop Chrome'] },
    },
    {
      name: 'screenshots',
      testMatch: /screenshots\.spec\.ts/,
      use: { ...devices['Desktop Chrome'], viewport: { width: 1280, height: 900 } },
    },
  ],
});
