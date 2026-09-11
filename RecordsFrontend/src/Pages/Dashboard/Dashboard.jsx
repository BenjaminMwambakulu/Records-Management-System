import React, { useMemo } from "react";
import { Link } from "react-router-dom";
import {
  CalendarDays,
  FileText,
  FolderTree,
  Users,
  Package,
  DollarSign,
  Clock,
} from "lucide-react";
import SummaryCard from "@/components/ui/SummaryCard";
import { Skeleton } from "@/components/ui/skeleton";
import useDashboardSummary from "@/hooks/useDashboardSummary";
import PermissionGate from "@/components/PermissionGate";
import { useAuth } from "@/Context/AuthContext";
import { hasPermission } from "@/lib/permissions";

const formatCurrency = (amount) =>
  `K ${Number(amount).toLocaleString("en-US", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`;

const buildCards = (data) => [
  {
    title: "Members",
    value: data.members,
    description: "Registered members",
    icon: <Users size={20} />,
    variant: "default",
    permission: "members.view",
  },
  {
    title: "Events",
    value: data.events,
    description: "Society events",
    icon: <CalendarDays size={20} />,
    variant: "default",
    permission: "events.view",
  },
  {
    title: "Documents",
    value: data.documents,
    description: "Stored documents",
    icon: <FileText size={20} />,
    variant: "default",
    permission: "documents.view",
  },
  {
    title: "Categories",
    value: data.document_categories,
    description: "Document categories",
    icon: <FolderTree size={20} />,
    variant: "default",
    permission: "documents.view",
  },
  {
    title: "Assets",
    value: data.assets,
    description: `${data.assets_on_loan ?? 0} currently on loan`,
    icon: <Package size={20} />,
    variant: "default",
    permission: "assets.view",
  },
  {
    title: "Financial Records",
    value: data.financial_records,
    description: `Income: ${formatCurrency(data.total_income ?? 0)}`,
    icon: <DollarSign size={20} />,
    variant: "success",
    permission: "financials.view",
  },
  {
    title: "Total Expense",
    value: formatCurrency(data.total_expense ?? 0),
    description: "Total expenses recorded",
    icon: <DollarSign size={20} />,
    variant: "danger",
    permission: "financials.view",
  },
];

function SummaryCardSkeleton() {
  return (
    <div className="rounded-2xl border border-csit-border bg-white p-6">
      <div className="flex items-start justify-between gap-4">
        <div className="space-y-3 flex-1 min-w-0">
          <Skeleton className="h-3 w-24" />
          <Skeleton className="h-8 w-16" />
          <Skeleton className="h-3 w-32" />
        </div>
        <Skeleton className="h-12 w-12 shrink-0 rounded-xl" />
      </div>
    </div>
  );
}

function ActivityItem({ activity }) {
  const causerName = activity.causer
    ? `${activity.causer.first_name ?? ""} ${activity.causer.last_name ?? ""}`.trim()
    : "System";

    const eventColors = {
      created: "bg-emerald-500",
      updated: "bg-blue-500",
      deleted: "bg-rose-500",
    };

    const dotColor = eventColors[activity.event] ?? "bg-slate-400";

    return (
    <div className="flex items-start gap-3 py-3">
      <div className={`mt-1 h-2 w-2 shrink-0 rounded-full ${dotColor}`} />
      <div className="flex-1 min-w-0">
        <p className="text-sm text-slate-700">
          <span className="font-medium">{causerName}</span>{" "}
          {activity.description}
        </p>
        <p className="text-xs text-slate-400 mt-0.5">
          {activity.created_at
            ? new Date(activity.created_at).toLocaleString()
            : ""}
        </p>
      </div>
    </div>
  );
}

export default function Dashboard() {
  const { user } = useAuth();
  const { data, isLoading, error, refetch } = useDashboardSummary();
  const visibleCards = useMemo(
    () =>
      buildCards(data ?? {}).filter((card) =>
        hasPermission(user, card.permission)
      ),
    [data, user]
  );

  return (
    <PermissionGate permission="dashboard.view">
      <div className="flex flex-col gap-6">
        <div>
          <h1 className="text-2xl font-semibold text-csit-text">Dashboard</h1>
          <p className="text-sm text-csit-text-muted mt-1">
            Overview of the society&apos;s records
          </p>
        </div>

        {error ? (
          <div className="rounded-2xl border border-rose-200 bg-rose-50/50 p-6 text-sm text-rose-600">
            <div>Failed to load dashboard summary. Please try again.</div>
            <button
              type="button"
              onClick={refetch}
              className="mt-3 rounded-lg border border-rose-300 bg-white px-3 py-1.5 text-xs font-medium text-rose-600 transition-colors hover:bg-rose-100"
            >
              Retry
            </button>
          </div>
        ) : (
          <>
            {visibleCards.length > 0 ? (
              <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {isLoading
                  ? Array.from({ length: visibleCards.length }).map((_, index) => (
                      <SummaryCardSkeleton key={index} />
                    ))
                  : visibleCards.map((card) => (
                      <SummaryCard key={card.title} {...card} />
                    ))}
              </div>
            ) : (
              !isLoading && (
                <div className="rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-400">
                  No dashboard sections are available for your access level.
                </div>
              )
            )}

            <div className="grid gap-4 lg:grid-cols-2">
              <PermissionGate permission="activity_logs.view" fallback={null}>
                <div className="rounded-2xl border border-slate-200 bg-white p-6">
                  <div className="flex items-center justify-between mb-4">
                    <div className="flex items-center gap-2">
                      <Clock size={18} className="text-slate-500" />
                      <h2 className="text-sm font-semibold text-slate-700">
                        Recent Activity
                      </h2>
                    </div>
                    <Link
                      to="/app/logs"
                      className="text-xs font-medium text-blue-600 hover:text-blue-700 transition-colors"
                    >
                      View All
                    </Link>
                  </div>
                  {isLoading ? (
                    <div className="space-y-3">
                      {Array.from({ length: 5 }).map((_, index) => (
                        <Skeleton key={index} className="h-10 w-full" />
                      ))}
                    </div>
                  ) : data.recent_activity?.length > 0 ? (
                    <div className="divide-y divide-slate-100">
                      {data.recent_activity.map((activity) => (
                        <ActivityItem key={activity.id} activity={activity} />
                      ))}
                    </div>
                  ) : (
                    <p className="text-sm text-slate-400">No recent activity</p>
                  )}
                </div>
              </PermissionGate>

              <PermissionGate permission="documents.view" fallback={null}>
                <div className="rounded-2xl border border-slate-200 bg-white p-6">
                  <div className="flex items-center justify-between mb-4">
                    <div className="flex items-center gap-2">
                      <FileText size={18} className="text-slate-500" />
                      <h2 className="text-sm font-semibold text-slate-700">
                        Recent Documents
                      </h2>
                    </div>
                    <Link
                      to="/app/documents"
                      className="text-xs font-medium text-blue-600 hover:text-blue-700 transition-colors"
                    >
                      View All
                    </Link>
                  </div>
                  {isLoading ? (
                    <div className="space-y-3">
                      {Array.from({ length: 5 }).map((_, index) => (
                        <Skeleton key={index} className="h-10 w-full" />
                      ))}
                    </div>
                  ) : data.recent_documents?.length > 0 ? (
                    <div className="divide-y divide-slate-100">
                      {data.recent_documents.map((doc) => (
                        <div key={doc.id} className="py-3">
                          <p className="text-sm font-medium text-slate-700 truncate">
                            {doc.title}
                          </p>
                          <p className="text-xs text-slate-400 mt-0.5">
                            {doc.category?.name ?? "Uncategorized"}
                            {doc.created_at
                              ? ` — ${new Date(doc.created_at).toLocaleDateString()}`
                              : ""}
                          </p>
                        </div>
                      ))}
                    </div>
                  ) : (
                    <p className="text-sm text-slate-400">No documents yet</p>
                  )}
                </div>
              </PermissionGate>
            </div>
          </>
        )}
      </div>
    </PermissionGate>
  );
}