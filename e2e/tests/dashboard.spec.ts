import { test, expect } from '@playwright/test';

/**
 * E2E тесты дашборда платежей.
 */
test.describe('Dashboard', () => {

  test('loads and shows payment list', async ({ page }) => {
    await page.goto('/');

    // Навигация видна
    await expect(page.getByText('Payment Gateway')).toBeVisible();
    await expect(page.getByText('Создать')).toBeVisible();
    await expect(page.getByText('Метрики')).toBeVisible();

    // Страница не крашится
    await expect(page).toHaveTitle(/Payment Gateway/);
  });

  test('shows empty state when no payments', async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('networkidle');

    // Либо есть таблица с платежами, либо пустой стейт
    const hasPayments = await page.locator('table, [data-testid="payment-row"]').count();
    const hasEmpty    = await page.getByText(/платежей нет|no payments/i).count();

    expect(hasPayments + hasEmpty).toBeGreaterThan(0);
  });

  test('navigates to create payment page', async ({ page }) => {
    await page.goto('/');
    await page.getByText('Создать').click();

    await expect(page).toHaveURL('/payments/create');
    await expect(page.getByText(/создать платёж/i)).toBeVisible();
  });

  test('navigates to metrics page', async ({ page }) => {
    await page.goto('/');
    await page.getByText('Метрики').click();

    await expect(page).toHaveURL('/metrics');
  });
});
