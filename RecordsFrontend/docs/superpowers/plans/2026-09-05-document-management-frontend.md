# Document Management Frontend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the Documents page (`/documents`) in RecordsFrontend, mirroring the existing members-directory patterns, covering browse/search/filter, upload, edit, archive/restore/delete, version history + new-version upload, and category management — all mutating UI gated to admin roles.

**Architecture:** A `useDocumentsDirectory` hook owns data fetching (mirrors `useMembersDirectory`); `DocumentsIndex` renders list + toolbar + pagination; small dialogs handle create/edit, detail/versions, and categories. Reads use the existing `api` helpers; uploads use `apiFetch` directly with FormData (the established pattern). No backend changes.

**Tech Stack:** React 18 (Vite), react-router-dom, existing `ui/` primitives (`table`, `dialog`, `tabs`, `button`, `input`, `empty`, `skeleton`, `card`, `avatar`), lucide-react icons, Tailwind + cva classes already in the repo.

**Spec:** `docs/superpowers/specs/2026-09-05-document-management-frontend-design.md`

## Global Constraints

- **No backend changes.** The `/api/v1/documents*` API exists and is tested (18 backend tests green).
- Frontend files only. Verification is `npm run lint` plus `npm run build` (no JS test runner exists in this repo — lint/build are the established gates; UI behavior is verified via the manual smoke checklist at the end of each task).
- Mirror existing repo patterns: `useMembersDirectory`, `MembersIndex` / `MemberFormDialog` — inline error boxes (NOT toast; toast infra is not mounted), icon buttons with `aria-label`, `extractErrorMessage` from `err.body.errors`.
- FormData payloads go through `apiFetch` directly; JSON payloads go through `api.post/put/delete`.
- Uploaded files are capped at 10MB (backend `max:10240` KB).
- Never add comments/docstrings unless a line absolutely needs explanation; the codebase is comment-free.
- Commit after each task with a conventional-style message.

---

### Task 1: Roles helper, shared error helper, and route

**Files:**
- Create: `src/lib/roles.js`
- Create: `src/lib/errors.js`
- Modify: `src/App.jsx`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `isDocumentManager(user)` — boolean. `user?.roles` is an array of Logto role objects with a `name` field; returns `true` when a role name is `"super_admin"` or `"org_admin"`.
  - `extractErrorMessage(err)` — string, same behavior as `MemberFormDialog` (reads `err.body.errors` first, else `err.message`, else fallback).
  - Route `documents` → lazy-free `import DocumentDirectory/DocumentsIndex` (created in Task 3; import can be added here referencing the future file, but Task 3 creates it — for this task, add the import + route only after `DocumentsIndex` exists. Simpler: create a minimal placeholder `DocumentsIndex.jsx` in this task so the route compiles, then flesh it out in Task 3.)

**Reasons for this task boundary:** Route + gating helper are the skeleton everything else consumes; a reviewer can meaningfully approve them independently.

- [ ] **Step 1: Create `src/lib/roles.js`**

```js
const ADMIN_ROLE_NAMES = ["super_admin", "org_admin"];

export function isDocumentManager(user) {
  if (!user || !Array.isArray(user.roles)) return false;
  return user.roles.some((role) => ADMIN_ROLE_NAMES.includes(role?.name));
}
```

- [ ] **Step 2: Create `src/lib/errors.js`**

```js
export function extractErrorMessage(err) {
  const bodyErrors = err?.body?.errors;
  if (bodyErrors && typeof bodyErrors === "object") {
    const messages = Object.values(bodyErrors).flat().slice(0, 3);
    if (messages.length) return messages.join(". ");
  }
  return err?.message || "Something went wrong. Please try again.";
}
```

- [ ] **Step 3: Create a placeholder `src/Pages/DocumentDirectory/DocumentsIndex.jsx`**

```jsx
export default function DocumentsIndex() {
  return <div>Documents</div>;
}
```

- [ ] **Step 4: Register the route in `src/App.jsx`**

Add import after `import MembersIndex from './Pages/MemberDirectory/MembersIndex';`:

```jsx
import DocumentsIndex from './Pages/DocumentDirectory/DocumentsIndex';
```

Add inside the authenticated `<Layout>` route, after `<Route path="members" ... />`:

```jsx
<Route path="documents" element={<DocumentsIndex />} />
```

- [ ] **Step 5: Verify lint/build**

Run: `npm run lint`
Expected: no new warnings for the new files (existing sidebar/nav warnings are pre-existing).

Run: `npm run build`
Expected: build succeeds.

- [ ] **Step 6: Commit**

```bash
git add src/lib/roles.js src/lib/errors.js src/Pages/DocumentDirectory/DocumentsIndex.jsx src/App.jsx
git commit -m "feat: add documents route and admin gating helpers"
```

---

### Task 2: `useDocumentsDirectory` hook

**Files:**
- Create: `src/hooks/useDocumentsDirectory.js`

**Interfaces:**
- Consumes: `api` and `apiFetch` from `@/APIClients/APIClient`.
- Produces (all consumed by Tasks 3–7):
  - State: `documents` (array), `pagination` (object|null), `isLoading` (bool), `error` (Error|null), `search` (string), `setSearch`, `categoryId` (string), `setCategoryId`, `status` (string: `""|"active"|"archived"`), `setStatus`, `page` (number), `setPage`, `categories` (array), `categoriesError` (Error|null).
  - `refetch()` — re-fetches `GET /v1/documents` with current filters; result handling identical to `useMembersDirectory` (list may be `body` or `body.data`).
  - `fetchCategories()` — `GET /v1/document-categories`; sets `categories` (list may be `body` or `body.data`).
  - `createDocument({ title, category_id, status, file })` — FormData POST `/v1/documents`, then refetch, returns response.
  - `updateDocument(id, { title, category_id, status })` — `api.put` `/v1/documents/{id}`, refetch, returns response.
  - `setDocumentStatus(id, status)` — `api.put` `/v1/documents/{id}` with `{ status }`, refetch, returns response.
  - `deleteDocument(id)` — `api.delete` `/v1/documents/{id}`, refetch.
  - `fetchVersions(id)` — `GET /v1/documents/{id}/versions`; returns the versions array (list may be `body` or `body.data`); does NOT set state.
  - `addVersion(id, { file, change_summary })` — FormData POST `/v1/documents/{id}/versions`, returns response.
  - `createCategory({ name, description })` — `api.post` `/v1/document-categories`, then `fetchCategories`.
  - `updateCategory(id, { name, description })` — `api.put` `/v1/document-categories/{id}`, then `fetchCategories`.
  - `deleteCategory(id)` — `api.delete` `/v1/document-categories/{id}`, then `fetchCategories`.

**Reason for task boundary:** The hook is the single data-access unit every UI task consumes; it is independently verifiable by wiring it to the placeholder page (Task 3 first edits extend on it).

- [ ] **Step 1: Write the hook**

```js
import { useCallback, useEffect, useRef, useState } from "react";
import { api, apiFetch } from "@/APIClients/APIClient";

const DEFAULT_PER_PAGE = 15;
const SEARCH_DEBOUNCE_MS = 400;

function unwrapList(body) {
  if (Array.isArray(body)) return body;
  return Array.isArray(body?.data) ? body.data : [];
}

export default function useDocumentsDirectory() {
  const [documents, setDocuments] = useState([]);
  const [pagination, setPagination] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState(null);
  const [search, setSearch] = useState("");
  const [debouncedSearch, setDebouncedSearch] = useState("");
  const [categoryId, setCategoryId] = useState("");
  const [status, setStatus] = useState("");
  const [page, setPage] = useState(1);
  const [categories, setCategories] = useState([]);
  const [categoriesError, setCategoriesError] = useState(null);
  const cancelledRef = useRef(false);

  useEffect(() => {
    const timer = setTimeout(() => {
      setDebouncedSearch(search);
      setPage(1);
    }, SEARCH_DEBOUNCE_MS);
    return () => clearTimeout(timer);
  }, [search]);

  useEffect(() => {
    setPage(1);
  }, [categoryId, status]);

  const refetch = useCallback(() => {
    cancelledRef.current = false;
    setError(null);
    setIsLoading(true);

    const params = new URLSearchParams({
      page: String(page),
      per_page: String(DEFAULT_PER_PAGE),
    });
    if (debouncedSearch.trim()) params.set("search", debouncedSearch.trim());
    if (categoryId) params.set("category_id", categoryId);
    if (status) params.set("status", status);

    api
      .get(`/v1/documents?${params.toString()}`)
      .then((response) => {
        if (cancelledRef.current) return;
        const body = response.data;
        const documentList = unwrapList(body);
        setDocuments(documentList);
        setPagination(
          Array.isArray(body)
            ? {
                current_page: 1,
                last_page: 1,
                total: documentList.length,
                per_page: documentList.length,
                from: documentList.length ? 1 : 0,
                to: documentList.length,
              }
            : body
        );
      })
      .catch((err) => {
        if (!cancelledRef.current) setError(err);
      })
      .finally(() => {
        if (!cancelledRef.current) setIsLoading(false);
      });
  }, [debouncedSearch, categoryId, status, page]);

  useEffect(() => {
    refetch();
    return () => {
      cancelledRef.current = true;
    };
  }, [refetch]);

  const fetchCategories = useCallback(() => {
    setCategoriesError(null);
    api
      .get("/v1/document-categories")
      .then((response) => {
        setCategories(unwrapList(response.data));
      })
      .catch((err) => setCategoriesError(err));
  }, []);

  useEffect(() => {
    fetchCategories();
  }, [fetchCategories]);

  const createDocument = useCallback(
    async (data) => {
      const formData = new FormData();
      formData.append("title", data.title);
      formData.append("category_id", data.category_id ?? "");
      formData.append("status", data.status ?? "active");
      formData.append("file", data.file);
      const response = await apiFetch("/v1/documents", { method: "POST", body: formData });
      await refetch();
      return response;
    },
    [refetch]
  );

  const updateDocument = useCallback(
    async (id, data) => {
      const response = await api.put(`/v1/documents/${id}`, data);
      await refetch();
      return response;
    },
    [refetch]
  );

  const setDocumentStatus = useCallback(
    async (id, nextStatus) => {
      const response = await api.put(`/v1/documents/${id}`, { status: nextStatus });
      await refetch();
      return response;
    },
    [refetch]
  );

  const deleteDocument = useCallback(
    async (id) => {
      const response = await api.delete(`/v1/documents/${id}`);
      await refetch();
      return response;
    },
    [refetch]
  );

  const fetchVersions = useCallback(async (id) => {
    const response = await api.get(`/v1/documents/${id}/versions`);
    return unwrapList(response.data);
  }, []);

  const addVersion = useCallback(async (id, data) => {
    const formData = new FormData();
    formData.append("file", data.file);
    if (data.change_summary) formData.append("change_summary", data.change_summary);
    return apiFetch(`/v1/documents/${id}/versions`, { method: "POST", body: formData });
  }, []);

  const createCategory = useCallback(async (data) => {
    const response = await api.post("/v1/document-categories", data);
    await fetchCategories();
    return response;
  }, [fetchCategories]);

  const updateCategory = useCallback(async (id, data) => {
    const response = await api.put(`/v1/document-categories/${id}`, data);
    await fetchCategories();
    return response;
  }, [fetchCategories]);

  const deleteCategory = useCallback(async (id) => {
    const response = await api.delete(`/v1/document-categories/${id}`);
    await fetchCategories();
    return response;
  }, [fetchCategories]);

  return {
    documents,
    pagination,
    isLoading,
    error,
    search,
    setSearch,
    categoryId,
    setCategoryId,
    status,
    setStatus,
    page,
    setPage,
    categories,
    categoriesError,
    refetch,
    fetchCategories,
    createDocument,
    updateDocument,
    setDocumentStatus,
    deleteDocument,
    fetchVersions,
    addVersion,
    createCategory,
    updateCategory,
    deleteCategory,
  };
}
```

- [ ] **Step 2: Verify lint/build**

Run: `npm run lint` — no new warnings. Run: `npm run build` — succeeds.

- [ ] **Step 3: Commit**

```bash
git add src/hooks/useDocumentsDirectory.js
git commit -m "feat: add useDocumentsDirectory data hook"
```

---

### Task 3: Documents list view

**Files:**
- Modify: `src/Pages/DocumentDirectory/DocumentsIndex.jsx` (replace placeholder entirely)
- Modify: `src/components/shadcn-space/blocks/sidebar-01/app-sidebar.jsx` (no change needed — navData already has `Documents`)

**Interfaces:**
- Consumes: `useDocumentsDirectory` (Task 2), `isDocumentManager` (`src/lib/roles.js`), `extractErrorMessage` (`src/lib/errors.js`), `useAuth` (`@/Context/AuthContext`), `ui/` primitives.
- Produces: `DocumentsIndex` default export rendering the full read view; exposes presentational hooks via props for Tasks 4–6 by rendering the dialogs passed down or containing their own state. For later tasks the page will hold: `formOpen`, `editingDocument`, `detailDocument`, `deleteTarget`, `isSubmitting`, `submitError`, `isDeleting`, `deleteError`, plus gated `canManage = isDocumentManager(user)`.

**Reason for task boundary:** Read path first — the page, list, filters, and pagination are testable on their own before any mutating UI.

- [ ] **Step 1: Implement the list page**

Replace `src/Pages/DocumentDirectory/DocumentsIndex.jsx`:

```jsx
import { useState } from "react";
import {
  ChevronLeft,
  ChevronRight,
  Download,
  Eye,
  FolderOpen,
  Pencil,
  Search,
  Trash2,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Card } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import useDocumentsDirectory from "@/hooks/useDocumentsDirectory";
import { isDocumentManager } from "@/lib/roles";
import { useAuth } from "@/Context/AuthContext";

const statusBadgeStyles = {
  active: "bg-emerald-50 text-emerald-700",
  archived: "bg-slate-100 text-slate-500",
};

function CategoryBadge({ category }) {
  if (!category) return <span className="text-xs text-csit-text-muted">Uncategorized</span>;
  return (
    <span className="rounded-md bg-[#110b79]/10 px-1.5 py-0.5 text-xs font-medium text-[#110b79]">
      {category.name}
    </span>
  );
}

function formatDate(value) {
  if (!value) return "—";
  return new Date(value).toLocaleDateString();
}

function TableSkeleton() {
  return (
    <TableBody>
      {Array.from({ length: 6 }).map((_, index) => (
        <TableRow key={index}>
          <TableCell><Skeleton className="h-3 w-40" /></TableCell>
          <TableCell><Skeleton className="h-5 w-20" /></TableCell>
          <TableCell><Skeleton className="h-5 w-16" /></TableCell>
          <TableCell><Skeleton className="h-3 w-10" /></TableCell>
          <TableCell><Skeleton className="h-3 w-24" /></TableCell>
          <TableCell><Skeleton className="h-3 w-24" /></TableCell>
        </TableRow>
      ))}
    </TableBody>
  );
}

export default function DocumentsIndex() {
  const { user } = useAuth();
  const {
    documents,
    pagination,
    isLoading,
    error,
    search,
    setSearch,
    categoryId,
    setCategoryId,
    status,
    setStatus,
    page,
    setPage,
    categories,
    refetch,
  } = useDocumentsDirectory();

  const canManage = isDocumentManager(user);

  const [formOpen, setFormOpen] = useState(false);
  const [editingDocument, setEditingDocument] = useState(null);
  const [detailDocument, setDetailDocument] = useState(null);
  const [deleteTarget, setDeleteTarget] = useState(null);

  const total = pagination?.total ?? 0;
  const lastPage = pagination?.last_page ?? 1;
  const from = pagination?.from ?? 0;
  const to = pagination?.to ?? 0;

  const downloadUrl = (doc) => doc.latest_version?.file_url ?? null;

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold text-csit-text">Documents</h1>
          <p className="mt-1 text-sm text-csit-text-muted">
            Society records, reports, and official files
          </p>
        </div>
      </div>

      <Card className="border-csit-border bg-white">
        <div className="flex flex-col gap-3 border-b border-csit-border p-4 sm:flex-row sm:items-center">
          <div className="relative flex-1">
            <Search
              size={16}
              className="pointer-events-none absolute top-1/2 left-2.5 -translate-y-1/2 text-csit-text-muted"
            />
            <Input
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder="Search documents…"
              className="pl-8"
            />
          </div>
          <select
            value={categoryId}
            onChange={(event) => setCategoryId(event.target.value)}
            className="h-8 rounded-lg border border-input bg-transparent px-2.5 text-sm outline-none transition-colors focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
            aria-label="Filter by category"
          >
            <option value="">All categories</option>
            {categories.map((category) => (
              <option key={category.id} value={category.id}>
                {category.name}
              </option>
            ))}
          </select>
          <select
            value={status}
            onChange={(event) => setStatus(event.target.value)}
            className="h-8 rounded-lg border border-input bg-transparent px-2.5 text-sm outline-none transition-colors focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
            aria-label="Filter by status"
          >
            <option value="">All statuses</option>
            <option value="active">Active</option>
            <option value="archived">Archived</option>
          </select>
        </div>

        {error ? (
          <div className="rounded-2xl border border-rose-200 bg-rose-50/50 p-6 text-sm text-rose-600">
            <div>Failed to load documents. Please try again.</div>
            <Button
              type="button"
              variant="outline"
              onClick={refetch}
              className="mt-3 border-rose-300 text-rose-600 hover:bg-rose-100"
            >
              Retry
            </Button>
          </div>
        ) : documents.length === 0 && !isLoading ? (
          <Empty className="border-0 py-16">
            <EmptyHeader>
              <EmptyMedia variant="icon">
                <FolderOpen />
              </EmptyMedia>
              <EmptyTitle>No documents found</EmptyTitle>
              <EmptyDescription>
                {search || categoryId || status
                  ? "Try adjusting your search or filters."
                  : "Documents uploaded here will appear in this list."}
              </EmptyDescription>
            </EmptyHeader>
          </Empty>
        ) : (
          <Table>
            <TableHeader>
              <TableRow className="hover:bg-transparent">
                <TableHead className="px-4">Title</TableHead>
                <TableHead>Category</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>Version</TableHead>
                <TableHead>Updated</TableHead>
                <TableHead className="px-4 text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            {isLoading ? (
              <TableSkeleton />
            ) : (
              <TableBody>
                {documents.map((doc) => (
                  <TableRow key={doc.id}>
                    <TableCell className="px-4">
                      <span className="font-medium text-csit-text">{doc.title}</span>
                    </TableCell>
                    <TableCell>
                      <CategoryBadge category={doc.category} />
                    </TableCell>
                    <TableCell>
                      <span
                        className={`rounded-md px-1.5 py-0.5 text-xs font-medium ${statusBadgeStyles[doc.status] ?? "bg-slate-100 text-slate-600"}`}
                      >
                        {doc.status}
                      </span>
                    </TableCell>
                    <TableCell className="text-csit-text-muted">
                      {doc.latest_version?.version_number ?? "—"}
                    </TableCell>
                    <TableCell className="text-csit-text-muted">
                      {formatDate(doc.updated_at)}
                    </TableCell>
                    <TableCell className="px-4">
                      <div className="flex items-center justify-end gap-1">
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon"
                          onClick={() => setDetailDocument(doc)}
                          aria-label={`View ${doc.title}`}
                        >
                          <Eye className="text-csit-text-muted" />
                        </Button>
                        {downloadUrl(doc) ? (
                          <a
                            href={downloadUrl(doc)}
                            target="_blank"
                            rel="noreferrer"
                            aria-label={`Open ${doc.title}`}
                            className="inline-flex size-8 items-center justify-center rounded-md text-csit-text-muted transition-colors hover:bg-accent hover:text-foreground"
                          >
                            <Download />
                          </a>
                        ) : null}
                        {canManage ? (
                          <>
                            <Button
                              type="button"
                              variant="ghost"
                              size="icon"
                              onClick={() => {
                                setEditingDocument(doc);
                                setFormOpen(true);
                              }}
                              aria-label={`Edit ${doc.title}`}
                            >
                              <Pencil className="text-csit-text-muted" />
                            </Button>
                            <Button
                              type="button"
                              variant="ghost"
                              size="icon"
                              onClick={() => setDeleteTarget(doc)}
                              aria-label={`Delete ${doc.title}`}
                              className="hover:bg-rose-50 hover:text-rose-600"
                            >
                              <Trash2 className="text-csit-text-muted" />
                            </Button>
                          </>
                        ) : null}
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            )}
          </Table>
        )}

        {!error && (
          <div className="flex items-center justify-between gap-4 border-t border-csit-border px-4 py-3">
            <p className="text-xs text-csit-text-muted">
              {total === 0 ? "No results" : `Showing ${from}–${to} of ${total} documents`}
            </p>
            <div className="flex items-center gap-2">
              <Button
                type="button"
                variant="outline"
                size="sm"
                disabled={page <= 1 || isLoading}
                onClick={() => setPage((current) => Math.max(1, current - 1))}
              >
                <ChevronLeft />
                Previous
              </Button>
              <span className="text-xs font-medium text-csit-text-muted">
                Page {Math.min(page, lastPage)} of {lastPage}
              </span>
              <Button
                type="button"
                variant="outline"
                size="sm"
                disabled={page >= lastPage || isLoading}
                onClick={() => setPage((current) => Math.min(lastPage, current + 1))}
              >
                Next
                <ChevronRight />
              </Button>
            </div>
          </div>
        )}
      </Card>
    </div>
  );
}
```

- [ ] **Step 2: Verify lint/build**

Run: `npm run lint` — no new warnings. Run: `npm run build` — succeeds.

- [ ] **Step 3: Manual smoke (read path)**

Start dev servers. Log in, navigate to `/documents`. Expect: breadcrumb shows `Home / Documents`; empty state renders (no rows yet); search/filter/category/status controls present and non-functioning-crash; pagination footer shows "No results".

- [ ] **Step 4: Commit**

```bash
git add src/Pages/DocumentDirectory/DocumentsIndex.jsx
git commit -m "feat: add documents list view with search, filters, and pagination"
```

---

### Task 4: Upload / edit document dialog

**Files:**
- Create: `src/Pages/DocumentDirectory/DocumentFormDialog.jsx`
- Modify: `src/Pages/DocumentDirectory/DocumentsIndex.jsx` (render dialog, handle submit)

**Interfaces:**
- Consumes: `useDocumentsDirectory.createDocument`, `.updateDocument` (Task 2); `useDocumentsDirectory.categories`; `extractErrorMessage` from `src/lib/errors.js`.
- Produces: `DocumentFormDialog` default export:
  - Props: `open` (bool), `onOpenChange` (fn), `document` (object|null; null = create), `categories` (array), `onSubmit` (async fn), `isSubmitting` (bool), `error` (string|null).
  - Also exports `extractErrorMessage` (re-export) for convenience? No — components use `src/lib/errors.js` directly.
  - Edit mode omits file input; create requires file. Fields: `title`, `category_id` (select, optional), `status` (select: active/archived, default active).
- `DocumentsIndex` gains: `handleSubmit` (create vs update), `isSubmitting`, `submitError` state, renders `<DocumentFormDialog>` with an "Upload document" header button (gated by `canManage`).

- [ ] **Step 1: Create `DocumentFormDialog.jsx`**

```jsx
import { useEffect, useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";

const STATUS_OPTIONS = [
  { value: "active", label: "Active" },
  { value: "archived", label: "Archived" },
];

export default function DocumentFormDialog({
  open,
  onOpenChange,
  document: doc,
  categories,
  onSubmit,
  isSubmitting,
  error,
}) {
  const isEdit = Boolean(doc);

  const [form, setForm] = useState({
    title: "",
    category_id: "",
    status: "active",
    file: null,
  });

  useEffect(() => {
    if (!open) return;
    setForm({
      title: doc?.title ?? "",
      category_id: doc?.category?.id != null ? String(doc.category.id) : "",
      status: doc?.status ?? "active",
      file: null,
    });
  }, [open, doc]);

  const updateField = (field, value) => {
    setForm((current) => ({ ...current, [field]: value }));
  };

  const handleSubmit = (event) => {
    event.preventDefault();
    if (!isEdit && !form.file) return;
    onSubmit({
      title: form.title.trim(),
      category_id: form.category_id ? Number(form.category_id) : null,
      status: form.status,
      ...(isEdit ? {} : { file: form.file }),
    });
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{isEdit ? "Edit document" : "Upload document"}</DialogTitle>
          <DialogDescription>
            {isEdit
              ? "Update the document's details below."
              : "Upload a file to add a new document."}
          </DialogDescription>
        </DialogHeader>

        <form id="document-form" onSubmit={handleSubmit} className="flex flex-col gap-3">
          <label className="flex flex-col gap-1.5 text-xs font-medium text-csit-text-muted">
            Title
            <Input
              required
              value={form.title}
              onChange={(event) => updateField("title", event.target.value)}
              placeholder="e.g. Executive Committee Report 2026"
            />
          </label>

          {!isEdit ? (
            <label className="flex flex-col gap-1.5 text-xs font-medium text-csit-text-muted">
              File
              <Input
                required
                type="file"
                onChange={(event) => updateField("file", event.target.files?.[0] ?? null)}
              />
            </label>
          ) : null}

          <div className="grid grid-cols-2 gap-3">
            <label className="flex flex-col gap-1.5 text-xs font-medium text-csit-text-muted">
              Category
              <select
                value={form.category_id}
                onChange={(event) => updateField("category_id", event.target.value)}
                className="h-9 rounded-lg border border-input bg-transparent px-2.5 text-sm outline-none transition-colors focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
              >
                <option value="">Uncategorized</option>
                {categories.map((category) => (
                  <option key={category.id} value={category.id}>
                    {category.name}
                  </option>
                ))}
              </select>
            </label>
            <label className="flex flex-col gap-1.5 text-xs font-medium text-csit-text-muted">
              Status
              <select
                value={form.status}
                onChange={(event) => updateField("status", event.target.value)}
                className="h-9 rounded-lg border border-input bg-transparent px-2.5 text-sm outline-none transition-colors focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
              >
                {STATUS_OPTIONS.map((option) => (
                  <option key={option.value} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </select>
            </label>
          </div>
        </form>

        {error && (
          <div className="rounded-lg border border-rose-200 bg-rose-50/50 px-3 py-2 text-xs text-rose-600">
            {error}
          </div>
        )}

        <DialogFooter>
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button type="submit" form="document-form" disabled={isSubmitting || (!isEdit && !form.file)}>
            {isSubmitting ? (isEdit ? "Saving…" : "Uploading…") : isEdit ? "Save changes" : "Upload document"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
```

Note: `form.file` in an edit payload is intentionally omitted (`docs.files` not sent).

- [ ] **Step 2: Wire into `DocumentsIndex.jsx`**

Import additions:

```jsx
import DocumentFormDialog from "./DocumentFormDialog";
import { extractErrorMessage } from "@/lib/errors";
import { Upload } from "lucide-react";
```

Overwrite the header right-side actions block — currently the page header has no buttons. Replace in `DocumentsIndex`:

```jsx
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold text-csit-text">Documents</h1>
          <p className="mt-1 text-sm text-csit-text-muted">
            Society records, reports, and official files
          </p>
        </div>
        {canManage ? (
          <div className="flex items-center gap-2">
            <Button
              onClick={() => {
                setEditingDocument(null);
                setSubmitError(null);
                setFormOpen(true);
              }}
            >
              <Upload />
              Upload document
            </Button>
          </div>
        ) : null}
      </div>
```

Also need the `createDocument`, `updateDocument` from the hook, new state, and `handleSubmit`. Update the hook destructure to add `createDocument, updateDocument` and add after `const [deleteTarget, setDeleteTarget] = useState(null);`:

```jsx
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState(null);

  const handleSubmit = async (data) => {
    setIsSubmitting(true);
    setSubmitError(null);
    try {
      if (editingDocument) {
        await updateDocument(editingDocument.id, data);
      } else {
        await createDocument(data);
      }
      setFormOpen(false);
    } catch (err) {
      setSubmitError(extractErrorMessage(err));
    } finally {
      setIsSubmitting(false);
    }
  };
```

Render the dialog after the `<Card>` (before the closing `</div>`):

```jsx
      <DocumentFormDialog
        open={formOpen}
        onOpenChange={setFormOpen}
        document={editingDocument}
        categories={categories}
        onSubmit={handleSubmit}
        isSubmitting={isSubmitting}
        error={submitError}
      />
```

- [ ] **Step 3: Verify lint/build**

Run: `npm run lint` — no new warnings. Run: `npm run build` — succeeds.

- [ ] **Step 4: Manual smoke**

Full non-admin (member) user: header has no "Upload document" button. Admin user: upload a small PDF → row appears; edit its title/category → row updates.

- [ ] **Step 5: Commit**

```bash
git add src/Pages/DocumentDirectory/DocumentFormDialog.jsx src/Pages/DocumentDirectory/DocumentsIndex.jsx
git commit -m "feat: add document upload and edit dialog"
```

---

### Task 5: Archive/restore and delete

**Files:**
- Modify: `src/Pages/DocumentDirectory/DocumentsIndex.jsx` (add archive/restore action + delete confirm dialog)

**Interfaces:**
- Consumes: `setDocumentStatus`, `deleteDocument` (Task 2); `extractErrorMessage`; Dialog primitives (already imported in Task 3? not yet — add).
- Produces: working Archive/Restore and Delete actions with inline error display (mirrors `MembersIndex` delete dialog).

- [ ] **Step 1: Add hook methods + state to `DocumentsIndex.jsx`**

Add to the hook destructure: `setDocumentStatus, deleteDocument`.

Add state after existing:

```jsx
  const [isDeleting, setIsDeleting] = useState(false);
  const [deleteError, setDeleteError] = useState(null);
  const [archiveError, setArchiveError] = useState(null);
```

Add handlers after `handleSubmit`:

```jsx
  const handleDelete = async () => {
    setIsDeleting(true);
    setDeleteError(null);
    try {
      await deleteDocument(deleteTarget.id);
      setDeleteTarget(null);
    } catch (err) {
      setDeleteError(extractErrorMessage(err));
    } finally {
      setIsDeleting(false);
    }
  };

  const handleToggleArchive = async (doc) => {
    setArchiveError(null);
    const nextStatus = doc.status === "archived" ? "active" : "archived";
    try {
      await setDocumentStatus(doc.id, nextStatus);
    } catch (err) {
      setArchiveError(extractErrorMessage(err));
    }
  };
```

- [ ] **Step 2: Add archive/restore button to each row (inside the `canManage` fragment)**

After the Edit button, before Delete:

```jsx
                            <Button
                              type="button"
                              variant="ghost"
                              size="icon"
                              onClick={() => handleToggleArchive(doc)}
                              aria-label={doc.status === "archived" ? `Restore ${doc.title}` : `Archive ${doc.title}`}
                            >
                              <Archive className="text-csit-text-muted" />
                            </Button>
```

Add `Archive` to the lucide import, and render `archiveError` above the delete dialog:

```jsx
      {archiveError && (
        <div className="rounded-lg border border-rose-200 bg-rose-50/50 px-3 py-2 text-xs text-rose-600">
          {archiveError}
        </div>
      )}
```

- [ ] **Step 3: Add the delete confirmation dialog** (import `Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle` from `@/components/ui/dialog`)

```jsx
      <Dialog
        open={Boolean(deleteTarget)}
        onOpenChange={(open) => {
          if (!open) setDeleteTarget(null);
        }}
      >
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>Delete document</DialogTitle>
            <DialogDescription>
              Are you sure you want to delete{" "}
              <span className="font-medium text-foreground">
                {deleteTarget?.title}
              </span>
              ? All versions will be removed. This action cannot be undone.
            </DialogDescription>
          </DialogHeader>

          {deleteError && (
            <div className="rounded-lg border border-rose-200 bg-rose-50/50 px-3 py-2 text-xs text-rose-600">
              {deleteError}
            </div>
          )}

          <DialogFooter>
            <Button
              type="button"
              variant="outline"
              onClick={() => setDeleteTarget(null)}
              disabled={isDeleting}
            >
              Cancel
            </Button>
            <Button
              type="button"
              variant="destructive"
              onClick={handleDelete}
              disabled={isDeleting}
            >
              {isDeleting ? "Deleting…" : "Delete document"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
```

- [ ] **Step 4: Verify lint/build**

Run: `npm run lint` — no new warnings. Run: `npm run build` — succeeds.

- [ ] **Step 5: Manual smoke**

Admin: archive a document → status flips to archived and list reflects it; restore → active. Delete a document → disappears; files removed.

- [ ] **Step 6: Commit**

```bash
git add src/Pages/DocumentDirectory/DocumentsIndex.jsx
git commit -m "feat: add document archive/restore and delete confirm"
```

---

### Task 6: Detail dialog with versions (Overview + Versions tabs + new version upload)

**Files:**
- Create: `src/Pages/DocumentDirectory/DocumentDetailDialog.jsx`
- Modify: `src/Pages/DocumentDirectory/DocumentsIndex.jsx` (render detail dialog, clear on close)

**Interfaces:**
- Consumes: `fetchVersions`, `addVersion` (Task 2); `isDocumentManager`; `extractErrorMessage`; `Tabs, TabsList, TabsTrigger, TabsContent` from `@/components/ui/tabs`. `doc` prop carries `category`, `latest_version`, `status`, `created_at`, `updated_at`.
- Produces: `DocumentDetailDialog` default export with props `open` (bool), `onOpenChange` (fn), `document` (object|null), `canManage` (bool).

- [ ] **Step 1: Create `DocumentDetailDialog.jsx`**

```jsx
import { useCallback, useEffect, useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Download, FileText, Plus } from "lucide-react";
import { extractErrorMessage } from "@/lib/errors";

const statusStyles = {
  active: "bg-emerald-50 text-emerald-700",
  archived: "bg-slate-100 text-slate-500",
};

function formatDate(value) {
  if (!value) return "—";
  return new Date(value).toLocaleString();
}

function MetaRow({ label, value }) {
  return (
    <div className="flex items-baseline justify-between gap-4 border-b border-csit-border py-2 text-sm">
      <span className="text-csit-text-muted">{label}</span>
      <span className="font-medium text-csit-text">{value ?? "—"}</span>
    </div>
  );
}

export default function DocumentDetailDialog({
  open,
  onOpenChange,
  document: doc,
  canManage,
  fetchVersions,
  addVersion,
}) {
  const [versions, setVersions] = useState([]);
  const [loadingVersions, setLoadingVersions] = useState(false);
  const [versionsError, setVersionsError] = useState(null);
  const [versionFile, setVersionFile] = useState(null);
  const [changeSummary, setChangeSummary] = useState("");
  const [isUploading, setIsUploading] = useState(false);
  const [uploadError, setUploadError] = useState(null);

  const loadVersions = useCallback(() => {
    if (!doc) return;
    setLoadingVersions(true);
    setVersionsError(null);
    fetchVersions(doc.id)
      .then(setVersions)
      .catch((err) => setVersionsError(extractErrorMessage(err)))
      .finally(() => setLoadingVersions(false));
  }, [doc, fetchVersions]);

  useEffect(() => {
    if (open && doc) {
      loadVersions();
      setVersionFile(null);
      setChangeSummary("");
      setUploadError(null);
    }
  }, [open, doc, loadVersions]);

  const handleNewVersion = async (event) => {
    event.preventDefault();
    if (!versionFile) return;
    setIsUploading(true);
    setUploadError(null);
    try {
      await addVersion(doc.id, { file: versionFile, change_summary: changeSummary.trim() || null });
      setVersionFile(null);
      setChangeSummary("");
      await loadVersions();
    } catch (err) {
      setUploadError(extractErrorMessage(err));
    } finally {
      setIsUploading(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>{doc?.title}</DialogTitle>
          <DialogDescription>Document details and version history</DialogDescription>
        </DialogHeader>

        {doc ? (
          <Tabs defaultValue="overview">
            <TabsList>
              <TabsTrigger value="overview">Overview</TabsTrigger>
              <TabsTrigger value="versions">Versions ({versions.length})</TabsTrigger>
            </TabsList>

            <TabsContent value="overview" className="pt-2">
              <MetaRow label="Category" value={doc.category?.name ?? "Uncategorized"} />
              <MetaRow label="Status" value={doc.status} />
              <MetaRow label="Latest version" value={doc.latest_version?.version_number ?? "—"} />
              <MetaRow
                label="Change summary"
                value={doc.latest_version?.change_summary ?? "—"}
              />
              <MetaRow label="Updated" value={formatDate(doc.updated_at)} />
              <div className="flex items-center justify-end gap-2 pt-4">
                {doc.latest_version?.file_url ? (
                  <a
                    href={doc.latest_version.file_url}
                    target="_blank"
                    rel="noreferrer"
                    className="inline-flex h-9 items-center justify-center gap-1.5 rounded-md bg-csit-primary px-4 text-sm font-medium text-primary-foreground transition-colors hover:bg-csit-primary/90"
                  >
                    <Download />
                    Download
                  </a>
                ) : (
                  <span className="text-xs text-csit-text-muted">No file uploaded</span>
                )}
              </div>
            </TabsContent>

            <TabsContent value="versions" className="pt-2">
              {loadingVersions ? (
                <p className="py-4 text-sm text-csit-text-muted">Loading versions…</p>
              ) : versionsError ? (
                <div className="rounded-lg border border-rose-200 bg-rose-50/50 px-3 py-2 text-xs text-rose-600">
                  {versionsError}
                </div>
              ) : versions.length === 0 ? (
                <p className="py-4 text-sm text-csit-text-muted">No versions uploaded yet.</p>
              ) : (
                <ul className="flex flex-col gap-2">
                  {versions.map((version) => (
                    <li
                      key={version.id}
                      className="flex items-center justify-between gap-3 rounded-lg border border-csit-border px-3 py-2 text-sm"
                    >
                      <div className="min-w-0">
                        <div className="flex items-center gap-2">
                          <FileText className="size-4 shrink-0 text-csit-text-muted" />
                          <span className="font-medium text-csit-text">
                            v{version.version_number}
                          </span>
                        </div>
                        <p className="mt-0.5 truncate text-xs text-csit-text-muted">
                          {version.change_summary || "No change summary"}
                        </p>
                        <p className="text-xs text-csit-text-muted">{formatDate(version.created_at)}</p>
                      </div>
                      {version.file_url ? (
                        <a
                          href={version.file_url}
                          target="_blank"
                          rel="noreferrer"
                          aria-label={`Download version ${version.version_number}`}
                          className="inline-flex size-8 shrink-0 items-center justify-center rounded-md text-csit-text-muted transition-colors hover:bg-accent hover:text-foreground"
                        >
                          <Download />
                        </a>
                      ) : null}
                    </li>
                  ))}
                </ul>
              )}

              {canManage ? (
                <form onSubmit={handleNewVersion} className="mt-4 flex flex-col gap-3 rounded-lg bg-muted/40 p-3">
                  <p className="text-xs font-medium text-csit-text">Upload new version</p>
                  <label className="flex flex-col gap-1.5 text-xs font-medium text-csit-text-muted">
                    File
                    <Input
                      required
                      type="file"
                      value={undefined}
                      onChange={(event) => setVersionFile(event.target.files?.[0] ?? null)}
                    />
                  </label>
                  <label className="flex flex-col gap-1.5 text-xs font-medium text-csit-text-muted">
                    Change summary
                    <Input
                      value={changeSummary}
                      onChange={(event) => setChangeSummary(event.target.value)}
                      placeholder="What changed in this version?"
                    />
                  </label>
                  {uploadError && (
                    <div className="rounded-lg border border-rose-200 bg-rose-50/50 px-3 py-2 text-xs text-rose-600">
                      {uploadError}
                    </div>
                  )}
                  <div className="flex justify-end">
                    <Button type="submit" size="sm" disabled={isUploading || !versionFile}>
                      <Plus />
                      {isUploading ? "Uploading…" : "Upload version"}
                    </Button>
                  </div>
                </form>
              ) : null}
            </TabsContent>
          </Tabs>
        ) : null}
      </DialogContent>
    </Dialog>
  );
}
```

Note: this dialog receives `fetchVersions` and `addVersion` as props from the parent so it stays presentational.

- [ ] **Step 2: Wire into `DocumentsIndex.jsx`**

Add to hook destructure: `fetchVersions, addVersion`. Add import `DocumentDetailDialog from "./DocumentDetailDialog";`. Render after the form dialog:

```jsx
      <DocumentDetailDialog
        open={Boolean(detailDocument)}
        onOpenChange={(open) => {
          if (!open) setDetailDocument(null);
        }}
        document={detailDocument}
        canManage={canManage}
        fetchVersions={fetchVersions}
        addVersion={addVersion}
      />
```

- [ ] **Step 3: Verify lint/build**

Run: `npm run lint` — no new warnings. Run: `npm run build` — succeeds.

- [ ] **Step 4: Manual smoke**

Open a document > Overview shows metadata and download link. Versions tab lists history (or empty state). Admin: upload a new version → appears at top; change summary shows. Non-admin: "Upload new version" form is hidden.

- [ ] **Step 5: Commit**

```bash
git add src/Pages/DocumentDirectory/DocumentDetailDialog.jsx src/Pages/DocumentDirectory/DocumentsIndex.jsx
git commit -m "feat: add document detail dialog with version history"
```

---

### Task 7: Category manager dialog

**Files:**
- Create: `src/Pages/DocumentDirectory/CategoryManagerDialog.jsx`
- Modify: `src/Pages/DocumentDirectory/DocumentsIndex.jsx` (render dialog + "Manage categories" header button, gated)

**Interfaces:**
- Consumes: `categories` state, `createCategory`, `updateCategory`, `deleteCategory` (Task 2); `extractErrorMessage`.
- Produces: `CategoryManagerDialog` default export with props `open` (bool), `onOpenChange` (fn), `categories` (array), `onCreate` (async fn), `onUpdate` (async fn), `onDelete` (async fn). Internally manages form (name, description), edit target, delete confirm target, submitting/error state.

- [ ] **Step 1: Create `CategoryManagerDialog.jsx`**

```jsx
import { useEffect, useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Pencil, Plus, Trash2 } from "lucide-react";
import { extractErrorMessage } from "@/lib/errors";

const emptyForm = { name: "", description: "" };

export default function CategoryManagerDialog({
  open,
  onOpenChange,
  categories,
  onCreate,
  onUpdate,
  onDelete,
}) {
  const [form, setForm] = useState(emptyForm);
  const [editing, setEditing] = useState(null);
  const [confirming, setConfirming] = useState(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    if (!open) return;
    setForm(emptyForm);
    setEditing(null);
    setConfirming(null);
    setError(null);
  }, [open]);

  const startEdit = (category) => {
    setEditing(category);
    setConfirming(null);
    setForm({ name: category.name ?? "", description: category.description ?? "" });
  };

  const submitForm = async (event) => {
    event.preventDefault();
    const payload = {
      name: form.name.trim(),
      description: form.description.trim(),
    };
    setIsSubmitting(true);
    setError(null);
    try {
      if (editing) {
        await onUpdate(editing.id, payload);
      } else {
        await onCreate(payload);
      }
      setForm(emptyForm);
      setEditing(null);
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleConfirmDelete = async () => {
    setIsSubmitting(true);
    setError(null);
    try {
      await onDelete(confirming.id);
      setConfirming(null);
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Manage categories</DialogTitle>
          <DialogDescription>Organize documents into categories.</DialogDescription>
        </DialogHeader>

        {error && (
          <div className="rounded-lg border border-rose-200 bg-rose-50/50 px-3 py-2 text-xs text-rose-600">
            {error}
          </div>
        )}

        <form onSubmit={submitForm} className="flex flex-col gap-3">
          <div className="grid grid-cols-2 gap-3">
            <label className="flex flex-col gap-1.5 text-xs font-medium text-csit-text-muted">
              Name
              <Input
                required
                value={form.name}
                onChange={(event) => setForm((current) => ({ ...current, name: event.target.value }))}
                placeholder="e.g. Meeting Minutes"
              />
            </label>
            <label className="flex flex-col gap-1.5 text-xs font-medium text-csit-text-muted">
              Description
              <Input
                value={form.description}
                onChange={(event) =>
                  setForm((current) => ({ ...current, description: event.target.value }))
                }
                placeholder="Optional"
              />
            </label>
          </div>
          <div className="flex justify-end">
            <Button type="submit" size="sm" disabled={isSubmitting}>
              <Plus />
              {editing ? "Save changes" : "Add category"}
            </Button>
          </div>
        </form>

        {categories.length === 0 ? (
          <p className="py-4 text-sm text-csit-text-muted">No categories yet.</p>
        ) : (
          <ul className="flex flex-col gap-2">
            {categories.map((category) => (
              <li
                key={category.id}
                className="flex items-center justify-between gap-3 rounded-lg border border-csit-border px-3 py-2 text-sm"
              >
                <div className="min-w-0">
                  <p className="font-medium text-csit-text">{category.name}</p>
                  <p className="truncate text-xs text-csit-text-muted">
                    {category.description || "No description"}
                  </p>
                </div>
                <div className="flex items-center gap-1">
                  <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    onClick={() => startEdit(category)}
                    aria-label={`Edit ${category.name}`}
                  >
                    <Pencil className="text-csit-text-muted" />
                  </Button>
                  <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    onClick={() => setConfirming(category)}
                    aria-label={`Delete ${category.name}`}
                    className="hover:bg-rose-50 hover:text-rose-600"
                  >
                    <Trash2 className="text-csit-text-muted" />
                  </Button>
                </div>
              </li>
            ))}
          </ul>
        )}

        {confirming && (
          <div className="flex items-center justify-between gap-3 rounded-lg border border-rose-200 bg-rose-50/50 px-3 py-2 text-xs text-rose-600">
            <span>
              Delete category{" "}
              <span className="font-medium">{confirming.name}</span>?
            </span>
            <div className="flex items-center gap-2">
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => setConfirming(null)}
                disabled={isSubmitting}
              >
                Cancel
              </Button>
              <Button
                type="button"
                variant="destructive"
                size="sm"
                onClick={handleConfirmDelete}
                disabled={isSubmitting}
              >
                {isSubmitting ? "Deleting…" : "Delete"}
              </Button>
            </div>
          </div>
        )}

        <DialogFooter>
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            Close
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
```

- [ ] **Step 2: Wire into `DocumentsIndex.jsx`**

Add to hook destructure: `createCategory, updateCategory, deleteCategory`. Import `CategoryManagerDialog from "./CategoryManagerDialog";`. Add state `const [categoryManagerOpen, setCategoryManagerOpen] = useState(false);`.

Add a "Manage categories" outline button next to "Upload document" (still inside `canManage`):

```jsx
          <div className="flex items-center gap-2">
            <Button variant="outline" onClick={() => setCategoryManagerOpen(true)}>
              Manage categories
            </Button>
            <Button
              onClick={() => {
                setEditingDocument(null);
                setSubmitError(null);
                setFormOpen(true);
              }}
            >
              <Upload />
              Upload document
            </Button>
          </div>
```

Render the dialog after the detail dialog:

```jsx
      <CategoryManagerDialog
        open={categoryManagerOpen}
        onOpenChange={setCategoryManagerOpen}
        categories={categories}
        onCreate={createCategory}
        onUpdate={updateCategory}
        onDelete={deleteCategory}
      />
```

- [ ] **Step 3: Verify lint/build**

Run: `npm run lint` — no new warnings. Run: `npm run build` — succeeds.

- [ ] **Step 4: Manual smoke**

Admin: add a category, edit it, delete it (with confirm bar). Category select in the docs toolbar and the form dialog reflect new categories.

- [ ] **Step 5: Commit**

```bash
git add src/Pages/DocumentDirectory/CategoryManagerDialog.jsx src/Pages/DocumentDirectory/DocumentsIndex.jsx
git commit -m "feat: add document category manager"
```

---

### Task 8: Final verification

**Files:**
- None (verification only).

- [ ] **Step 1: Full frontend verification**

Run: `npm run lint` — only pre-existing warnings (sidebar/nav files).
Run: `npm run build` — succeeds.
Run: `git status --short` in `RecordsFrontend` — only expected DocumentDirectory changes.

- [ ] **Step 2: Backend regression check**

Run: `php artisan test` in `RecordsAPI`
Expected: all tests pass (no backend changes, but confirm 76+ still green and imports/API contract untouched).

- [ ] **Step 3: Manual end-to-end smoke**

Using admin + non-admin accounts:
1. Non-admin sees read-only docs list, no Upload / Manage categories / action icons, no version-upload form.
2. Admin: upload PDF/Excel doc → appears with `v1` badge; filter by its category; search its title.
3. Edit title/category/status; archive then restore.
4. Open > Overview shows metadata + working download link (new tab).
5. Open > Versions: initial version listed; upload v2 with change summary; both visible; download v1 and v2.
6. Categories: add/edit/delete; toolbar + form selects stay in sync.
7. Page refresh on `/documents` → page still loads, sidebar "Documents" highlighted, breadcrumb `Home / Documents`.

- [ ] **Step 4: Final commit (any leftover fixes)**

```bash
git add -A
git commit -m "chore: finalize documents feature verification"
```

(Only if Task steps 1–3 produced uncommitted fixes.)