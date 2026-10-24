<template>
  <BaseSettingCard :title="$t('e_invoicing.title')" :description="$t('e_invoicing.description')">
    <div v-if="loading" class="py-6 text-sm text-muted">…</div>

    <form v-else-if="form" class="space-y-6" @submit.prevent="save">
      <div
        v-if="readiness && !readiness.capable"
        class="rounded-lg bg-alert-warning-bg p-4 text-sm text-alert-warning-text"
        role="status"
      >
        {{ $t('e_invoicing.not_capable') }}
      </div>

      <div
        v-if="readiness"
        class="rounded-lg p-4 text-sm"
        :class="readiness.ready ? 'bg-alert-success-bg text-alert-success-text' : 'bg-alert-warning-bg text-alert-warning-text'"
        role="status"
      >
        <p>{{ readiness.ready ? $t('e_invoicing.ready') : $t('e_invoicing.not_ready') }}</p>
        <RequirementList v-if="!readiness.ready" :missing="readiness.missing" />
      </div>

      <BaseSwitchSection
        v-model="form.enabled"
        :title="$t('e_invoicing.enable')"
        :description="$t('e_invoicing.enable_desc')"
      />

      <div class="grid gap-6 md:grid-cols-2">
        <BaseInputGroup :label="$t('e_invoicing.iban')" :help-text="$t('e_invoicing.iban_help')" :error="errors.iban">
          <BaseInput v-model="iban" autocomplete="off" :invalid="Boolean(errors.iban)" />
        </BaseInputGroup>

        <BaseInputGroup :label="$t('e_invoicing.bic')" :error="errors.bic">
          <BaseInput v-model="bic" autocomplete="off" :invalid="Boolean(errors.bic)" />
        </BaseInputGroup>
      </div>

      <BaseInputGroup :label="$t('e_invoicing.payment_terms')" :help-text="$t('e_invoicing.payment_terms_help')" :error="errors.payment_terms">
        <BaseTextarea v-model="paymentTerms" rows="2" :invalid="Boolean(errors.payment_terms)" />
      </BaseInputGroup>

      <div>
        <BaseButton type="submit" variant="primary" :loading="saving" :disabled="saving">
          {{ $t('e_invoicing.save') }}
        </BaseButton>
      </div>
    </form>
  </BaseSettingCard>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import type { AxiosInstance } from 'axios'
import { E_INVOICING_API, errorResponse, type CompanyReadiness, type EInvoicingSettings, type Notify } from '@/api'
import RequirementList from '@/components/RequirementList.vue'

const props = defineProps<{
  client: AxiosInstance
  notify: Notify
  t: (key: string) => string
}>()

const loading = ref(true)
const saving = ref(false)
const form = ref<EInvoicingSettings | null>(null)
const readiness = ref<CompanyReadiness | null>(null)
const errors = reactive<Record<string, string | null>>({ iban: null, bic: null, payment_terms: null })

// The inputs bind to strings; the API speaks null for an empty value.
const iban = computed({ get: () => form.value?.iban ?? '', set: (value: string) => { if (form.value) form.value.iban = value } })
const bic = computed({ get: () => form.value?.bic ?? '', set: (value: string) => { if (form.value) form.value.bic = value } })
const paymentTerms = computed({ get: () => form.value?.payment_terms ?? '', set: (value: string) => { if (form.value) form.value.payment_terms = value } })

onMounted(() => { void load() })

function apply(data: { settings: EInvoicingSettings; readiness: CompanyReadiness }): void {
  form.value = { ...data.settings }
  readiness.value = data.readiness
}

async function load(): Promise<void> {
  loading.value = true
  try {
    const { data } = await props.client.get(E_INVOICING_API.settings)
    apply(data)
  } catch {
    props.notify('error', props.t('e_invoicing.load_failed'))
  } finally {
    loading.value = false
  }
}

async function save(): Promise<void> {
  if (!form.value) return

  saving.value = true
  Object.keys(errors).forEach((key) => { errors[key] = null })

  try {
    const { data } = await props.client.put(E_INVOICING_API.settings, form.value)
    apply(data)
    props.notify('success', props.t('e_invoicing.saved'))
  } catch (error: unknown) {
    const response = errorResponse(error)
    const fieldErrors = response?.status === 422 ? (response.data as { errors?: Record<string, string[]> } | undefined)?.errors : null

    if (fieldErrors) {
      for (const key of Object.keys(errors)) {
        errors[key] = fieldErrors[key]?.[0] ?? null
      }
    } else {
      props.notify('error', props.t('e_invoicing.save_failed'))
    }
  } finally {
    saving.value = false
  }
}
</script>
