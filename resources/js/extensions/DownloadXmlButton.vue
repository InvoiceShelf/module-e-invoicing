<template>
  <BaseButton variant="primary-outline" :loading="busy" :disabled="busy" @click="download">
    <template #left="slotProps">
      <BaseIcon name="CodeBracketIcon" :class="slotProps.class" />
    </template>
    {{ $t('e_invoicing.download_xml') }}
  </BaseButton>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import type { AxiosInstance } from 'axios'
import type { EntityContext } from '@invoiceshelf/modules/frontend'
import { E_INVOICING_API, errorResponse, type Notify } from '@/api'

const props = defineProps<{
  context: EntityContext
  client: AxiosInstance
  notify: Notify
  t: (key: string, values?: Record<string, unknown>) => string
}>()

const busy = ref(false)

async function download(): Promise<void> {
  const record = props.context.record()
  if (!record) return

  busy.value = true
  try {
    const response = await props.client.get<Blob>(E_INVOICING_API.xml(record.id), { responseType: 'blob' })
    const name = /filename="([^"]+)"/.exec(String(response.headers['content-disposition'] ?? ''))?.[1] ?? `${record.number ?? record.id}.xml`
    const url = URL.createObjectURL(response.data)
    const link = document.createElement('a')
    link.href = url
    link.download = name
    link.click()
    URL.revokeObjectURL(url)
  } catch (error: unknown) {
    props.notify('error', await failureMessage(error))
  } finally {
    busy.value = false
  }
}

/** A 422 names what the invoice is missing; its body arrives as a Blob because of the response type. */
async function failureMessage(error: unknown): Promise<string> {
  const response = errorResponse(error)

  if (response?.status === 422 && response.data instanceof Blob) {
    try {
      const body = JSON.parse(await response.data.text()) as { missing?: string[] }
      const missing = (body.missing ?? []).map((key) => props.t(`e_invoicing.requirements.${key}`)).join('; ')

      return props.t('e_invoicing.xml_unavailable', { missing })
    } catch {
      // fall through to the generic message
    }
  }

  return props.t('e_invoicing.xml_failed')
}
</script>
