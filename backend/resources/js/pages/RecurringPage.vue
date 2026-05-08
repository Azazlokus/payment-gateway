<template>
  <div class="max-w-3xl">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-semibold text-gray-900">Рекуррентные платежи</h1>
    </div>

    <p class="text-sm text-gray-500 mb-6">
      Сохранённые методы оплаты. Вы можете списать средства без редиректа клиента.
    </p>

    <LoadingSpinner v-if="loading" />
    <AlertMessage :message="error" type="error" />

    <div v-if="!loading && methods.length === 0" class="text-center text-gray-400 py-16">
      Нет сохранённых методов оплаты.<br>
      <span class="text-xs">Создайте платёж с опцией «Сохранить метод».</span>
    </div>

    <div class="space-y-3">
      <div
        v-for="method in methods"
        :key="method.payment_method_id"
        class="bg-white rounded-xl border border-gray-200 p-5"
      >
        <div class="flex items-center justify-between">
          <div>
            <p class="text-xs font-mono text-gray-400 mb-1">{{ method.payment_method_id }}</p>
            <div class="flex items-center gap-3">
              <span class="text-sm font-medium text-gray-800">{{ providerLabel(method.provider) }}</span>
              <span class="text-xs text-gray-400">
                последнее использование {{ formatDate(method.last_used_at) }}
              </span>
            </div>
          </div>
          <button
            @click="openChargeForm(method)"
            class="text-sm bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors"
          >
            Списать
          </button>
        </div>
      </div>
    </div>

    <!-- Модалка списания -->
    <div v-if="chargeTarget" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Списание без редиректа</h2>
        <p class="text-xs font-mono text-gray-400 mb-4">{{ chargeTarget.payment_method_id }}</p>

        <AlertMessage :message="chargeError" type="error" />
        <AlertMessage :message="chargeSuccess" type="success" />

        <div class="space-y-3">
          <div>
            <label class="block text-xs text-gray-500 mb-1">Сумма (руб.)</label>
            <input v-model.number="chargeForm.amount" type="number" min="1" step="0.01"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">Описание</label>
            <input v-model="chargeForm.description" type="text" maxlength="255"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
        </div>

        <div class="flex gap-3 mt-5">
          <button @click="chargeTarget = null"
                  class="flex-1 border border-gray-300 text-gray-600 text-sm py-2 rounded-lg hover:bg-gray-50">
            Отмена
          </button>
          <button @click="doCharge" :disabled="charging || !chargeForm.amount || !chargeForm.description"
                  class="flex-1 bg-blue-600 text-white text-sm py-2 rounded-lg hover:bg-blue-700 disabled:opacity-50">
            {{ charging ? 'Отправка…' : 'Списать' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import LoadingSpinner from '@/components/LoadingSpinner.vue';
import AlertMessage from '@/components/AlertMessage.vue';

const methods      = ref([]);
const loading      = ref(false);
const error        = ref('');
const chargeTarget = ref(null);
const chargeForm   = ref({ amount: '', description: '' });
const chargeError  = ref('');
const chargeSuccess = ref('');
const charging     = ref(false);

const providerLabels = { yookassa: 'YooKassa', robokassa: 'Robokassa', cloudpayments: 'CloudPayments', sbp: 'СБП', alfabank: 'Альфа-Банк' };
function providerLabel(p) { return providerLabels[p] ?? p; }
function formatDate(iso) {
  return new Date(iso).toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

async function fetchMethods() {
  loading.value = true;
  error.value = '';
  try {
    const { data } = await axios.get('/api/v1/recurring/methods');
    methods.value = data.data;
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Ошибка загрузки методов';
  } finally {
    loading.value = false;
  }
}

function openChargeForm(method) {
  chargeTarget.value = method;
  chargeForm.value = { amount: method.last_amount / 100, description: 'Повторное списание' };
  chargeError.value = '';
  chargeSuccess.value = '';
}

async function doCharge() {
  charging.value = true;
  chargeError.value = '';
  chargeSuccess.value = '';
  try {
    const { data } = await axios.post('/api/v1/recurring/charge', {
      payment_method_id: chargeTarget.value.payment_method_id,
      amount:            Math.round(chargeForm.value.amount * 100),
      description:       chargeForm.value.description,
    }, {
      headers: { 'Idempotency-Key': crypto.randomUUID() },
    });
    chargeSuccess.value = `Платёж создан: ${data.data?.id}`;
    setTimeout(() => { chargeTarget.value = null; }, 2000);
  } catch (e) {
    chargeError.value = e.response?.data?.message ?? 'Ошибка списания';
  } finally {
    charging.value = false;
  }
}

onMounted(fetchMethods);
</script>
