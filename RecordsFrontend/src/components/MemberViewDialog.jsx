import "date-utils";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { ACADEMIC_TRACK_OPTIONS, MEMBER_ROLE_OPTIONS } from "@/hooks/useMembersDirectory";

const roleBadgeStyles = {
  superadmin: "bg-[#110b79]/10 text-[#110b79]",
  admin: "bg-[#5d5279]/10 text-[#5d5279]",
  member: "bg-slate-100 text-slate-600",
};

function initialsOf(name) {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0])
    .join("")
    .toUpperCase();
}

function roleLabel(value) {
  return MEMBER_ROLE_OPTIONS.find((option) => option.value === value)?.label ?? value;
}

function academicTrackLabel(value) {
  return (
    ACADEMIC_TRACK_OPTIONS.find((option) => option.value === value)?.label ??
    value
  );
}

function MetaRow({ label, value }) {
  return (
    <div className="flex items-center justify-between gap-4 border-b border-csit-border py-2 text-sm last:border-0">
      <span className="text-csit-text-muted">{label}</span>
      <span className="text-right font-medium text-csit-text">{value ?? "—"}</span>
    </div>
  );
}

function formatDate(value) {
  if (!value) return "—";
  return new Date(value).toFormat("DD MMM YYYY");
}

export default function MemberViewDialog({ member, open, onOpenChange }) {
  const skills = Array.isArray(member?.skills) ? member.skills : [];

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>Member details</DialogTitle>
        </DialogHeader>

        {member ? (
          <div className="flex flex-col gap-4">
            <div className="flex items-center gap-3">
              <Avatar className="size-12">
                <AvatarFallback>{initialsOf(member.full_name)}</AvatarFallback>
              </Avatar>
              <div>
                <p className="text-base font-semibold text-csit-text">
                  {member.full_name}
                </p>
                <p className="text-sm text-csit-text-muted">
                  {member.student_id}
                </p>
              </div>
            </div>

            <Card className="border-csit-border bg-white p-3">
              <MetaRow label="Email" value={member.email} />
              <MetaRow label="Academic track" value={academicTrackLabel(member.academic_track)} />
              <MetaRow label="Enrolled year" value={member.enrolled_year} />
              <MetaRow label="Study year" value={member.study_year ? `${member.study_year}º` : null} />
              <MetaRow label="Joined" value={formatDate(member.created_at)} />
              <MetaRow label="Updated" value={formatDate(member.updated_at)} />
            </Card>

            <Card className="border-csit-border bg-white p-3">
              <p className="mb-2 text-xs font-semibold text-csit-text-muted">
                Roles
              </p>
              <div className="flex flex-wrap gap-1">
                {(member.roles ?? []).length === 0 ? (
                  <span className="text-xs text-csit-text-muted">—</span>
                ) : (
                  member.roles.map((memberRole) => (
                    <span
                      key={memberRole}
                      className={`rounded-md px-1.5 py-0.5 text-xs font-medium ${roleBadgeStyles[memberRole] ?? "bg-slate-100 text-slate-600"}`}
                    >
                      {roleLabel(memberRole)}
                    </span>
                  ))
                )}
              </div>
            </Card>

            {skills.length > 0 ? (
              <Card className="border-csit-border bg-white p-3">
                <p className="mb-2 text-xs font-semibold text-csit-text-muted">
                  Skills
                </p>
                <div className="flex flex-wrap gap-1">
                  {skills.map((skill) => (
                    <span
                      key={skill}
                      className="rounded-md bg-slate-100 px-1.5 py-0.5 text-xs font-medium text-slate-600"
                    >
                      {skill}
                    </span>
                  ))}
                </div>
              </Card>
            ) : null}
          </div>
        ) : null}

        <DialogFooter>
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            Close
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}