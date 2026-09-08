# Financial Records — Design

Date: 2026-09-06
Status: Approved (chat), pending spec review
Scope: **RecordsAPI (backend) + RecordsFrontend (frontend).** This is a full new subsystem; the
backend `/api/v1/financial-*` API does not yet exist (model + table only).

## Context

- The `FinancialRecord` model and `financial_records` table exist (title, type, category, amount,
  transaction_date, recorded_by). No data exists yet. There is **no** controller, resource, service,
  request, route, factory, or test.
- The frontend sidebar already lists "Financial Records" → `/financial-records` (Wallet icon) but
  there is no route/page — clicking it 404s today.
- The seeders already define `financials.*` permissions (`financials.view|create|update|delete|export`)
  assigned to the `executive` role; `superadmin` gets all permissions.
- Established backend patterns to mirror: `EventService`/`EventController`/`EventResource` (permission
  gating on writes), `DocumentCategoryService`/`DocumentCategoryController`/`DocumentCategoryResource`
  (managed category table), `APIResponse` trait.
- Established frontend patterns to mirror: `useEventsDirectory` hook, `EventsIndex`, `EventFormDialog`,
  `DocumentCategoryManagerDialog`, `SummaryCard`, `formatMoney`, `api`/`apiFetch` client.

## Goals

1. Record income and expense entries (title, type, category, amount, transaction date).
2. See financial health at a glance: total income, total expense, net balance.
3. Browse/search/filter records (by title, type, category) with pagination.
4. Manage finance categories (add, rename, delete) via a managed table + category_id FK.
5. Export the current filtered records to CSV (gated to financial managers, like writes).
6. Gate all mutating UI/API to the existing admin/`financials.*` roles (writes); reads open to any
   authenticated user.

## Non-goals / out of scope

- No period-based reporting beyond the live summary (no monthly/yearly breakdowns, charts).
- No soft-delete/restore of records (hard delete with confirmation, matching events).
- No export of the summary; export is the filtered records only.
- No changes to the `financials.*` role/permission model beyond what the seeder already defines.

## Schema changes (RecordsAPI)

### `financial_categories` table (new)

| column | type | notes |
|---|---|---|
| id | bigint PK | |
| name | string(255) | unique |
| description | string / text nullable | |
| timestamps | | |

### `financial_records` table (modify)

Replace the existing string `category` column with a nullable `category_id` foreign key to
`financial_categories`. Because no rows exist, this is a clean drop+add in a single migration.

| column | type | notes |
|---|---|---|
| id | bigint PK | |
| title | string(255) | |
| type | string | `income` | `expense` (enum via casts) |
| amount | decimal(10,2) | > 0 |
| transaction_date | date | |
| category_id | bigint FK nullable | → financial_categories, nullOnDelete |
| recorded_by | bigint FK | → users, restrictOnDelete |
| timestamps | | |

`FinancialRecord` model changes: replace the `category` fillable with `category_id`, cast via
`TransactionType`, add `category()` (BelongsTo FinancialCategory) alongside existing `recordedBy()`.
New `FinancialCategory` model: `name`, `description` fillable; `records()` HasMany; LogsActivity.

## Backend API (RecordsAPI) — `/api/v1`

Protected by `auth:logto`. Reads (`index`, `show`, `summary`, category `index`/`show`) open
to any authenticated user. Writes gated via `permission:financials.create|update|delete,logto`
(records) and `permission:financials.update|delete,logto` (category writes); export gated via
`permission:financials.export,logto` — matching the events middleware convention.

### Financial records

- `GET /financial-records` — list with `search` (title), `type` (`income|expense`), `category_id`,
  `per_page`; paginated; `FinancialRecordResource::collection`.
- `GET /financial-records/{id}` — single record.
- `POST /financial-records` — create; sets `recorded_by` = authenticated user.
- `PUT /financial-records/{id}` — update.
- `DELETE /financial-records/{id}` — delete.
- `GET /financial-records/summary` — `{ total_income, total_expense, balance }`.
- `GET /financial-records/export` — streams a CSV of the *filtered* records (same filters as index,
  no pagination), columns: title, category, type, amount, transaction_date, recorded_by.
  **Gated**: `permission:financials.export,logto` (aligned with the existing `financials.export`
  grant, not open like the read endpoints).

### Financial categories

- `GET /financial-categories` — list ordered by name.
- `GET /financial-categories/{id}` — single.
- `POST /financial-categories` — create (admin).
- `PUT /financial-categories/{id}` — update (admin).
- `DELETE /financial-categories/{id}` — delete (admin); records keep their category_id (set null).

### Resource shapes

`FinancialRecordResource`: `id, title, type (income|expense), amount, transaction_date, category{
id,name}, recorded_by{id,name}, created_at, updated_at`.

`FinancialCategoryResource`: `id, name, description, created_at, updated_at`.

`FinancialSummaryResource`: `total_income, total_expense, balance` (all decimal strings).

### Files (RecordsAPI)

- Migration: `add_financial_categories_table_and_financial_record_category_fk`
- Models: `app/Models/FinancialCategory.php`; edit `app/Models/FinancialRecord.php`
- Services: `app/Services/FinancialRecordService.php`, `app/Services/FinancialCategoryService.php`
- Controllers: `app/Http/Controllers/Api/FinanceController.php`,
  `app/Http/Controllers/Api/FinancialCategoryController.php`
- Requests: `StoreFinancialRecordRequest`, `UpdateFinancialRecordRequest`,
  `StoreFinancialCategoryRequest`, `UpdateFinancialCategoryRequest`
- Resources: `FinancialRecordResource`, `FinancialCategoryResource`, `FinancialSummaryResource`
- Factories: `FinancialRecordFactory.php`, `FinancialCategoryFactory.php`
- Routes: extend `routes/api.php`
- Tests: `FinancialRecordApiTest.php`, `FinancialCategoryApiTest.php`, `FinancialExportTest.php`

## Frontend (RecordsFrontend)

### Route & layout

- Add `/financial-records` route in `src/App.jsx` under the authenticated `Layout`, rendering
  `FinancialRecordsIndex` from `src/Pages/FinancialDirectory/FinancialRecordsIndex.jsx`.
- Sidebar item and `PageBreadcrumb` already resolve via `navData` (href `/financial-records`).

### `lib/roles.js`

- Add `isFinancialManager(user)` → `isAdminUser(user)` (same super_admin/org_admin gating; aligns with
  the backend `executive`/`superadmin` `financials.*` grants). Reuse for writes; reads are open.

### `hooks/useFinancialDirectory.js`

State: `records`, `summary`, `pagination`, `isLoading`, `error`, `search`, `type` (`""|income|expense`),
`categoryId`, `page`, `categories`, `isExporting`.

Methods (all via `api` / `apiFetch`):
- `refetch()` — `GET /v1/financial-records` with filters (mirror `useEventsDirectory` list handling).
- `fetchSummary()` — `GET /v1/financial-records/summary`.
- `fetchCategories()` — `GET /v1/financial-categories`.
- `createRecord(payload)`, `updateRecord(id, payload)`, `deleteRecord(id)` — CRUD JSON.
- `exportCsv()` — `apiFetch('/v1/financial-records/export?...filters', { raw: true })` then
  blob-download (CSV).
- `createCategory`, `updateCategory`, `deleteCategory` — category CRUD.

### Components (under `src/Pages/FinancialDirectory/`)

- `FinancialRecordsIndex.jsx`
  - Header (title, subtitle) + `Add record` and `Manage categories` buttons (gated) + `Export CSV`
    button (gated).
  - Summary cards row: Total Income (success), Total Expenses (danger), Net Balance (default) via
    `SummaryCard`, amounts formatted with `formatMoney`.
  - Toolbar: debounced search input, type select (All/Income/Expense), category select, Export.
  - Table: Title, Category, Type badge (income green / expense rose), Amount (income emerald /
    expense rose), Transaction date, Actions (View/Edit/Delete — Edit/Delete gated).
  - Empty state, loading skeleton, pagination footer (mirror `EventsIndex`).
- `FinancialRecordFormDialog.jsx` — create/edit: `title` (required), `type` (select income/expense),
  `category_id` (select), `amount` (number > 0), `transaction_date` (date input).
- Delete confirmation dialog (inline in index, mirror `EventsIndex`).
- `FinancialCategoryManagerDialog.jsx` — mirrors `DocumentCategoryManagerDialog` (list, add/rename/
  delete, gated writes).

### API client usage

- JSON payloads: existing `api.get/post/put/delete`.
- CSV export: `apiFetch` with `{ raw: true }` to expose the `Response`, then `response.blob()` → object
  URL → programmatic `<a download>`.

### Validation and errors

- Mirror events/members: `extractErrorMessage` from `err.body.errors`; inline error boxes (not toast —
  toast infra is not mounted); submit buttons disabled while pending; 422 and 403 handled.

## Verification

- RecordsAPI: `vendor/bin/pest` targeted tests green; `vendor/bin/pint --dirty` clean.
- RecordsFrontend: `npm run lint` and `npm run build` clean.
- Manual smoke: list/filter/search, summary cards, add/edit/delete record, category management,
  export CSV downloads, non-admin sees read-only UI — no Add/Manage/Export buttons (reads + summary
  open, mutations + export hidden).

## Open questions

None.
