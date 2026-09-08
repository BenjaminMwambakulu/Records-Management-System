import React from "react";
import { TrendingDown, TrendingUp } from "lucide-react";

export default function SummaryCard({
  title,
  value,
  description,
  icon,
  variant = "default",
  className = "",
  isTrendingUp,
}) {
  // Styles for the main card container based on variant type
  const variantStyles = {
    default: "border-slate-200 hover:border-slate-300",
    success: "border-emerald-100 bg-emerald-50/20 hover:border-emerald-200",
    warning: "border-amber-100 bg-amber-50/20 hover:border-amber-200",
    danger: "border-rose-100 bg-rose-50/20 hover:border-rose-200",
  };

  // Styles for the action/icon container matching the variant
  const iconStyles = {
    default: "border-slate-100 bg-slate-50 text-slate-600",
    success: "border-emerald-100 bg-emerald-50 text-emerald-600",
    warning: "border-amber-100 bg-amber-50 text-amber-600",
    danger: "border-neutral-200/50 bg-rose-50 text-rose-600",
  };

  return (
    <div
      className={`
        rounded-2xl border bg-white
        transition-all duration-300 ease-out
        hover:shadow-md hover:-translate-y-0.5
        ${variantStyles[variant]}
        ${className}
      `}
    >
      <div className="p-6">
        <div className="flex items-start justify-between gap-4">
          {/* Left Content Column */}
          <div className="space-y-2 flex-1 min-w-0">
            <p className="text-xs font-semibold tracking-wide uppercase text-slate-500 truncate">
              {title}
            </p>

            <h3 className="text-3xl font-bold tracking-tight text-slate-900">
              {value ?? 0}
            </h3>

            {description && (
              <div className="flex items-center gap-1.5 text-xs font-medium mt-1">
                {typeof isTrendingUp === "boolean" &&
                  (isTrendingUp ? (
                    <span className="flex items-center gap-0.5 text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded">
                      <TrendingUp size={14} />
                    </span>
                  ) : (
                    <span className="flex items-center gap-0.5 text-rose-600 bg-rose-50 px-1.5 py-0.5 rounded">
                      <TrendingDown size={14} />
                    </span>
                  ))}

                <span className="text-slate-500 truncate" title={description}>
                  {description}
                </span>
              </div>
            )}
          </div>

          {/* Right Icon Block */}
          {icon && (
            <div
              className={`flex h-12 w-12 shrink-0 items-center justify-center rounded-xl border font-medium ${iconStyles[variant]}`}
            >
              {icon}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
