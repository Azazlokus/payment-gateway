<template>
  <div class="max-w-xl">
    <div class="flex items-center gap-3 mb-6">
      <router-link to="/" class="text-gray-400 hover:text-gray-600 text-sm">← Назад</router-link>
      <h1 class="text-2xl font-semibold text-gray-900">Крипто-депозит</h1>
    </div>

    <!-- Форма создания -->
    <div v-if="!deposit" class="bg-white rounded-xl border border-gray-200 p-6">
      <AlertMessage :message="error" type="error" />

      <form @submit.prevent="submit" class="space-y-5">
        <!-- Актив -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Актив</label>
          <select
            v-model="form.asset"
            required
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option value="TON">TON</option>
            <option value="USDT_TON">USDT-TON</option>
          </select>
        </div>

        <!-- Сумма -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Сумма (руб.)</label>
          <input
            v-model.number="amountRubles"
            type="number"
            min="1"
            step="0.01"
            required
            placeholder="100.00"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
          <p class="text-xs text-gray-400 mt-1">Минимум 1 рубль (100 копеек)</p>
        </div>

        <!-- Payment ID -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Payment ID</label>
          <input
            v-model="form.payment_id"
            type="text"
            required
            maxlength="255"
            placeholder="pay-order-1234"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
          <p class="text-xs text-gray-400 mt-1">Внешний идентификатор платежа в вашей системе</p>
        </div>

        <div class="pt-2">
          <button
            type="submit"
            :disabled="submitting"
            class="w-full bg-blue-600 text-white font-medium py-2.5 rounded-lg hover:bg-blue-700 disabled:opacity-50 transition-colors text-sm"
          >
            {{ submitting ? 'Создание…' : 'Создать депозит' }}
          </button>
        </div>
      </form>
    </div>

    <!-- Детали депозита после создания -->
    <div v-else class="space-y-4">
      <!-- Статус -->
      <div class="bg-white rounded-xl border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-semibold text-gray-900">Депозит создан</h2>
          <StatusBadge :status="deposit.status" />
        </div>

        <dl class="space-y-3 text-sm">
          <div class="flex justify-between">
            <dt class="text-gray-500">Актив</dt>
            <dd class="font-medium text-gray-900">{{ deposit.asset }}</dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-gray-500">Сумма</dt>
            <dd class="font-medium text-gray-900">{{ deposit.cryptoAmount }} {{ assetLabel }}</dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-gray-500">Сумма (руб.)</dt>
            <dd class="text-gray-700">{{ (deposit.fiatAmountKopecks / 100).toFixed(2) }} ₽</dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-gray-500">Истекает</dt>
            <dd class="text-gray-700">{{ formatDate(deposit.expiresAt) }}</dd>
          </div>
          <div v-if="deposit.txHash" class="flex justify-between">
            <dt class="text-gray-500">TX Hash</dt>
            <dd class="font-mono text-xs text-gray-700 truncate max-w-xs">{{ deposit.txHash }}</dd>
          </div>
        </dl>
      </div>

      <!-- Адрес и мемо -->
      <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
        <h2 class="text-base font-semibold text-gray-900">Реквизиты для оплаты</h2>

        <div>
          <p class="text-xs text-gray-500 mb-1 uppercase tracking-wide">Адрес депозита</p>
          <div class="flex items-center gap-2">
            <code class="flex-1 text-xs bg-gray-50 border border-gray-200 rounded px-3 py-2 font-mono break-all">
              {{ deposit.depositAddress }}
            </code>
            <button
              @click="copy(deposit.depositAddress)"
              class="text-xs text-blue-600 hover:underline shrink-0"
            >Скопировать</button>
          </div>
        </div>

        <div>
          <p class="text-xs text-gray-500 mb-1 uppercase tracking-wide">Комментарий / Memo</p>
          <div class="flex items-center gap-2">
            <code class="flex-1 text-lg font-mono bg-gray-50 border border-gray-200 rounded px-3 py-2 text-center tracking-widest">
              {{ deposit.memo }}
            </code>
            <button
              @click="copy(deposit.memo)"
              class="text-xs text-blue-600 hover:underline shrink-0"
            >Скопировать</button>
          </div>
          <p class="text-xs text-red-500 mt-1 font-medium">
            Обязательно укажите этот комментарий при переводе — иначе платёж не будет засчитан.
          </p>
        </div>

        <!-- QR-код (ссылка через qrPayload) -->
        <div class="flex flex-col items-center gap-2 pt-2">
          <img :src="qrImageUrl" alt="QR-код для оплаты" class="w-40 h-40 border border-gray-200 rounded-lg" />
          <a
            :href="deposit.qrPayload"
            class="text-xs text-blue-600 hover:underline"
          >Открыть в кошельке TON</a>
        </div>
      </div>

      <!-- Статус-поллинг -->
      <div
        v-if="deposit.status === 'awaiting'"
        class="bg-amber-50 border border-amber-200 rounded-xl px-5 py-4 text-sm text-amber-800"
      >
        <LoadingSpinner class="inline-block mr-2" />
        Ожидаем поступление средств… Статус обновляется автоматически.
      </div>

      <div
        v-if="deposit.status === 'confirmed'"
        class="bg-green-50 border border-green-200 rounded-xl px-5 py-4 text-sm text-green-800 font-medium"
      >
        Платёж подтверждён
      </div>

      <div
        v-if="deposit.status === 'expired'"
        class="bg-red-50 border border-red-200 rounded-xl px-5 py-4 text-sm text-red-800"
      >
        Депозит истёк. Создайте новый.
      </div>

      <button
        @click="resetForm"
        class="w-full border border-gray-300 text-gray-700 text-sm font-medium py-2.5 rounded-lg hover:bg-gray-50 transition-colors"
      >
        Создать новый депозит
      </button>
    </div>

    <!-- Toast -->
    <div
      v-if="copied"
      class="fixed bottom-6 right-6 bg-gray-900 text-white text-sm px-4 py-2 rounded-lg shadow-lg transition-opacity"
    >
      Скопировано
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onUnmounted } from 'vue';
import { cryptoApi } from '@/api/payments.js';
import AlertMessage from '@/components/AlertMessage.vue';
import LoadingSpinner from '@/components/LoadingSpinner.vue';
import StatusBadge from '@/components/StatusBadge.vue';

const form = ref({ asset: 'TON', payment_id: '' });
const amountRubles = ref('');
const submitting = ref(false);
const error = ref('');
const deposit = ref(null);
const copied = ref(false);

let pollTimer = null;

const assetLabel = computed(() => ({
  TON: 'TON',
  USDT_TON: 'USDT',
})[deposit.value?.asset] ?? '');

// Google Charts QR — no install needed, pure URL
const qrImageUrl = computed(() => {
  if (!deposit.value?.qrPayload) return '';
  const encoded = encodeURIComponent(deposit.value.qrPayload);
  return `https://chart.googleapis.com/chart?chs=160x160&cht=qr&chl=${encoded}&choe=UTF-8`;
});

function formatDate(iso) {
  return new Date(iso).toLocaleString('ru-RU');
}

async function submit() {
  error.value = '';
  submitting.value = true;

  try {
    const { data } = await cryptoApi.createDeposit({
      payment_id: form.value.payment_id,
      fiat_amount_kopecks: Math.round(parseFloat(amountRubles.value) * 100),
      asset: form.value.asset,
    });
    deposit.value = data;
    startPolling(data.depositId);
  } catch (e) {
    const errors = e.response?.data?.errors;
    if (errors) {
      error.value = Object.values(errors).flat().join(' ');
    } else {
      error.value = e.response?.data?.message ?? 'Ошибка создания депозита';
    }
  } finally {
    submitting.value = false;
  }
}

function startPolling(depositId) {
  const TERMINAL = ['confirmed', 'expired', 'overpaid'];

  pollTimer = setInterval(async () => {
    try {
      const { data } = await cryptoApi.getDeposit(depositId);
      deposit.value = data;

      if (TERMINAL.includes(data.status)) {
        clearInterval(pollTimer);
        pollTimer = null;
      }
    } catch {
      // keep polling on transient errors
    }
  }, 15_000);
}

function resetForm() {
  clearInterval(pollTimer);
  pollTimer = null;
  deposit.value = null;
  error.value = '';
  amountRubles.value = '';
  form.value = { asset: 'TON', payment_id: '' };
}

async function copy(text) {
  await navigator.clipboard.writeText(text).catch(() => {});
  copied.value = true;
  setTimeout(() => { copied.value = false; }, 2000);
}

onUnmounted(() => {
  clearInterval(pollTimer);
});
</script>
