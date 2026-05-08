/** Статус платежа */
export type PaymentStatus = 'Pending' | 'Succeeded' | 'Cancelled' | 'Refunded';

/** Провайдер платежа */
export type PaymentProvider = 'yookassa' | 'robokassa' | 'cloudpayments' | 'sbp' | 'alfabank';

/** Платёж */
export interface Payment {
  id: string;
  status: PaymentStatus;
  provider: PaymentProvider;
  /** Сумма в копейках */
  amount: number;
  currency: string;
  description: string;
  external_id: string | null;
  payment_url: string | null;
  payment_method_id: string | null;
  metadata: Record<string, unknown> | null;
  created_at: string;
  updated_at: string;
}

/** Параметры создания платежа */
export interface CreatePaymentParams {
  /** Сумма в копейках, минимум 100 (= 1 руб) */
  amount: number;
  description: string;
  return_url: string;
  /** UUID для идемпотентности; если не передан — генерируется автоматически */
  idempotency_key?: string;
  provider?: PaymentProvider;
  payment_method_type?: 'bank_card' | 'yoo_money' | 'sbp' | 'sberbank' | 'tinkoff_bank' | 'cash';
  save_payment_method?: boolean;
  payment_method_id?: string;
  metadata?: Record<string, unknown>;
}

/** Параметры возврата */
export interface RefundParams {
  /** Сумма возврата в копейках; если не передана — полный возврат */
  amount?: number;
  reason?: string;
  idempotency_key?: string;
}

/** Paginated ответ */
export interface PaginatedResponse<T> {
  data: T[];
  per_page: number;
  next_cursor: string | null;
  prev_cursor: string | null;
}

/** Фильтры для списка платежей */
export interface PaymentFilters {
  status?: PaymentStatus;
  provider?: PaymentProvider;
  from_date?: string;
  to_date?: string;
  per_page?: number;
  cursor?: string;
}

/** Аналитика выручки (один день) */
export interface RevenueDay {
  date: string;
  revenue_kopecks: number;
  revenue_rub: number;
  count: number;
}

/** Строка воронки конверсии */
export interface FunnelItem {
  status: PaymentStatus;
  count: number;
  total_rub: number;
  conversion_pct: number;
}

/** Запись журнала аудита */
export interface AuditLog {
  id: number;
  action: string;
  subject_type: string | null;
  subject_id: string | null;
  ip: string | null;
  api_key_hint: string | null;
  metadata: Record<string, unknown> | null;
  created_at: string;
}
