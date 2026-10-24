const { Fragment: e, computed: t, createBlock: n, createCommentVNode: r, createElementBlock: i, createElementVNode: a, createTextVNode: o, createVNode: s, defineComponent: c, h: l, normalizeClass: u, onMounted: d, openBlock: f, reactive: p, ref: m, renderList: h, resolveComponent: g, toDisplayString: _, watch: v, withCtx: y, withModifiers: b } = window.__invoiceshelf_vue;
// resources/js/api.ts
var x = {
	capabilities: "/api/v1/e-invoicing/capabilities",
	settings: "/api/v1/e-invoicing/settings",
	readiness: (e) => `/api/v1/e-invoicing/invoices/${e}/readiness`,
	xml: (e) => `/api/v1/e-invoicing/invoices/${e}/xml`
}, S = [
	"seller_name",
	"seller_street",
	"seller_postcode",
	"seller_city",
	"seller_country",
	"seller_tax_registration",
	"seller_iban"
];
function C(e) {
	return typeof e == "object" && e && "response" in e ? e.response ?? null : null;
}
// resources/js/messages.ts
var w = { en: { e_invoicing: {
	title: "E-Invoicing",
	description: "Issue invoices as ZUGFeRD / Factur-X e-invoices: the PDF carries the EN 16931 XML that accounting software reads.",
	enable: "Embed the e-invoice in invoice PDFs",
	enable_desc: "Every invoice PDF of this company that meets the requirements becomes a hybrid e-invoice. The others stay ordinary PDFs.",
	bank_details: "Payment details",
	iban: "IBAN",
	iban_help: "The account the e-invoice asks to be paid to. Required on invoices.",
	bic: "BIC",
	payment_terms: "Payment terms",
	payment_terms_help: "Written on every e-invoice, and required on one without a due date.",
	save: "Save",
	saved: "E-invoicing settings saved.",
	load_failed: "Could not load the e-invoicing settings.",
	save_failed: "Could not save the e-invoicing settings.",
	not_capable: "Invoice PDFs cannot carry the XML yet: e-invoices need the Gotenberg PDF driver, version 8.34 or newer, as the full image (not -chromium). Your settings are kept, and the XML can still be downloaded from each invoice.",
	ready: "Your company details are complete for e-invoices.",
	not_ready: "Complete these before invoices can become e-invoices:",
	panel: {
		title: "E-invoice",
		ready: "This invoice's PDF carries its EN 16931 XML (ZUGFeRD / Factur-X).",
		not_capable: "This invoice meets the requirements, but the PDF service cannot embed the XML, so its PDF is an ordinary one.",
		fallback: "This invoice goes out as an ordinary PDF, without the e-invoice XML. Missing:",
		settings_link: "E-invoicing settings"
	},
	download_xml: "Download XML",
	xml_unavailable: "No e-invoice XML for this invoice yet. Missing: {missing}",
	xml_failed: "Could not download the e-invoice XML.",
	requirements: {
		invoice_number: "An invoice number",
		invoice_date: "An invoice date",
		invoice_currency: "An invoice currency",
		seller_name: "Your company name",
		seller_street: "Your company street address",
		seller_postcode: "Your company post code",
		seller_city: "Your company city",
		seller_country: "Your company country",
		seller_tax_registration: "Your company VAT ID",
		seller_iban: "An IBAN in the e-invoicing settings",
		buyer_name: "A customer name",
		buyer_country: "The customer's billing country",
		buyer_vat_id: "The customer's VAT ID, with its country prefix, in the customer's tax ID (reverse charge and intra-community supplies need it)",
		line_items: "At least one line item",
		line_item_name: "A name on every line item",
		net_prices: "Net prices: tax-inclusive invoices cannot become e-invoices",
		tax_category_code: "A tax type with a tax category code on every tax",
		tax_rate_percentage: "A percentage tax: fixed-amount taxes cannot become e-invoices",
		invoice_tax: "A tax on every line item",
		single_tax_rate_per_line: "A single tax per line item",
		tax_exemption_reason: "An exemption reason on the exempt tax type",
		negative_price: "No negative price on a line with a positive amount (deductions such as a down payment are fine)",
		tax_rate_for_category: "A tax rate that fits its category: above 0 % for standard rate, exactly 0 % for zero-rated, exempt and the like",
		unsupported_tax_category: "A supported tax category: outside the scope of tax (O), Canary Islands (L) and Ceuta and Melilla (M) are not supported yet",
		payment_terms: "A due date on the invoice, or payment terms in the e-invoicing settings",
		valid_xml: "Valid e-invoice XML could not be produced from this invoice"
	}
} } }, T = /* @__PURE__ */ c({
	__name: "DownloadXmlButton",
	props: {
		context: {},
		client: { type: [Function, Object] },
		notify: { type: Function },
		t: { type: Function }
	},
	setup(e) {
		let t = e, r = m(!1);
		async function i() {
			let e = t.context.record();
			if (e) {
				r.value = !0;
				try {
					let n = await t.client.get(x.xml(e.id), { responseType: "blob" }), r = /filename="([^"]+)"/.exec(String(n.headers["content-disposition"] ?? ""))?.[1] ?? `${e.number ?? e.id}.xml`, i = URL.createObjectURL(n.data), a = document.createElement("a");
					a.href = i, a.download = r, a.click(), URL.revokeObjectURL(i);
				} catch (e) {
					t.notify("error", await a(e));
				} finally {
					r.value = !1;
				}
			}
		}
		async function a(e) {
			let n = C(e);
			if (n?.status === 422 && n.data instanceof Blob) try {
				let e = (JSON.parse(await n.data.text()).missing ?? []).map((e) => t.t(`e_invoicing.requirements.${e}`)).join("; ");
				return t.t("e_invoicing.xml_unavailable", { missing: e });
			} catch {}
			return t.t("e_invoicing.xml_failed");
		}
		return (e, t) => {
			let a = g("BaseIcon"), c = g("BaseButton");
			return f(), n(c, {
				variant: "primary-outline",
				loading: r.value,
				disabled: r.value,
				onClick: i
			}, {
				left: y((e) => [s(a, {
					name: "CodeBracketIcon",
					class: u(e.class)
				}, null, 8, ["class"])]),
				default: y(() => [o(" " + _(e.$t("e_invoicing.download_xml")), 1)]),
				_: 1
			}, 8, ["loading", "disabled"]);
		};
	}
}), E = { class: "mt-2 list-disc space-y-1 ps-5 text-sm" }, D = /* @__PURE__ */ c({
	__name: "RequirementList",
	props: { missing: {} },
	setup(t) {
		return (n, r) => (f(), i("ul", E, [(f(!0), i(e, null, h(t.missing, (e) => (f(), i("li", { key: e }, _(n.$t(`e_invoicing.requirements.${e}`)), 1))), 128))]));
	}
}), O = { class: "flex items-start gap-3" }, k = { class: "min-w-0 text-sm text-body" }, A = { class: "font-medium text-heading" }, j = {
	key: 0,
	class: "mt-1"
}, M = {
	key: 1,
	class: "mt-1"
}, N = { class: "mt-1" }, P = {
	key: 0,
	class: "mt-2"
}, F = /* @__PURE__ */ c({
	__name: "InvoicePanel",
	props: {
		context: {},
		client: { type: [Function, Object] }
	},
	setup(c) {
		let l = c, d = m(null), p = t(() => l.context.record()?.id ?? null), h = t(() => !!(d.value?.ready && d.value.capable)), b = t(() => d.value?.missing.some((e) => S.includes(e)) ?? !1);
		return v(p, async (e) => {
			if (d.value = null, e !== null) try {
				let { data: t } = await l.client.get(x.readiness(e));
				p.value === e && (d.value = t);
			} catch {
				d.value = null;
			}
		}, { immediate: !0 }), (t, c) => {
			let l = g("BaseIcon"), p = g("router-link"), m = g("BaseCard");
			return d.value && d.value.enabled ? (f(), n(m, {
				key: 0,
				"container-class": "px-4 py-3"
			}, {
				default: y(() => [a("div", O, [s(l, {
					name: h.value ? "CheckBadgeIcon" : "ExclamationTriangleIcon",
					class: u(["mt-0.5 h-5 w-5 shrink-0", h.value ? "text-status-green" : "text-status-yellow"])
				}, null, 8, ["name", "class"]), a("div", k, [a("p", A, _(t.$t("e_invoicing.panel.title")), 1), h.value ? (f(), i("p", j, _(t.$t("e_invoicing.panel.ready")), 1)) : d.value.ready ? (f(), i("p", M, _(t.$t("e_invoicing.panel.not_capable")), 1)) : (f(), i(e, { key: 2 }, [
					a("p", N, _(t.$t("e_invoicing.panel.fallback")), 1),
					s(D, { missing: d.value.missing }, null, 8, ["missing"]),
					b.value ? (f(), i("p", P, [s(p, {
						class: "text-primary-500 underline",
						to: "/admin/settings/e-invoicing"
					}, {
						default: y(() => [o(_(t.$t("e_invoicing.panel.settings_link")), 1)]),
						_: 1
					})])) : r("", !0)
				], 64))])])]),
				_: 1
			})) : r("", !0);
		};
	}
}), I = {
	key: 0,
	class: "py-6 text-sm text-muted"
}, L = {
	key: 0,
	class: "rounded-lg bg-alert-warning-bg p-4 text-sm text-alert-warning-text",
	role: "status"
}, R = { class: "grid gap-6 md:grid-cols-2" }, z = /* @__PURE__ */ c({
	__name: "SettingsPage",
	props: {
		client: { type: [Function, Object] },
		notify: { type: Function },
		t: { type: Function }
	},
	setup(e) {
		let c = e, l = m(!0), h = m(!1), v = m(null), S = m(null), w = p({
			iban: null,
			bic: null,
			payment_terms: null
		}), T = t({
			get: () => v.value?.iban ?? "",
			set: (e) => {
				v.value && (v.value.iban = e);
			}
		}), E = t({
			get: () => v.value?.bic ?? "",
			set: (e) => {
				v.value && (v.value.bic = e);
			}
		}), O = t({
			get: () => v.value?.payment_terms ?? "",
			set: (e) => {
				v.value && (v.value.payment_terms = e);
			}
		});
		d(() => {
			A();
		});
		function k(e) {
			v.value = { ...e.settings }, S.value = e.readiness;
		}
		async function A() {
			l.value = !0;
			try {
				let { data: e } = await c.client.get(x.settings);
				k(e);
			} catch {
				c.notify("error", c.t("e_invoicing.load_failed"));
			} finally {
				l.value = !1;
			}
		}
		async function j() {
			if (v.value) {
				h.value = !0, Object.keys(w).forEach((e) => {
					w[e] = null;
				});
				try {
					let { data: e } = await c.client.put(x.settings, v.value);
					k(e), c.notify("success", c.t("e_invoicing.saved"));
				} catch (e) {
					let t = C(e), n = t?.status === 422 ? t.data?.errors : null;
					if (n) for (let e of Object.keys(w)) w[e] = n[e]?.[0] ?? null;
					else c.notify("error", c.t("e_invoicing.save_failed"));
				} finally {
					h.value = !1;
				}
			}
		}
		return (e, t) => {
			let c = g("BaseSwitchSection"), d = g("BaseInput"), p = g("BaseInputGroup"), m = g("BaseTextarea"), x = g("BaseButton"), C = g("BaseSettingCard");
			return f(), n(C, {
				title: e.$t("e_invoicing.title"),
				description: e.$t("e_invoicing.description")
			}, {
				default: y(() => [l.value ? (f(), i("div", I, "…")) : v.value ? (f(), i("form", {
					key: 1,
					class: "space-y-6",
					onSubmit: b(j, ["prevent"])
				}, [
					S.value && !S.value.capable ? (f(), i("div", L, _(e.$t("e_invoicing.not_capable")), 1)) : r("", !0),
					S.value ? (f(), i("div", {
						key: 1,
						class: u(["rounded-lg p-4 text-sm", S.value.ready ? "bg-alert-success-bg text-alert-success-text" : "bg-alert-warning-bg text-alert-warning-text"]),
						role: "status"
					}, [a("p", null, _(S.value.ready ? e.$t("e_invoicing.ready") : e.$t("e_invoicing.not_ready")), 1), S.value.ready ? r("", !0) : (f(), n(D, {
						key: 0,
						missing: S.value.missing
					}, null, 8, ["missing"]))], 2)) : r("", !0),
					s(c, {
						modelValue: v.value.enabled,
						"onUpdate:modelValue": t[0] ||= (e) => v.value.enabled = e,
						title: e.$t("e_invoicing.enable"),
						description: e.$t("e_invoicing.enable_desc")
					}, null, 8, [
						"modelValue",
						"title",
						"description"
					]),
					a("div", R, [s(p, {
						label: e.$t("e_invoicing.iban"),
						"help-text": e.$t("e_invoicing.iban_help"),
						error: w.iban
					}, {
						default: y(() => [s(d, {
							modelValue: T.value,
							"onUpdate:modelValue": t[1] ||= (e) => T.value = e,
							autocomplete: "off",
							invalid: !!w.iban
						}, null, 8, ["modelValue", "invalid"])]),
						_: 1
					}, 8, [
						"label",
						"help-text",
						"error"
					]), s(p, {
						label: e.$t("e_invoicing.bic"),
						error: w.bic
					}, {
						default: y(() => [s(d, {
							modelValue: E.value,
							"onUpdate:modelValue": t[2] ||= (e) => E.value = e,
							autocomplete: "off",
							invalid: !!w.bic
						}, null, 8, ["modelValue", "invalid"])]),
						_: 1
					}, 8, ["label", "error"])]),
					s(p, {
						label: e.$t("e_invoicing.payment_terms"),
						"help-text": e.$t("e_invoicing.payment_terms_help"),
						error: w.payment_terms
					}, {
						default: y(() => [s(m, {
							modelValue: O.value,
							"onUpdate:modelValue": t[3] ||= (e) => O.value = e,
							rows: "2",
							invalid: !!w.payment_terms
						}, null, 8, ["modelValue", "invalid"])]),
						_: 1
					}, 8, [
						"label",
						"help-text",
						"error"
					]),
					a("div", null, [s(x, {
						type: "submit",
						variant: "primary",
						loading: h.value,
						disabled: h.value
					}, {
						default: y(() => [o(_(e.$t("e_invoicing.save")), 1)]),
						_: 1
					}, 8, ["loading", "disabled"])])
				], 32)) : r("", !0)]),
				_: 1
			}, 8, ["title", "description"]);
		};
	}
}), B = "e-invoicing";
window.InvoiceShelf.booting((e, t, n) => {
	let r = m(!1), i = async () => {
		try {
			let { data: e } = await n.client.get(x.capabilities);
			r.value = !!e.manage_settings;
		} catch {
			r.value = !1;
		}
	};
	n.addMessages(w), n.on("bootstrap:completed", ({ adminMode: e }) => {
		e || i();
	}), n.on("company:changing", () => {
		r.value = !1;
	}), n.on("company:changed", ({ companyId: e }) => {
		e !== null && i();
	});
	let a = (t, n) => String(e.config.globalProperties.$t(t, n ?? {})), o = n.notify.bind(n), s = {
		client: n.client,
		notify: o,
		t: a
	};
	n.registerCompanySettingsPage({
		id: `${B}.settings`,
		title: "e_invoicing.title",
		icon: "DocumentCheckIcon",
		path: "e-invoicing",
		priority: 70,
		visible: () => r.value,
		meta: { ability: `${B}:manage-settings` },
		component: V(z, s)
	}), n.registerEntityPanel("invoice", {
		id: `${B}.status`,
		component: F,
		props: { client: n.client }
	}), n.registerEntityAction("invoice", "detail", {
		id: `${B}.download-xml`,
		component: T,
		props: s
	});
});
function V(e, t) {
	return c({ setup: (n, { attrs: r }) => () => l(e, {
		...r,
		...t
	}) });
}
