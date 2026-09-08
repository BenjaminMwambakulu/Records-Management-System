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
import { extractErrorMessage } from "@/lib/errors";

export default function RoleFormDialog({
  open,
  onOpenChange,
  role,
  onSubmit,
  isSubmitting,
}) {
  const isEdit = Boolean(role);
  const [name, setName] = useState("");
  const [error, setError] = useState(null);

  useEffect(() => {
    if (!open) return;
    setName(role?.name ?? "");
    setError(null);
  }, [open, role]);

  const handleSubmit = async (event) => {
    event.preventDefault();
    setError(null);
    try {
      await onSubmit({ name: name.trim() });
      onOpenChange(false);
    } catch (err) {
      setError(extractErrorMessage(err));
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{isEdit ? "Edit role" : "Create role"}</DialogTitle>
          <DialogDescription>
            {isEdit ? "Update the role name." : "Add a new role to the system."}
          </DialogDescription>
        </DialogHeader>

        <form id="role-form" onSubmit={handleSubmit} className="flex flex-col gap-3">
          <label className="flex flex-col gap-1.5 text-xs font-medium text-csit-text-muted">
            Name
            <Input
              required
              value={name}
              onChange={(event) => setName(event.target.value)}
              placeholder="e.g. moderator"
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
          <Button type="submit" form="role-form" disabled={isSubmitting}>
            {isSubmitting ? (isEdit ? "Saving…" : "Creating…") : isEdit ? "Save changes" : "Create role"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
