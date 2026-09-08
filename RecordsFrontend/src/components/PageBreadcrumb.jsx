import { Link, useLocation } from "react-router-dom";
import { ChevronRight } from "lucide-react";
import { navData } from "@/components/shadcn-space/blocks/sidebar-01/app-sidebar";

function findCurrentPageTitle(route) {
  for (const item of navData) {
    if (item.isSection) continue;
    if (item.href && route === item.href) return item.title;
    if (item.children?.some((child) => child.href && route.startsWith(child.href)))
      return item.title;
    if (item.href && item.href !== "/" && route.startsWith(item.href)) return item.title;
  }
  return null;
}

export default function PageBreadcrumb() {
  const currentTitle = findCurrentPageTitle(useLocation().pathname);

  return (
    <nav aria-label="Breadcrumb">
      <ol className="flex items-center gap-1.5 text-sm">
        <li>
          <Link
            to="/"
            className="text-muted-foreground transition-colors hover:text-foreground"
          >
            Home
          </Link>
        </li>
        {currentTitle ? (
          <>
            <li aria-hidden="true">
              <ChevronRight className="size-4 text-muted-foreground/60" />
            </li>
            <li>
              <span className="font-medium text-foreground">{currentTitle}</span>
            </li>
          </>
        ) : null}
      </ol>
    </nav>
  );
}