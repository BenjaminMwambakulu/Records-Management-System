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

export default function FinancialCategoryManagerDialog({
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
        setEditing(null);
        setForm(emptyForm);
      } else {
        await onCreate(payload);
        setForm(emptyForm);
      }
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setIsSubmitting(false);
    }
  };

  const confirmDelete = async () => {
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
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>Financial categories</DialogTitle>
          <DialogDescription>
            {confirming
              ? `Delete "${confirming.name}"? Records using it will become uncategorized.`
              : "Organize records into categories."}
          </DialogDescription>
        </DialogHeader>

        {error && (
          <div className="rounded-lg border border-rose-200 bg-rose-50/50 px-3 py-2 text-xs text-rose-600">
            {error}
          </div>
        )}

        {confirming ? (
          <DialogFooter>
            <Button
              type="button"
              variant="outline"
              onClick={() => setConfirming(null)}
              disabled={isSubmitting}
            >
              Cancel
            </Button>
            <Button
              type="button"
              variant="destructive"
              onClick={confirmDelete}
              disabled={isSubmitting}
            >
              {isSubmitting ? "Deleting…" : "Delete category"}
            </Button>
          </DialogFooter>
        ) : (
          <>
            <form onSubmit={submitForm} className="flex flex-col gap-3 border-b border-csit-border pb-4">
              <label className="flex flex-col gap-1.5 text-xs font-medium text-csit-text-muted">
                Name
                <Input
                  required
                  value={form.name}
                  onChange={(event) => setForm((current) => ({ ...current, name: event.target.value }))}
                  placeholder="e.g. Membership Fees"
                />
              </label>
              <label className="flex flex-col gap-1.5 text-xs font-medium text-csit-text-muted">
                Description
                <Input
                  value={form.description}
                  onChange={(event) => setForm((current) => ({ ...current, description: event.target.value }))}
                  placeholder="Optional"
                />
              </label>
              <div className="flex justify-end">
                <Button type="submit" size="sm" disabled={isSubmitting}>
                  <Plus />
                  {isSubmitting ? "Saving…" : editing ? "Save changes" : "Add category"}
                </Button>
              </div>
            </form>

            <ul className="flex flex-col gap-2 pt-2">
              {categories.length === 0 ? (
                <li className="py-4 text-center text-sm text-csit-text-muted">
                  No categories yet. Add one above.
                </li>
              ) : (
                categories.map((category) => (
                  <li
                    key={category.id}
                    className="flex items-center justify-between gap-3 rounded-lg border border-csit-border px-3 py-2 text-sm"
                  >
                    <div className="min-w-0">
                      <p className="font-medium text-csit-text">{category.name}</p>
                      {category.description ? (
                        <p className="truncate text-xs text-csit-text-muted">{category.description}</p>
                      ) : null}
                    </div>
                    <div className="flex shrink-0 items-center gap-1">
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
                ))
              )}
            </ul>
          </>
        )}
      </DialogContent>
    </Dialog>
  );
}
