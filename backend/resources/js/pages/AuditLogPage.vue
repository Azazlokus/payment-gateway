<template>
  <div class="max-w-5xl">
    <h1 class="text-2xl font-semibold text-gray-900 mb-6">Журнал аудита</h1>

    <!-- Фильтры -->
    <div class="bg-white rounded-xl border border-gray-200 p-4 mb-6 flex flex-wrap gap-3">
      <select v-model="filters.action" @change="load(1)" class="text-sm border border-gray-200 rounded-lg px-3 py-2">
        <option value="">Все действия</option>
        <option v-for="a in actions" :key="a" :value="a">{{ a }}</option>
      </select>
      <input
        v-model="filters.subject_id"
        @input="debouncedLoad"
        placeholder="ID объекта"
        class="text-sm border border-gray-200 rounded-lg px-3 py-2 w-64"
      />
      <input
        v-model="filters.ip"
        @input="debouncedLoad"
        placeholder="IP-адрес"
        class="text-sm border border-gray-200 rounded-lg px-3 py-2 w-40"
      />
      <button @click="resetFilters" class="text-sm text-gray-400 hover:text-gray-700">Сбросить</button>
    </div>

    <LoadingSpinner v-if="loading" />
    <AlertMessage :message="error" type="error" />

    <div v-if="!loading && rows.length === 0" class="text-center text-gray-400 py-16">
      Записей не найдено.
    </div>

    <div v-if="rows.length" class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-200">
          <tr>
            <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase">Время</th>
            <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase">Действие</th>
            <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase">Объект</th>
            <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase">IP</th>
            <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase">API Key</th>
            <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase">Детали</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="row in rows" :key="row.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 font-mono text-xs text-gray-500 whitespace-nowrap">
              {{ formatTime(row.created_at) }}
            </td>
            <td class="px-4 py-3">
              <span :class="actionBadge(row.action)" class="text-xs font-medium px-2 py-1 rounded-full">
                {{ row.action }}
              </span>
            </td>
            <td class="px-4 py-3 font-mono text-xs text-gray-600">
              <router-link
                v-if="row.subject_type === 'payment' && row.subject_id"
                :to="`/payments/${row.subject_id}`"
                class="text-indigo-600 hover:underline"
              >{{ row.subject_id }}</router-link>
              <span v-else>{{ row.subject_id ?? '—' }}</span>
            </td>
            <td class="px-4 py-3 text-xs text-gray-500">{{ row.ip ?? '—' }}</td>
            <td class="px-4 py-3 font-mono text-xs text-gray-400">{{ row.api_key_hint ?? '—' }}</td>
            <td class="px-4 py-3 text-xs text-gray-400">
              <span v-if="row.metadata" class="font-mono">{{ JSON.stringify(row.metadata) }}</span>
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

const rows    = ref([])
const loading = ref(false)
const error   = ref('')
const page    = ref(1)
const lastPage = ref(1)

const actions = [
  'payment.created', 'payment.cancelled', 'payment.refunded',
  'dispute.filed', 'dispute.resolved',
]

const filters = ref({ action: '', subject_id: '', ip: '' })

let debounceTimer = null
function debouncedLoad () {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => load(1), 400)
}

function resetFilters () {
  filters.value = { action: '', subject_id: '', ip: '' }
  load(1)
}

async function load (p = 1) {
  loading.value = true
  error.value = ''
  try {
    const params = { page: p, per_page: 30, ...filters.value }
    // Убираем пустые параметры
    Object.keys(params).forEach(k => params[k] === '' && delete params[k])

    const { data } = await axios.get('/api/v1/audit-logs', { params })
    rows.value     = data.data
    page.value     = data.current_page
    lastPage.value = data.last_page
  } catch (e) {
    error.value = 'Ошибка загрузки журнала аудита'
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
  if (!ts) return ''
  return new Date(ts).toLocaleString('ru-RU', { dateStyle: 'short', timeStyle: 'medium' })
}

function actionBadge (action) {
  if (action.includes('created'))  return 'bg-green-100 text-green-700'
  if (action.includes('cancelled')) return 'bg-red-100 text-red-700'
  if (action.includes('refunded')) return 'bg-yellow-100 text-yellow-700'
  if (action.includes('dispute'))  return 'bg-orange-100 text-orange-700'
  return 'bg-gray-100 text-gray-600'
}

onMounted(() => load(1))
</script>
