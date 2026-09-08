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
import { api } from "@/APIClients/APIClient";
import { extractErrorMessage } from "@/lib/errors";

export default function AssignUserDialog({
  open,
  onOpenChange,
  onAssign,
}) {
  const [search, setSearch] = useState("");
  const [users, setUsers] = useState([]);
  const [isSearching, setIsSearching] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    if (!open) {
      setSearch("");
      setUsers([]);
      setError(null);
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
        .catch((err) => setError(extractErrorMessage(err)))
        .finally(() => setIsSearching(false));
    }, 400);
    return () => clearTimeout(timer);
  }, [search, open]);

  const handleAssign = async (userId) => {
    setError(null);
    try {
      await onAssign(userId);
      onOpenChange(false);
    } catch (err) {
      setError(extractErrorMessage(err));
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Assign role to user</DialogTitle>
          <DialogDescription>Search for a user to assign this role to.</DialogDescription>
        </DialogHeader>

        <Input
          value={search}
          onChange={(event) => setSearch(event.target.value)}
          placeholder="Search by name or email…"
        />

        {error && (
          <div className="rounded-lg border border-rose-200 bg-rose-50/50 px-3 py-2 text-xs text-rose-600">
            {error}
          </div>
        )}

        <div className="flex flex-col gap-1 max-h-60 overflow-y-auto">
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
                className="flex items-center gap-3 rounded-lg border border-csit-border px-3 py-2 text-left text-sm hover:bg-muted/50 transition-colors"
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
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            Close
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
