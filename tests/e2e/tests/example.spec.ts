import { test, expect } from '@playwright/test';

test('submit form and see saved message', async ({ page }) => {
  await page.goto('/');
  await page.fill('input[name="value"]', 'hello-playwright');
  await page.click('button[type="submit"]');
  await expect(page.locator('text=Saved.')).toHaveCount(1);
});
