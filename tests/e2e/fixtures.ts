import { test as base, expect } from '@playwright/test';

/**
 * Fail a test when a page shows a PHP error, which Dolibarr prints
 * inline when it is not in production mode.
 */
export const test = base.extend({
  page: async ({ page }, use) => {
    const responses: string[] = [];
    page.on('response', async (response) => {
      if (response.request().resourceType() !== 'document') return;
      const body = await response.text().catch(() => '');
      if (/(Fatal error|Parse error|Uncaught )/.test(body)) responses.push(response.url());
    });
    await use(page);
    expect(responses, 'pages with a PHP error').toEqual([]);
  },
});

export { expect };
