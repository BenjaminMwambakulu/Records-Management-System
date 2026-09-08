import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";

const EVENT_STYLES = {
  created: "bg-emerald-50 text-emerald-700",
  updated: "bg-sky-50 text-sky-700",
  deleted: "bg-rose-50 text-rose-700",
  restored: "bg-violet-50 text-violet-700",
};

export function formatPropertyValue(value) {
  if (value == null) return "—";
  if (typeof value === "boolean") return value ? "true" : "false";
  if (typeof value === "object") return JSON.stringify(value);
  return String(value);
}

function changesFromRaw(changes) {
  const attributes = changes?.attributes;
  const old = changes?.old;
  const keys = [
    ...(attributes && typeof attributes === "object" ? Object.keys(attributes) : []),
    ...(old && typeof old === "object" ? Object.keys(old) : []),
  ];
  return Array.from(new Set(keys)).sort().map((key) => ({
    field: key,
    oldValue: old?.[key],
    newValue: attributes?.[key],
  }));
}

function eventBadgeClass(event) {
  return event && EVENT_STYLES[event] ? EVENT_STYLES[event] : "bg-slate-100 text-slate-600";
}

function MetaRow({ label, value }) {
  return (
    <div className="flex items-baseline justify-between gap-4 border-b border-csit-border py-2 text-sm last:border-0">
      <span className="text-csit-text-muted">{label}</span>
      <span className="break-words text-right font-medium text-csit-text">{value ?? "—"}</span>
    </div>
  );
}

export default function LogDetailDialog({ log, onOpenChange }) {
  const changes = log ? changesFromRaw(log.changes) : [];

  return (
    <Dialog open={Boolean(log)} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle className="flex flex-wrap items-center gap-2">
            Activity details
            {log?.event ? (
              <span
                className={`rounded-md px-1.5 py-0.5 text-xs font-medium ${eventBadgeClass(log.event)}`}
              >
                {log.event}
              </span>
            ) : null}
          </DialogTitle>
          <DialogDescription>{log?.description ?? "Activity log entry"}</DialogDescription>
        </DialogHeader>

        <div>
          <h3 className="mb-2 text-xs font-semibold uppercase tracking-wide text-csit-text-muted">
            Details
          </h3>
          <MetaRow label="Performed by" value={log?.causer?.full_name ?? "System"} />
          <MetaRow label="Model" value={log?.subject?.type_short ?? log?.subject?.type ?? "—"} />
          <MetaRow label="Name" value={log?.subject?.name ?? "—"} />
          <MetaRow label="Subject type" value={log?.subject?.type} />
          <MetaRow label="Logged at" value={log?.created_at ? new Date(log.created_at).toLocaleString() : "—"} />
        </div>

        <div>
          <h3 className="mb-2 text-xs font-semibold uppercase tracking-wide text-csit-text-muted">
            Changes
          </h3>
          {changes.length === 0 ? (
            <p className="rounded-lg border border-csit-border bg-muted/30 px-3 py-4 text-sm text-csit-text-muted">
              No field changes were recorded for this action.
            </p>
          ) : (
            <Table>
              <TableHeader>
                <TableRow className="hover:bg-transparent">
                  <TableHead className="px-3">Field</TableHead>
                  <TableHead className="px-3">Old value</TableHead>
                  <TableHead className="px-3">New value</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {changes.map((change) => (
                  <TableRow key={change.field}>
                    <TableCell className="px-3">
                      <span className="font-medium text-csit-text">{change.field}</span>
                    </TableCell>
                    <TableCell className="px-3 align-top text-csit-text-muted">
                      {change.oldValue === undefined
                        ? "—"
                        : formatPropertyValue(change.oldValue)}
                    </TableCell>
                    <TableCell className="px-3 align-top text-csit-text">
                      {change.newValue === undefined
                        ? "—"
                        : formatPropertyValue(change.newValue)}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </div>
      </DialogContent>
    </Dialog>
  );
}