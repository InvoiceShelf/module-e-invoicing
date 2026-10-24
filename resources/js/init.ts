import { defineComponent, h, ref } from 'vue'
import type { Component } from 'vue'
import type { InvoiceShelfExtensionApi } from '@invoiceshelf/modules/frontend'
import '../css/module.css'
import { E_INVOICING_API } from './api'
import { messages } from './messages'
import DownloadXmlButton from './extensions/DownloadXmlButton.vue'
import InvoicePanel from './extensions/InvoicePanel.vue'
import SettingsPage from './pages/SettingsPage.vue'

/** The module.json slug; abilities and contribution ids are namespaced with it. */
const MODULE = 'e-invoicing'

/**
 * Everything E-Invoicing adds to the host, registered before the app mounts:
 * a company settings page (owners only), a status panel on every invoice and
 * a Download XML action on its page. The host tears them all down when the
 * module is disabled.
 */
window.InvoiceShelf.booting((app, _router, extensions) => {
  const canManageSettings = ref(false)

  const refreshCapabilities = async (): Promise<void> => {
    try {
      const { data } = await extensions.client.get<{ manage_settings: boolean }>(E_INVOICING_API.capabilities)
      canManageSettings.value = Boolean(data.manage_settings)
    } catch {
      canManageSettings.value = false
    }
  }

  extensions.addMessages(messages)
  extensions.on('bootstrap:completed', ({ adminMode }) => { if (!adminMode) void refreshCapabilities() })
  extensions.on('company:changing', () => { canManageSettings.value = false })
  extensions.on('company:changed', ({ companyId }) => { if (companyId !== null) void refreshCapabilities() })

  // The host's i18n, for strings built in code rather than in a template.
  const t = (key: string, values?: Record<string, unknown>): string => String(app.config.globalProperties.$t(key, values ?? {}))
  const notify = extensions.notify.bind(extensions)
  const host = { client: extensions.client, notify, t }

  extensions.registerCompanySettingsPage({
    id: `${MODULE}.settings`,
    title: 'e_invoicing.title',
    icon: 'DocumentCheckIcon',
    path: 'e-invoicing',
    priority: 70,
    visible: () => canManageSettings.value,
    meta: { ability: `${MODULE}:manage-settings` },
    component: withProps(SettingsPage, host),
  })

  extensions.registerEntityPanel('invoice', {
    id: `${MODULE}.status`,
    component: InvoicePanel,
    props: { client: extensions.client },
  })

  extensions.registerEntityAction('invoice', 'detail', {
    id: `${MODULE}.download-xml`,
    component: DownloadXmlButton,
    props: host,
  })
})

/**
 * Hand a page the host services it cannot reach by itself: a module bundle
 * runs on the host's Vue but not on its Pinia or router injections.
 */
function withProps(page: Component, extra: Record<string, unknown>): Component {
  return defineComponent({
    setup: (_props, { attrs }) => () => h(page, { ...attrs, ...extra }),
  })
}

export type { InvoiceShelfExtensionApi }
