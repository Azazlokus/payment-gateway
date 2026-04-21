<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-semibold text-gray-900">Платежи</h1>
      <router-link
        to="/payments/create"
        class="bg-blue-600 text-white text-sm font-medium px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors"
      >
        + Создать платёж
      </router-link>
    </div>

    <!-- Фильтры -->
    <div class="bg-white rounded-xl border border-gray-200 p-4 mb-6 flex flex-wrap gap-3">
      <select v-model="filters.status" @change="fetchPayments" class="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
        <option value="">Все статусы</option>
        <option value="Pending">Ожидает</option>
        <option value="Succeeded">Оплачен</option>
        <option value="Cancelled">Отменён</option>
        <option value="Refunded">Возврат</option>
      </select>
      <select v-model="filters.provider" @change="fetchPayments" class="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
        <option value="">Все провайдеры</option>
        <option value="yookassa">YooKassa</option>
        <option value="robokassa">Robokassa</option>
        <option value="cloudpayments">CloudPayments</option>
        <option value="sbp">СБП</option>
        <option value="alfabank">Альфа-Банк</option>
      </select>
      <button @click="resetFilters" class="text-sm text-gray-500 hover:text-gray-700 px-3 py-2">
        Сбросить
      </button>
    </div>

    <LoadingSpinner v-if="loading" />
    <AlertMessage :message="error" type="error" />

    <!-- Таблица -->
    <div v-if="!loading && payments.length > 0" class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-200">
          <tr>
            <th class="text-left text-gray-500 font-medium px-5 py-3">ID</th>
            <th class="text-left text-gray-500 font-medium px-5 py-3">Провайдер</th>
            <th class="text-left text-gray-500 font-medium px-5 py-3">Сумма</th>
            <th class="text-left text-gray-500 font-medium px-5 py-3">Статус</th>
            <th class="text-left text-gray-500 font-medium px-5 py-3">Дата</th>
            <th class="px-5 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr
            v-for="payment in payments"
            :key="payment.id"
            class="hover:bg-gray-50 transition-colors"
          >
            <td class="px-5 py-4 font-mono text-xs text-gray-500">{{ payment.id.slice(0, 12) }}…</td>
            <td class="px-5 py-4 capitalize text-gray-700">{{ providerLabel(payment.provider) }}</td>
            <td class="px-5 py-4 text-gray-900 font-medium">{{ formatAmount(payment.amount, payment.currency) }}</td>
            <td class="px-5 py-4">
              <StatusBadge :status="payment.status" />
            </td>
            <td class="px-5 py-4 text-gray-500">{{ formatDate(payment.created_at) }}</td>
            <td class="px-5 py-4 text-right">
              <router-link
                :to="`/payments/${payment.id}`"
                class="text-blue-600 hover:text-blue-800 text-xs font-medium"
              >
                Детали →
              </router-link>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Пагинация -->
      <div v-if="meta && meta.last_page > 1" class="px-5 py-4 border-t border-gray-100 flex items-center justify-between">
        <p class="text-xs text-gray-500">
          Показано {{ meta.from }}–{{ meta.to }} из {{ meta.total }}
        </p>
        <div class="flex gap-2">
          <button
            :disabled="meta.current_page <= 1"
            @click="changePage(meta.current_page - 1)"
            class="px-3 py-1 text-xs border border-gray-300 rounded-lg disabled:opacity-40 hover:bg-gray-50"
          >
            ←
          </button>
          <button
            :disabled="meta.current_page >= meta.last_page"
            @click="changePage(meta.current_page + 1)"
            class="px-3 py-1 text-xs border border-gray-300 rounded-lg disabled:opacity-40 hover:bg-gray-50"
          >
            →
          </button>
        </div>
      </div>
    </div>

    <!-- Пустое состояние -->
    <div v-if="!loading && payments.length === 0 && !error" class="text-center py-20 text-gray-400">
      <p class="text-lg mb-2">Платежей нет</p>
      <router-link to="/payments/create" class="text-blue-600 text-sm hover:underline">
        Создать первый платёж
      </router-link>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { paymentsApi } from '@/api/payments.js';
import StatusBadge from '@/components/StatusBadge.vue';
import LoadingSpinner from '@/components/LoadingSpinner.vue';
import AlertMessage from '@/components/AlertMessage.vue';

const payments = ref([]);
const meta = ref(null);
const loading = ref(false);
const error = ref('');
const filters = ref({ status: '', provider: '', page: 1 });

const providerLabels = {
  yookassa: 'YooKassa',
  robokassa: 'Robokassa',
  cloudpayments: 'CloudPayments',
  sbp: 'СБП',
  alfabank: 'Альфа-Банк',
};

function providerLabel(p) {
  return providerLabels[p] ?? p;
}

function formatAmount(kopecks, currency) {
  const rubles = kopecks / 100;
  return new Intl.NumberFormat('ru-RU', { style: 'currency', currency: currency ?? 'RUB' }).format(rubles);
}

function formatDate(iso) {
  return new Date(iso).toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

async function fetchPayments() {
  loading.value = true;
  error.value = '';
  try {
    const params = {};
    if (filters.value.status) params.status = filters.value.status;
    if (filters.value.provider) params.provider = filters.value.provider;
    params.page = filters.value.page;

    const { data } = await paymentsApi.list(params);
    payments.value = data.data;
    meta.value = data.meta;
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Ошибка загрузки платежей';
  } finally {
    loading.value = false;
  }
}

function changePage(page) {
  filters.value.page = page;
  fetchPayments();
}

function resetFilters() {
  filters.value = { status: '', provider: '', page: 1 };
  fetchPayments();
}

onMounted(fetchPayments);
</script>
