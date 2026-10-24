import type { App } from 'vue'
import type { Router } from 'vue-router'
import type { InvoiceShelfExtensionApi } from '@invoiceshelf/modules/frontend'

declare global {
  interface Window {
    InvoiceShelf: {
      booting(callback: (app: App, router: Router, extensions: InvoiceShelfExtensionApi) => void): void
    }
  }
}

export {}
