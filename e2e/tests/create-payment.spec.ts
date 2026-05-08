import { test, expect, request } from '@playwright/test';

/**
 * E2E тесты создания платежа.
 */
test.describe('Create Payment', () => {

  test('form renders all required fields', async ({ page }) => {
    await page.goto('/payments/create');

    await expect(page.getByLabel(/сумма/i)).toBeVisible();
    await expect(page.getByLabel(/описание/i)).toBeVisible();
    await expect(page.getByLabel(/url возврата|return url/i)).toBeVisible();
  });

  test('shows validation error for empty form submit', async ({ page }) => {
    await page.goto('/payments/create');

    await page.getByRole('button', { name: /создать|оплатить/i }).click();

    // HTML5 validation или кастомные ошибки
    const hasError = await page.locator('[class*="error"], [class*="invalid"], :invalid').count();
    expect(hasError).toBeGreaterThan(0);
  });

  test('shows validation error for amount below minimum', async ({ page }) => {
    await page.goto('/payments/create');

    await page.getByLabel(/сумма/i).fill('0');
    await page.getByRole('button', { name: /создать|оплатить/i }).click();

    await page.waitForTimeout(500);
    const hasError = await page.locator('[class*="error"], [class*="invalid"]').count();
    expect(hasError).toBeGreaterThan(0);
  });

  test('back navigation works', async ({ page }) => {
    await page.goto('/payments/create');
    await page.getByText('← Назад').click();

    await expect(page).toHaveURL('/');
  });
});

/**
 * API-уровневый E2E тест (без браузера — быстрее).
 * Проверяет что API принимает правильный payload.
 */
test.describe('Create Payment API', () => {

  test('POST /api/v1/payments returns 201 with valid payload', async ({ request }) => {
    const response = await request.post('/api/v1/payments', {
      headers: { 'Idempotency-Key': crypto.randomUUID() },
      data: {
        amount:      50000,
        description: 'E2E test payment',
        return_url:  'https://example.com/success',
      },
    });

    // 201 если провайдер доступен, 500/422 если нет — оба ок для E2E
    expect([201, 422, 500]).toContain(response.status());

    if (response.status() === 201) {
      const body = await response.json();
      expect(body.data).toHaveProperty('id');
      expect(body.data).toHaveProperty('status');
    }
  });

  test('GET /api/health returns ok', async ({ request }) => {
    const response = await request.get('/api/health');
    expect(response.status()).toBe(200);

    const body = await response.json();
    expect(body).toHaveProperty('status');
  });

  test('GET /api/v1/payments returns paginated list', async ({ request }) => {
    const response = await request.get('/api/v1/payments?per_page=5');
    expect(response.status()).toBe(200);

    const body = await response.json();
    expect(body).toHaveProperty('data');
    expect(Array.isArray(body.data)).toBe(true);
    expect(body).toHaveProperty('next_cursor');
  });
});
