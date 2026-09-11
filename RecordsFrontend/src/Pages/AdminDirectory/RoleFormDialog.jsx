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

export default function RoleFormDialog({
  open,
  onOpenChange,
  role,
  onSubmit,
  isSubmitting,
  error,
  fieldErrors = {},
  onFieldChange,
  fieldProps = () => ({}),
}) {
  const isEdit = Boolean(role);
  const [name, setName] = useState("");

  useEffect(() => {
    if (!open) return;
    setName(role?.name ?? "");
  }, [open, role]);

  const handleSubmit = (event) => {
    event.preventDefault();
    onSubmit({ name: name.trim() });
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
              onChange={(event) => {
                setName(event.target.value);
                onFieldChange?.("name");
              }}
              placeholder="e.g. moderator"
              {...fieldProps("name")}
            />
            {fieldErrors?.name?.length > 0 && (
              <span id="name-error" className="text-xs text-destructive" role="alert">
                {fieldErrors.name[0]}
              </span>
            )}
          </label>
        </form>

        {error && (
          <div className="rounded-lg border border-rose-200 bg-rose-50/50 px-3 py-2 text-xs text-rose-600">
            {error}
          </div>
        )}

        <DialogFooter>
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={isSubmitting}>
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