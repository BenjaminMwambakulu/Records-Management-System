export function hasPermission(user, permission) {
  if (!user || !Array.isArray(user.permissions)) return false;
  return user.permissions.includes(permission);
}

export function hasAnyPermission(user, permissions) {
  return permissions.some((p) => hasPermission(user, p));
}

export function hasAllPermissions(user, permissions) {
  return permissions.every((p) => hasPermission(user, p));
}
