import { test as base, expect } from '@playwright/test';

/**
 * Fail a test when a page shows a PHP error, warning or notice, which
 * Dolibarr prints inline when it is not in production mode.
 */
export const test = base.extend({
  page: async ({ page }, use) => {
    const responses: string[] = [];
    page.on('response', async (response) => {
      if (response.request().resourceType() !== 'document') return;
      const body = await response.text().catch(() => '');
      const match = body.match(/<b>(Fatal error|Parse error|Warning|Notice|Deprecated)<\/b>:[^<]*<b>[^<]*<\/b>|Uncaught [A-Za-z]+/);
      if (match) responses.push(`${response.url()}: ${match[0]}`);
    });
    await use(page);
    expect(responses, 'pages with a PHP error').toEqual([]);
  },
});

export { expect };

/** Module page URL, inside the accountancy menu. */
export function modulePage(path: string, params: Record<string, string | number> = {}): string {
  const query = new URLSearchParams({ mainmenu: 'accountancy', leftmenu: 'automicroent' });
  for (const [key, value] of Object.entries(params)) query.set(key, String(value));
  return `/custom/automicroent/${path}?${query}`;
}
