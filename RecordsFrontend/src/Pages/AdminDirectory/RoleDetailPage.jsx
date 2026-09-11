import { useEffect, useState } from "react";
import { useParams, useNavigate } from "react-router-dom";
import { ArrowLeft, Loader2, Plus, Trash2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { extractErrorMessage, isValidationError } from "@/lib/errors";
import { notify } from "@/lib/toast";
import { useFormErrors } from "@/hooks/useFormErrors";
import useRolesDirectory from "@/hooks/useRolesDirectory";
import RoleFormDialog from "./RoleFormDialog";
import DeleteRoleDialog from "./DeleteRoleDialog";
import AssignUserDialog from "./AssignUserDialog";
import PermissionGate from "@/components/PermissionGate";

function ModuleSection({ module, permissions, selected, onToggle }) {
  const allChecked = permissions.every((p) => selected.includes(p));
  const someChecked = permissions.some((p) => selected.includes(p));

  const toggleAll = () => {
    if (allChecked) {
      onToggle(selected.filter((s) => !permissions.includes(s)));
    } else {
      const merged = [...new Set([...selected, ...permissions])];
      onToggle(merged);
    }
  };

  return (
    <div className="flex flex-col gap-2">
      <div className="flex items-center gap-2">
        <input
          type="checkbox"
          checked={allChecked}
          ref={(el) => { if (el) el.indeterminate = someChecked && !allChecked; }}
          onChange={toggleAll}
          className="h-4 w-4 rounded border-input accent-primary"
        />
        <span className="text-sm font-medium text-csit-text capitalize">{module}</span>
        <span className="text-xs text-csit-text-muted">
          {permissions.filter((p) => selected.includes(p)).length}/{permissions.length}
        </span>
      </div>
      <div className="ml-6 flex flex-wrap gap-2">
        {permissions.map((perm) => (
          <label key={perm} className="flex items-center gap-1.5 text-xs text-csit-text-muted">
            <input
              type="checkbox"
              checked={selected.includes(perm)}
              onChange={() => {
                onToggle(
                  selected.includes(perm)
                    ? selected.filter((s) => s !== perm)
                    : [...selected, perm]
                );
              }}
              className="h-3.5 w-3.5 rounded border-input accent-primary"
            />
            {perm.split(".")[1]}
          </label>
        ))}
      </div>
    </div>
  );
}

export default function RoleDetailPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const {
    currentRole, isLoading, error, permissions, isSubmitting,
    fetchRole, updateRole, deleteRole, syncPermissions, assignRoleToUser, removeRoleFromUser,
  } = useRolesDirectory();
  const editForm = useFormErrors();
  const deleteForm = useFormErrors();
  const assignForm = useFormErrors();
  const [selectedPerms, setSelectedPerms] = useState([]);
  const [editOpen, setEditOpen] = useState(false);
  const [deleteTarget, setDeleteTarget] = useState(null);
  const [assignOpen, setAssignOpen] = useState(false);
  const [removingUserId, setRemovingUserId] = useState(null);

  useEffect(() => {
    if (id) fetchRole(id);
  }, [id, fetchRole]);

  useEffect(() => {
    if (currentRole?.permissions) {
      setSelectedPerms(currentRole.permissions.map((p) => p.name));
    }
  }, [currentRole]);

  const handleUpdateRole = async (data) => {
    editForm.clear();
    try {
      await updateRole(currentRole.id, data);
      setEditOpen(false);
      notify.success("Role updated", `Role "${data.name}" was updated.`);
    } catch (err) {
      editForm.applyApiError(err);
      if (!isValidationError(err)) notify.error("Could not update role", extractErrorMessage(err));
    }
  };

  const handleDeleteRole = async (id) => {
    deleteForm.clear();
    try {
      await deleteRole(id);
      setDeleteTarget(null);
      notify.success("Role deleted", "Role was deleted.");
      navigate("/app/roles");
    } catch (err) {
      deleteForm.applyApiError(err);
      if (!isValidationError(err)) notify.error("Could not delete role", extractErrorMessage(err));
    }
  };

  const handleAssignUser = async (userId) => {
    assignForm.clear();
    try {
      await assignRoleToUser(currentRole.id, userId);
      setAssignOpen(false);
      notify.success("Member(s) added", "User was added to this role.");
    } catch (err) {
      assignForm.applyApiError(err);
      if (!isValidationError(err)) notify.error("Could not add member", extractErrorMessage(err));
    }
  };

  const handleRemoveUser = async (userId) => {
    const member = currentRole?.users?.find((u) => u.id === userId);
    const memberName = member ? `${member.first_name} ${member.last_name}` : "Member";
    if (!window.confirm(`Remove ${memberName} from the "${currentRole.name}" role? They will lose this role's permissions.`)) {
      return;
    }
    setRemovingUserId(userId);
    try {
      await removeRoleFromUser(currentRole.id, userId);
      notify.success("Member removed", `${memberName} was removed from this role.`);
    } catch (err) {
      notify.error("Could not remove member", extractErrorMessage(err));
    } finally {
      setRemovingUserId(null);
    }
  };

  const handleSyncPermissions = async () => {
    try {
      await syncPermissions(currentRole.id, selectedPerms);
      notify.success("Permissions saved", "Role permissions were updated.");
    } catch (err) {
      notify.error("Could not save permissions", extractErrorMessage(err));
    }
  };

  if (isLoading && !currentRole) {
    return (
      <div className="flex flex-col gap-6">
        <Skeleton className="h-8 w-48" />
        <Skeleton className="h-64 w-full" />
      </div>
    );
  }

  if (error) {
    return (
      <div className="flex flex-col gap-6" role="alert">
        <Button variant="ghost" size="sm" onClick={() => navigate("/app/roles")} className="w-fit">
          <ArrowLeft /> Back to roles
        </Button>
        <Card className="border-csit-border bg-white p-6">
          <p className="text-sm text-rose-600">{extractErrorMessage(error)}</p>
          <Button type="button" variant="outline" size="sm" onClick={() => fetchRole(id)} className="mt-4">
            Retry
          </Button>
        </Card>
      </div>
    );
  }

  return (
    <PermissionGate permission="roles.manage">
      <div className="flex flex-col gap-6">
        <div className="flex flex-wrap items-center justify-between gap-4">
          <div className="flex items-center gap-3">
            <Button variant="ghost" size="icon" onClick={() => navigate("/app/roles")} aria-label="Back">
              <ArrowLeft />
            </Button>
            <div>
              <h1 className="text-2xl font-semibold text-csit-text">{currentRole?.name}</h1>
              <p className="mt-1 text-sm text-csit-text-muted">
                {currentRole?.permissions?.length ?? 0} permissions · {currentRole?.users?.length ?? 0} users
              </p>
            </div>
          </div>
          <div className="flex items-center gap-2">
            <Button type="button" variant="outline" onClick={() => setEditOpen(true)}>
              Edit
            </Button>
            {currentRole?.name !== "superadmin" ? (
              <Button type="button" variant="outline" onClick={() => setDeleteTarget(currentRole)}>
                <Trash2 /> Delete
              </Button>
            ) : null}
          </div>
        </div>

        <Card className="border-csit-border bg-white p-6">
          <h2 className="mb-4 text-sm font-semibold uppercase tracking-wide text-csit-text-muted">Permissions</h2>
          <div className="flex flex-col gap-4">
            {Object.entries(permissions).map(([module, perms]) => (
              <ModuleSection
                key={module}
                module={module}
                permissions={perms}
                selected={selectedPerms}
                onToggle={setSelectedPerms}
              />
            ))}
          </div>
          <div className="mt-4 flex justify-end">
            <Button
              type="button"
              onClick={handleSyncPermissions}
              disabled={isSubmitting}
            >
              {isSubmitting ? (
                <>
                  <Loader2 className="size-4 animate-spin" />
                  Saving…
                </>
              ) : "Save permissions"}
            </Button>
          </div>
        </Card>

        <Card className="border-csit-border bg-white p-6">
          <div className="mb-4 flex items-center justify-between">
            <h2 className="text-sm font-semibold uppercase tracking-wide text-csit-text-muted">Assigned users</h2>
            <Button type="button" variant="outline" size="sm" onClick={() => setAssignOpen(true)}>
              <Plus /> Add user
            </Button>
          </div>
          {currentRole?.users?.length === 0 ? (
            <p className="py-4 text-center text-sm text-csit-text-muted">No users assigned to this role.</p>
          ) : (
            <div className="flex flex-col gap-2">
              {currentRole?.users?.map((u) => (
                <div key={u.id} className="flex items-center justify-between gap-3 rounded-lg border border-csit-border px-3 py-2">
                  <div className="min-w-0">
                    <p className="text-sm font-medium text-csit-text">{u.first_name} {u.last_name}</p>
                    <p className="truncate text-xs text-csit-text-muted">{u.email}</p>
                  </div>
                  {currentRole?.name !== "superadmin" || (currentRole?.users?.length ?? 0) > 1 ? (
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      onClick={() => handleRemoveUser(u.id)}
                      disabled={removingUserId === u.id}
                      aria-label={`Remove ${u.first_name}`}
                      className="hover:bg-rose-50 hover:text-rose-600"
                    >
                      {removingUserId === u.id ? (
                        <Loader2 className="size-4 animate-spin text-csit-text-muted" />
                      ) : (
                        <Trash2 className="text-csit-text-muted" />
                      )}
                    </Button>
                  ) : null}
                </div>
              ))}
            </div>
          )}
        </Card>

        <RoleFormDialog
          open={editOpen}
          onOpenChange={(open) => {
            if (!open && isSubmitting) return;
            setEditOpen(open);
            if (open) editForm.clear();
          }}
          role={currentRole}
          onSubmit={handleUpdateRole}
          isSubmitting={isSubmitting}
          error={editForm.formError}
          fieldErrors={editForm.fieldErrors}
          onFieldChange={editForm.clearField}
          fieldProps={editForm.fieldProps}
        />

        <DeleteRoleDialog
          open={Boolean(deleteTarget)}
          onOpenChange={(open) => {
            if (!open && isSubmitting) return;
            setDeleteTarget(open ? deleteTarget : null);
          }}
          role={deleteTarget}
          onDelete={handleDeleteRole}
          isSubmitting={isSubmitting}
          error={deleteForm.formError}
        />

        <AssignUserDialog
          open={assignOpen}
          onOpenChange={(open) => {
            if (!open && isSubmitting) return;
            setAssignOpen(open);
          }}
          onAssign={handleAssignUser}
          isSubmitting={isSubmitting}
          error={assignForm.formError}
          fieldErrors={assignForm.fieldErrors}
          onFieldChange={assignForm.clearField}
          fieldProps={assignForm.fieldProps}
        />
      </div>
    </PermissionGate>
  );
}