import { useState } from "react";
import {
  ChevronLeft,
  ChevronRight,
  Eye,
  GraduationCap,
  Pencil,
  Plus,
  Search,
  Trash2,
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
import useYearRepStudents from "@/hooks/useYearRepStudents";
import { ACADEMIC_TRACK_OPTIONS } from "@/hooks/useMembersDirectory";
import StudentFormDialog, { extractErrorMessage } from "./StudentFormDialog";
import PermissionGate from "@/components/PermissionGate";
import MemberViewDialog from "@/components/MemberViewDialog";

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
        </TableRow>
      ))}
    </TableBody>
  );
}

export default function MyStudentsIndex() {
  const {
    students,
    pagination,
    isLoading,
    error,
    search,
    setSearch,
    page,
    setPage,
    refetch,
    createStudent,
    updateStudent,
    deleteStudent,
    stats,
  } = useYearRepStudents();

  const [formOpen, setFormOpen] = useState(false);
  const [editingStudent, setEditingStudent] = useState(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState(null);

  const [deleteTarget, setDeleteTarget] = useState(null);
  const [isDeleting, setIsDeleting] = useState(false);
  const [deleteError, setDeleteError] = useState(null);

  const [viewStudent, setViewStudent] = useState(null);

  const openCreate = () => {
    setEditingStudent(null);
    setSubmitError(null);
    setFormOpen(true);
  };

  const openEdit = (student) => {
    setEditingStudent(student);
    setSubmitError(null);
    setFormOpen(true);
  };

  const handleSubmit = async (data) => {
    setIsSubmitting(true);
    setSubmitError(null);
    try {
      if (editingStudent) {
        await updateStudent(editingStudent.id, data);
      } else {
        await createStudent(data);
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
      await deleteStudent(deleteTarget.id);
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
    <PermissionGate permission="members.year_rep.manage">
      <div className="flex flex-col gap-6">
        <div className="flex flex-wrap items-end justify-between gap-4">
          <div>
            <h1 className="text-2xl font-semibold text-csit-text">My Students</h1>
            <p className="mt-1 text-sm text-csit-text-muted">
              {stats
                ? `${academicTrackLabel(stats.academic_track)} — Year ${stats.study_year} (${stats.total_students} students)`
                : "Students in your academic track and year"}
            </p>
          </div>
          <Button onClick={openCreate}>
            <Plus />
            Add student
          </Button>
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
          </div>

          {error ? (
            <div className="rounded-2xl border border-rose-200 bg-rose-50/50 p-6 text-sm text-rose-600">
              <div>Failed to load students. Please try again.</div>
              <Button
                type="button"
                variant="outline"
                onClick={refetch}
                className="mt-3 border-rose-300 text-rose-600 hover:bg-rose-100"
              >
                Retry
              </Button>
            </div>
          ) : students.length === 0 && !isLoading ? (
            <Empty className="border-0 py-16">
              <EmptyHeader>
                <EmptyMedia variant="icon">
                  <GraduationCap />
                </EmptyMedia>
                <EmptyTitle>No students found</EmptyTitle>
                <EmptyDescription>
                  {search
                    ? "Try adjusting your search."
                    : "Get started by adding your first student."}
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
                  <TableHead>Enrolled</TableHead>
                  <TableHead>Study Year</TableHead>
                  <TableHead className="px-4 text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              {isLoading ? (
                <TableSkeleton />
              ) : (
                <TableBody>
                  {students.map((student) => (
                    <TableRow key={student.id}>
                      <TableCell className="px-4">
                        <div className="flex items-center gap-2.5">
                          <Avatar>
                            <AvatarFallback>
                              {initialsOf(student.full_name)}
                            </AvatarFallback>
                          </Avatar>
                          <span className="font-medium text-csit-text">
                            {student.full_name}
                          </span>
                        </div>
                      </TableCell>
                      <TableCell className="text-csit-text-muted">
                        {student.student_id}
                      </TableCell>
                      <TableCell className="text-csit-text-muted">
                        {student.email}
                      </TableCell>
                      <TableCell className="text-csit-text-muted">
                        {student.enrolled_year ?? "—"}
                      </TableCell>
                      <TableCell className="text-csit-text-muted">
                        {student.study_year ? `${student.study_year}º` : "—"}
                      </TableCell>
                      <TableCell className="px-4">
                        <div className="flex items-center justify-end gap-1">
                          <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={() => setViewStudent(student)}
                            aria-label={`View ${student.full_name}`}
                          >
                            <Eye className="text-csit-text-muted" />
                          </Button>
                          <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={() => openEdit(student)}
                            aria-label={`Edit ${student.full_name}`}
                          >
                            <Pencil className="text-csit-text-muted" />
                          </Button>
                          <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={() => {
                              setDeleteError(null);
                              setDeleteTarget(student);
                            }}
                            aria-label={`Delete ${student.full_name}`}
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
                  : `Showing ${from}–${to} of ${total} students`}
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

        <StudentFormDialog
          open={formOpen}
          onOpenChange={setFormOpen}
          student={editingStudent}
          onSubmit={handleSubmit}
          isSubmitting={isSubmitting}
          error={submitError}
        />

        <MemberViewDialog
          member={viewStudent}
          open={Boolean(viewStudent)}
          onOpenChange={(open) => {
            if (!open) setViewStudent(null);
          }}
        />

        <Dialog
          open={Boolean(deleteTarget)}
          onOpenChange={(open) => {
            if (!open) setDeleteTarget(null);
          }}
        >
          <DialogContent className="sm:max-w-md">
            <DialogHeader>
              <DialogTitle>Delete student</DialogTitle>
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
                {isDeleting ? "Deleting…" : "Delete student"}
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </div>
    </PermissionGate>
  );
}
