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

const TYPE_OPTIONS = [
  { value: "income", label: "Income" },
  { value: "expense", label: "Expense" },
];

export default function FinancialRecordFormDialog({
  open,
  onOpenChange,
  record,
  categories,
  onSubmit,
  isSubmitting,
  error,
}) {
  const isEdit = Boolean(record);

  const [form, setForm] = useState({
    title: "",
    type: "income",
    category_id: "",
    amount: "",
    transaction_date: "",
  });

  useEffect(() => {
    if (!open) return;
    setForm({
      title: record?.title ?? "",
      type: record?.type ?? "income",
      category_id: record?.category?.id != null ? String(record.category.id) : "",
      amount: record?.amount ?? "",
      transaction_date: record?.transaction_date ?? "",
    });
  }, [open, record]);

  const updateField = (field, value) => {
    setForm((current) => ({ ...current, [field]: value }));
  };

  const handleSubmit = (event) => {
    event.preventDefault();
    onSubmit({
      title: form.title.trim(),
      type: form.type,
      category_id: form.category_id ? Number(form.category_id) : null,
      amount: form.amount,
      transaction_date: form.transaction_date,
    });
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{isEdit ? "Edit record" : "Add financial record"}</DialogTitle>
          <DialogDescription>
            {isEdit ? "Update the record's details below." : "Record an income or expense."}
          </DialogDescription>
        </DialogHeader>

        <form id="financial-record-form" onSubmit={handleSubmit} className="flex flex-col gap-3">
          <label className="flex flex-col gap-1.5 text-xs font-medium text-csit-text-muted">
            Title
            <Input
              required
              value={form.title}
              onChange={(event) => updateField("title", event.target.value)}
              placeholder="e.g. Membership fees"
            />
          </label>

          <div className="grid grid-cols-2 gap-3">
            <label className="flex flex-col gap-1.5 text-xs font-medium text-csit-text-muted">
              Type
              <select
                value={form.type}
                onChange={(event) => updateField("type", event.target.value)}
                className="h-9 rounded-lg border border-input bg-transparent px-2.5 text-sm outline-none transition-colors focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
              >
                {TYPE_OPTIONS.map((option) => (
                  <option key={option.value} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </select>
            </label>
            <label className="flex flex-col gap-1.5 text-xs font-medium text-csit-text-muted">
              Amount (MWK)
              <Input
                required
                type="number"
                min="0.01"
                step="0.01"
                value={form.amount}
                onChange={(event) => updateField("amount", event.target.value)}
                placeholder="0.00"
              />
            </label>
          </div>

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
              Transaction date
              <Input
                required
                type="date"
                value={form.transaction_date}
                onChange={(event) => updateField("transaction_date", event.target.value)}
              />
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
          <Button type="submit" form="financial-record-form" disabled={isSubmitting}>
            {isSubmitting ? (isEdit ? "Saving…" : "Adding…") : isEdit ? "Save changes" : "Add record"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}