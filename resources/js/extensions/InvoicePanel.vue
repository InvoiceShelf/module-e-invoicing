<template>
  <BaseCard v-if="status && status.enabled" container-class="px-4 py-3">
    <div class="flex items-start gap-3">
      <BaseIcon
        :name="good ? 'CheckBadgeIcon' : 'ExclamationTriangleIcon'"
        class="mt-0.5 h-5 w-5 shrink-0"
        :class="good ? 'text-status-green' : 'text-status-yellow'"
      />
      <div class="min-w-0 text-sm text-body">
        <p class="font-medium text-heading">{{ $t('e_invoicing.panel.title') }}</p>
        <p v-if="good" class="mt-1">{{ $t('e_invoicing.panel.ready') }}</p>
        <p v-else-if="status.ready" class="mt-1">{{ $t('e_invoicing.panel.not_capable') }}</p>
        <template v-else>
          <p class="mt-1">{{ $t('e_invoicing.panel.fallback') }}</p>
          <RequirementList :missing="status.missing" />
          <p v-if="sellerSide" class="mt-2">
            <router-link class="text-primary-500 underline" to="/admin/settings/e-invoicing">
              {{ $t('e_invoicing.panel.settings_link') }}
            </router-link>
          </p>
        </template>
      </div>
    </div>
  </BaseCard>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import type { AxiosInstance } from 'axios'
import type { EntityContext } from '@invoiceshelf/modules/frontend'
import { E_INVOICING_API, SELLER_REQUIREMENTS, type InvoiceReadiness } from '@/api'
import RequirementList from '@/components/RequirementList.vue'

const props = defineProps<{ context: EntityContext; client: AxiosInstance }>()

const status = ref<InvoiceReadiness | null>(null)
const invoiceId = computed(() => props.context.record()?.id ?? null)
const good = computed(() => Boolean(status.value?.ready && status.value.capable))
const sellerSide = computed(() => status.value?.missing.some((requirement) => SELLER_REQUIREMENTS.includes(requirement)) ?? false)

// Shows nothing while e-invoicing is off, for a member who may not view
// invoices, or when the status cannot be read: the panel is a hint, never a
// reason for the invoice page to fail.
watch(invoiceId, async (id) => {
  status.value = null
  if (id === null) return

  try {
    const { data } = await props.client.get<InvoiceReadiness>(E_INVOICING_API.readiness(id))
    if (invoiceId.value === id) status.value = data
  } catch {
    status.value = null
  }
}, { immediate: true })
</script>
