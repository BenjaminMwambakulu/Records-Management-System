import { useEffect, useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Switch } from "@/components/ui/switch";
import { ImagePlus, ImageOff } from "lucide-react";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";

function toDateTimeLocal(value) {
  if (!value) return "";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "";
  const pad = (n) => String(n).padStart(2, "0");
  return `${
    date.getFullYear()
  }-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

export default function EventFormDialog({
  open,
  onOpenChange,
  event,
  onSubmit,
  isSubmitting,
  error,
  fieldErrors,
  onFieldChange,
  fieldProps = () => ({}),
}) {
  const isEdit = Boolean(event);

  const [form, setForm] = useState({
    title: "",
    description: "",
    location: "",
    event_date: "",
    start_time: "",
    duration: "",
    entry_fee: "",
    budget: "",
    is_published: false,
    cover: null,
    removeCover: false,
  });
  const [dateError, setDateError] = useState("");

  useEffect(() => {
    if (!open) return;
    setDateError("");
    setForm({
      title: event?.title ?? "",
      description: event?.description ?? "",
      location: event?.location ?? "",
      event_date: toDateTimeLocal(event?.event_date),
      start_time: event?.start_time ?? "",
      duration: event?.duration != null ? String(event.duration) : "",
      entry_fee: event?.entry_fee != null ? String(event.entry_fee) : "",
      budget: event?.budget != null ? String(event.budget) : "",
      is_published: event?.is_published ?? false,
      cover: null,
      removeCover: false,
    });
  }, [open, event]);

  const updateField = (field, value) => {
    setForm((current) => ({ ...current, [field]: value }));
  };

  const selectCover = (file) => {
    setForm((current) => ({
      ...current,
      cover: file ?? null,
      removeCover: false,
    }));
  };

  const handleSubmit = (event) => {
    event.preventDefault();

    const eventDate = new Date(form.event_date);
    if (Number.isNaN(eventDate.getTime()) || eventDate <= new Date()) {
      setDateError("Event date must be in the future.");
      return;
    }
    setDateError("");

    const payload = {
      title: form.title.trim(),
      description: form.description.trim() || null,
      location: form.location.trim() || null,
      event_date: eventDate.toISOString(),
      start_time: form.start_time || null,
      duration: form.duration ? Number(form.duration) : null,
      entry_fee: Number(form.entry_fee),
      budget: Number(form.budget),
      is_published: form.is_published,
    };
    if (form.cover) {
      payload.cover = form.cover;
    } else if (isEdit && form.removeCover && event?.cover_url) {
      payload.removeCover = true;
    }
    onSubmit(payload);
  };

  const coverPreview = form.cover
    ? URL.createObjectURL(form.cover)
    : event?.cover_url;

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>{isEdit ? "Edit event" : "Add event"}</DialogTitle>
          <DialogDescription>
            {isEdit
              ? "Update the event details below."
              : "Create a new event record."}
          </DialogDescription>
        </DialogHeader>

        <form id="event-form" onSubmit={handleSubmit} className="flex flex-col gap-3">
          <label className="flex flex-col gap-1.5 text-xs font-medium text-csit-text-muted">
            Title
            <Input
              id="title"
              required
              {...fieldProps("title")}
              value={form.title}
              onChange={(event) => {
                updateField("title", event.target.value);
                onFieldChange?.("title");
              }}
              placeholder="e.g. Hackathon 2026"
            />
            {fieldErrors?.title?.length > 0 && (
              <span id="title-error" className="text-xs text-destructive" role="alert">
                {fieldErrors.title[0]}
              </span>
            )}
          </label>

          <div className="grid grid-cols-2 gap-3">
            <label className="flex flex-col gap-1.5 text-xs font-medium text-csit-text-muted">
              Entry fee (K)
              <Input
                id="entry_fee"
                required
                {...fieldProps("entry_fee")}
                type="number"
                min="0"
                step="0.01"
                value={form.entry_fee}
                onChange={(event) => {
                  updateField("entry_fee", event.target.value);
                  onFieldChange?.("entry_fee");
                }}
                placeholder="e.g. 5000"
              />
              {fieldErrors?.entry_fee?.length > 0 && (
                <span id="entry_fee-error" className="text-xs text-destructive" role="alert">
                  {fieldErrors.entry_fee[0]}
                </span>
              )}
            </label>
            <label className="flex flex-col gap-1.5 text-xs font-medium text-csit-text-muted">
              Budget (K)
              <Input
                id="budget"
                required
                {...fieldProps("budget")}
                type="number"
                min="0"
                step="0.01"
                value={form.budget}
                onChange={(event) => {
                  updateField("budget", event.target.value);
                  onFieldChange?.("budget");
                }}
                placeholder="e.g. 200000"
              />
              {fieldErrors?.budget?.length > 0 && (
                <span id="budget-error" className="text-xs text-destructive" role="alert">
                  {fieldErrors.budget[0]}
                </span>
              )}
            </label>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <label className="flex flex-col gap-1.5 text-xs font-medium text-csit-text-muted">
              Event date
              <Input
                id="event_date"
                required
                {...fieldProps("event_date")}
                type="datetime-local"
                value={form.event_date}
                onChange={(event) => {
                  updateField("event_date", event.target.value);
                  onFieldChange?.("event_date");
                  setDateError("");
                }}
              />
              {fieldErrors?.event_date?.length > 0 && (
                <span id="event_date-error" className="text-xs text-destructive" role="alert">
                  {fieldErrors.event_date[0]}
                </span>
              )}
              {dateError && (
                <span role="alert" className="text-xs text-destructive">{dateError}</span>
              )}
            </label>
            <label className="flex flex-col gap-1.5 text-xs font-medium text-csit-text-muted">
              Location / Venue
              <Input
                id="location"
                {...fieldProps("location")}
                value={form.location}
                onChange={(event) => {
                  updateField("location", event.target.value);
                  onFieldChange?.("location");
                }}
                placeholder="e.g. Lecture Hall 3"
              />
              {fieldErrors?.location?.length > 0 && (
                <span id="location-error" className="text-xs text-destructive" role="alert">
                  {fieldErrors.location[0]}
                </span>
              )}
            </label>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <label className="flex flex-col gap-1.5 text-xs font-medium text-csit-text-muted">
              Start time
              <Input
                id="start_time"
                {...fieldProps("start_time")}
                type="time"
                value={form.start_time}
                onChange={(event) => {
                  updateField("start_time", event.target.value);
                  onFieldChange?.("start_time");
                }}
              />
              {fieldErrors?.start_time?.length > 0 && (
                <span id="start_time-error" className="text-xs text-destructive" role="alert">
                  {fieldErrors.start_time[0]}
                </span>
              )}
            </label>
            <label className="flex flex-col gap-1.5 text-xs font-medium text-csit-text-muted">
              Duration (minutes)
              <Input
                id="duration"
                {...fieldProps("duration")}
                type="number"
                min="1"
                value={form.duration}
                onChange={(event) => {
                  updateField("duration", event.target.value);
                  onFieldChange?.("duration");
                }}
                placeholder="e.g. 120"
              />
              {fieldErrors?.duration?.length > 0 && (
                <span id="duration-error" className="text-xs text-destructive" role="alert">
                  {fieldErrors.duration[0]}
                </span>
              )}
            </label>
          </div>

          <label className="flex flex-col gap-1.5 text-xs font-medium text-csit-text-muted">
            Description
            <Textarea
              id="description"
              {...fieldProps("description")}
              rows={3}
              value={form.description}
              onChange={(event) => {
                updateField("description", event.target.value);
                onFieldChange?.("description");
              }}
              placeholder="What will happen at this event?"
            />
            {fieldErrors?.description?.length > 0 && (
              <span id="description-error" className="text-xs text-destructive" role="alert">
                {fieldErrors.description[0]}
              </span>
            )}
          </label>

          <label className="flex items-center justify-between gap-3 rounded-lg border border-csit-border bg-white px-3 py-2.5 text-xs font-medium text-csit-text">
            Publish event
            <Switch
              checked={form.is_published}
              onCheckedChange={(value) => {
                updateField("is_published", value);
                onFieldChange?.("is_published");
              }}
            />
          </label>

          <div className="flex flex-col gap-2">
            <div className="flex items-center justify-between">
              <label className="text-xs font-medium text-csit-text-muted">Cover image</label>
              {isEdit && event?.cover_url ? (
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  className="h-7 text-xs text-rose-600 hover:bg-rose-50"
                  onClick={() =>
                    setForm((current) => ({
                      ...current,
                      removeCover: !current.removeCover,
                      cover: null,
                    }))
                  }
                >
                  <ImageOff />
                  {form.removeCover ? "Keep cover" : "Remove cover"}
                </Button>
              ) : null}
            </div>
            {coverPreview && !form.removeCover ? (
              <img
                src={coverPreview}
                alt="Event cover preview"
                className="h-36 w-full rounded-lg border border-csit-border object-cover"
              />
            ) : null}
            <label className="flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-dashed border-csit-border bg-muted/30 px-3 py-4 text-xs text-csit-text-muted transition-colors hover:border-csit-primary hover:text-csit-text">
              <ImagePlus className="size-4" />
              {isEdit && event?.cover_url
                ? "Choose a new cover image"
                : "Upload a cover image"}
              <Input
                type="file"
                accept="image/*"
                className="sr-only"
                value={undefined}
                onChange={(event) => selectCover(event.target.files?.[0] ?? null)}
              />
            </label>
          </div>
        </form>

        {error && (
          <div role="alert" className="rounded-lg border border-rose-200 bg-rose-50/50 px-3 py-2 text-xs text-rose-600">
            {error}
          </div>
        )}

        <DialogFooter>
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button type="submit" form="event-form" disabled={isSubmitting}>
            {isSubmitting
              ? isEdit
                ? "Saving…"
                : "Creating…"
              : isEdit
                ? "Save changes"
                : "Create event"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}