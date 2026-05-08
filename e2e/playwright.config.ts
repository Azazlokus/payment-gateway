import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright E2E конфиг.
 *
 * Запуск:
 *   cd e2e && npm install && npm test
 *   npm run test:headed   — с видимым браузером
 *   npm run test:ui       — интерактивный UI режим
 *
 * Переменные окружения:
 *   BASE_URL=http://localhost:8000   — URL приложения (default)
 *   API_KEY=secret                   — X-Api-Key для API запросов
 */
export default defineConfig({
  testDir: './tests',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : undefined,

  reporter: [
    ['html', { open: 'never' }],
    ['list'],
  ],

  use: {
    baseURL:       process.env.BASE_URL ?? 'http://localhost:8000',
    trace:         'on-first-retry',
    screenshot:    'only-on-failure',
    video:         'retain-on-failure',
    extraHTTPHeaders: {
      'X-Api-Key': process.env.API_KEY ?? '',
    },
  },

  projects: [
    {
      name: 'chromium',
      use:  { ...devices['Desktop Chrome'] },
    },
    // Раскомментируй для кросс-браузерного тестирования:
    // { name: 'firefox', use: { ...devices['Desktop Firefox'] } },
    // { name: 'webkit',  use: { ...devices['Desktop Safari']  } },
  ],
});
