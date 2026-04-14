<template>
  <div class="max-w-2xl">
    <div class="flex items-center gap-3 mb-6">
      <router-link to="/" class="text-gray-400 hover:text-gray-600 text-sm">← Назад</router-link>
      <h1 class="text-2xl font-semibold text-gray-900">Детали платежа</h1>
    </div>

    <LoadingSpinner v-if="loading" />
    <AlertMessage :message="error" type="error" />

    <div v-if="payment" class="space-y-5">
      <!-- Основная информация -->
      <div class="bg-white rounded-xl border border-gray-200 p-6">
        <div class="flex items-start justify-between mb-6">
          <div>
            <p class="text-xs font-mono text-gray-400 mb-1">{{ payment.id }}</p>
            <StatusBadge :status="payment.status" />
          </div>
          <div class="text-right">
            <p class="text-2xl font-semibold text-gray-900">{{ formatAmount(payment.amount, payment.currency) }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ providerLabel(payment.provider) }}</p>
          </div>
        </div>

        <dl class="grid grid-cols-2 gap-4 text-sm">
          <div>
            <dt class="text-gray-400 text-xs mb-1">Описание</dt>
            <dd class="text-gray-900">{{ payment.description }}</dd>
          </div>
          <div>
            <dt class="text-gray-400 text-xs mb-1">Внешний ID</dt>
            <dd class="font-mono text-gray-700 text-xs">{{ payment.external_id ?? '—' }}</dd>
          </div>
          <div v-if="payment.refunded_amount > 0">
            <dt class="text-gray-400 text-xs mb-1">Возвращено</dt>
            <dd class="text-blue-700">{{ formatAmount(payment.refunded_amount, payment.currency) }}</dd>
          </div>
          <div>
            <dt class="text-gray-400 text-xs mb-1">Создан</dt>
            <dd class="text-gray-700">{{ formatDate(payment.created_at) }}</dd>
          </div>
          <div>
            <dt class="text-gray-400 text-xs mb-1">Обновлён</dt>
            <dd class="text-gray-700">{{ formatDate(payment.updated_at) }}</dd>
          </div>
        </dl>

        <!-- Ссылка на оплату -->
        <div v-if="payment.confirmation_url" class="mt-4 p-3 bg-blue-50 rounded-lg flex items-center justify-between">
          <p class="text-xs text-blue-700">Ссылка для оплаты активна</p>
          <a :href="payment.confirmation_url" target="_blank" class="text-xs text-blue-600 font-medium hover:underline">
            Открыть →
          </a>
        </div>
      </div>

      <!-- Действия -->
      <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h2 class="text-sm font-semibold text-gray-700 mb-4">Действия</h2>

        <AlertMessage :message="actionError" type="error" />
        <AlertMessage :message="actionSuccess" type="success" />

        <div class="flex flex-wrap gap-3">
          <!-- Синхронизация -->
          <button
            @click="syncPayment"
            :disabled="acting"
            class="text-sm border border-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-50 disabled:opacity-50 transition-colors"
          >
            Синхронизировать
          </button>

          <!-- Отмена -->
          <button
            v-if="payment.status === 'Pending'"
            @click="cancelPayment"
            :disabled="acting"
            class="text-sm border border-red-300 text-red-600 px-4 py-2 rounded-lg hover:bg-red-50 disabled:opacity-50 transition-colors"
          >
            Отменить
          </button>

          <!-- Возврат -->
          <button
            v-if="payment.status === 'Succeeded'"
            @click="showRefundForm = !showRefundForm"
            class="text-sm border border-blue-300 text-blue-600 px-4 py-2 rounded-lg hover:bg-blue-50 transition-colors"
          >
            Возврат
          </button>
        </div>

        <!-- Форма возврата -->
        <div v-if="showRefundForm" class="mt-4 border-t border-gray-100 pt-4">
          <h3 class="text-sm font-medium text-gray-700 mb-3">Сумма возврата</h3>
          <div class="flex gap-3 items-end">
            <div class="flex-1">
              <label class="block text-xs text-gray-500 mb-1">
                Сумма (руб.) — максимум {{ formatAmount(maxRefundable, payment.currency) }}
              </label>
              <input
                v-model.number="refundAmount"
                type="number"
                :max="maxRefundable / 100"
                min="0.01"
                step="0.01"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
            <button
              @click="doRefund"
              :disabled="acting || !refundAmount"
              class="bg-blue-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-blue-700 disabled:opacity-50 transition-colors"
            >
              {{ acting ? 'Отправка…' : 'Выполнить возврат' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Метаданные -->
      <div v-if="payment.metadata && Object.keys(payment.metadata).length > 0" class="bg-white rounded-xl border border-gray-200 p-6">
        <h2 class="text-sm font-semibold text-gray-700 mb-3">Метаданные</h2>
        <pre class="text-xs text-gray-600 bg-gray-50 rounded-lg p-3 overflow-x-auto">{{ JSON.stringify(payment.metadata, null, 2) }}</pre>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { paymentsApi } from '@/api/payments.js';
import StatusBadge from '@/components/StatusBadge.vue';
import LoadingSpinner from '@/components/LoadingSpinner.vue';
import AlertMessage from '@/components/AlertMessage.vue';

const props = defineProps({ id: { type: String, required: true } });

const payment = ref(null);
const loading = ref(false);
const error = ref('');
const acting = ref(false);
const actionError = ref('');
const actionSuccess = ref('');
const showRefundForm = ref(false);
const refundAmount = ref('');

const maxRefundable = computed(() =>
  (payment.value?.amount ?? 0) - (payment.value?.refunded_amount ?? 0)
);

const providerLabels = {
  yookassa: 'YooKassa', robokassa: 'Robokassa',
  cloudpayments: 'CloudPayments', sbp: 'СБП', alfabank: 'Альфа-Банк',
};
function providerLabel(p) { return providerLabels[p] ?? p; }

function formatAmount(kopecks, currency) {
  return new Intl.NumberFormat('ru-RU', { style: 'currency', currency: currency ?? 'RUB' }).format(kopecks / 100);
}

function formatDate(iso) {
  return new Date(iso).toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

async function fetchPayment() {
  loading.value = true;
  error.value = '';
  try {
    const { data } = await paymentsApi.get(props.id);
    payment.value = data.data ?? data;
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Ошибка загрузки платежа';
  } finally {
    loading.value = false;
  }
}

async function runAction(fn) {
  acting.value = true;
  actionError.value = '';
  actionSuccess.value = '';
  try {
    await fn();
    await fetchPayment();
  } catch (e) {
    actionError.value = e.response?.data?.message ?? 'Ошибка операции';
  } finally {
    acting.value = false;
  }
}

function cancelPayment() {
  runAction(async () => {
    await paymentsApi.cancel(props.id);
    actionSuccess.value = 'Платёж отменён';
  });
}

function syncPayment() {
  runAction(async () => {
    await paymentsApi.sync(props.id);
    actionSuccess.value = 'Статус синхронизирован';
  });
}

function doRefund() {
  if (!refundAmount.value) return;
  runAction(async () => {
    const kopecks = Math.round(parseFloat(refundAmount.value) * 100);
    await paymentsApi.refund(props.id, { amount: kopecks }, crypto.randomUUID());
    actionSuccess.value = `Возврат на ${formatAmount(kopecks, payment.value.currency)} выполнен`;
    showRefundForm.value = false;
    refundAmount.value = '';
  });
}

onMounted(fetchPayment);
</script>
