import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

test('submit form and see saved message', async ({ page }, testInfo) => {
  const outDir = path.join(process.cwd(), 'tests', 'e2e', 'artifacts');
  try {
    await page.goto('/');
    await page.fill('input[name="value"]', 'hello-playwright');

    // click and wait for either navigation or the success text
    await page.click('button[type="submit"]');

    await expect(page.locator('text=Saved.')).toHaveCount(1, { timeout: 15000 });
  } catch (err) {
    // Capture debug artifacts for CI debugging
    try {
      if (!fs.existsSync(outDir)) fs.mkdirSync(outDir, { recursive: true });
      const prefix = `failure-${Date.now()}`;
      await page.screenshot({ path: path.join(outDir, `${prefix}.png`), fullPage: true });
      const html = await page.content();
      fs.writeFileSync(path.join(outDir, `${prefix}.html`), html, 'utf8');
      console.error('Saved debug artifacts to', outDir);
    } catch (inner) {
      console.error('Failed saving artifacts', inner);
    }
    throw err;
  }
});
