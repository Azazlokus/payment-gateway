<template>
  <span :class="badgeClass" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
    {{ label }}
  </span>
</template>

<script setup>
const props = defineProps({
  status: { type: String, required: true },
});

const statusMap = {
  Pending:   { label: 'Ожидает',   cls: 'bg-yellow-100 text-yellow-800' },
  Succeeded: { label: 'Оплачен',   cls: 'bg-green-100 text-green-800' },
  Cancelled: { label: 'Отменён',   cls: 'bg-gray-100 text-gray-700' },
  Refunded:  { label: 'Возврат',   cls: 'bg-blue-100 text-blue-800' },
};

const info = computed(() => statusMap[props.status] ?? { label: props.status, cls: 'bg-gray-100 text-gray-600' });
const label = computed(() => info.value.label);
const badgeClass = computed(() => info.value.cls);
</script>

<script>
import { computed } from 'vue';
export default {};
</script>
