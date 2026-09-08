import { useCallback, useEffect, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import "date-utils";
import { ArrowLeft, CalendarDays, Loader2, MapPin, Users } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { formatMoney } from "@/lib/utils";
import useEventsDirectory from "@/hooks/useEventsDirectory";

function formatDate(value) {
  if (!value) return "—";
  return new Date(value).toFormat("DD MMM YYYY");
}

function initialsOf(name) {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0])
    .join("")
    .toUpperCase();
}

function MetaRow({ label, value }) {
  return (
    <div className="flex items-baseline justify-between gap-4 border-b border-csit-border py-2 text-sm last:border-0">
      <span className="text-csit-text-muted">{label}</span>
      <span className="font-medium text-csit-text">{value ?? "—"}</span>
    </div>
  );
}

function AttendeesTable({ attendances, isLoading, error, onRetry }) {
  if (isLoading) {
    return (
      <TableBody>
        <TableRow>
          <TableCell colSpan={4}>
            <div className="my-4 flex justify-center">
              <Loader2 size={24} className="animate-spin text-csit-primary" />
            </div>
          </TableCell>
        </TableRow>
      </TableBody>
    );
  }

  if (error) {
    return (
      <div className="rounded-2xl border border-rose-200 bg-rose-50/50 p-6 text-sm text-rose-600">
        <div>Failed to load attendees. Please try again.</div>
        <Button
          type="button"
          variant="outline"
          onClick={onRetry}
          className="mt-3 border-rose-300 text-rose-600 hover:bg-rose-100"
        >
          Retry
        </Button>
      </div>
    );
  }

  if (attendances.length === 0) {
    return (
      <div className="p-4">
        <Empty className="border-0 py-10">
          <EmptyHeader>
            <EmptyMedia variant="icon">
              <Users />
            </EmptyMedia>
            <EmptyTitle>No attendees yet</EmptyTitle>
            <EmptyDescription>
              Members check in at the event via QR code before appearing here.
            </EmptyDescription>
          </EmptyHeader>
        </Empty>
      </div>
    );
  }

  return (
    <Table>
      <TableHeader>
        <TableRow className="hover:bg-transparent">
          <TableHead className="px-4">Member</TableHead>
          <TableHead>Student ID</TableHead>
          <TableHead>Academic track</TableHead>
          <TableHead className="px-4">Checked in</TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        {attendances.map((attendance) => {
          const member = attendance.user ?? {};
          return (
            <TableRow key={attendance.id}>
              <TableCell className="px-4">
                <div className="flex items-center gap-2.5">
                  <Avatar>
                    <AvatarFallback>{initialsOf(member.full_name)}</AvatarFallback>
                  </Avatar>
                  <span className="font-medium text-csit-text">
                    {member.full_name ?? "—"}
                  </span>
                </div>
              </TableCell>
              <TableCell className="text-csit-text-muted">
                {member.student_id ?? "—"}
              </TableCell>
              <TableCell className="text-csit-text-muted">
                {member.academic_track_label ?? member.academic_track ?? "—"}
              </TableCell>
              <TableCell className="text-csit-text-muted px-4">
                {attendance.checked_in_at
                  ? new Date(attendance.checked_in_at).toLocaleString()
                  : "—"}
              </TableCell>
            </TableRow>
          );
        })}
      </TableBody>
    </Table>
  );
}

export default function EventDetailPage() {
  const { id } = useParams();
  const navigate = useNavigate();

  const { fetchEvent, fetchAttendances } = useEventsDirectory();

  const [event, setEvent] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState(null);

  const [attendances, setAttendances] = useState([]);
  const [attendancesLoading, setAttendancesLoading] = useState(true);
  const [attendancesError, setAttendancesError] = useState(null);

  const load = useCallback(() => {
    setIsLoading(true);
    setError(null);
    setAttendancesLoading(true);
    setAttendancesError(null);

    Promise.allSettled([fetchEvent(id), fetchAttendances(id)])
      .then(([eventResult, attendeesResult]) => {
        if (eventResult.status === "fulfilled") {
          setEvent(eventResult.value);
        } else {
          setError(eventResult.reason);
        }
        if (attendeesResult.status === "fulfilled") {
          setAttendances(attendeesResult.value);
        } else {
          setAttendancesError(attendeesResult.reason);
        }
      })
      .finally(() => {
        setIsLoading(false);
        setAttendancesLoading(false);
      });
  }, [id, fetchEvent, fetchAttendances]);

  useEffect(() => {
    load();
  }, [load]);

  const reloadAttendances = useCallback(() => {
    setAttendancesLoading(true);
    setAttendancesError(null);
    fetchAttendances(id)
      .then(setAttendances)
      .catch((err) => setAttendancesError(err))
      .finally(() => setAttendancesLoading(false));
  }, [id, fetchAttendances]);

  if (isLoading) {
    return (
      <div className="flex min-h-64 items-center justify-center">
        <Loader2 size={32} className="animate-spin text-[#7A5CF0]" />
      </div>
    );
  }

  if (error || !event) {
    return (
      <div className="flex flex-col items-center justify-center gap-3 py-16 text-center">
        <p className="text-sm text-rose-600">Failed to load this event.</p>
        <div className="flex gap-2">
          <Button variant="outline" onClick={() => navigate("/app/events")}>
            Back to events
          </Button>
          <Button onClick={load}>Retry</Button>
        </div>
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-6">
      <Button
        type="button"
        variant="ghost"
        onClick={() => navigate("/app/events")}
        className="w-fit justify-start gap-1.5 text-sm font-medium text-csit-text-muted hover:text-csit-text"
      >
        <ArrowLeft className="size-4" />
        Back to events
      </Button>

      {event.cover_url ? (
        <img
          src={event.cover_url}
          alt={`Cover of ${event.title}`}
          className="h-56 w-full rounded-xl border border-csit-border object-cover"
        />
      ) : (
        <div className="flex h-32 w-full items-center justify-center rounded-xl border border-dashed border-csit-border bg-muted/30">
          <CalendarDays className="size-8 text-csit-text-muted" />
        </div>
      )}

      <div className="grid gap-6 xl:grid-cols-[1fr_320px]">
        <div className="flex flex-col gap-6">
          <div>
            <div className="flex flex-wrap items-center gap-2">
              <h1 className="text-2xl font-semibold text-csit-text">{event.title}</h1>
              <span
                className={`rounded-md px-1.5 py-0.5 text-xs font-medium ${
                  event.is_published
                    ? "bg-emerald-50 text-emerald-700"
                    : "bg-slate-100 text-slate-600"
                }`}
              >
                {event.is_published ? "Published" : "Draft"}
              </span>
            </div>
            <div className="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-csit-text-muted">
              <span className="inline-flex items-center gap-1.5">
                <CalendarDays className="size-4" />
                {formatDate(event.event_date)}
              </span>
              <span className="inline-flex items-center gap-1.5">
                <MapPin className="size-4" />
                {event.location ?? "No location set"}
              </span>
            </div>
            {event.description ? (
              <p className="mt-4 max-w-prose whitespace-pre-line text-sm text-csit-text">
                {event.description}
              </p>
            ) : null}
          </div>

          <Card className="border-csit-border bg-white">
            <div className="border-b border-csit-border px-4 py-3">
              <h2 className="text-sm font-semibold text-csit-text">
                Attendees ({attendances.length})
              </h2>
            </div>
            <AttendeesTable
              attendances={attendances}
              isLoading={attendancesLoading}
              error={attendancesError}
              onRetry={reloadAttendances}
            />
          </Card>
        </div>

        <Card className="h-fit border-csit-border bg-white p-4 xl:sticky xl:top-6">
          <h2 className="mb-3 text-sm font-semibold text-csit-text">Event details</h2>
          <MetaRow label="Title" value={event.title} />
          <MetaRow label="Location" value={event.location} />
          <MetaRow label="Event date" value={formatDate(event.event_date)} />
          <MetaRow label="Start time" value={event.start_time ?? "—"} />
          <MetaRow label="Duration" value={event.duration ? `${event.duration} minutes` : "—"} />
          <MetaRow label="Entry fee" value={formatMoney(event.entry_fee)} />
          <MetaRow label="Budget" value={formatMoney(event.budget)} />
          <MetaRow label="Status" value={event.is_published ? "Published" : "Draft"} />
          <MetaRow label="Created" value={formatDate(event.created_at)} />
          <MetaRow label="Last updated" value={formatDate(event.updated_at)} />
        </Card>
      </div>
    </div>
  );
}