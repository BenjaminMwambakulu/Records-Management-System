import React, { useEffect, useState } from "react";
import {
  ChevronLeft,
  ChevronRight,
  Eye,
  Package,
  Pencil,
  Plus,
  Search,
  Trash2,
  ArrowDownToLine,
  ArrowUpFromLine,
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
import SummaryCard from "@/components/ui/SummaryCard";
import { useAuth } from "@/Context/AuthContext";
import { isAssetManager } from "@/lib/roles";
import { api } from "@/APIClients/APIClient";
import useAssetsDirectory from "@/hooks/useAssetsDirectory";
import AssetFormDialog, { extractErrorMessage } from "./AssetFormDialog";
import PermissionGate from "@/components/PermissionGate";

function formatDate(value) {
  if (!value) return "—";
  return new Date(value).toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
}

function statusColor(status) {
  const colors = {
    available: "bg-emerald-50 text-emerald-700",
    borrowed: "bg-amber-50 text-amber-700",
    maintenance: "bg-slate-100 text-slate-600",
    retired: "bg-rose-50 text-rose-600",
  };
  return colors[status] ?? "bg-slate-100 text-slate-600";
}

function TableSkeleton() {
  return (
    <TableBody>
      {Array.from({ length: 6 }).map((_, index) => (
        <TableRow key={index}>
          <TableCell><Skeleton className="h-3 w-44" /></TableCell>
          <TableCell><Skeleton className="h-3 w-28" /></TableCell>
          <TableCell><Skeleton className="h-3 w-24" /></TableCell>
          <TableCell><Skeleton className="h-3 w-20" /></TableCell>
          <TableCell><Skeleton className="h-3 w-16" /></TableCell>
          <TableCell><Skeleton className="h-5 w-20" /></TableCell>
          <TableCell><Skeleton className="h-5 w-16" /></TableCell>
        </TableRow>
      ))}
    </TableBody>
  );
}

const AssetRow = React.memo(function AssetRow({ asset, canManage, onCheckout, onReturn, onEdit, onDelete }) {
  return (
    <TableRow>
      <TableCell className="px-4">
        <div className="flex items-center gap-2.5">
          <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-csit-primary/10 text-csit-primary">
            <Package className="size-4" />
          </span>
          <span className="font-medium text-csit-text">{asset.name}</span>
        </div>
      </TableCell>
      <TableCell className="text-csit-text-muted">
        {asset.serial_number ?? "—"}
      </TableCell>
      <TableCell className="text-csit-text-muted">
        {asset.category}
      </TableCell>
      <TableCell className="text-csit-text-muted">
        {asset.active_loan?.borrower?.full_name ?? "—"}
      </TableCell>
      <TableCell className="text-csit-text-muted">
        {formatDate(asset.active_loan?.due_date)}
      </TableCell>
      <TableCell>
        <span
          className={`rounded-md px-1.5 py-0.5 text-xs font-medium ${statusColor(asset.status)} `}
        >
          {asset.status}
        </span>
      </TableCell>
      <TableCell className="px-4">
        <div className="flex items-center justify-end gap-1">
          {asset.status === "available" && canManage ? (
            <Button
              type="button"
              variant="ghost"
              size="icon"
              onClick={() => onCheckout(asset)}
              aria-label={`Check out ${asset.name}`}
              className="hover:bg-emerald-50 hover:text-emerald-600"
              title="Check out"
            >
              <ArrowUpFromLine className="text-csit-text-muted" />
            </Button>
          ) : null}
          {asset.status === "borrowed" && canManage ? (
            <Button
              type="button"
              variant="ghost"
              size="icon"
              onClick={() => onReturn(asset)}
              aria-label={`Return ${asset.name}`}
              className="hover:bg-emerald-50 hover:text-emerald-600"
              title="Return"
            >
              <ArrowDownToLine className="text-csit-text-muted" />
            </Button>
          ) : null}
          {canManage ? (
            <>
              <Button
                type="button"
                variant="ghost"
                size="icon"
                onClick={() => onEdit(asset)}
                aria-label={`Edit ${asset.name}`}
              >
                <Pencil className="text-csit-text-muted" />
              </Button>
              <Button
                type="button"
                variant="ghost"
                size="icon"
                onClick={() => onDelete(asset)}
                aria-label={`Delete ${asset.name}`}
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

export default function AssetsIndex() {
  const { user } = useAuth();
  const canManage = isAssetManager(user);

  const {
    assets,
    pagination,
    isLoading,
    error,
    search,
    setSearch,
    status,
    setStatus,
    category,
    setCategory,
    categories,
    page,
    setPage,
    summary,
    refetch,
    createAsset,
    updateAsset,
    deleteAsset,
    checkoutAsset,
    returnAsset,
  } = useAssetsDirectory();

  const [formOpen, setFormOpen] = useState(false);
  const [editingAsset, setEditingAsset] = useState(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState(null);

  const [deleteTarget, setDeleteTarget] = useState(null);
  const [isDeleting, setIsDeleting] = useState(false);
  const [deleteError, setDeleteError] = useState(null);

  const [returnTarget, setReturnTarget] = useState(null);
  const [isReturning, setIsReturning] = useState(false);
  const [returnError, setReturnError] = useState(null);

  const [checkoutTarget, setCheckoutTarget] = useState(null);
  const [isCheckingOut, setIsCheckingOut] = useState(false);
  const [checkoutError, setCheckoutError] = useState(null);
  const [borrowerId, setBorrowerId] = useState("");
  const [dueDate, setDueDate] = useState("");
  const [members, setMembers] = useState([]);

  const openCreate = () => {
    setEditingAsset(null);
    setSubmitError(null);
    setFormOpen(true);
  };

  const openEdit = (asset) => {
    setEditingAsset(asset);
    setSubmitError(null);
    setFormOpen(true);
  };

  const handleSubmit = async (data) => {
    setIsSubmitting(true);
    setSubmitError(null);
    try {
      if (editingAsset) {
        await updateAsset(editingAsset.id, data);
      } else {
        await createAsset(data);
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
      await deleteAsset(deleteTarget.id);
      setDeleteTarget(null);
    } catch (err) {
      setDeleteError(extractErrorMessage(err));
    } finally {
      setIsDeleting(false);
    }
  };

  const handleReturn = async () => {
    setIsReturning(true);
    setReturnError(null);
    try {
      await returnAsset(returnTarget.id);
      setReturnTarget(null);
    } catch (err) {
      setReturnError(extractErrorMessage(err));
    } finally {
      setIsReturning(false);
    }
  };

  useEffect(() => {
    if (!checkoutTarget) return;
    const fetchMembers = async () => {
      try {
        const response = await api.get("/v1/members");
        const data = response?.data;
        setMembers(Array.isArray(data) ? data : data?.data ?? []);
      } catch {
        setMembers([]);
      }
    };
    fetchMembers();
  }, [checkoutTarget]);

  const handleCheckout = async () => {
    setIsCheckingOut(true);
    setCheckoutError(null);
    try {
      await checkoutAsset(checkoutTarget.id, {
        borrower_id: Number(borrowerId),
        due_date: new Date(dueDate).toISOString(),
      });
      setCheckoutTarget(null);
      setBorrowerId("");
      setDueDate("");
    } catch (err) {
      setCheckoutError(extractErrorMessage(err));
    } finally {
      setIsCheckingOut(false);
    }
  };

  const total = pagination?.total ?? 0;
  const lastPage = pagination?.last_page ?? 1;
  const from = pagination?.from ?? 0;
  const to = pagination?.to ?? 0;

  const availableCount = summary?.available ?? 0;
  const borrowedCount = summary?.borrowed ?? 0;
  const maintenanceCount = summary?.maintenance ?? 0;
  const retiredCount = summary?.retired ?? 0;

  return (
    <PermissionGate permission="assets.view">
      <div className="flex flex-col gap-6">
        {/* Header */}
        <div className="flex flex-wrap items-end justify-between gap-4">
          <div>
            <h1 className="text-2xl font-semibold text-csit-text">Assets</h1>
            <p className="mt-1 text-sm text-csit-text-muted">
              Manage society assets and equipment
            </p>
          </div>
          {canManage ? (
            <Button onClick={openCreate}>
              <Plus />
              Add asset
            </Button>
          ) : null}
        </div>

        {/* Summary Cards */}
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <SummaryCard
            title="Available"
            value={availableCount}
            icon={<Package className="size-5" />}
            variant="success"
          />
          <SummaryCard
            title="Borrowed"
            value={borrowedCount}
            icon={<ArrowUpFromLine className="size-5" />}
            variant="warning"
          />
          <SummaryCard
            title="Maintenance"
            value={maintenanceCount}
            icon={<ArrowDownToLine className="size-5" />}
          />
          <SummaryCard
            title="Retired"
            value={retiredCount}
            icon={<Trash2 className="size-5" />}
            variant="danger"
          />
        </div>

        {/* Table Card */}
        <Card className="border-csit-border bg-white">
          {/* Filters */}
          <div className="flex flex-col gap-3 border-b border-csit-border p-4 sm:flex-row sm:items-center">
            <div className="relative flex-1">
              <Search
                size={16}
                className="pointer-events-none absolute top-1/2 left-2.5 -translate-y-1/2 text-csit-text-muted"
              />
              <Input
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                placeholder="Search by name, serial number, or category…"
                className="pl-8"
              />
            </div>
            <select
              value={status}
              onChange={(event) => setStatus(event.target.value)}
              className="h-8 rounded-lg border border-input bg-transparent px-2.5 text-sm outline-none transition-colors focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
              aria-label="Filter by status"
            >
              <option value="">All statuses</option>
              <option value="available">Available</option>
              <option value="borrowed">Borrowed</option>
              <option value="maintenance">Maintenance</option>
              <option value="retired">Retired</option>
            </select>
            {categories.length > 0 && (
              <select
                value={category}
                onChange={(event) => setCategory(event.target.value)}
                className="h-8 rounded-lg border border-input bg-transparent px-2.5 text-sm outline-none transition-colors focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                aria-label="Filter by category"
              >
                <option value="">All categories</option>
                {categories.map((cat) => (
                  <option key={cat} value={cat}>
                    {cat}
                  </option>
                ))}
              </select>
            )}
          </div>

          {/* Error */}
          {error ? (
            <div className="rounded-2xl border border-rose-200 bg-rose-50/50 p-6 text-sm text-rose-600">
              <div>Failed to load assets. Please try again.</div>
              <Button
                type="button"
                variant="outline"
                onClick={refetch}
                className="mt-3 border-rose-300 text-rose-600 hover:bg-rose-100"
              >
                Retry
              </Button>
            </div>
          ) : assets.length === 0 && !isLoading ? (
            <Empty className="border-0 py-16">
              <EmptyHeader>
                <EmptyMedia variant="icon">
                  <Package />
                </EmptyMedia>
                <EmptyTitle>No assets found</EmptyTitle>
                <EmptyDescription>
                  {search || status || category
                    ? "Try adjusting your search or filters."
                    : canManage
                      ? "Get started by adding your first asset."
                      : "No assets have been added yet."}
                </EmptyDescription>
              </EmptyHeader>
            </Empty>
          ) : (
            <Table>
              <TableHeader>
                <TableRow className="hover:bg-transparent">
                  <TableHead className="px-4">Name</TableHead>
                  <TableHead>Serial Number</TableHead>
                  <TableHead>Category</TableHead>
                  <TableHead>Borrower</TableHead>
                  <TableHead>Due Date</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead className="px-4 text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              {isLoading ? (
                <TableSkeleton />
              ) : (
                <TableBody>
                  {assets.map((asset) => (
                    <AssetRow
                      key={asset.id}
                      asset={asset}
                      canManage={canManage}
                      onCheckout={(a) => {
                        setCheckoutError(null);
                        setBorrowerId("");
                        const tomorrow = new Date();
                        tomorrow.setDate(tomorrow.getDate() + 7);
                        setDueDate(tomorrow.toISOString().split("T")[0]);
                        setCheckoutTarget(a);
                      }}
                      onReturn={(a) => {
                        setReturnError(null);
                        setReturnTarget(a);
                      }}
                      onEdit={openEdit}
                      onDelete={(a) => {
                        setDeleteError(null);
                        setDeleteTarget(a);
                      }}
                    />
                  ))}
                </TableBody>
              )}
            </Table>
          )}

          {/* Pagination */}
          {!error && (
            <div className="flex items-center justify-between gap-4 border-t border-csit-border px-4 py-3">
              <p className="text-xs text-csit-text-muted">
                {total === 0
                  ? "No results"
                  : `Showing ${from}–${to} of ${total} assets`}
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

        {/* Create / Edit Dialog */}
        <AssetFormDialog
          open={formOpen}
          onOpenChange={setFormOpen}
          asset={editingAsset}
          onSubmit={handleSubmit}
          isSubmitting={isSubmitting}
          error={submitError}
        />

        {/* Delete Confirmation Dialog */}
        <Dialog
          open={Boolean(deleteTarget)}
          onOpenChange={(open) => {
            if (!open) setDeleteTarget(null);
          }}
        >
          <DialogContent className="sm:max-w-md">
            <DialogHeader>
              <DialogTitle>Retire asset</DialogTitle>
              <DialogDescription>
                Are you sure you want to retire{" "}
                <span className="font-medium text-foreground">
                  {deleteTarget?.name}
                </span>
                ? This will mark the asset as retired.
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
                {isDeleting ? "Retiring…" : "Retire asset"}
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>

        {/* Return Confirmation Dialog */}
        <Dialog
          open={Boolean(returnTarget)}
          onOpenChange={(open) => {
            if (!open) setReturnTarget(null);
          }}
        >
          <DialogContent className="sm:max-w-md">
            <DialogHeader>
              <DialogTitle>Return asset</DialogTitle>
              <DialogDescription>
                Mark{" "}
                <span className="font-medium text-foreground">
                  {returnTarget?.name}
                </span>{" "}
                as returned and available for checkout.
              </DialogDescription>
            </DialogHeader>

            {returnError && (
              <div className="rounded-lg border border-rose-200 bg-rose-50/50 px-3 py-2 text-xs text-rose-600">
                {returnError}
              </div>
            )}

            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                onClick={() => setReturnTarget(null)}
                disabled={isReturning}
              >
                Cancel
              </Button>
              <Button
                type="button"
                onClick={handleReturn}
                disabled={isReturning}
              >
                {isReturning ? "Returning…" : "Confirm return"}
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>

        {/* Checkout Dialog */}
        <Dialog
          open={Boolean(checkoutTarget)}
          onOpenChange={(open) => {
            if (!open) setCheckoutTarget(null);
          }}
        >
          <DialogContent className="sm:max-w-md">
            <DialogHeader>
              <DialogTitle>Check out asset</DialogTitle>
              <DialogDescription>
                Check out{" "}
                <span className="font-medium text-foreground">
                  {checkoutTarget?.name}
                </span>{" "}
                to a borrower.
              </DialogDescription>
            </DialogHeader>

            <div className="flex flex-col gap-3">
              <label className="flex flex-col gap-1.5 text-xs font-medium text-csit-text-muted">
                Borrower
                <select
                  value={borrowerId}
                  onChange={(event) => setBorrowerId(event.target.value)}
                  className="h-8 rounded-lg border border-input bg-transparent px-2.5 text-sm outline-none transition-colors focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                >
                  <option value="">Select a member</option>
                  {members.map((member) => (
                    <option key={member.id} value={member.id}>
                      {member.full_name ?? member.name ?? `Member #${member.id}`}
                    </option>
                  ))}
                </select>
              </label>
              <label className="flex flex-col gap-1.5 text-xs font-medium text-csit-text-muted">
                Due date
                <Input
                  type="date"
                  value={dueDate}
                  onChange={(event) => setDueDate(event.target.value)}
                />
              </label>
            </div>

            {checkoutError && (
              <div className="rounded-lg border border-rose-200 bg-rose-50/50 px-3 py-2 text-xs text-rose-600">
                {checkoutError}
              </div>
            )}

            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                onClick={() => setCheckoutTarget(null)}
                disabled={isCheckingOut}
              >
                Cancel
              </Button>
              <Button
                type="button"
                onClick={handleCheckout}
                disabled={isCheckingOut || !borrowerId || !dueDate}
              >
                {isCheckingOut ? "Checking out…" : "Confirm checkout"}
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </div>
    </PermissionGate>
  );
}
