import { useState } from "react";
import { useNavigate } from "react-router-dom";
import "date-utils";
import { CalendarDays, ChevronLeft, ChevronRight, Eye, Pencil, Plus, Search, Trash2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Card } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { useAuth } from "@/Context/AuthContext";
import { isEventManager } from "@/lib/roles";
import { formatMoney } from "@/lib/utils";
import useEventsDirectory from "@/hooks/useEventsDirectory";
import EventFormDialog, { extractErrorMessage } from "./EventFormDialog";
import PermissionGate from "@/components/PermissionGate";

function formatDate(value) {
  if (!value) return "—";
  return new Date(value).toFormat("DD MMM YYYY");
}

function publishedLabel(value) {
  return value ? "Published" : "Draft";
}

function TableSkeleton() {
  return (
    <TableBody>
      {Array.from({ length: 6 }).map((_, index) => (
        <TableRow key={index}>
          <TableCell><Skeleton className="h-3 w-44" /></TableCell>
          <TableCell><Skeleton className="h-3 w-28" /></TableCell>
          <TableCell><Skeleton className="h-3 w-24" /></TableCell>
          <TableCell><Skeleton className="h-3 w-16" /></TableCell>
          <TableCell><Skeleton className="h-3 w-16" /></TableCell>
          <TableCell><Skeleton className="h-3 w-16" /></TableCell>
          <TableCell><Skeleton className="h-3 w-16" /></TableCell>
          <TableCell><Skeleton className="h-5 w-16" /></TableCell>
          <TableCell><Skeleton className="h-5 w-16" /></TableCell>
        </TableRow>
      ))}
    </TableBody>
  );
}

export default function EventsIndex() {
  const { user } = useAuth();
  const canManage = isEventManager(user);
  const navigate = useNavigate();

  const {
    events,
    pagination,
    isLoading,
    error,
    search,
    setSearch,
    published,
    setPublished,
    page,
    setPage,
    refetch,
    createEvent,
    updateEvent,
    deleteEvent,
  } = useEventsDirectory();

  const [formOpen, setFormOpen] = useState(false);
  const [editingEvent, setEditingEvent] = useState(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState(null);

  const [deleteTarget, setDeleteTarget] = useState(null);
  const [isDeleting, setIsDeleting] = useState(false);
  const [deleteError, setDeleteError] = useState(null);

  const openCreate = () => {
    setEditingEvent(null);
    setSubmitError(null);
    setFormOpen(true);
  };

  const openEdit = (event) => {
    setEditingEvent(event);
    setSubmitError(null);
    setFormOpen(true);
  };

  const handleSubmit = async (data) => {
    setIsSubmitting(true);
    setSubmitError(null);
    try {
      if (editingEvent) {
        await updateEvent(editingEvent.id, data);
      } else {
        await createEvent(data);
      }
      setFormOpen(false);
    } catch (err) {
      setSubmitError(extractErrorMessage(err));
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleDelete = async () => {
    setIsDeleting(true);
    setDeleteError(null);
    try {
      await deleteEvent(deleteTarget.id);
      setDeleteTarget(null);
    } catch (err) {
      setDeleteError(extractErrorMessage(err));
    } finally {
      setIsDeleting(false);
    }
  };

  const total = pagination?.total ?? 0;
  const lastPage = pagination?.last_page ?? 1;
  const from = pagination?.from ?? 0;
  const to = pagination?.to ?? 0;

  return (
    <PermissionGate permission="events.view">
      <div className="flex flex-col gap-6">
        <div className="flex flex-wrap items-end justify-between gap-4">
          <div>
            <h1 className="text-2xl font-semibold text-csit-text">Events</h1>
            <p className="mt-1 text-sm text-csit-text-muted">
              Upcoming and past society events
            </p>
          </div>
          {canManage ? (
            <Button onClick={openCreate}>
              <Plus />
              Add event
            </Button>
          ) : null}
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
                onChange={(event) => setSearch(event.target.value)}
                placeholder="Search by title or location…"
                className="pl-8"
              />
            </div>
            <select
              value={published}
              onChange={(event) => setPublished(event.target.value)}
              className="h-8 rounded-lg border border-input bg-transparent px-2.5 text-sm outline-none transition-colors focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
              aria-label="Filter by publish status"
            >
              <option value="">All events</option>
              <option value="true">Published</option>
              <option value="false">Draft</option>
            </select>
          </div>

          {error ? (
            <div className="rounded-2xl border border-rose-200 bg-rose-50/50 p-6 text-sm text-rose-600">
              <div>Failed to load events. Please try again.</div>
              <Button
                type="button"
                variant="outline"
                onClick={refetch}
                className="mt-3 border-rose-300 text-rose-600 hover:bg-rose-100"
              >
                Retry
              </Button>
            </div>
          ) : events.length === 0 && !isLoading ? (
            <Empty className="border-0 py-16">
              <EmptyHeader>
                <EmptyMedia variant="icon">
                  <CalendarDays />
                </EmptyMedia>
                <EmptyTitle>No events found</EmptyTitle>
                <EmptyDescription>
                  {search || published
                    ? "Try adjusting your search or filter."
                    : canManage
                      ? "Get started by adding your first event."
                      : "No events have been scheduled yet."}
                </EmptyDescription>
              </EmptyHeader>
            </Empty>
          ) : (
            <Table>
              <TableHeader>
                <TableRow className="hover:bg-transparent">
                  <TableHead className="px-4">Title</TableHead>
                  <TableHead>Location</TableHead>
                  <TableHead>Event date</TableHead>
                  <TableHead>Start time</TableHead>
                  <TableHead>Duration</TableHead>
                  <TableHead>Entry fee</TableHead>
                  <TableHead>Budget</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead className="px-4 text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              {isLoading ? (
                <TableSkeleton />
              ) : (
                <TableBody>
                  {events.map((event) => (
                    <TableRow key={event.id}>
                      <TableCell className="px-4">
                        <div className="flex items-center gap-2.5">
                          {event.cover_url ? (
                            <img
                              src={event.cover_url}
                              alt=""
                              className="size-8 shrink-0 rounded-lg object-cover"
                            />
                          ) : (
                            <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-csit-primary/10 text-csit-primary">
                              <CalendarDays className="size-4" />
                            </span>
                          )}
                          <span className="font-medium text-csit-text">{event.title}</span>
                        </div>
                      </TableCell>
                      <TableCell className="text-csit-text-muted">
                        {event.location ?? "—"}
                      </TableCell>
                      <TableCell className="text-csit-text-muted">
                        {formatDate(event.event_date)}
                      </TableCell>
                      <TableCell className="text-csit-text-muted">
                        {event.start_time ?? "—"}
                      </TableCell>
                      <TableCell className="text-csit-text-muted">
                        {event.duration ? `${event.duration} min` : "—"}
                      </TableCell>
                      <TableCell className="text-csit-text-muted">
                        {formatMoney(event.entry_fee)}
                      </TableCell>
                      <TableCell className="text-csit-text-muted">
                        {formatMoney(event.budget)}
                      </TableCell>
                      <TableCell>
                        <span
                          className={`rounded-md px-1.5 py-0.5 text-xs font-medium ${
                            event.is_published
                              ? "bg-emerald-50 text-emerald-700"
                              : "bg-slate-100 text-slate-600"
                          }`}
                        >
                          {publishedLabel(event.is_published)}
                        </span>
                      </TableCell>
                      <TableCell className="px-4">
                        <div className="flex items-center justify-end gap-1">
                          <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={() => navigate(`/app/events/${event.id}`)}
                            aria-label={`View ${event.title}`}
                          >
                            <Eye className="text-csit-text-muted" />
                          </Button>
                          {canManage ? (
                            <>
                              <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                onClick={() => openEdit(event)}
                                aria-label={`Edit ${event.title}`}
                              >
                                <Pencil className="text-csit-text-muted" />
                              </Button>
                              <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                onClick={() => {
                                  setDeleteError(null);
                                  setDeleteTarget(event);
                                }}
                                aria-label={`Delete ${event.title}`}
                                className="hover:bg-rose-50 hover:text-rose-600"
                              >
                                <Trash2 className="text-csit-text-muted" />
                              </Button>
                            </>
                          ) : null}
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
                  : `Showing ${from}–${to} of ${total} events`}
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
                  onClick={() => setPage((current) => Math.min(lastPage, current + 1))}
                >
                  Next
                  <ChevronRight />
                </Button>
              </div>
            </div>
          )}
        </Card>

        <EventFormDialog
          open={formOpen}
          onOpenChange={setFormOpen}
          event={editingEvent}
          onSubmit={handleSubmit}
          isSubmitting={isSubmitting}
          error={submitError}
        />

        <Dialog
          open={Boolean(deleteTarget)}
          onOpenChange={(open) => {
            if (!open) setDeleteTarget(null);
          }}
        >
          <DialogContent className="sm:max-w-md">
            <DialogHeader>
              <DialogTitle>Delete event</DialogTitle>
              <DialogDescription>
                Are you sure you want to delete{" "}
                <span className="font-medium text-foreground">
                  {deleteTarget?.title}
                </span>
                ? This action cannot be undone.
              </DialogDescription>
            </DialogHeader>

            {deleteError && (
              <div className="rounded-lg border border-rose-200 bg-rose-50/50 px-3 py-2 text-xs text-rose-600">
                {deleteError}
              </div>
            )}

            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                onClick={() => setDeleteTarget(null)}
                disabled={isDeleting}
              >
                Cancel
              </Button>
              <Button
                type="button"
                variant="destructive"
                onClick={handleDelete}
                disabled={isDeleting}
              >
                {isDeleting ? "Deleting…" : "Delete event"}
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </div>
    </PermissionGate>
  );
}