import React, { useState } from "react";
import { useNavigate } from "react-router-dom";
import "date-utils";
import {
  Archive,
  ChevronLeft,
  ChevronRight,
  Download,
  Eye,
  FolderOpen,
  Pencil,
  Search,
  Trash2,
  Upload,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Card } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import useDocumentsDirectory from "@/hooks/useDocumentsDirectory";
import { isDocumentManager } from "@/lib/roles";
import { extractErrorMessage } from "@/lib/errors";
import { useAuth } from "@/Context/AuthContext";
import DocumentFormDialog from "./DocumentFormDialog";
import CategoryManagerDialog from "./CategoryManagerDialog";
import PermissionGate from "@/components/PermissionGate";

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
  return new Date(value).toFormat("DD MMM YYYY");
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

const DocumentRow = React.memo(function DocumentRow({ doc, canManage, onView, onEdit, onToggleArchive, onDelete }) {
  const downloadUrl = doc.latest_version?.file_url ?? null;
  return (
    <TableRow>
      <TableCell className="px-4">
        <span className="font-medium text-csit-text">{doc.title}</span>
      </TableCell>
      <TableCell>
        <CategoryBadge category={doc.category} />
      </TableCell>
      <TableCell>
        <div className="flex flex-wrap items-center gap-1.5">
          <span
            className={`rounded-md px-1.5 py-0.5 text-xs font-medium ${statusBadgeStyles[doc.status] ?? "bg-slate-100 text-slate-600"}`}
          >
            {doc.status}
          </span>
          <span
            className={`rounded-md px-1.5 py-0.5 text-xs font-medium ${doc.is_public ? "bg-sky-50 text-sky-700" : "bg-amber-50 text-amber-700"}`}
          >
            {doc.is_public ? "Public" : "Private"}
          </span>
        </div>
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
            onClick={() => onView(doc)}
            aria-label={`View ${doc.title}`}
          >
            <Eye className="text-csit-text-muted" />
          </Button>
          {downloadUrl ? (
            <a
              href={downloadUrl}
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
                onClick={() => onEdit(doc)}
                aria-label={`Edit ${doc.title}`}
              >
                <Pencil className="text-csit-text-muted" />
              </Button>
              <Button
                type="button"
                variant="ghost"
                size="icon"
                onClick={() => onToggleArchive(doc)}
                aria-label={doc.status === "archived" ? `Restore ${doc.title}` : `Archive ${doc.title}`}
              >
                <Archive className="text-csit-text-muted" />
              </Button>
              <Button
                type="button"
                variant="ghost"
                size="icon"
                onClick={() => onDelete(doc)}
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
  );
});

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
    createDocument,
    updateDocument,
    setDocumentStatus,
    deleteDocument,
    createCategory,
    updateCategory,
    deleteCategory,
  } = useDocumentsDirectory();

  const canManage = isDocumentManager(user);
  const navigate = useNavigate();

  const [formOpen, setFormOpen] = useState(false);
  const [editingDocument, setEditingDocument] = useState(null);
  const [deleteTarget, setDeleteTarget] = useState(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState(null);
  const [isDeleting, setIsDeleting] = useState(false);
  const [deleteError, setDeleteError] = useState(null);
  const [archiveError, setArchiveError] = useState(null);
  const [categoryManagerOpen, setCategoryManagerOpen] = useState(false);

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

  const total = pagination?.total ?? 0;
  const lastPage = pagination?.last_page ?? 1;
  const from = pagination?.from ?? 0;
  const to = pagination?.to ?? 0;

  const downloadUrl = (doc) => doc.latest_version?.file_url ?? null;

  return (
    <PermissionGate permission="documents.view">
      <div className="flex flex-col gap-6">
        <div className="flex flex-wrap items-end justify-between gap-4">
          <div>
            <h1 className="text-2xl font-semibold text-csit-text">Documents</h1>
            <p className="mt-1 text-sm text-csit-text-muted">
              Society records, reports, and official files
            </p>
          </div>
          {canManage ? (
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
          ) : null}
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
                    <DocumentRow
                      key={doc.id}
                      doc={doc}
                      canManage={canManage}
                      onView={(d) => navigate(`/app/documents/${d.id}`)}
                      onEdit={(d) => {
                        setEditingDocument(d);
                        setFormOpen(true);
                      }}
                      onToggleArchive={handleToggleArchive}
                      onDelete={(d) => setDeleteTarget(d)}
                    />
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

        <DocumentFormDialog
          open={formOpen}
          onOpenChange={setFormOpen}
          document={editingDocument}
          categories={categories}
          onSubmit={handleSubmit}
          isSubmitting={isSubmitting}
          error={submitError}
        />

        {archiveError && (
          <div className="rounded-lg border border-rose-200 bg-rose-50/50 px-3 py-2 text-xs text-rose-600">
            {archiveError}
          </div>
        )}

        <CategoryManagerDialog
          open={categoryManagerOpen}
          onOpenChange={setCategoryManagerOpen}
          categories={categories}
          onCreate={createCategory}
          onUpdate={updateCategory}
          onDelete={deleteCategory}
        />

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
      </div>
    </PermissionGate>
  );
}
