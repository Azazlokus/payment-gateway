<template>
  <div class="max-w-4xl">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-semibold text-gray-900">Платёжные ссылки</h1>
      <button
        @click="showCreateModal = true"
        class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg"
      >
        + Создать ссылку
      </button>
    </div>

    <LoadingSpinner v-if="loading" />
    <AlertMessage :message="error" type="error" />
    <AlertMessage :message="successMsg" type="success" />

    <div v-if="!loading && links.length === 0" class="text-center text-gray-400 py-16">
      Нет платёжных ссылок.<br>
      <span class="text-xs">Создайте ссылку для приёма платежей без API.</span>
    </div>

    <div class="space-y-3">
      <div
        v-for="link in links"
        :key="link.id"
        class="bg-white rounded-xl border border-gray-200 p-5"
      >
        <div class="flex items-start justify-between gap-4">
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-3 mb-2">
              <span
                :class="link.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                class="text-xs font-medium px-2 py-1 rounded-full"
              >{{ link.is_active ? 'Активна' : 'Неактивна' }}</span>
              <span class="text-sm font-semibold text-gray-900">{{ formatAmount(link.amount) }}</span>
              <span v-if="link.description" class="text-sm text-gray-500">{{ link.description }}</span>
            </div>

            <div class="flex items-center gap-2 mb-2">
              <span class="font-mono text-xs text-indigo-600 bg-indigo-50 px-2 py-1 rounded">
                {{ payUrl(link.token) }}
              </span>
              <button
                @click="copyLink(link.token)"
                class="text-xs text-gray-400 hover:text-gray-700"
              >Копировать</button>
            </div>

            <div class="flex gap-4 text-xs text-gray-400">
              <span>Использований: {{ link.uses }} / {{ link.max_uses ?? '∞' }}</span>
              <span v-if="link.expires_at">Истекает: {{ formatDate(link.expires_at) }}</span>
            </div>
          </div>

          <button
            @click="deleteLink(link.id)"
            class="text-xs text-red-400 hover:text-red-600 shrink-0"
          >Удалить</button>
        </div>
      </div>
    </div>

    <!-- Модальное окно создания -->
    <div
      v-if="showCreateModal"
      class="fixed inset-0 bg-black/40 flex items-center justify-center z-50"
      @click.self="showCreateModal = false"
    >
      <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-md">
        <h2 class="text-lg font-semibold mb-4">Новая платёжная ссылка</h2>

        <div class="space-y-4">
          <div>
            <label class="block text-sm text-gray-600 mb-1">Сумма (руб)</label>
            <input
              v-model.number="form.amount"
              type="number"
              min="1"
              step="1"
              placeholder="100"
              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm"
            />
          </div>
          <div>
            <label class="block text-sm text-gray-600 mb-1">Описание</label>
            <input
              v-model="form.description"
              type="text"
              placeholder="Оплата услуги..."
              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm"
            />
          </div>
          <div>
            <label class="block text-sm text-gray-600 mb-1">Макс. использований (пусто = без ограничений)</label>
            <input
              v-model.number="form.max_uses"
              type="number"
              min="1"
              placeholder="без ограничений"
              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm"
            />
          </div>
          <div>
            <label class="block text-sm text-gray-600 mb-1">Срок действия</label>
            <input
              v-model="form.expires_at"
              type="datetime-local"
              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm"
            />
          </div>
          <div>
            <label class="block text-sm text-gray-600 mb-1">Return URL</label>
            <input
              v-model="form.return_url"
              type="url"
              placeholder="https://example.com/success"
              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm"
            />
          </div>
        </div>

        <AlertMessage :message="createError" type="error" class="mt-3" />

        <div class="flex gap-3 mt-6">
          <button
            @click="createLink"
            :disabled="creating"
            class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium py-2 rounded-lg disabled:opacity-50"
          >{{ creating ? 'Создание...' : 'Создать' }}</button>
          <button
            @click="showCreateModal = false"
            class="flex-1 border border-gray-200 text-sm text-gray-600 py-2 rounded-lg hover:bg-gray-50"
          >Отмена</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import LoadingSpinner from '../components/LoadingSpinner.vue'
import AlertMessage from '../components/AlertMessage.vue'

const links          = ref([])
const loading        = ref(false)
const error          = ref('')
const successMsg     = ref('')
const showCreateModal = ref(false)
const creating       = ref(false)
const createError    = ref('')

const form = ref({
  amount: null,
  description: '',
  max_uses: null,
  expires_at: '',
  return_url: '',
})

async function load () {
  loading.value = true
  error.value = ''
  try {
    const { data } = await axios.get('/api/v1/payment-links')
    links.value = data.data ?? data
  } catch {
    error.value = 'Ошибка загрузки'
  } finally {
    loading.value = false
  }
}

async function createLink () {
  if (!form.value.amount || form.value.amount < 1) {
    createError.value = 'Укажите сумму'
    return
  }
  creating.value = true
  createError.value = ''
  try {
    const payload = {
      amount:      Math.round(form.value.amount * 100), // в копейки
      description: form.value.description || undefined,
      max_uses:    form.value.max_uses || undefined,
      expires_at:  form.value.expires_at || undefined,
      return_url:  form.value.return_url || undefined,
    }
    await axios.post('/api/v1/payment-links', payload)
    showCreateModal.value = false
    form.value = { amount: null, description: '', max_uses: null, expires_at: '', return_url: '' }
    successMsg.value = 'Ссылка создана'
    setTimeout(() => { successMsg.value = '' }, 3000)
    await load()
  } catch (e) {
    createError.value = e.response?.data?.message ?? 'Ошибка создания'
  } finally {
    creating.value = false
  }
}

async function deleteLink (id) {
  if (!confirm('Удалить ссылку?')) return
  try {
    await axios.delete(`/api/v1/payment-links/${id}`)
    links.value = links.value.filter(l => l.id !== id)
  } catch {
    error.value = 'Ошибка удаления'
  }
}

function payUrl (token) {
  return `${window.location.origin}/pay/${token}`
}

function copyLink (token) {
  navigator.clipboard.writeText(payUrl(token))
}

function formatAmount (kopecks) {
  return new Intl.NumberFormat('ru-RU', { style: 'currency', currency: 'RUB' }).format(kopecks / 100)
}

function formatDate (ts) {
  return new Date(ts).toLocaleString('ru-RU', { dateStyle: 'short', timeStyle: 'short' })
}

onMounted(load)
</script>
