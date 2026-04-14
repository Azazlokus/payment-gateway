<template>
  <div v-if="message" :class="alertClass" class="rounded-lg px-4 py-3 text-sm mb-4">
    {{ message }}
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  message: { type: String, default: '' },
  type: { type: String, default: 'error' }, // error | success | info
});

const alertClass = computed(() => ({
  'bg-red-50 text-red-700 border border-red-200':     props.type === 'error',
  'bg-green-50 text-green-700 border border-green-200': props.type === 'success',
  'bg-blue-50 text-blue-700 border border-blue-200':  props.type === 'info',
}));
</script>
