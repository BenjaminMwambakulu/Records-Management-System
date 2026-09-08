import { useAuth } from "@/Context/AuthContext";
import { hasAnyPermission, hasAllPermissions } from "@/lib/permissions";
import AccessDenied from "./AccessDenied";

export default function PermissionGate({ permission, permissions, requireAll = false, fallback, children }) {
  const { user } = useAuth();
  const perms = permission ? [permission] : permissions;
  const check = requireAll ? hasAllPermissions : hasAnyPermission;

  if (!check(user, perms)) {
    return fallback || <AccessDenied />;
  }

  return children;
}
