<template>
  <div class="max-w-5xl">
    <h1 class="text-2xl font-semibold text-gray-900 mb-6">Исходящие уведомления</h1>

    <!-- Фильтры -->
    <div class="bg-white rounded-xl border border-gray-200 p-4 mb-6 flex flex-wrap gap-3 items-center">
      <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
        <input type="checkbox" v-model="onlyFailed" @change="load(1)" class="rounded" />
        Только неуспешные
      </label>
      <input
        v-model="paymentIdFilter"
        @input="debouncedLoad"
        placeholder="Payment ID"
        class="text-sm border border-gray-200 rounded-lg px-3 py-2 font-mono w-64"
      />
      <button @click="resetFilters" class="text-sm text-gray-400 hover:text-gray-700">Сбросить</button>
    </div>

    <LoadingSpinner v-if="loading" />
    <AlertMessage :message="error" type="error" />

    <div v-if="!loading && rows.length === 0" class="text-center text-gray-400 py-16">
      Нет записей.
    </div>

    <div v-if="rows.length" class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-200">
          <tr>
            <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase">Время</th>
            <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase">Payment</th>
            <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase">URL</th>
            <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase">Статус</th>
            <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase">Время ответа</th>
            <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase">Ошибка</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="row in rows" :key="row.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 font-mono text-xs text-gray-500 whitespace-nowrap">
              {{ formatTime(row.sent_at) }}
            </td>
            <td class="px-4 py-3">
              <router-link
                :to="`/payments/${row.payment_id}`"
                class="font-mono text-xs text-indigo-600 hover:underline"
              >{{ shortId(row.payment_id) }}</router-link>
            </td>
            <td class="px-4 py-3 text-xs text-gray-600 max-w-xs truncate" :title="row.url">{{ row.url }}</td>
            <td class="px-4 py-3">
              <span
                :class="row.success ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                class="text-xs font-medium px-2 py-1 rounded-full"
              >{{ row.response_status ?? (row.success ? 'OK' : 'ERR') }}</span>
            </td>
            <td class="px-4 py-3 text-xs text-gray-500">{{ row.duration_ms != null ? row.duration_ms + ' мс' : '—' }}</td>
            <td class="px-4 py-3 text-xs text-red-500 max-w-xs truncate" :title="row.error">
              {{ row.error ?? '' }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Пагинация -->
    <div v-if="lastPage > 1" class="flex justify-center gap-2 mt-4">
      <button
        v-for="p in pageRange"
        :key="p"
        @click="load(p)"
        :class="p === page ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 border border-gray-200'"
        class="px-3 py-1 rounded-lg text-sm"
      >{{ p }}</button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import LoadingSpinner from '../components/LoadingSpinner.vue'
import AlertMessage from '../components/AlertMessage.vue'

const rows            = ref([])
const loading         = ref(false)
const error           = ref('')
const page            = ref(1)
const lastPage        = ref(1)
const onlyFailed      = ref(false)
const paymentIdFilter = ref('')

let debounceTimer = null
function debouncedLoad () {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => load(1), 400)
}

function resetFilters () {
  onlyFailed.value = false
  paymentIdFilter.value = ''
  load(1)
}

async function load (p = 1) {
  loading.value = true
  error.value = ''
  try {
    const params = { page: p }
    if (onlyFailed.value) params.failed = 1
    if (paymentIdFilter.value) params.payment_id = paymentIdFilter.value

    const { data } = await axios.get('/api/v1/webhook-logs', { params })
    rows.value     = data.data
    page.value     = data.current_page
    lastPage.value = data.last_page
  } catch {
    error.value = 'Ошибка загрузки'
  } finally {
    loading.value = false
  }
}

const pageRange = computed(() => {
  const start = Math.max(1, page.value - 2)
  const end   = Math.min(lastPage.value, page.value + 2)
  return Array.from({ length: end - start + 1 }, (_, i) => start + i)
})

function formatTime (ts) {
  if (!ts) return '—'
  return new Date(ts).toLocaleString('ru-RU', { dateStyle: 'short', timeStyle: 'medium' })
}

function shortId (id) {
  return id ? id.slice(0, 8) + '…' : '—'
}

onMounted(() => load(1))
</script>
