import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests',
  timeout: 30 * 1000,
  expect: {
    timeout: 10 * 1000,
  },
  fullyParallel: false,
  retries: process.env.CI ? 2 : 0,
  use: {
    baseURL: 'http://127.0.0.1:8080',
    headless: true,
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
  },
});
