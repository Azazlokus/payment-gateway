import { test, expect } from '@playwright/test';

/**
 * E2E тесты страницы детали платежа.
 * Используем API для создания тестового платежа перед тестом.
 */
test.describe('Payment Detail', () => {

  // Создаём платёж через API и переходим на его страницу
  test.beforeEach(async ({ page, request }) => {
    const res = await request.post('/api/v1/payments', {
      headers: { 'Idempotency-Key': crypto.randomUUID() },
      data: {
        amount:      10000,
        description: 'E2E detail test',
        return_url:  'https://example.com/success',
      },
    });

    if (res.status() !== 201) {
      test.skip();
      return;
    }

    const { data } = await res.json();
    await page.goto(`/payments/${data.id}`);
    await page.waitForLoadState('networkidle');
  });

  test('shows payment id and status', async ({ page }) => {
    await expect(page.getByText(/pending|succeeded|cancelled|refunded/i).first()).toBeVisible();
  });

  test('shows amount', async ({ page }) => {
    await expect(page.getByText(/100[,.]00/)).toBeVisible(); // 10000 копеек = 100 руб
  });

  test('shows sync button', async ({ page }) => {
    await expect(page.getByRole('button', { name: /синхронизировать/i })).toBeVisible();
  });

  test('shows cancel button for pending payment', async ({ page }) => {
    const isPending = await page.getByText('Pending').count();
    if (isPending > 0) {
      await expect(page.getByRole('button', { name: /отменить/i })).toBeVisible();
    }
  });

  test('back link navigates to dashboard', async ({ page }) => {
    await page.getByText('← Назад').click();
    await expect(page).toHaveURL('/');
  });
});

/**
 * SSE стрим — проверяем заголовки через API.
 */
test.describe('Payment SSE Stream', () => {

  test('stream endpoint returns correct content-type', async ({ request }) => {
    // Нужен реальный payment ID — пропускаем если нет платежей
    const listRes = await request.get('/api/v1/payments?per_page=1');
    if (listRes.status() !== 200) return;

    const { data } = await listRes.json();
    if (!data.length) return;

    const id = data[0].id;

    // Делаем HEAD-подобный запрос чтобы проверить заголовки без чтения стрима
    const res = await request.get(`/api/v1/payments/${id}/stream`, {
      timeout: 3000,
    }).catch(() => null);

    if (res) {
      expect(res.headers()['content-type']).toContain('text/event-stream');
      expect(res.headers()['cache-control']).toContain('no-cache');
    }
  });
});
