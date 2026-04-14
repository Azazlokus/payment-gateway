<template>
  <div class="max-w-xl">
    <div class="flex items-center gap-3 mb-6">
      <router-link to="/" class="text-gray-400 hover:text-gray-600 text-sm">← Назад</router-link>
      <h1 class="text-2xl font-semibold text-gray-900">Новый платёж</h1>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-6">
      <AlertMessage :message="error" type="error" />
      <AlertMessage :message="success" type="success" />

      <form @submit.prevent="submit" class="space-y-5">
        <!-- Провайдер -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Провайдер</label>
          <select v-model="form.provider" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">Выберите провайдер</option>
            <option value="yookassa">YooKassa</option>
            <option value="robokassa">Robokassa</option>
            <option value="cloudpayments">CloudPayments</option>
            <option value="sbp">СБП (QR-код)</option>
            <option value="alfabank">Альфа-Банк</option>
          </select>
        </div>

        <!-- Сумма -->
        <div class="flex gap-3">
          <div class="flex-1">
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
          </div>
          <div class="w-28">
            <label class="block text-sm font-medium text-gray-700 mb-1">Валюта</label>
            <select v-model="form.currency" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="RUB">RUB</option>
              <option value="USD">USD</option>
              <option value="EUR">EUR</option>
            </select>
          </div>
        </div>

        <!-- Описание -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Описание</label>
          <input
            v-model="form.description"
            type="text"
            required
            maxlength="255"
            placeholder="Оплата заказа №1234"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <!-- Return URL -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Return URL</label>
          <input
            v-model="form.return_url"
            type="url"
            required
            placeholder="https://example.com/payment/success"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <!-- Рекуррентные платежи (только YooKassa) -->
        <div v-if="form.provider === 'yookassa'" class="border border-blue-100 rounded-xl p-4 bg-blue-50 space-y-3">
          <p class="text-xs font-semibold text-blue-700 uppercase tracking-wide">Рекуррентные платежи (YooKassa)</p>

          <div class="flex items-center gap-3">
            <input
              id="save_method"
              v-model="form.save_payment_method"
              type="checkbox"
              class="rounded border-gray-300 text-blue-600"
            />
            <label for="save_method" class="text-sm text-gray-700">
              Сохранить метод оплаты для будущих списаний
            </label>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Payment Method ID
              <span class="text-gray-400 font-normal">(для списания без редиректа)</span>
            </label>
            <input
              v-model="form.payment_method_id"
              type="text"
              placeholder="pm_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
            />
            <p class="text-xs text-gray-400 mt-1">
              Если указан — платёж списывается без редиректа (рекуррентное списание)
            </p>
          </div>
        </div>

        <!-- Notification URL (опционально) -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Webhook URL
            <span class="text-gray-400 font-normal">(опционально)</span>
          </label>
          <input
            v-model="notificationUrl"
            type="url"
            placeholder="https://example.com/webhook"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
          <p class="text-xs text-gray-400 mt-1">Будет вызван при изменении статуса платежа</p>
        </div>

        <!-- Idempotency key -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Idempotency-Key
            <span class="text-gray-400 font-normal">(опционально)</span>
          </label>
          <div class="flex gap-2">
            <input
              v-model="idempotencyKey"
              type="text"
              placeholder="uuid-v4"
              class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
            <button type="button" @click="generateKey" class="text-xs border border-gray-300 rounded-lg px-3 py-2 text-gray-600 hover:bg-gray-50">
              Сгенерировать
            </button>
          </div>
        </div>

        <div class="pt-2">
          <button
            type="submit"
            :disabled="submitting"
            class="w-full bg-blue-600 text-white font-medium py-2.5 rounded-lg hover:bg-blue-700 disabled:opacity-50 transition-colors text-sm"
          >
            {{ submitting ? 'Создание…' : 'Создать платёж' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import { paymentsApi } from '@/api/payments.js';
import AlertMessage from '@/components/AlertMessage.vue';

const router = useRouter();

const form = ref({
  provider: '',
  currency: 'RUB',
  description: '',
  return_url: '',
  save_payment_method: false,
  payment_method_id: '',
});
const amountRubles = ref('');
const notificationUrl = ref('');
const idempotencyKey = ref('');
const submitting = ref(false);
const error = ref('');
const success = ref('');

function generateKey() {
  idempotencyKey.value = crypto.randomUUID();
}

async function submit() {
  error.value = '';
  success.value = '';
  submitting.value = true;

  const payload = {
    provider:     form.value.provider,
    currency:     form.value.currency,
    description:  form.value.description,
    return_url:   form.value.return_url,
    amount:       Math.round(parseFloat(amountRubles.value) * 100),
    metadata:     notificationUrl.value ? { notification_url: notificationUrl.value } : {},
    ...(form.value.save_payment_method && { save_payment_method: true }),
    ...(form.value.payment_method_id   && { payment_method_id: form.value.payment_method_id }),
  };

  try {
    const { data } = await paymentsApi.create(payload, idempotencyKey.value || null);
    const payment = data.data ?? data;

    if (payment.confirmation_url) {
      success.value = `Платёж создан. Перенаправление на страницу оплаты…`;
      setTimeout(() => window.open(payment.confirmation_url, '_blank'), 1000);
    } else {
      success.value = 'Платёж создан';
    }

    setTimeout(() => router.push(`/payments/${payment.id}`), 1500);
  } catch (e) {
    const errors = e.response?.data?.errors;
    if (errors) {
      error.value = Object.values(errors).flat().join(' ');
    } else {
      error.value = e.response?.data?.message ?? 'Ошибка создания платежа';
    }
  } finally {
    submitting.value = false;
  }
}
</script>
