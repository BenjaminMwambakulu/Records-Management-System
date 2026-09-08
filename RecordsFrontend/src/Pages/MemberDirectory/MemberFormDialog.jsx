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
import {
  ACADEMIC_TRACK_OPTIONS,
  MEMBER_ROLE_OPTIONS,
} from "@/hooks/useMembersDirectory";

export function extractErrorMessage(err) {
  const bodyErrors = err?.body?.errors;
  if (bodyErrors && typeof bodyErrors === "object") {
    const messages = Object.values(bodyErrors).flat().slice(0, 3);
    if (messages.length) return messages.join(". ");
  }
  return err?.message || "Something went wrong. Please try again.";
}

export default function MemberFormDialog({
  open,
  onOpenChange,
  member,
  onSubmit,
  isSubmitting,
  error,
}) {
  const isEdit = Boolean(member);

  const [form, setForm] = useState({
    first_name: "",
    last_name: "",
    student_id: "",
    email: "",
    academic_track: "",
    enrolled_year: "",
    study_year: "",
    skills: "",
    roles: [],
  });

  useEffect(() => {
    if (!open) return;
    setForm({
      first_name: member?.first_name ?? "",
      last_name: member?.last_name ?? "",
      student_id: member?.student_id ?? "",
      email: member?.email ?? "",
      academic_track: member?.academic_track ?? "",
      enrolled_year:
        member?.enrolled_year != null ? String(member.enrolled_year) : "",
      study_year: member?.study_year != null ? String(member.study_year) : "",
      skills: Array.isArray(member?.skills) ? member.skills.join(", ") : "",
      roles: Array.isArray(member?.roles)
        ? member.roles
        : ["member"],
    });
  }, [open, member]);

  const updateField = (field, value) => {
    setForm((current) => ({ ...current, [field]: value }));
  };

  const toggleRole = (role) => {
    setForm((current) => ({
      ...current,
      roles: current.roles.includes(role)
        ? current.roles.filter((r) => r !== role)
        : [...current.roles, role],
    }));
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
      academic_track: form.academic_track || null,
      enrolled_year: toYearValue(form.enrolled_year),
      study_year: toYearValue(form.study_year),
      skills: form.skills
        .split(",")
        .map((skill) => skill.trim())
        .filter(Boolean),
      roles: form.roles,
    };
    onSubmit(payload);
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{isEdit ? "Edit member" : "Add member"}</DialogTitle>
          <DialogDescription>
            {isEdit
              ? "Update the member's details below."
              : "Create a new member record."}
          </DialogDescription>
        </DialogHeader>

        <form id="member-form" onSubmit={handleSubmit} className="flex flex-col gap-3">
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

          <div className="grid grid-cols-3 gap-3">
            <label className="flex flex-col gap-1.5 text-xs font-medium text-csit-text-muted">
              Academic track
              <select
                value={form.academic_track}
                onChange={(event) =>
                  updateField("academic_track", event.target.value)
                }
                className="h-9 rounded-lg border border-input bg-transparent px-2.5 text-sm outline-none transition-colors focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
              >
                <option value="">Select track</option>
                {ACADEMIC_TRACK_OPTIONS.map((option) => (
                  <option key={option.value} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </select>
            </label>
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
              Year of study
              <select
                value={form.study_year}
                onChange={(event) => updateField("study_year", event.target.value)}
                className="h-9 rounded-lg border border-input bg-transparent px-2.5 text-sm outline-none transition-colors focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
              >
                <option value="">Select year</option>
                {[1, 2, 3, 4].map((year) => (
                  <option key={year} value={year}>
                    {year}º Year
                  </option>
                ))}
              </select>
            </label>
          </div>

          <label className="flex flex-col gap-1.5 text-xs font-medium text-csit-text-muted">
            Skills
            <Input
              value={form.skills}
              onChange={(event) => updateField("skills", event.target.value)}
              placeholder="Comma separated, e.g. Python, UI Design"
            />
          </label>

          <fieldset className="flex flex-col gap-2">
            <legend className="text-xs font-medium text-csit-text-muted">
              Roles
            </legend>
            <div className="flex flex-wrap gap-2">
              {MEMBER_ROLE_OPTIONS.map((option) => (
                <label
                  key={option.value}
                  className="flex cursor-pointer items-center gap-1.5 rounded-lg border border-csit-border bg-white px-2.5 py-1.5 text-xs font-medium text-csit-text transition-colors has-checked:border-csit-primary has-checked:bg-csit-primary has-checked:text-white"
                >
                  <input
                    type="checkbox"
                    className="accent-csit-primary"
                    checked={form.roles.includes(option.value)}
                    onChange={() => toggleRole(option.value)}
                  />
                  {option.label}
                </label>
              ))}
            </div>
          </fieldset>
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
          <Button type="submit" form="member-form" disabled={isSubmitting}>
            {isSubmitting
              ? isEdit
                ? "Saving…"
                : "Creating…"
              : isEdit
                ? "Save changes"
                : "Create member"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}