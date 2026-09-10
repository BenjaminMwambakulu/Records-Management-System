"use client";

import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuItem } from "@/components/ui/sidebar";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Button } from "@/components/ui/button";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import { Link } from "react-router-dom";
import { useAuth } from "@/Context/AuthContext";
import { getImagePath } from "@/lib/utils";
import { NavMain } from "@/components/shadcn-space/blocks/sidebar-01/nav-main";
import { CalendarDays, FolderOpen, GraduationCap, History, LayoutDashboard, LogOut, Package, Shield, Users, Wallet } from "lucide-react";
import { hasPermission } from "@/lib/permissions";

export const navData = [
  { label: "Overview", isSection: true },
  { title: "Dashboard", icon: LayoutDashboard, href: "/app", permission: "dashboard.view" },

  { label: "Management", isSection: true },
  { title: "Members", icon: Users, href: "/app/members", permission: "members.view" },
  { title: "My Students", icon: GraduationCap, href: "/app/my-students", permission: "members.year_rep.manage" },
  { title: "Events", icon: CalendarDays, href: "/app/events", permission: "events.view" },
  { title: "Financial Records", icon: Wallet, href: "/app/financial-records", permission: "financials.view" },
  { title: "Assets", icon: Package, href: "/app/assets", permission: "assets.view" },
  { title: "Documents", icon: FolderOpen, href: "/app/documents", permission: "documents.view" },

  { label: "Admin", isSection: true },
  { title: "Activity Logs", icon: History, href: "/app/logs", permission: "activity_logs.view" },
  { title: "Roles & Permissions", icon: Shield, href: "/app/roles", permission: "roles.manage" },
];

function visibleNavData(user) {
  const result = [];
  let pendingSection = null;
  for (const item of navData) {
    if (item.isSection) {
      pendingSection = item;
      continue;
    }
    if (!hasPermission(user, item.permission)) continue;
    if (pendingSection) {
      result.push(pendingSection);
      pendingSection = null;
    }
    result.push(item);
  }
  return result;
}

export function AppSidebar() {
  const { user, logout } = useAuth();

  const rawName = user?.name;
  const isPlaceholderName =
    !rawName ||
    /^(undefined|null|NaN|\[object Object\])(\s+(undefined|null|NaN))?$/i.test(
      rawName.trim()
    );
  const displayName = isPlaceholderName
    ? user?.username || "Guest"
    : rawName;
  const displayEmail = user?.email || "Not signed in";
  const initials = displayName
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0])
    .join("")
    .toUpperCase();

  const handleLogout = () => {
    logout(import.meta.env.VITE_LOGTO_POST_LOGOUT_REDIRECT_URI);
  };

  return (
    <Sidebar collapsible="icon" className="px-0 h-full [&_[data-slot=sidebar-inner]]:h-full">
      <div className="flex h-full flex-col gap-6">
        {/* ---------------- Header ---------------- */}
        <SidebarHeader className="px-4 pt-5">
          <SidebarMenu>
            <SidebarMenuItem>
              <a href="#" className="w-full h-full flex items-center px-2 group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:px-0">
                <img
                  src={getImagePath("csit-logo.png")}
                  alt="CSIT Society Logo"
                  className="h-10 w-auto object-contain group-data-[collapsible=icon]:h-8 group-data-[collapsible=icon]:w-8 group-data-[collapsible=icon]:object-cover group-data-[collapsible=icon]:object-center"
                />
              </a>
            </SidebarMenuItem>
          </SidebarMenu>
        </SidebarHeader>

        {/* ---------------- Content ---------------- */}
        <SidebarContent className="min-h-0 flex-1 overflow-hidden">
          <ScrollArea className="h-full">
            <div className="px-4 group-data-[collapsible=icon]:px-2">
              <NavMain items={visibleNavData(user)} />
            </div>
          </ScrollArea>
        </SidebarContent>

        {/* ---------------- Footer ---------------- */}
        <SidebarFooter>
          <SidebarMenu>
            <SidebarMenuItem>
              <div className="flex items-center gap-3 px-3 py-2 group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:px-0">
                <Link
                  to="/app/settings"
                  className="flex min-w-0 flex-1 items-center gap-3 group-data-[collapsible=icon]:justify-center"
                >
                  <Avatar className="size-9 shrink-0">
                    {user?.avatar ? (
                      <AvatarImage src={user.avatar} alt={displayName} />
                    ) : null}
                    <AvatarFallback>{initials}</AvatarFallback>
                  </Avatar>
                  <div className="grid min-w-0 flex-1 gap-0.5 leading-none group-data-[collapsible=icon]:hidden">
                    <span className="truncate text-sm font-medium text-foreground group-hover:underline">
                      {displayName}
                    </span>
                    <span className="truncate text-xs text-muted-foreground">
                      {displayEmail}
                    </span>
                  </div>
                </Link>
                <TooltipProvider delayDuration={200}>
                  <Tooltip>
                    <TooltipTrigger
                      render={
                        <Button
                          variant="ghost"
                          size="icon"
                          className="size-8 shrink-0 cursor-pointer group-data-[collapsible=icon]:hidden"
                          onClick={handleLogout}
                          aria-label="Log out"
                        >
                          <LogOut className="size-4" />
                        </Button>
                      }
                    />
                    <TooltipContent side="right">Log out</TooltipContent>
                  </Tooltip>
                </TooltipProvider>
              </div>
            </SidebarMenuItem>
          </SidebarMenu>
        </SidebarFooter>
      </div>
    </Sidebar>
  );
}
