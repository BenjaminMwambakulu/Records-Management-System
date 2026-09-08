import { useState } from "react";
import "date-utils";
import { ChevronLeft, ChevronRight, Eye, History, Search } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Card } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import useActivityLogs from "@/hooks/useActivityLogs";
import LogDetailDialog from "./LogDetailDialog";
import PermissionGate from "@/components/PermissionGate";

function formatDateTime(value) {
  if (!value) return "—";
  return new Date(value).toLocaleString();
}

function formatSubject(log) {
  const typeName = log?.subject?.type_short ?? log?.subject?.type?.split("\\").pop() ?? "";
  if (log?.subject?.name && log.subject.name !== typeName) {
    return `${typeName} — ${log.subject.name}`;
  }
  if (log?.subject?.name) return log.subject.name;
  if (typeName) return typeName;
  return "—";
}

const EVENT_OPTIONS = ["created", "updated", "deleted", "restored"];

const SUBJECT_TYPE_OPTIONS = [
  { value: "App\\Models\\Event", label: "Events" },
  { value: "App\\Models\\User", label: "Users" },
  { value: "App\\Models\\Document", label: "Documents" },
  { value: "App\\Models\\DocumentVersion", label: "Document versions" },
  { value: "App\\Models\\DocumentCategory", label: "Document categories" },
  { value: "App\\Models\\Asset", label: "Assets" },
  { value: "App\\Models\\AssetLoan", label: "Asset loans" },
  { value: "App\\Models\\Attendance", label: "Attendance" },
  { value: "App\\Models\\FinancialRecord", label: "Financial records" },
  { value: "App\\Models\\FinancialCategory", label: "Financial categories" },
];

const EVENT_STYLES = {
  created: "bg-emerald-50 text-emerald-700",
  updated: "bg-sky-50 text-sky-700",
  deleted: "bg-rose-50 text-rose-700",
  restored: "bg-violet-50 text-violet-700",
};

function eventBadgeClass(event) {
  return event && EVENT_STYLES[event] ? EVENT_STYLES[event] : "bg-slate-100 text-slate-600";
}

function TableSkeleton() {
  return (
    <TableBody>
      {Array.from({ length: 6 }).map((_, index) => (
        <TableRow key={index}>
          <TableCell><Skeleton className="h-3 w-56" /></TableCell>
          <TableCell><Skeleton className="h-3 w-32" /></TableCell>
          <TableCell><Skeleton className="h-3 w-28" /></TableCell>
          <TableCell><Skeleton className="h-5 w-16" /></TableCell>
          <TableCell><Skeleton className="h-3 w-28" /></TableCell>
          <TableCell><Skeleton className="h-5 w-16" /></TableCell>
        </TableRow>
      ))}
    </TableBody>
  );
}

export default function LogsIndex() {
  const {
    logs,
    pagination,
    isLoading,
    error,
    search,
    setSearch,
    event,
    setEvent,
    subjectType,
    setSubjectType,
    page,
    setPage,
    refetch,
  } = useActivityLogs();

  const [selected, setSelected] = useState(null);

  const total = pagination?.total ?? 0;
  const lastPage = pagination?.last_page ?? 1;
  const from = pagination?.from ?? 0;
  const to = pagination?.to ?? 0;

  return (
    <PermissionGate permission="activity_logs.view">
      <div className="flex flex-col gap-6">
        <div>
          <h1 className="text-2xl font-semibold text-csit-text">Activity Logs</h1>
          <p className="mt-1 text-sm text-csit-text-muted">
            A record of actions taken across the society.
          </p>
        </div>

        <Card className="border-csit-border bg-white">
          <div className="flex flex-col gap-3 border-b border-csit-border p-4 sm:flex-row sm:items-center">
            <div className="relative flex-1">
              <Search
                size={16}
                className="pointer-events-none absolute top-1/2 left-2.5 -translate-y-1/2 text-csit-text-muted"
              />
              <Input
                value={search}
                onChange={(ctx) => setSearch(ctx.target.value)}
                placeholder="Search description or actor…"
                className="pl-8"
              />
            </div>
            <select
              value={event}
              onChange={(ctx) => setEvent(ctx.target.value)}
              className="h-8 rounded-lg border border-input bg-transparent px-2.5 text-sm outline-none transition-colors focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
              aria-label="Filter by event type"
            >
              <option value="">All events</option>
              {EVENT_OPTIONS.map((option) => (
                <option key={option} value={option}>
                  {option}
                </option>
              ))}
            </select>
            <select
              value={subjectType}
              onChange={(ctx) => setSubjectType(ctx.target.value)}
              className="h-8 rounded-lg border border-input bg-transparent px-2.5 text-sm outline-none transition-colors focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
              aria-label="Filter by subject type"
            >
              <option value="">All subjects</option>
              {SUBJECT_TYPE_OPTIONS.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          </div>

          {error ? (
            <div className="rounded-2xl border border-rose-200 bg-rose-50/50 p-6 text-sm text-rose-600">
              <div>Failed to load activity logs. Please try again.</div>
              <Button
                type="button"
                variant="outline"
                onClick={refetch}
                className="mt-3 border-rose-300 text-rose-600 hover:bg-rose-100"
              >
                Retry
              </Button>
            </div>
          ) : logs.length === 0 && !isLoading ? (
            <Empty className="border-0 py-16">
              <EmptyHeader>
                <EmptyMedia variant="icon">
                  <History />
                </EmptyMedia>
                <EmptyTitle>No activity logs found</EmptyTitle>
                <EmptyDescription>
                  {search || event || subjectType
                    ? "Try adjusting your search or filters."
                    : "Actions taken across the society will appear here."}
                </EmptyDescription>
              </EmptyHeader>
            </Empty>
          ) : (
            <Table>
              <TableHeader>
                <TableRow className="hover:bg-transparent">
                  <TableHead className="px-4">Description</TableHead>
                  <TableHead>Subject</TableHead>
                  <TableHead>Actor</TableHead>
                  <TableHead>Event</TableHead>
                  <TableHead>Logged at</TableHead>
                  <TableHead className="px-4 text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              {isLoading ? (
                <TableSkeleton />
              ) : (
                <TableBody>
                  {logs.map((log) => (
                    <TableRow key={log.id}>
                      <TableCell className="px-4">
                        <span className="font-medium text-csit-text">{log.description}</span>
                      </TableCell>
                      <TableCell className="text-csit-text-muted">
                        {formatSubject(log)}
                      </TableCell>
                      <TableCell className="text-csit-text-muted">
                        {log.causer?.full_name ?? "System"}
                      </TableCell>
                      <TableCell>
                        <span
                          className={`rounded-md px-1.5 py-0.5 text-xs font-medium ${eventBadgeClass(log.event)}`}
                        >
                          {log.event ?? "—"}
                        </span>
                      </TableCell>
                      <TableCell className="text-csit-text-muted">
                        {formatDateTime(log.created_at)}
                      </TableCell>
                      <TableCell className="px-4">
                        <div className="flex items-center justify-end">
                          <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={() => setSelected(log)}
                            aria-label={`View details for ${log.description}`}
                          >
                            <Eye className="text-csit-text-muted" />
                          </Button>
                        </div>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              )}
            </Table>
          )}

          {!error && (
            <div className="flex items-center justify-between gap-4 border-t border-csit-border px-4 py-3">
              <p className="text-xs text-csit-text-muted">
                {total === 0
                  ? "No results"
                  : `Showing ${from}–${to} of ${total} logs`}
              </p>
              <div className="flex items-center gap-2">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  disabled={page <= 1 || isLoading}
                  onClick={() => setPage((current) => Math.max(1, current - 1))}
                >
                  <ChevronLeft />
                  Previous
                </Button>
                <span className="text-xs font-medium text-csit-text-muted">
                  Page {Math.min(page, lastPage)} of {lastPage}
                </span>
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  disabled={page >= lastPage || isLoading}
                  onClick={() =>
                    setPage((current) => Math.min(lastPage, current + 1))
                  }
                >
                  Next
                  <ChevronRight />
                </Button>
              </div>
            </div>
          )}
        </Card>

        <LogDetailDialog
          log={selected}
          onOpenChange={(open) => {
            if (!open) setSelected(null);
          }}
        />
      </div>
    </PermissionGate>
  );
}