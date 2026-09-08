# Document Management Frontend — Design

Date: 2026-09-05
Status: Approved (chat), pending spec review
Scope: RecordsFrontend only. Backend `/api/v1/documents*` API is complete and tested — no backend changes.

## Context

The sidebar already lists "Documents" (`/documents`). The backend exposes a full document
subsystem (models, controllers, resources, tests — 18 green) built on Spatie MediaLibrary:

- `GET /v1/documents?search=&category_id=&status=&per_page=`
- `GET /v1/documents/{id}`
- `POST /v1/documents` (admin; FormData: `title`, `category_id`, `file`, `status`)
- `PUT /v1/documents/{id}` (admin; `title`, `category_id`, `status` — no file)
- `DELETE /v1/documents/{id}` (admin)
- `POST /v1/documents/{id}/versions` (admin; FormData: `file`, `change_summary`)
- `GET /v1/documents/{id}/versions`
- `GET /v1/document-categories`, `GET/POST/PUT/DELETE /v1/document-categories/{id}` (writes admin)

Reads are open to any authenticated user; mutations are `role:admin|superadmin,logto`.

DocumentResource shape: `id, title, category{id,name,description}, status (active|archived),
latest_version{id,version_number,change_summary,file_url,uploaded_by}, versions[], created_by,
created_at, updated_at`.

## Goals

1. Browse documents: search by title, filter by category and status, paginated.
2. Manage documents (admins): upload, edit metadata, archive/restore, delete.
3. Version history: view past versions, upload a new version with a change summary.
4. Manage categories (admins): add, rename/update, delete.
5. Gate all mutating UI on the admin logto role names `super_admin` and `org_admin`.

## Non-goals / out of scope

- No backend changes (download auth, new endpoints, storage changes).
- No inline preview of file contents — documents open/download in a new tab via `file_url`.

## Design

### Routing and layout

- Add `/documents` route in `src/App.jsx` under the authenticated `Layout`, rendering
  `DocumentsIndex` from `Pages/DocumentDirectory/DocumentsIndex.jsx`.
- Sidebar "Documents" item and the `PageBreadcrumb` already resolve via `navData` (href `/documents`).

### `lib/roles.js`

- `isDocumentManager(user)` — returns true when `user.roles` contains a role whose `name` is
  `super_admin` or `org_admin`. Used to gate Upload/Manage-categories/archive/delete/version actions.

### `hooks/useDocumentsDirectory.js`

State: `documents` (array), `categories` (array), `page`, `perPage`, `search`, `categoryId`,
`status`, `loading`, `error`, `versions` (per selected document).

Methods (all via ApiClient):
- `fetchDocuments()` — GET `/v1/documents` with filters; handle paginator (mirror members page).
- `fetchCategories()` — GET `/v1/document-categories`.
- `fetchVersions(id)` — GET `/v1/documents/{id}/versions`.
- `createDocument(payload)` — POST FormData (`title`, `category_id`, `file`, `status`).
- `updateDocument(id, payload)` — PUT JSON (`title`, `category_id`, `status`).
- `updateStatus(id, status)` — PUT JSON (`status: active|archived`) for archive/restore.
- `deleteDocument(id)` — DELETE.
- `addVersion(id, {file, change_summary})` — POST FormData.
- `createCategory(name, description)`, `updateCategory(id, payload)`, `deleteCategory(id)`.
- `refetch()`.

Pagination/filter changes trigger `fetchDocuments`; updates close dialogs, toast, and refetch.

### Components (under `Pages/DocumentDirectory/`)

- `DocumentsIndex.jsx`
  - Header: page title, subtitle, `Upload document` and `Manage categories` buttons (both gated).
  - Toolbar: search input (debounced), category select, status select (`active|archived|all`).
  - Table (reuse existing `ui/table`): Title (with download icon linking `latest_version.file_url`),
    Category, Status badge (color-coded like members badges), Version number, Updated, Actions menu.
  - Actions: View (opens detail dialog), Archive/Restore, Edit, Delete (gated).
  - Empty state and loading skeleton matching members page.
- `DocumentFormDialog.jsx`
  - Create: `title` (required), `category_id` (select), `status` (active default), `file` (required).
  - Edit: same minus file; prefilled from row.
- `DocumentDetailDialog.jsx`
  - Tabs: Overview and Versions (reuse `ui/tabs`).
  - Overview: metadata + `Open` (link to `file_url`, new tab) + `Download`.
  - Versions: list ordered by version number, each showing number, change summary, uploader,
    date, open/download link; `Upload new version` button (gated) with file + change_summary.
- `CategoryManagerDialog.jsx`
  - List categories with edit/delete per row (gated) + inline add form (name, description).

### API client usage

- JSON payloads: existing `api.get/put/delete` helpers.
- FormData: `apiFetch` directly (matching the established member-import pattern) with auth header.

### Validation and errors

- Mirror members pages: `toast` on success/failure, `error` banner on list load failure, submit
  button disabled during pending uploads.
- Handle 422 responses (validation) and 403 (role) from the UI.

## Verification

- `npm run lint` and `npm run build` clean.
- Manual smoke: list/filter/search, upload, edit, archive/restore, delete, versions, categories,
  non-admin sees read-only UI.
- Backend untouched; existing test suite already green.

## Open questions

None. (Download auth on the backend is out of scope and noted.)