import { useEffect, useState } from "react";
import { Loader2 } from "lucide-react";
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
import { api } from "@/APIClients/APIClient";
import { extractErrorMessage } from "@/lib/errors";

export default function AssignUserDialog({
  open,
  onOpenChange,
  onAssign,
  isSubmitting,
  error,
  fieldErrors = {},
  onFieldChange,
  fieldProps = () => ({}),
}) {
  const [search, setSearch] = useState("");
  const [users, setUsers] = useState([]);
  const [isSearching, setIsSearching] = useState(false);
  const [searchError, setSearchError] = useState(null);

  useEffect(() => {
    if (!open) {
      setSearch("");
      setUsers([]);
      setSearchError(null);
      return;
    }
    const timer = setTimeout(() => {
      if (!search.trim()) { setUsers([]); return; }
      setIsSearching(true);
      api.get(`/v1/members?search=${encodeURIComponent(search.trim())}&per_page=10`)
        .then((response) => {
          const body = response.data;
          setUsers(Array.isArray(body) ? body : body?.data ?? []);
        })
        .catch((err) => setSearchError(extractErrorMessage(err)))
        .finally(() => setIsSearching(false));
    }, 400);
    return () => clearTimeout(timer);
  }, [search, open]);

  const handleAssign = (userId) => {
    onAssign(userId);
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Assign role to user</DialogTitle>
          <DialogDescription>Search for a user to assign this role to.</DialogDescription>
        </DialogHeader>

        <label className="flex flex-col gap-1.5 text-xs font-medium text-csit-text-muted">
          Search users
          <Input
            disabled={isSubmitting}
            value={search}
            onChange={(event) => {
              setSearch(event.target.value);
              onFieldChange?.("user");
            }}
            placeholder="Search by name or email…"
            {...fieldProps("user")}
          />
          {fieldErrors?.user?.length > 0 && (
            <span id="user-error" className="text-xs text-destructive" role="alert">
              {fieldErrors.user[0]}
            </span>
          )}
        </label>

        {searchError && (
          <div className="rounded-lg border border-rose-200 bg-rose-50/50 px-3 py-2 text-xs text-rose-600">
            {searchError}
          </div>
        )}

        {error && (
          <div className="rounded-lg border border-rose-200 bg-rose-50/50 px-3 py-2 text-xs text-rose-600">
            {error}
          </div>
        )}

        <div className="flex max-h-60 flex-col gap-1 overflow-y-auto">
          {isSearching ? (
            <p className="py-4 text-center text-sm text-csit-text-muted">Searching…</p>
          ) : users.length === 0 ? (
            <p className="py-4 text-center text-sm text-csit-text-muted">
              {search.trim() ? "No users found." : "Type to search for users."}
            </p>
          ) : (
            users.map((u) => (
              <button
                key={u.id}
                type="button"
                onClick={() => handleAssign(u.id)}
                disabled={isSubmitting}
                className="flex items-center gap-3 rounded-lg border border-csit-border px-3 py-2 text-left text-sm transition-colors hover:bg-muted/50 disabled:opacity-50"
              >
                <div className="min-w-0">
                  <p className="font-medium text-csit-text">{u.first_name} {u.last_name}</p>
                  <p className="truncate text-xs text-csit-text-muted">{u.email}</p>
                </div>
              </button>
            ))
          )}
        </div>

        <DialogFooter>
          {isSubmitting && (
            <span className="flex items-center gap-1 text-xs text-csit-text-muted">
              <Loader2 className="size-3.5 animate-spin" />
              Assigning…
            </span>
          )}
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={isSubmitting}>
            Close
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}