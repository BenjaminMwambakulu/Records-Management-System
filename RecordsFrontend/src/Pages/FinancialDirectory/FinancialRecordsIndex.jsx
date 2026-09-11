import React, { useState } from "react";
import {
  ChevronLeft,
  ChevronRight,
  Download,
  Eye,
  Pencil,
  Plus,
  Search,
  Trash2,
  Wallet,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Card } from "@/components/ui/card";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Skeleton } from "@/components/ui/skeleton";
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import SummaryCard from "@/components/ui/SummaryCard";
import FinancialCategoryManagerDialog from "./FinancialCategoryManagerDialog";
import FinancialRecordDetailDialog from "./FinancialRecordDetailDialog";
import FinancialRecordFormDialog from "./FinancialRecordFormDialog";
import { useAuth } from "@/Context/AuthContext";
import { isFinancialManager } from "@/lib/roles";
import { formatMoney } from "@/lib/utils";
import { extractErrorMessage, isValidationError } from "@/lib/errors";
import { notify } from "@/lib/toast";
import useFinancialDirectory from "@/hooks/useFinancialDirectory";
import { useFormErrors } from "@/hooks/useFormErrors";
import PermissionGate from "@/components/PermissionGate";

function formatDate(value) {
  if (!value) return "—";
  return new Date(value).toLocaleDateString();
}

function typeStyles(type) {
  return type === "income"
    ? "bg-emerald-50 text-emerald-700"
    : "bg-rose-50 text-rose-700";
}

function TableSkeleton() {
  return (
    <TableBody>
      {Array.from({ length: 6 }).map((_, index) => (
        <TableRow key={index}>
          <TableCell><Skeleton className="h-3 w-44" /></TableCell>
          <TableCell><Skeleton className="h-3 w-24" /></TableCell>
          <TableCell><Skeleton className="h-5 w-16" /></TableCell>
          <TableCell><Skeleton className="h-3 w-20" /></TableCell>
          <TableCell><Skeleton className="h-3 w-20" /></TableCell>
          <TableCell><Skeleton className="h-5 w-16" /></TableCell>
        </TableRow>
      ))}
    </TableBody>
  );
}

const FinancialRecordRow = React.memo(function FinancialRecordRow({ record, canManage, onView, onEdit, onDelete }) {
  return (
    <TableRow>
      <TableCell className="px-4">
        <span className="font-medium text-csit-text">{record.title}</span>
      </TableCell>
      <TableCell className="text-csit-text-muted">
        {record.category?.name ?? "—"}
      </TableCell>
      <TableCell>
        <span className={`rounded-md px-1.5 py-0.5 text-xs font-medium ${typeStyles(record.type)}`}>
          {record.type}
        </span>
      </TableCell>
      <TableCell className="text-right">
        <span className={`font-medium ${record.type === "income" ? "text-emerald-600" : "text-rose-600"}`}>
          {record.type === "income" ? "+" : "−"}{formatMoney(record.amount)}
        </span>
      </TableCell>
      <TableCell className="text-csit-text-muted">
        {formatDate(record.transaction_date)}
      </TableCell>
      <TableCell className="px-4">
        <div className="flex items-center justify-end gap-1">
          <Button
            type="button"
            variant="ghost"
            size="icon"
            onClick={() => onView(record)}
            aria-label={`View ${record.title}`}
          >
            <Eye className="text-csit-text-muted" />
          </Button>
          {canManage ? (
            <>
              <Button
                type="button"
                variant="ghost"
                size="icon"
                onClick={() => onEdit(record)}
                aria-label={`Edit ${record.title}`}
              >
                <Pencil className="text-csit-text-muted" />
              </Button>
              <Button
                type="button"
                variant="ghost"
                size="icon"
                onClick={() => onDelete(record)}
                aria-label={`Delete ${record.title}`}
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

export default function FinancialRecordsIndex() {
  const { user } = useAuth();
  const canManage = isFinancialManager(user);

  const {
    records,
    pagination,
    summary,
    isLoading,
    error,
    search,
    setSearch,
    type,
    setType,
    categoryId,
    setCategoryId,
    page,
    setPage,
    categories,
    categoriesError,
    isExporting,
    refetch,
    exportCsv,
    createRecord,
    updateRecord,
    deleteRecord,
    createCategory,
    updateCategory,
    deleteCategory,
  } = useFinancialDirectory();

  const [categoryManagerOpen, setCategoryManagerOpen] = useState(false);
  const [formOpen, setFormOpen] = useState(false);
  const [editingRecord, setEditingRecord] = useState(null);
  const [deleteTarget, setDeleteTarget] = useState(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isDeleting, setIsDeleting] = useState(false);
  const [deleteError, setDeleteError] = useState(null);
  const [viewingRecord, setViewingRecord] = useState(null);

  const {
    fieldErrors,
    formError,
    applyApiError,
    clear: clearFormErrors,
    clearField,
    fieldProps,
  } = useFormErrors();

  const openCreate = () => {
    clearFormErrors();
    setEditingRecord(null);
    setFormOpen(true);
  };

  const openEdit = (record) => {
    clearFormErrors();
    setEditingRecord(record);
    setFormOpen(true);
  };

  const openView = (record) => {
    setViewingRecord(record);
  };

  const handleSubmit = async (data) => {
    setIsSubmitting(true);
    clearFormErrors();
    try {
      if (editingRecord) {
        await updateRecord(editingRecord.id, data);
        notify.success("Record updated", `"${data.title}" was updated.`);
      } else {
        await createRecord(data);
        notify.success("Record added", `"${data.title}" was added.`);
      }
      setFormOpen(false);
    } catch (err) {
      applyApiError(err);
      if (!isValidationError(err)) notify.error("Save failed", extractErrorMessage(err));
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleDelete = async () => {
    setIsDeleting(true);
    setDeleteError(null);
    try {
      await deleteRecord(deleteTarget.id);
      setDeleteTarget(null);
      notify.success("Record deleted", `"${deleteTarget.title}" was removed.`);
    } catch (err) {
      const message = extractErrorMessage(err);
      setDeleteError(message);
      notify.error("Delete failed", message);
    } finally {
      setIsDeleting(false);
    }
  };

  const handleExport = async () => {
    try {
      await exportCsv();
      notify.success("Export complete", "Your CSV download should begin shortly.");
    } catch (err) {
      notify.error("Export failed", extractErrorMessage(err));
    }
  };

  const total = pagination?.total ?? 0;
  const lastPage = pagination?.last_page ?? 1;
  const from = pagination?.from ?? 0;
  const to = pagination?.to ?? 0;

  return (
    <PermissionGate permission="financials.view">
      <div className="flex flex-col gap-6">
        <div className="flex flex-wrap items-end justify-between gap-4">
          <div>
            <h1 className="text-2xl font-semibold text-csit-text">Financial Records</h1>
            <p className="mt-1 text-sm text-csit-text-muted">
              Income and expenses for the society
            </p>
          </div>
          {canManage ? (
            <div className="flex flex-wrap items-center gap-2">
              <Button
                type="button"
                variant="outline"
                onClick={() => setCategoryManagerOpen(true)}
              >
                Manage categories
              </Button>
              <Button
                type="button"
                variant="outline"
                onClick={handleExport}
                disabled={isExporting}
              >
                <Download />
                {isExporting ? "Exporting…" : "Export CSV"}
              </Button>
              <Button onClick={openCreate}>
                <Plus />
                Add record
              </Button>
            </div>
          ) : null}
        </div>

        <div className="grid gap-4 sm:grid-cols-3">
          <SummaryCard
            title="Total Income"
            value={formatMoney(summary?.total_income)}
            description="All recorded income"
            variant="success"
            icon={<Wallet />}
          />
          <SummaryCard
            title="Total Expenses"
            value={formatMoney(summary?.total_expense)}
            description="All recorded expenses"
            variant="danger"
            icon={<Wallet />}
          />
          <SummaryCard
            title="Net Balance"
            value={formatMoney(summary?.balance)}
            description="Income minus expenses"
            variant="default"
            icon={<Wallet />}
          />
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
                placeholder="Search by title…"
                className="pl-8"
              />
            </div>
            <select
              value={type}
              onChange={(event) => setType(event.target.value)}
              className="h-8 rounded-lg border border-input bg-transparent px-2.5 text-sm outline-none transition-colors focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
              aria-label="Filter by type"
            >
              <option value="">All types</option>
              <option value="income">Income</option>
              <option value="expense">Expense</option>
            </select>
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
          </div>

          {error ? (
            <div className="rounded-2xl border border-rose-200 bg-rose-50/50 p-6 text-sm text-rose-600">
              <div>Failed to load financial records. Please try again.</div>
              <Button
                type="button"
                variant="outline"
                onClick={refetch}
                className="mt-3 border-rose-300 text-rose-600 hover:bg-rose-100"
              >
                Retry
              </Button>
            </div>
          ) : records.length === 0 && !isLoading ? (
            <Empty className="border-0 py-16">
              <EmptyHeader>
                <EmptyMedia variant="icon">
                  <Wallet />
                </EmptyMedia>
                <EmptyTitle>No records found</EmptyTitle>
                <EmptyDescription>
                  {search || type || categoryId
                    ? "Try adjusting your search or filters."
                    : canManage
                      ? "Get started by adding your first record."
                      : "No financial records have been recorded yet."}
                </EmptyDescription>
              </EmptyHeader>
            </Empty>
          ) : (
            <Table>
              <TableHeader>
                <TableRow className="hover:bg-transparent">
                  <TableHead className="px-4">Title</TableHead>
                  <TableHead>Category</TableHead>
                  <TableHead>Type</TableHead>
                  <TableHead className="text-right">Amount</TableHead>
                  <TableHead>Date</TableHead>
                  <TableHead className="px-4 text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              {isLoading ? (
                <TableSkeleton />
              ) : (
                <TableBody>
                  {records.map((record) => (
                    <FinancialRecordRow
                      key={record.id}
                      record={record}
                      canManage={canManage}
                      onView={openView}
                      onEdit={openEdit}
                      onDelete={(r) => setDeleteTarget(r)}
                    />
                  ))}
                </TableBody>
              )}
            </Table>
          )}

          {!error && (
            <div className="flex items-center justify-between gap-4 border-t border-csit-border px-4 py-3">
              <p className="text-xs text-csit-text-muted">
                {total === 0 ? "No results" : `Showing ${from}–${to} of ${total} records`}
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

        <FinancialRecordFormDialog
          open={formOpen}
          onOpenChange={setFormOpen}
          record={editingRecord}
          categories={categories}
          onSubmit={handleSubmit}
          isSubmitting={isSubmitting}
          error={formError}
          fieldErrors={fieldErrors}
          onFieldChange={clearField}
          fieldProps={fieldProps}
        />

        <FinancialRecordDetailDialog
          open={Boolean(viewingRecord)}
          onOpenChange={(open) => {
            if (!open) setViewingRecord(null);
          }}
          record={viewingRecord}
        />

        <Dialog
          open={Boolean(deleteTarget)}
          onOpenChange={(open) => {
            if (!open) setDeleteTarget(null);
          }}
        >
          <DialogContent className="sm:max-w-md">
            <DialogHeader>
              <DialogTitle>Delete record</DialogTitle>
              <DialogDescription>
                Are you sure you want to delete{" "}
                <span className="font-medium text-foreground">
                  {deleteTarget?.title}
                </span>
                ? This action cannot be undone.
              </DialogDescription>
            </DialogHeader>

            {deleteError && (
              <div
                role="alert"
                className="rounded-lg border border-rose-200 bg-rose-50/50 px-3 py-2 text-xs text-rose-600"
              >
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
                {isDeleting ? "Deleting…" : "Delete record"}
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>

        <FinancialCategoryManagerDialog
          open={categoryManagerOpen}
          onOpenChange={setCategoryManagerOpen}
          categories={categories}
          fetchError={categoriesError}
          onCreate={createCategory}
          onUpdate={updateCategory}
          onDelete={deleteCategory}
        />
      </div>
    </PermissionGate>
  );
}
