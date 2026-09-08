import { useState } from "react";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { extractErrorMessage } from "@/lib/errors";

export default function DeleteRoleDialog({
  open,
  onOpenChange,
  role,
  onDelete,
  isSubmitting,
}) {
  const [error, setError] = useState(null);

  const handleDelete = async () => {
    setError(null);
    try {
      await onDelete(role.id);
      onOpenChange(false);
    } catch (err) {
      setError(extractErrorMessage(err));
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Delete role</DialogTitle>
          <DialogDescription>
            Are you sure you want to delete{" "}
            <span className="font-medium text-foreground">{role?.name}</span>?
            {role?.users_count > 0 ? ` This role is assigned to ${role.users_count} user(s).` : ""}
            {" "}This action cannot be undone.
          </DialogDescription>
        </DialogHeader>

        {error && (
          <div className="rounded-lg border border-rose-200 bg-rose-50/50 px-3 py-2 text-xs text-rose-600">
            {error}
          </div>
        )}

        <DialogFooter>
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={isSubmitting}>
            Cancel
          </Button>
          <Button type="button" variant="destructive" onClick={handleDelete} disabled={isSubmitting}>
            {isSubmitting ? "Deleting…" : "Delete role"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
