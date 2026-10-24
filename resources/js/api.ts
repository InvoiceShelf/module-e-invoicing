/** The module's API, relative to the server; always called through `extensions.client`. */
export const E_INVOICING_API = {
  capabilities: '/api/v1/e-invoicing/capabilities',
  settings: '/api/v1/e-invoicing/settings',
  readiness: (invoiceId: number): string => `/api/v1/e-invoicing/invoices/${invoiceId}/readiness`,
  xml: (invoiceId: number): string => `/api/v1/e-invoicing/invoices/${invoiceId}/xml`,
}

export type Notify = (type: 'success' | 'error' | 'warning' | 'info', message: string) => void

export interface CompanyReadiness {
  capable: boolean
  enabled: boolean
  ready: boolean
  missing: string[]
}

export interface InvoiceReadiness extends CompanyReadiness {
  fallback: boolean
}

export interface EInvoicingSettings {
  enabled: boolean
  iban: string | null
  bic: string | null
  payment_terms: string | null
}

/** The gaps an owner closes in company or module settings, not on the invoice. */
export const SELLER_REQUIREMENTS = [
  'seller_name',
  'seller_street',
  'seller_postcode',
  'seller_city',
  'seller_country',
  'seller_tax_registration',
  'seller_iban',
]

/** The HTTP response of a failed request, read without bundling axios itself. */
export function errorResponse(error: unknown): { status?: number; data?: unknown } | null {
  if (typeof error === 'object' && error !== null && 'response' in error) {
    const response = (error as { response?: { status?: number; data?: unknown } }).response

    return response ?? null
  }

  return null
}
