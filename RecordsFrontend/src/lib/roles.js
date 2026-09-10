import { hasAnyPermission, hasPermission } from "./permissions";

export { hasPermission } from "./permissions";

const ADMIN_ROLE_NAMES = ["super_admin", "org_admin"];

function hasRole(user, roleNames) {
  if (!user || !Array.isArray(user.roles)) return false;
  return user.roles.some((role) => roleNames.includes(role?.name));
}

export function isAdminUser(user) {
  return hasRole(user, ADMIN_ROLE_NAMES);
}

export function isMember(user) {
  if (isAdminUser(user)) return false;
  return hasRole(user, ["member"]);
}

export function canAccessDashboard(user) {
  return hasPermission(user, "dashboard.view");
}

export function isDocumentManager(user) {
  return hasAnyPermission(user, ["documents.create", "documents.update", "documents.delete"]);
}

export function isEventManager(user) {
  return hasAnyPermission(user, ["events.create", "events.update", "events.delete"]);
}

export function isFinancialManager(user) {
  return hasPermission(user, "financials.view");
}

export function isAssetManager(user) {
  return hasPermission(user, "assets.view");
}
