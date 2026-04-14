<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-semibold text-gray-900">Метрики</h1>
      <button
        @click="fetchMetrics"
        :disabled="loading"
        class="text-sm border border-gray-300 text-gray-600 px-4 py-2 rounded-lg hover:bg-gray-50 disabled:opacity-50 transition-colors"
      >
        {{ loading ? 'Загрузка…' : 'Обновить' }}
      </button>
    </div>

    <AlertMessage :message="error" type="error" />
    <LoadingSpinner v-if="loading && !metrics.length" />

    <!-- Карточки сводки -->
    <div v-if="summary.length > 0" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
      <div
        v-for="card in summary"
        :key="card.name"
        class="bg-white rounded-xl border border-gray-200 p-5"
      >
        <p class="text-xs text-gray-400 mb-1">{{ card.label }}</p>
        <p class="text-2xl font-semibold text-gray-900">{{ card.value }}</p>
        <p v-if="card.sub" class="text-xs text-gray-500 mt-1">{{ card.sub }}</p>
      </div>
    </div>

    <!-- Таблица всех метрик -->
    <div v-if="metrics.length > 0" class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-100">
        <h2 class="text-sm font-semibold text-gray-700">Все счётчики</h2>
      </div>

      <!-- Группировка по имени метрики -->
      <div v-for="group in groupedMetrics" :key="group.name" class="border-b border-gray-50 last:border-0">
        <div class="px-5 py-3 bg-gray-50">
          <p class="text-xs font-mono text-gray-500">{{ group.name }}</p>
        </div>
        <table class="w-full text-sm">
          <tbody class="divide-y divide-gray-50">
            <tr v-for="(entry, i) in group.entries" :key="i" class="hover:bg-gray-50">
              <td class="px-5 py-2.5">
                <div class="flex flex-wrap gap-1.5">
                  <span
                    v-for="(val, key) in entry.labels"
                    :key="key"
                    class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-blue-50 text-blue-700 font-mono"
                  >
                    {{ key }}="{{ val }}"
                  </span>
                  <span v-if="!Object.keys(entry.labels).length" class="text-gray-400 text-xs italic">без меток</span>
                </div>
              </td>
              <td class="px-5 py-2.5 text-right font-semibold text-gray-900 font-mono">{{ entry.value }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Сырой Prometheus текст -->
    <div v-if="raw" class="mt-6">
      <details class="bg-white rounded-xl border border-gray-200">
        <summary class="px-5 py-4 text-sm font-medium text-gray-700 cursor-pointer hover:bg-gray-50 rounded-xl">
          Raw Prometheus format
        </summary>
        <pre class="px-5 pb-5 text-xs text-gray-600 overflow-x-auto whitespace-pre-wrap">{{ raw }}</pre>
      </details>
    </div>

    <p v-if="updatedAt" class="text-xs text-gray-400 mt-4 text-right">
      Обновлено: {{ updatedAt }}
    </p>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import LoadingSpinner from '@/components/LoadingSpinner.vue';
import AlertMessage from '@/components/AlertMessage.vue';

const raw = ref('');
const metrics = ref([]);
const loading = ref(false);
const error = ref('');
const updatedAt = ref('');
let refreshTimer = null;

// Парсим Prometheus text format в структуру
function parsePrometheus(text) {
  const result = [];
  const lines = text.split('\n').filter(l => l && !l.startsWith('#'));

  for (const line of lines) {
    const braceStart = line.indexOf('{');
    const braceEnd = line.indexOf('}');
    const spaceIdx = line.lastIndexOf(' ');

    let name, labels, value;

    if (braceStart !== -1 && braceEnd !== -1) {
      name = line.substring(0, braceStart);
      const labelStr = line.substring(braceStart + 1, braceEnd);
      labels = {};
      for (const pair of labelStr.split(',')) {
        const [k, v] = pair.split('=');
        if (k && v) labels[k.trim()] = v.replace(/"/g, '').trim();
      }
      value = line.substring(braceEnd + 2).trim();
    } else {
      name = line.substring(0, spaceIdx);
      labels = {};
      value = line.substring(spaceIdx + 1).trim();
    }

    result.push({ name, labels, value: parseInt(value) || value });
  }

  return result;
}

const groupedMetrics = computed(() => {
  const map = {};
  for (const m of metrics.value) {
    if (!map[m.name]) map[m.name] = { name: m.name, entries: [] };
    map[m.name].entries.push({ labels: m.labels, value: m.value });
  }
  return Object.values(map);
});

const summary = computed(() => {
  const totals = {};
  for (const m of metrics.value) {
    const val = parseInt(m.value) || 0;
    totals[m.name] = (totals[m.name] || 0) + val;
  }

  const cards = [];

  if ('payments_created_total' in totals)
    cards.push({ name: 'created', label: 'Создано платежей', value: totals['payments_created_total'] });
  if ('payments_succeeded_total' in totals)
    cards.push({ name: 'succeeded', label: 'Успешных', value: totals['payments_succeeded_total'] });
  if ('payments_cancelled_total' in totals)
    cards.push({ name: 'cancelled', label: 'Отменённых', value: totals['payments_cancelled_total'] });
  if ('payments_refunded_total' in totals)
    cards.push({ name: 'refunded', label: 'Возвратов', value: totals['payments_refunded_total'] });
  if ('webhooks_processed_total' in totals)
    cards.push({ name: 'webhooks', label: 'Вебхуков', value: totals['webhooks_processed_total'] });
  if ('webhooks_failed_total' in totals)
    cards.push({ name: 'wh_failed', label: 'Вебхуков упало', value: totals['webhooks_failed_total'] });

  return cards;
});

async function fetchMetrics() {
  loading.value = true;
  error.value = '';
  try {
    const { data } = await axios.get('/api/metrics');
    raw.value = data;
    metrics.value = parsePrometheus(data);
    updatedAt.value = new Date().toLocaleTimeString('ru-RU');
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Ошибка загрузки метрик';
  } finally {
    loading.value = false;
  }
}

onMounted(() => {
  fetchMetrics();
  refreshTimer = setInterval(fetchMetrics, 30_000); // авто-обновление каждые 30 сек
});

onUnmounted(() => {
  clearInterval(refreshTimer);
});
</script>
