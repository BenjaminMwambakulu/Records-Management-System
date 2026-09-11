import { useEffect, useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";

const STATUS_OPTIONS = [
  { value: "available", label: "Available" },
  { value: "borrowed", label: "Borrowed" },
  { value: "maintenance", label: "Maintenance" },
  { value: "retired", label: "Retired" },
];

const CATEGORY_OPTIONS = [
  "Equipment",
  "Furniture",
  "Electronics",
  "Supplies",
  "Other",
];

export default function AssetFormDialog({
  open,
  onOpenChange,
  asset,
  onSubmit,
  isSubmitting,
  error,
  fieldErrors,
  onFieldChange,
  fieldProps,
}) {
  const isEdit = Boolean(asset);

  const [form, setForm] = useState({
    name: "",
    serial_number: "",
    category: "",
    status: "available",
    notes: "",
  });

  useEffect(() => {
    if (!open) return;
    setForm({
      name: asset?.name ?? "",
      serial_number: asset?.serial_number ?? "",
      category: asset?.category ?? "",
      status: asset?.status ?? "available",
      notes: asset?.notes ?? "",
    });
  }, [open, asset]);

  const updateField = (field, value) => {
    setForm((current) => ({ ...current, [field]: value }));
    onFieldChange?.(field);
  };

  const handleSubmit = (event) => {
    event.preventDefault();
    const payload = {
      name: form.name.trim(),
      serial_number: form.serial_number.trim() || null,
      category: form.category.trim(),
      status: form.status,
      notes: form.notes.trim() || null,
    };
    onSubmit(payload);
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>{isEdit ? "Edit asset" : "Add asset"}</DialogTitle>
          <DialogDescription>
            {isEdit
              ? "Update the asset details below."
              : "Create a new asset record."}
          </DialogDescription>
        </DialogHeader>

        <form id="asset-form" onSubmit={handleSubmit} className="flex flex-col gap-3">
          <label className="flex flex-col gap-1.5 text-xs font-medium text-csit-text-muted">
            Name
            <Input
              id="name"
              required
              value={form.name}
              {...fieldProps("name")}
              onChange={(event) => updateField("name", event.target.value)}
              placeholder="e.g. Projector Epson EB-X41"
            />
            {fieldErrors?.name?.length > 0 && (
              <span id="name-error" className="text-xs text-destructive" role="alert">
                {fieldErrors.name[0]}
              </span>
            )}
          </label>

          <label className="flex flex-col gap-1.5 text-xs font-medium text-csit-text-muted">
            Serial Number
            <Input
              id="serial_number"
              value={form.serial_number}
              {...fieldProps("serial_number")}
              onChange={(event) => updateField("serial_number", event.target.value)}
              placeholder="e.g. PR-0041"
            />
            {fieldErrors?.serial_number?.length > 0 && (
              <span id="serial_number-error" className="text-xs text-destructive" role="alert">
                {fieldErrors.serial_number[0]}
              </span>
            )}
          </label>

          <label className="flex flex-col gap-1.5 text-xs font-medium text-csit-text-muted">
            Category
            <select
              id="category"
              value={form.category}
              {...fieldProps("category")}
              onChange={(event) => updateField("category", event.target.value)}
              required
              className="h-8 rounded-lg border border-input bg-transparent px-2.5 text-sm outline-none transition-colors focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
            >
              <option value="">Select category</option>
              {CATEGORY_OPTIONS.map((cat) => (
                <option key={cat} value={cat}>
                  {cat}
                </option>
              ))}
            </select>
            {fieldErrors?.category?.length > 0 && (
              <span id="category-error" className="text-xs text-destructive" role="alert">
                {fieldErrors.category[0]}
              </span>
            )}
          </label>

          <label className="flex flex-col gap-1.5 text-xs font-medium text-csit-text-muted">
            Status
            <select
              id="status"
              value={form.status}
              {...fieldProps("status")}
              onChange={(event) => updateField("status", event.target.value)}
              className="h-8 rounded-lg border border-input bg-transparent px-2.5 text-sm outline-none transition-colors focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
            >
              {STATUS_OPTIONS.map((opt) => (
                <option key={opt.value} value={opt.value}>
                  {opt.label}
                </option>
              ))}
            </select>
            {fieldErrors?.status?.length > 0 && (
              <span id="status-error" className="text-xs text-destructive" role="alert">
                {fieldErrors.status[0]}
              </span>
            )}
          </label>

          <label className="flex flex-col gap-1.5 text-xs font-medium text-csit-text-muted">
            Notes
            <Textarea
              id="notes"
              rows={3}
              value={form.notes}
              {...fieldProps("notes")}
              onChange={(event) => updateField("notes", event.target.value)}
              placeholder="Additional notes about this asset..."
            />
            {fieldErrors?.notes?.length > 0 && (
              <span id="notes-error" className="text-xs text-destructive" role="alert">
                {fieldErrors.notes[0]}
              </span>
            )}
          </label>
        </form>

        {error && (
          <div
            role="alert"
            className="rounded-lg border border-rose-200 bg-rose-50/50 px-3 py-2 text-xs text-rose-600"
          >
            {error}
          </div>
        )}

        <DialogFooter>
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button type="submit" form="asset-form" disabled={isSubmitting}>
            {isSubmitting
              ? isEdit
                ? "Saving…"
                : "Creating…"
              : isEdit
                ? "Save changes"
                : "Create asset"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}