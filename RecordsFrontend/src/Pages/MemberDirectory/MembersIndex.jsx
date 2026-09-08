import { useState } from "react";
import {
  ChevronLeft,
  ChevronRight,
  Pencil,
  Plus,
  Search,
  Trash2,
  Upload,
  Users,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
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
import useMembersDirectory, {
  ACADEMIC_TRACK_OPTIONS,
  MEMBER_ROLE_OPTIONS,
} from "@/hooks/useMembersDirectory";
import MemberFormDialog, { extractErrorMessage } from "./MemberFormDialog";
import MemberImportDialog from "./MemberImportDialog";
import PermissionGate from "@/components/PermissionGate";

const roleBadgeStyles = {
  superadmin: "bg-[#110b79]/10 text-[#110b79]",
  admin: "bg-[#5d5279]/10 text-[#5d5279]",
  member: "bg-slate-100 text-slate-600",
};

function roleLabel(value) {
  return MEMBER_ROLE_OPTIONS.find((option) => option.value === value)?.label ?? value;
}

function academicTrackLabel(value) {
  return (
    ACADEMIC_TRACK_OPTIONS.find((option) => option.value === value)?.label ??
    value
  );
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

function TableSkeleton() {
  return (
    <TableBody>
      {Array.from({ length: 6 }).map((_, index) => (
        <TableRow key={index}>
          <TableCell>
            <div className="flex items-center gap-2.5">
              <Skeleton className="size-8 shrink-0 rounded-full" />
              <Skeleton className="h-3 w-32" />
            </div>
          </TableCell>
          <TableCell>
            <Skeleton className="h-3 w-20" />
          </TableCell>
          <TableCell>
            <Skeleton className="h-3 w-40" />
          </TableCell>
          <TableCell>
            <Skeleton className="h-3 w-24" />
          </TableCell>
          <TableCell>
            <Skeleton className="h-3 w-12" />
          </TableCell>
          <TableCell>
            <Skeleton className="h-5 w-16" />
          </TableCell>
        </TableRow>
      ))}
    </TableBody>
  );
}

export default function MembersIndex() {
  const {
    members,
    pagination,
    isLoading,
    error,
    search,
    setSearch,
    role,
    setRole,
    page,
    setPage,
    refetch,
    createMember,
    updateMember,
    deleteMember,
  } = useMembersDirectory();

  const [formOpen, setFormOpen] = useState(false);
  const [importOpen, setImportOpen] = useState(false);
  const [editingMember, setEditingMember] = useState(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState(null);

  const [deleteTarget, setDeleteTarget] = useState(null);
  const [isDeleting, setIsDeleting] = useState(false);
  const [deleteError, setDeleteError] = useState(null);

  const openCreate = () => {
    setEditingMember(null);
    setSubmitError(null);
    setFormOpen(true);
  };

  const openEdit = (member) => {
    setEditingMember(member);
    setSubmitError(null);
    setFormOpen(true);
  };

  const handleSubmit = async (data) => {
    setIsSubmitting(true);
    setSubmitError(null);
    try {
      if (editingMember) {
        await updateMember(editingMember.id, data);
      } else {
        await createMember(data);
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
      await deleteMember(deleteTarget.id);
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
    <PermissionGate permission="members.view">
      <div className="flex flex-col gap-6">
        <div className="flex flex-wrap items-end justify-between gap-4">
          <div>
            <h1 className="text-2xl font-semibold text-csit-text">Members</h1>
            <p className="mt-1 text-sm text-csit-text-muted">
              Directory of registered society members
            </p>
          </div>
          <div className="flex items-center gap-2">
            <Button variant="outline" onClick={() => setImportOpen(true)}>
              <Upload />
              Import members
            </Button>
            <Button onClick={openCreate}>
              <Plus />
              Add member
            </Button>
          </div>
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
                placeholder="Search by name, student ID or email…"
                className="pl-8"
              />
            </div>
            <select
              value={role}
              onChange={(event) => setRole(event.target.value)}
              className="h-8 rounded-lg border border-input bg-transparent px-2.5 text-sm outline-none transition-colors focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
              aria-label="Filter by role"
            >
              <option value="">All roles</option>
              {MEMBER_ROLE_OPTIONS.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          </div>

          {error ? (
            <div className="rounded-2xl border border-rose-200 bg-rose-50/50 p-6 text-sm text-rose-600">
              <div>Failed to load members. Please try again.</div>
              <Button
                type="button"
                variant="outline"
                onClick={refetch}
                className="mt-3 border-rose-300 text-rose-600 hover:bg-rose-100"
              >
                Retry
              </Button>
            </div>
          ) : members.length === 0 && !isLoading ? (
            <Empty className="border-0 py-16">
              <EmptyHeader>
                <EmptyMedia variant="icon">
                  <Users />
                </EmptyMedia>
                <EmptyTitle>No members found</EmptyTitle>
                <EmptyDescription>
                  {search || role
                    ? "Try adjusting your search or role filter."
                    : "Get started by adding your first member."}
                </EmptyDescription>
              </EmptyHeader>
            </Empty>
          ) : (
            <Table>
              <TableHeader>
                <TableRow className="hover:bg-transparent">
                  <TableHead className="px-4">Name</TableHead>
                  <TableHead>Student ID</TableHead>
                  <TableHead>Email</TableHead>
                  <TableHead>Academic track</TableHead>
                  <TableHead>Enrolled</TableHead>
                  <TableHead>Study year</TableHead>
                  <TableHead>Roles</TableHead>
                  <TableHead className="px-4 text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              {isLoading ? (
                <TableSkeleton />
              ) : (
                <TableBody>
                  {members.map((member) => (
                    <TableRow key={member.id}>
                      <TableCell className="px-4">
                        <div className="flex items-center gap-2.5">
                          <Avatar>
                            <AvatarFallback>
                              {initialsOf(member.full_name)}
                            </AvatarFallback>
                          </Avatar>
                          <span className="font-medium text-csit-text">
                            {member.full_name}
                          </span>
                        </div>
                      </TableCell>
                      <TableCell className="text-csit-text-muted">
                        {member.student_id}
                      </TableCell>
                      <TableCell className="text-csit-text-muted">
                        {member.email}
                      </TableCell>
                      <TableCell className="text-csit-text-muted">
                        {academicTrackLabel(member.academic_track) ?? "—"}
                      </TableCell>
                      <TableCell className="text-csit-text-muted">
                        {member.enrolled_year ?? "—"}
                      </TableCell>
                      <TableCell className="text-csit-text-muted">
                        {member.study_year ? `${member.study_year}º` : "—"}
                      </TableCell>
                      <TableCell>
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
                      </TableCell>
                      <TableCell className="px-4">
                        <div className="flex items-center justify-end gap-1">
                          <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={() => openEdit(member)}
                            aria-label={`Edit ${member.full_name}`}
                          >
                            <Pencil className="text-csit-text-muted" />
                          </Button>
                          <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={() => {
                              setDeleteError(null);
                              setDeleteTarget(member);
                            }}
                            aria-label={`Delete ${member.full_name}`}
                            className="hover:bg-rose-50 hover:text-rose-600"
                          >
                            <Trash2 className="text-csit-text-muted" />
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
                  : `Showing ${from}–${to} of ${total} members`}
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

        <MemberFormDialog
          open={formOpen}
          onOpenChange={setFormOpen}
          member={editingMember}
          onSubmit={handleSubmit}
          isSubmitting={isSubmitting}
          error={submitError}
        />

        <MemberImportDialog
          open={importOpen}
          onOpenChange={setImportOpen}
          onImported={refetch}
        />

        <Dialog
          open={Boolean(deleteTarget)}
          onOpenChange={(open) => {
            if (!open) setDeleteTarget(null);
          }}
        >
          <DialogContent className="sm:max-w-md">
            <DialogHeader>
              <DialogTitle>Delete member</DialogTitle>
              <DialogDescription>
                Are you sure you want to delete{" "}
                <span className="font-medium text-foreground">
                  {deleteTarget?.full_name}
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
                {isDeleting ? "Deleting…" : "Delete member"}
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </div>
    </PermissionGate>
  );
}
