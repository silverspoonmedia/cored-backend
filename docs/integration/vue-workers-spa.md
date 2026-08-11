# External SPA port — Monolith-Vue-Workers

Separate Vue 3 SPA that consumes the Monolith `/api/v1/*` API. Lives in a sibling repository (`monolith-vue-workers`), not inside the Monolith codebase. This doc is the integration contract between the SPA and the API.

For the Blade admin + Vue islands frontend, see [web/blade-admin.md](../web/blade-admin.md).

## Architecture

| Piece | Role |
|-------|------|
| Vue 3 + Vite + TypeScript | SPA runtime, SFC components, type safety |
| Cloudflare Workers | Deployment target (`@cloudflare/vite-plugin`) |
| Token-based auth | JWT stored in memory + sessionStorage; sent as `Authorization: Bearer` header (no HttpOnly cookie — that is Blade-only) |
| `fetch` wrapper (`src/api/client.ts`) | Builds URLs from `VITE_API_BASE_URL`, injects Bearer token, handles 401 refresh + retry, parses `{ status, message, data }` envelope |
| `vue-i18n` | Full locale system (`en`, `id`, `ja`) converted from Laravel PHP lang files |

Base URL: `VITE_API_BASE_URL` (e.g. `https://monolith-ph24.spnd.uk/api/v1`). All API call paths in composables are relative to this base.

## API contract reference

The SPA consumes the same `/api/v1/*` endpoints as the Blade admin. Per-domain API docs:

- [payment-gateway](../domains/payment-gateway/README.md)
- [microstock](../domains/microstock/README.md)
- [notification](../domains/notification/README.md)
- [product-activations](../domains/product-activations/README.md)
- [identity](../domains/identity/README.md)
- [navigation](../domains/navigation/README.md)
- [file-hosting](../domains/file-hosting/README.md)
- [inference](../domains/inference/README.md)
- [service](../domains/service/README.md)
- [vtual](../domains/vtual/README.md)

### Endpoint path gotchas

These paths tripped up the SPA port — documented here to prevent regression:

| SPA composable | Correct API path | Common mistake |
|----------------|------------------|----------------|
| `usePaymentsIncome.fetchOverview` | `GET payments/income` | `payments/income/overview` (route name is `payments.income.overview` but URI has no `/overview` suffix) |
| `useFileHostingUsage.fetchBilling` | `GET microstock/billing` | `file-hosting/microstock/billing` (extra `file-hosting/` prefix — that is the web route, not API) |
| `useFileHostingUsage.fetchLedger` | `GET microstock/billing/ledger` | `file-hosting/microstock/billing/ledger` |
| `useFileHostingUsage.fetchUsageEvents` | `GET microstock/billing/usage-events` | `file-hosting/microstock/billing/usage-events` |
| `useFileHostingUsage.fetchAdminOverview` | `GET admin/microstock/billing/overview` | `file-hosting/microstock/billing/admin/overview` |
| `useFileHostingUsage.fetchAdminUsageEvents` | `GET admin/microstock/billing/usage-events` | `file-hosting/microstock/billing/admin/usage-events` |
| `usePaymentChannels.syncPolicy` | `POST payment-channels/sync-policy` | No API route existed previously; added in `routes/api/v1/payment-gateway.php` |

### Response shape gotchas

| Endpoint | Returns | Gotcha |
|----------|---------|--------|
| `GET notifications/unread-count` | `{ unread: number }` | Field is `unread`, not `count` |
| `GET microstock/billing` | `balance_micro_usd`, `balance_usd`, `estimated_per_analysis_micro_usd`, `estimated_per_analysis_usd`, `daily_free: { enabled, limit, used, remaining, resets_at }` | Field names are `balance_usd` / `estimated_per_analysis_usd`, not `balance_formatted` / `estimate_formatted`. Daily free uses `used`/`limit`, not `slots_consumed_today`/`daily_limit`. |
| `GET admin/product-activations` (index) | Flat `ProductActivationResource[]` in `data` | No `{ data, pagination }` wrapper — `res.data` is the array directly |
| `GET admin/product-activations/products/{productKey}` | `{ summary, activations, pagination }` in `data` | This one DOES have a wrapper — opposite of index |
| `GET payment-channels?subtotal=` | `PaymentChannelResource[]` with flattened quote fields | Quote fields (`estimated_channel_fee`, `estimated_channel_fee_formatted`, `fee_label`, etc.) are at top level, NOT nested under `checkout_quote`. See [payment-gateway](../domains/payment-gateway/README.md#paymentchannelresource-checkout-quote-flattening). |

## Type alignment

TS types in `src/api/types.ts` must match Laravel API Resources exactly. No transformation layer.

### Conventions

- **snake_case everywhere** — Laravel returns snake_case, TS uses snake_case. No camelCase conversion.
- **API envelope** — every response is `{ status: 'success'|'error', message: string, data: T }`. The `data` field is the payload.
- **Date triplets** — date fields come as `{ key (ISO string), key_formatted (human-readable), key_human (relative) }`. All three are pre-formatted strings; never call `new Date(apiField)`.
- **Nullable fields** — Laravel may return `null` where a type expects a string. Use `string | null` in TS types.
- **UUIDs** — all model IDs are UUID v7 strings (see [platform/identifiers.md](../platform/identifiers.md)). Never type IDs as `number`.

### Pagination shapes

Three patterns exist in the API:

1. **Flat array** — `ProductActivationResource::collection(...)` returned directly. `data` is `T[]`. No pagination metadata.
2. **`{ items, pagination: { current_page, last_page, per_page, total, has_more } }`** — used by microstock billing ledger/usage-events, inference usage events.
3. **`{ summary, activations, pagination: { current_page, last_page, per_page, total } }`** — product activation per-product show endpoint. `has_more` is not returned here.

## i18n porting notes

The SPA uses `vue-i18n` with JSON locale files converted from Laravel PHP lang files.

### Conversion rules

| Laravel PHP | vue-i18n JSON |
|-------------|---------------|
| `lang/en/web/auth.php` | `src/i18n/locales/en.json` under `web.auth.*` |
| `lang/en/ui.php` | `src/i18n/locales/en.json` under `ui.*` |
| `trans('web/auth.title')` | `t('web.auth.title')` — use `.` separator, not `/` |
| `:variable` placeholder | `{variable}` placeholder |
| `trans_choice('key', count)` | `t('key', { count })` or custom pluralization helper |

### Usage pattern

Every `.vue` file using translations must have:

```vue
<script setup lang="ts">
import { useI18n } from 'vue-i18n'
const { t } = useI18n()
// or for message arrays (settings fields/hints):
// const { t, tm } = useI18n()
</script>
```

All user-visible strings must be wrapped in `t()`. This includes:
- Text nodes in templates: `{{ t('key') }}`
- Component prop labels: `:title="t('key')"` not `title="Hardcoded"`
- Placeholders: `:placeholder="t('key')"`
- Toast/error messages in catch blocks: `showError(t('key'))`
- `prompt()` / `confirm()` dialog text

Options arrays that contain labels should be `computed()` so they re-render on locale change:

```ts
const basisOptions = computed(() => [
  { value: 'paid_at', label: t('web.payments.income.basis_paid_at') },
  { value: 'withdrawable_at', label: t('web.payments.income.basis_withdrawable_at') },
])
```

### Locale files

- `src/i18n/locales/en.json` — reference locale
- `src/i18n/locales/id.json` — Indonesian
- `src/i18n/locales/ja.json` — Japanese
- `src/i18n/index.ts` — `createI18n()` instance, `setLocale()` helper, `applyHtmlLang()`

Locale parity: all three files must have the same key set. Laravel `:variable` placeholders must be converted to `{variable}` in all locales (not just `en`).

## Component conventions

### ConfirmModal

```vue
<ConfirmModal
  v-model="showConfirm"
  :title="t('title_key')"
  :confirm-label="t('confirm_key')"
  :confirm-variant="'danger'"
  @confirm="handleConfirm"
  @cancel="showConfirm = false"
>
  <p>{{ t('message_key') }}</p>
</ConfirmModal>
```

| Prop | Type | Notes |
|------|------|-------|
| `v-model` | `boolean` | Controls visibility. NOT `:show`. |
| `title` | `string` | |
| `message` | `string` | Rendered in `<p>` when no slot content |
| `confirm-label` | `string` | NOT `confirm-text` |
| `cancel-label` | `string` | |
| `confirm-variant` | `string` | Bare name: `'danger'`, `'success'`, `'primary'`, `'warning'`. NOT `'btn-danger'` — component prepends `btn-`. |

Default slot overrides `<p>` message when slot content is provided.

### SubmitButton

```vue
<SubmitButton :loading="saving" :label="t('save_key')" variant="danger" />
```

Uses `variant` prop (not `confirm-class`). Values: `'primary'`, `'danger'`, `'success'`, `'warning'`.

### FormInput

Supports `maxlength?: number` prop, passed through to underlying `<input>`.

### FormSelect2

Wraps jQuery Select2. Handles plain and AJAX modes. See [web/ui-patterns.md](../web/ui-patterns.md) for Select2 usage patterns.
