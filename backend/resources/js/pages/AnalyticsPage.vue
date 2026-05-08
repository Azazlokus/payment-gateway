<template>
  <div class="max-w-5xl">
    <h1 class="text-2xl font-semibold text-gray-900 mb-6">Аналитика</h1>

    <!-- Контроли -->
    <div class="flex flex-wrap gap-3 mb-6">
      <select v-model="days" @change="loadAll" class="text-sm border border-gray-200 rounded-lg px-3 py-2">
        <option :value="7">7 дней</option>
        <option :value="14">14 дней</option>
        <option :value="30">30 дней</option>
        <option :value="90">90 дней</option>
      </select>
      <select v-model="provider" @change="loadRevenue" class="text-sm border border-gray-200 rounded-lg px-3 py-2">
        <option value="">Все провайдеры</option>
        <option value="yookassa">YooKassa</option>
        <option value="robokassa">Robokassa</option>
        <option value="cloudpayments">CloudPayments</option>
        <option value="sbp">СБП</option>
        <option value="alfabank">Альфа-Банк</option>
      </select>
    </div>

    <LoadingSpinner v-if="loading" />
    <AlertMessage :message="error" type="error" />

    <!-- Сводные карточки -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <p class="text-xs text-gray-400 mb-1">Выручка за период</p>
        <p class="text-2xl font-semibold text-gray-900">{{ formatRub(totalRevenue) }}</p>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <p class="text-xs text-gray-400 mb-1">Успешных платежей</p>
        <p class="text-2xl font-semibold text-green-600">{{ totalCount }}</p>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <p class="text-xs text-gray-400 mb-1">Конверсия</p>
        <p class="text-2xl font-semibold text-indigo-600">{{ conversionRate }}%</p>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <p class="text-xs text-gray-400 mb-1">Средний чек</p>
        <p class="text-2xl font-semibold text-gray-900">{{ formatRub(avgCheck) }}</p>
      </div>
    </div>

    <!-- SVG-график выручки по дням -->
    <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
      <h2 class="text-sm font-semibold text-gray-700 mb-4">Выручка по дням (руб)</h2>
      <div v-if="revenueData.length" class="relative">
        <svg :viewBox="`0 0 ${svgW} ${svgH}`" class="w-full" style="height: 200px;">
          <!-- Горизонтальные линии сетки -->
          <line
            v-for="i in 5"
            :key="i"
            :x1="padding"
            :y1="padding + (chartH / 4) * (i - 1)"
            :x2="svgW - padding"
            :y2="padding + (chartH / 4) * (i - 1)"
            stroke="#f0f0f0"
            stroke-width="1"
          />

          <!-- Столбики -->
          <rect
            v-for="(d, idx) in revenueData"
            :key="d.date"
            :x="barX(idx)"
            :y="barY(d.revenue_rub)"
            :width="barWidth"
            :height="barHeight(d.revenue_rub)"
            :fill="d.revenue_rub > 0 ? '#6366f1' : '#e5e7eb'"
            rx="2"
          >
            <title>{{ d.date }}: {{ formatRub(d.revenue_rub) }}</title>
          </rect>

          <!-- Подписи дат (каждая N-я) -->
          <text
            v-for="(d, idx) in revenueData"
            v-show="idx % labelStep === 0"
            :key="`label-${d.date}`"
            :x="barX(idx) + barWidth / 2"
            :y="svgH - 4"
            text-anchor="middle"
            font-size="9"
            fill="#9ca3af"
          >{{ shortDate(d.date) }}</text>
        </svg>
      </div>
      <p v-else class="text-gray-400 text-sm text-center py-8">Нет данных за выбранный период</p>
    </div>

    <!-- Воронка конверсии -->
    <div class="bg-white rounded-xl border border-gray-200 p-6">
      <h2 class="text-sm font-semibold text-gray-700 mb-4">Воронка конверсии</h2>
      <div v-if="funnelData.length" class="space-y-3">
        <div v-for="item in sortedFunnel" :key="item.status">
          <div class="flex items-center justify-between mb-1">
            <span class="text-sm text-gray-700 flex items-center gap-2">
              <span :class="statusColor(item.status)" class="w-2 h-2 rounded-full inline-block"></span>
              {{ statusLabel(item.status) }}
            </span>
            <span class="text-sm font-medium text-gray-900">
              {{ item.count }} ({{ item.conversion_pct }}%)
            </span>
          </div>
          <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
            <div
              :class="statusBarColor(item.status)"
              class="h-full rounded-full transition-all duration-500"
              :style="`width: ${item.conversion_pct}%`"
            ></div>
          </div>
          <p class="text-xs text-gray-400 mt-0.5">{{ formatRub(item.total_rub) }}</p>
        </div>
      </div>
      <p v-else class="text-gray-400 text-sm text-center py-8">Нет данных</p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import LoadingSpinner from '../components/LoadingSpinner.vue'
import AlertMessage from '../components/AlertMessage.vue'

const revenueData = ref([])
const funnelData  = ref([])
const loading     = ref(false)
const error       = ref('')
const days        = ref(30)
const provider    = ref('')

// SVG размеры
const svgW    = 800
const svgH    = 220
const padding = 20
const chartH  = svgH - padding * 2 - 20 // оставляем место для подписей

const maxRevenue = computed(() => Math.max(...revenueData.value.map(d => d.revenue_rub), 1))
const barWidth   = computed(() => Math.max(2, (svgW - padding * 2) / (revenueData.value.length || 1) - 2))
const labelStep  = computed(() => Math.ceil(revenueData.value.length / 10))

function barX (idx) {
  return padding + idx * ((svgW - padding * 2) / (revenueData.value.length || 1))
}
function barY (val) {
  return padding + chartH - barHeight(val)
}
function barHeight (val) {
  return Math.max(1, (val / maxRevenue.value) * chartH)
}
function shortDate (d) {
  const [, m, day] = d.split('-')
  return `${day}.${m}`
}

const totalRevenue = computed(() => revenueData.value.reduce((s, d) => s + d.revenue_rub, 0))
const totalCount   = computed(() => revenueData.value.reduce((s, d) => s + d.count, 0))
const avgCheck     = computed(() => totalCount.value > 0 ? totalRevenue.value / totalCount.value : 0)
const conversionRate = computed(() => {
  const succeeded = funnelData.value.find(f => f.status === 'Succeeded')
  return succeeded ? succeeded.conversion_pct : 0
})

const sortedFunnel = computed(() =>
  [...funnelData.value].sort((a, b) => b.count - a.count)
)

function statusLabel (s) {
  return { Pending: 'В ожидании', Succeeded: 'Успешно', Cancelled: 'Отменено', Refunded: 'Возврат' }[s] ?? s
}
function statusColor (s) {
  return { Pending: 'bg-yellow-400', Succeeded: 'bg-green-500', Cancelled: 'bg-red-400', Refunded: 'bg-blue-400' }[s] ?? 'bg-gray-400'
}
function statusBarColor (s) {
  return { Pending: 'bg-yellow-400', Succeeded: 'bg-green-500', Cancelled: 'bg-red-400', Refunded: 'bg-blue-400' }[s] ?? 'bg-gray-400'
}
function formatRub (val) {
  return new Intl.NumberFormat('ru-RU', { style: 'currency', currency: 'RUB', maximumFractionDigits: 0 }).format(val)
}

async function loadRevenue () {
  try {
    const params = { days: days.value }
    if (provider.value) params.provider = provider.value
    const { data } = await axios.get('/api/v1/analytics/revenue', { params })
    revenueData.value = data.data
  } catch {
    error.value = 'Ошибка загрузки выручки'
  }
}

async function loadFunnel () {
  try {
    const from = new Date(Date.now() - days.value * 86400000).toISOString().slice(0, 10)
    const { data } = await axios.get('/api/v1/analytics/funnel', { params: { from } })
    funnelData.value = data.data
  } catch {
    error.value = 'Ошибка загрузки воронки'
  }
}

async function loadAll () {
  loading.value = true
  error.value = ''
  await Promise.all([loadRevenue(), loadFunnel()])
  loading.value = false
}

onMounted(loadAll)
</script>
