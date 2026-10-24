<template>
  <ul class="mt-2 list-disc space-y-1 ps-5 text-sm">
    <li v-for="requirement in missing" :key="requirement">
      {{ $t(`e_invoicing.requirements.${requirement}`) }}
    </li>
  </ul>
</template>

<script setup lang="ts">
defineProps<{ missing: string[] }>()
</script>
