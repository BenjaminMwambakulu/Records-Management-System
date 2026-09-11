import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { Plus, Shield, Trash2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { extractErrorMessage, isValidationError } from "@/lib/errors";
import { notify } from "@/lib/toast";
import { useFormErrors } from "@/hooks/useFormErrors";
import useRolesDirectory from "@/hooks/useRolesDirectory";
import RoleFormDialog from "./RoleFormDialog";
import DeleteRoleDialog from "./DeleteRoleDialog";
import PermissionGate from "@/components/PermissionGate";

function TableSkeleton() {
  return (
    <TableBody>
      {Array.from({ length: 4 }).map((_, index) => (
        <TableRow key={index}>
          <TableCell><Skeleton className="h-4 w-32" /></TableCell>
          <TableCell><Skeleton className="h-4 w-16" /></TableCell>
          <TableCell><Skeleton className="h-4 w-16" /></TableCell>
          <TableCell><Skeleton className="h-4 w-24" /></TableCell>
          <TableCell><Skeleton className="h-4 w-20" /></TableCell>
        </TableRow>
      ))}
    </TableBody>
  );
}

export default function RolesIndex() {
  const navigate = useNavigate();
  const { roles, isLoading, error, fetchRoles, createRole, deleteRole, isSubmitting } = useRolesDirectory();
  const createForm = useFormErrors();
  const deleteForm = useFormErrors();
  const [createOpen, setCreateOpen] = useState(false);
  const [deleteTarget, setDeleteTarget] = useState(null);

  const handleCreate = async (data) => {
    createForm.clear();
    try {
      await createRole(data);
      setCreateOpen(false);
      notify.success("Role created", `Role "${data.name}" was created.`);
    } catch (err) {
      createForm.applyApiError(err);
      if (!isValidationError(err)) notify.error("Could not create role", extractErrorMessage(err));
    }
  };

  const handleDelete = async (id) => {
    deleteForm.clear();
    try {
      await deleteRole(id);
      setDeleteTarget(null);
      notify.success("Role deleted", "Role was deleted.");
    } catch (err) {
      deleteForm.applyApiError(err);
      if (!isValidationError(err)) notify.error("Could not delete role", extractErrorMessage(err));
    }
  };

  return (
    <PermissionGate permission="roles.manage">
    <div className="flex flex-col gap-6">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold text-csit-text">Roles & Permissions</h1>
          <p className="mt-1 text-sm text-csit-text-muted">Manage roles and their permissions.</p>
        </div>
        <Button type="button" onClick={() => setCreateOpen(true)}>
          <Plus />
          Create role
        </Button>
      </div>

      <Card className="border-csit-border bg-white">
        {error ? (
          <div className="flex flex-col items-center gap-3 py-12" role="alert">
            <p className="text-sm text-rose-600">{extractErrorMessage(error)}</p>
            <Button type="button" variant="outline" size="sm" onClick={fetchRoles}>
              Retry
            </Button>
          </div>
        ) : (
          <Table>
            <TableHeader>
              <TableRow className="hover:bg-transparent">
                <TableHead className="px-4">Name</TableHead>
                <TableHead className="px-4">Permissions</TableHead>
                <TableHead className="px-4">Users</TableHead>
                <TableHead className="px-4">Created</TableHead>
                <TableHead className="px-4 text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            {isLoading ? (
              <TableSkeleton />
            ) : roles.length === 0 ? (
              <TableBody>
                <TableRow>
                  <TableCell colSpan={5}>
                    <Empty className="py-12">
                      <EmptyHeader>
                        <EmptyMedia variant="icon"><Shield /></EmptyMedia>
                        <EmptyTitle>No roles</EmptyTitle>
                        <EmptyDescription>Create your first role to get started.</EmptyDescription>
                      </EmptyHeader>
                    </Empty>
                  </TableCell>
                </TableRow>
              </TableBody>
            ) : (
              <TableBody>
                {roles.map((role) => (
                  <TableRow
                    key={role.id}
                    className="cursor-pointer"
                    onClick={() => navigate(`/app/roles/${role.id}`)}
                  >
                    <TableCell className="px-4 font-medium text-csit-text">{role.name}</TableCell>
                    <TableCell className="px-4">
                      <span className="inline-flex items-center rounded-full bg-sky-50 px-2.5 py-0.5 text-xs font-medium text-sky-700">
                        {role.permissions_count}
                      </span>
                    </TableCell>
                    <TableCell className="px-4">
                      <span className="inline-flex items-center rounded-full bg-violet-50 px-2.5 py-0.5 text-xs font-medium text-violet-700">
                        {role.users_count}
                      </span>
                    </TableCell>
                    <TableCell className="px-4 text-sm text-csit-text-muted">
                      {role.created_at ? new Date(role.created_at).toLocaleDateString() : "—"}
                    </TableCell>
                    <TableCell className="px-4 text-right">
                      {role.name !== "superadmin" ? (
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon"
                          onClick={(e) => { e.stopPropagation(); setDeleteTarget(role); }}
                          aria-label={`Delete ${role.name}`}
                          className="hover:bg-rose-50 hover:text-rose-600"
                        >
                          <Trash2 className="text-csit-text-muted" />
                        </Button>
                      ) : null}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            )}
          </Table>
        )}
      </Card>

      <RoleFormDialog
        open={createOpen}
        onOpenChange={(open) => {
          if (!open && isSubmitting) return;
          setCreateOpen(open);
          if (open) createForm.clear();
        }}
        onSubmit={handleCreate}
        isSubmitting={isSubmitting}
        error={createForm.formError}
        fieldErrors={createForm.fieldErrors}
        onFieldChange={createForm.clearField}
        fieldProps={createForm.fieldProps}
      />

      <DeleteRoleDialog
        open={Boolean(deleteTarget)}
        onOpenChange={(open) => {
          if (!open && isSubmitting) return;
          setDeleteTarget(open ? deleteTarget : null);
        }}
        role={deleteTarget}
        onDelete={handleDelete}
        isSubmitting={isSubmitting}
        error={deleteForm.formError}
      />
    </div>
    </PermissionGate>
  );
}