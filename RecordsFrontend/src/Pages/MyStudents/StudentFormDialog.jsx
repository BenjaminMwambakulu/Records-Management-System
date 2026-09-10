import { useEffect, useState } from "react";
import { useAuth } from "@/Context/AuthContext";
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
import { ACADEMIC_TRACK_OPTIONS } from "@/hooks/useMembersDirectory";

export function extractErrorMessage(err) {
  const bodyErrors = err?.body?.errors;
  if (bodyErrors && typeof bodyErrors === "object") {
    const messages = Object.values(bodyErrors).flat().slice(0, 3);
    if (messages.length) return messages.join(". ");
  }
  return err?.message || "Something went wrong. Please try again.";
}

function academicTrackLabel(value) {
  return (
    ACADEMIC_TRACK_OPTIONS.find((option) => option.value === value)?.label ??
    value
  );
}

export default function StudentFormDialog({
  open,
  onOpenChange,
  student,
  onSubmit,
  isSubmitting,
  error,
}) {
  const { user } = useAuth();
  const isEdit = Boolean(student);

  const trackLabel = academicTrackLabel(user?.academic_track);

  const [form, setForm] = useState({
    first_name: "",
    last_name: "",
    student_id: "",
    email: "",
    enrolled_year: "",
    skills: "",
  });

  useEffect(() => {
    if (!open) return;
    setForm({
      first_name: student?.first_name ?? "",
      last_name: student?.last_name ?? "",
      student_id: student?.student_id ?? "",
      email: student?.email ?? "",
      enrolled_year:
        student?.enrolled_year != null ? String(student.enrolled_year) : "",
      skills: Array.isArray(student?.skills) ? student.skills.join(", ") : "",
    });
  }, [open, student]);

  const updateField = (field, value) => {
    setForm((current) => ({ ...current, [field]: value }));
  };

  const handleSubmit = (event) => {
    event.preventDefault();
    const toYearValue = (value) => {
      if (value === "" || value === null || value === undefined) return null;
      const year = Number(value);
      return Number.isNaN(year) ? null : year;
    };
    const payload = {
      first_name: form.first_name.trim(),
      last_name: form.last_name.trim(),
      student_id: form.student_id.trim(),
      email: form.email.trim().toLowerCase(),
      enrolled_year: toYearValue(form.enrolled_year),
      skills: form.skills
        .split(",")
        .map((skill) => skill.trim())
        .filter(Boolean),
    };
    onSubmit(payload);
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{isEdit ? "Edit student" : "Add student"}</DialogTitle>
          <DialogDescription>
            {isEdit
              ? "Update the student's details below."
              : "Create a new student record."}
          </DialogDescription>
        </DialogHeader>

        <div className="rounded-lg border border-csit-border bg-csit-surface px-3 py-2 text-xs text-csit-text-muted">
          Track: <span className="font-medium text-csit-text">{trackLabel}</span>
          {" — "}
          Year: <span className="font-medium text-csit-text">{user?.study_year}º</span>
        </div>

        <form id="student-form" onSubmit={handleSubmit} className="flex flex-col gap-3">
          <div className="grid grid-cols-2 gap-3">
            <label className="flex flex-col gap-1.5 text-xs font-medium text-csit-text-muted">
              First name
              <Input
                required
                value={form.first_name}
                onChange={(event) => updateField("first_name", event.target.value)}
                placeholder="John"
              />
            </label>
            <label className="flex flex-col gap-1.5 text-xs font-medium text-csit-text-muted">
              Last name
              <Input
                required
                value={form.last_name}
                onChange={(event) => updateField("last_name", event.target.value)}
                placeholder="Doe"
              />
            </label>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <label className="flex flex-col gap-1.5 text-xs font-medium text-csit-text-muted">
              Student ID
              <Input
                required
                value={form.student_id}
                onChange={(event) => updateField("student_id", event.target.value)}
                placeholder="BIT-000-00"
              />
            </label>
            <label className="flex flex-col gap-1.5 text-xs font-medium text-csit-text-muted">
              Email
              <Input
                required
                type="email"
                value={form.email}
                onChange={(event) => updateField("email", event.target.value)}
                placeholder="name@must.ac.mw"
              />
            </label>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <label className="flex flex-col gap-1.5 text-xs font-medium text-csit-text-muted">
              Enrolled year
              <Input
                type="number"
                min="1950"
                max="2200"
                value={form.enrolled_year}
                onChange={(event) => updateField("enrolled_year", event.target.value)}
                placeholder="e.g. 2024"
              />
            </label>
            <label className="flex flex-col gap-1.5 text-xs font-medium text-csit-text-muted">
              Skills
              <Input
                value={form.skills}
                onChange={(event) => updateField("skills", event.target.value)}
                placeholder="Comma separated"
              />
            </label>
          </div>
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
          <Button type="submit" form="student-form" disabled={isSubmitting}>
            {isSubmitting
              ? isEdit
                ? "Saving…"
                : "Creating…"
              : isEdit
                ? "Save changes"
                : "Create student"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
