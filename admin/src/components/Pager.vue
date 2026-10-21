<template>
  <div class="pager" v-if="total > 0">
    <span>共 <b class="serif-num">{{ total }}</b> 条</span>
    <button class="btn btn-ghost btn-sm" :disabled="page <= 1" @click="$emit('change', page - 1)">上一页</button>
    <span class="serif-num" style="min-width:48px;text-align:center">{{ page }} / {{ pages }}</span>
    <button class="btn btn-ghost btn-sm" :disabled="page >= pages" @click="$emit('change', page + 1)">下一页</button>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  total: { type: Number, default: 0 },
  page: { type: Number, default: 1 },
  size: { type: Number, default: 20 },
})
defineEmits(['change'])

const pages = computed(() => Math.max(1, Math.ceil(props.total / props.size)))
</script>
