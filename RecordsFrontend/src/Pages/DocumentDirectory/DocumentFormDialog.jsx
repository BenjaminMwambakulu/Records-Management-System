import { useEffect, useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Switch } from "@/components/ui/switch";
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
  fieldErrors,
  onFieldChange,
  fieldProps,
}) {
  const isEdit = Boolean(doc);

  const [form, setForm] = useState({
    title: "",
    category_id: "",
    status: "active",
    is_public: false,
    file: null,
  });

  useEffect(() => {
    if (!open) return;
    setForm({
      title: doc?.title ?? "",
      category_id: doc?.category?.id != null ? String(doc.category.id) : "",
      status: doc?.status ?? "active",
      is_public: doc?.is_public ?? false,
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
      is_public: form.is_public,
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
              onChange={(event) => {
                updateField("title", event.target.value);
                onFieldChange?.("title");
              }}
              {...fieldProps("title")}
              placeholder="e.g. Executive Committee Report 2026"
            />
            {fieldErrors?.title?.length > 0 && (
              <span id="title-error" className="text-xs text-destructive" role="alert">{fieldErrors.title[0]}</span>
            )}
          </label>

          {!isEdit ? (
            <label className="flex flex-col gap-1.5 text-xs font-medium text-csit-text-muted">
              File
              <Input
                required
                type="file"
                onChange={(event) => {
                  updateField("file", event.target.files?.[0] ?? null);
                  onFieldChange?.("file");
                }}
                {...fieldProps("file")}
              />
              {fieldErrors?.file?.length > 0 && (
                <span id="file-error" className="text-xs text-destructive" role="alert">{fieldErrors.file[0]}</span>
              )}
            </label>
          ) : null}

          <div className="grid grid-cols-2 gap-3">
            <label className="flex flex-col gap-1.5 text-xs font-medium text-csit-text-muted">
              Category
              <select
                value={form.category_id}
                onChange={(event) => {
                  updateField("category_id", event.target.value);
                  onFieldChange?.("category_id");
                }}
                {...fieldProps("category_id")}
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
                onChange={(event) => {
                  updateField("status", event.target.value);
                  onFieldChange?.("status");
                }}
                {...fieldProps("status")}
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

          <label className="flex items-center justify-between gap-3 rounded-lg border border-csit-border bg-white px-3 py-2.5 text-xs font-medium text-csit-text">
            Make document public
            <Switch
              checked={form.is_public}
              onCheckedChange={(value) => updateField("is_public", value)}
            />
          </label>
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