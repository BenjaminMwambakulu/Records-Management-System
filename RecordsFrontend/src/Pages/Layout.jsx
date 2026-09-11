import { Link, Outlet } from "react-router-dom";
import { AppSidebar } from "@/components/shadcn-space/blocks/sidebar-01/app-sidebar";
import { SidebarInset, SidebarProvider, SidebarTrigger } from "@/components/ui/sidebar";
import { Separator } from "@/components/ui/separator";
import { Button } from "@/components/ui/button";
import { Home } from "lucide-react";
import PageBreadcrumb from "@/components/PageBreadcrumb";
import CopyTokenButton from "@/components/CopyTokenButton";

export default function Layout() {
  return (
    <SidebarProvider>
      <AppSidebar />
      <SidebarInset className="bg-csit-surface">
        <header className="flex h-16 shrink-0 items-center gap-2 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12">
          <div className="flex items-center gap-2 px-4">
            <SidebarTrigger className="-ml-1" />
            <Separator orientation="vertical" className="mr-2 data-[orientation=vertical]:h-4" />
            <PageBreadcrumb />
          </div>
          <div className="ml-auto flex items-center gap-2 px-4">
            <CopyTokenButton />
            <Link to="/" aria-label="View public site">
              <Button variant="ghost" size="sm" className="hidden h-8 gap-1.5 px-3 text-xs font-medium text-csit-text-muted hover:text-csit-text sm:inline-flex">
                <Home className="size-3.5" />
                View site
              </Button>
            </Link>
          </div>
        </header>
        <div className="flex flex-1 flex-col gap-4 p-4 pt-0">
          <Outlet />
        </div>
      </SidebarInset>
    </SidebarProvider>
  );
}