import type {
  Payment,
  CreatePaymentParams,
  RefundParams,
  PaginatedResponse,
  PaymentFilters,
  RevenueDay,
  FunnelItem,
  AuditLog,
} from './types';

export class PaymentGatewayError extends Error {
  constructor(
    public readonly status: number,
    public readonly code: string,
    message: string,
  ) {
    super(message);
    this.name = 'PaymentGatewayError';
  }
}

export interface ClientOptions {
  /** Базовый URL API, например https://pay.example.com */
  baseUrl: string;
  /** X-Api-Key заголовок */
  apiKey: string;
  /** Таймаут в мс (default: 30_000) */
  timeout?: number;
}

/**
 * Typed HTTP клиент для Payment Gateway API.
 *
 * @example
 * ```ts
 * const client = new PaymentGatewayClient({
 *   baseUrl: 'https://pay.example.com',
 *   apiKey:  process.env.PAYMENT_API_KEY!,
 * });
 *
 * const payment = await client.createPayment({
 *   amount:      50000,        // 500 руб
 *   description: 'Заказ №42',
 *   return_url:  'https://example.com/success',
 * });
 *
 * console.log(payment.payment_url); // ссылка для перехода клиента
 * ```
 */
export class PaymentGatewayClient {
  private readonly baseUrl: string;
  private readonly headers: Record<string, string>;
  private readonly timeout: number;

  constructor(opts: ClientOptions) {
    this.baseUrl  = opts.baseUrl.replace(/\/$/, '');
    this.timeout  = opts.timeout ?? 30_000;
    this.headers  = {
      'Content-Type': 'application/json',
      'X-Api-Key':    opts.apiKey,
    };
  }

  // ─── Payments ─────────────────────────────────────────────────────────────

  /** Создать платёж */
  async createPayment(params: CreatePaymentParams): Promise<Payment> {
    const idempotencyKey = params.idempotency_key ?? this.uuid();
    const { idempotency_key: _, ...body } = params;

    const res = await this.request<{ data: Payment }>('POST', '/api/v1/payments', body, {
      'Idempotency-Key': idempotencyKey,
    });
    return res.data;
  }

  /** Получить платёж по ID */
  async getPayment(id: string): Promise<Payment> {
    const res = await this.request<{ data: Payment }>('GET', `/api/v1/payments/${id}`);
    return res.data;
  }

  /** Список платежей с фильтрацией и курсорной пагинацией */
  async listPayments(filters: PaymentFilters = {}): Promise<PaginatedResponse<Payment>> {
    return this.request<PaginatedResponse<Payment>>(
      'GET',
      '/api/v1/payments',
      undefined,
      {},
      filters as Record<string, string>,
    );
  }

  /** Отменить платёж */
  async cancelPayment(id: string, reason?: string): Promise<Payment> {
    const res = await this.request<{ data: Payment }>('POST', `/api/v1/payments/${id}/cancel`, { reason });
    return res.data;
  }

  /** Вернуть деньги */
  async refundPayment(id: string, params: RefundParams = {}): Promise<Payment> {
    const idempotencyKey = params.idempotency_key ?? this.uuid();
    const { idempotency_key: _, ...body } = params;

    const res = await this.request<{ data: Payment }>(
      'POST',
      `/api/v1/payments/${id}/refund`,
      body,
      { 'Idempotency-Key': idempotencyKey },
    );
    return res.data;
  }

  /** Синхронизировать статус с провайдером */
  async syncPayment(id: string): Promise<Payment> {
    const res = await this.request<{ data: Payment }>('POST', `/api/v1/payments/${id}/sync`);
    return res.data;
  }

  // ─── Analytics ────────────────────────────────────────────────────────────

  /** Выручка по дням */
  async getRevenue(days: number = 30, provider?: string): Promise<RevenueDay[]> {
    const params: Record<string, string> = { days: String(days) };
    if (provider) params.provider = provider;
    const res = await this.request<{ data: RevenueDay[] }>('GET', '/api/v1/analytics/revenue', undefined, {}, params);
    return res.data;
  }

  /** Воронка конверсии */
  async getFunnel(from?: string, to?: string): Promise<FunnelItem[]> {
    const params: Record<string, string> = {};
    if (from) params.from = from;
    if (to)   params.to   = to;
    const res = await this.request<{ data: FunnelItem[] }>('GET', '/api/v1/analytics/funnel', undefined, {}, params);
    return res.data;
  }

  // ─── Audit ────────────────────────────────────────────────────────────────

  /** Журнал аудита */
  async getAuditLogs(params: {
    action?: string;
    subject_id?: string;
    ip?: string;
    page?: number;
    per_page?: number;
  } = {}): Promise<{ data: AuditLog[]; total: number; current_page: number; last_page: number }> {
    const qs = Object.fromEntries(
      Object.entries(params).filter(([, v]) => v !== undefined).map(([k, v]) => [k, String(v)]),
    ) as Record<string, string>;
    return this.request('GET', '/api/v1/audit-logs', undefined, {}, qs);
  }

  // ─── Health ───────────────────────────────────────────────────────────────

  /** Проверить доступность API */
  async healthCheck(): Promise<{ status: string }> {
    return this.request('GET', '/api/health');
  }

  // ─── Private ──────────────────────────────────────────────────────────────

  private async request<T>(
    method: string,
    path: string,
    body?: unknown,
    extraHeaders: Record<string, string> = {},
    query: Record<string, string> = {},
  ): Promise<T> {
    const url = new URL(this.baseUrl + path);
    for (const [k, v] of Object.entries(query)) {
      if (v !== undefined) url.searchParams.set(k, v);
    }

    const ctrl   = new AbortController();
    const timer  = setTimeout(() => ctrl.abort(), this.timeout);

    try {
      const resp = await fetch(url.toString(), {
        method,
        headers: { ...this.headers, ...extraHeaders },
        body: body !== undefined ? JSON.stringify(body) : undefined,
        signal: ctrl.signal,
      });

      const json = await resp.json().catch(() => ({}));

      if (!resp.ok) {
        throw new PaymentGatewayError(
          resp.status,
          (json as { code?: string }).code ?? 'unknown_error',
          (json as { message?: string }).message ?? `HTTP ${resp.status}`,
        );
      }

      return json as T;
    } catch (err) {
      if ((err as Error).name === 'AbortError') {
        throw new PaymentGatewayError(408, 'timeout', `Request timed out after ${this.timeout}ms`);
      }
      throw err;
    } finally {
      clearTimeout(timer);
    }
  }

  private uuid (): string {
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
      const r = (Math.random() * 16) | 0;
      return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
    });
  }
}
