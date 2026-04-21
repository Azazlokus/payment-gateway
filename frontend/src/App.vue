<template>
  <div class="min-h-screen bg-gray-50">
    <nav class="bg-white border-b border-gray-200 px-6 py-4">
      <div class="max-w-7xl mx-auto flex items-center justify-between">
        <router-link to="/" class="text-xl font-semibold text-gray-900">
          Payment Gateway
        </router-link>
        <div class="flex gap-6">
          <router-link
            to="/"
            class="text-sm text-gray-600 hover:text-gray-900"
            active-class="text-blue-600 font-medium"
          >
            Платежи
          </router-link>
          <router-link
            to="/payments/create"
            class="text-sm text-gray-600 hover:text-gray-900"
            active-class="text-blue-600 font-medium"
          >
            Создать
          </router-link>
          <router-link
            to="/metrics"
            class="text-sm text-gray-600 hover:text-gray-900"
            active-class="text-blue-600 font-medium"
          >
            Метрики
          </router-link>
        </div>
      </div>
    </nav>

    <main class="max-w-7xl mx-auto px-6 py-8">
      <router-view />
    </main>
  </div>
</template>
