import { useNavigate, useRouterState } from "@tanstack/react-router";
import { ChevronRight, LayoutDashboard, LayoutGrid } from "lucide-react";

import emblem from "@/assets/brand/concord-emblem.png";

interface SidebarProps {
  collapsed: boolean;
}

export function Sidebar({ collapsed }: SidebarProps) {
  const navigate = useNavigate();
  const pathname = useRouterState({
    select: (state) => state.location.pathname,
  });

  const isDashboard = pathname === "/";
  const isLegacyApps = pathname === "/legacy-apps";

  const navigationClass = (active: boolean) =>
    `flex w-full items-center rounded-lg py-3 text-sm font-medium transition-colors ${
      collapsed ? "justify-center px-0" : "gap-3 px-3"
    } ${
      active
        ? "bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300"
        : "text-muted-foreground hover:bg-muted hover:text-foreground"
    }`;

  return (
    <aside
      className={`fixed inset-y-0 left-0 z-40 flex flex-col border-r bg-card transition-[width] duration-200 ${
        collapsed ? "w-16" : "w-64"
      }`}
    >
      <div
        className={`flex h-16 shrink-0 items-center border-b ${
          collapsed ? "justify-center px-2" : "gap-3 px-3"
        }`}
      >
        <button
          type="button"
          onClick={() => navigate({ to: "/" })}
          aria-label="Go to Concord dashboard"
          title="Go to dashboard"
          className={`flex min-w-0 items-center rounded-md text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ${
            collapsed ? "justify-center" : "flex-1 gap-3"
          }`}
        >
          <img
            src={emblem}
            alt=""
            aria-hidden="true"
            className="h-11 w-11 shrink-0 object-contain"
          />

          {!collapsed && (
            <div className="min-w-0 flex-1">
              <div className="concord-wordmark text-sm">CONCORD</div>
              <div className="mt-1 text-xs text-muted-foreground">
                Dashboard
              </div>
            </div>
          )}
        </button>

        {!collapsed && (
          <span className="shrink-0 rounded-full bg-muted px-2 py-1 text-xs font-medium text-muted-foreground">
            V1.0
          </span>
        )}
      </div>

      <nav className="flex-1 space-y-2 overflow-y-auto p-3">
        {!collapsed && (
          <p className="px-2 pb-1 pt-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
            Dashboard
          </p>
        )}

        <button
          type="button"
          onClick={() => navigate({ to: "/" })}
          title={collapsed ? "Dashboard" : undefined}
          aria-current={isDashboard ? "page" : undefined}
          className={navigationClass(isDashboard)}
        >
          <LayoutDashboard className="h-5 w-5 shrink-0" />
          {!collapsed && <span>Dashboard</span>}
        </button>

        {!collapsed && (
          <p className="px-2 pb-1 pt-5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
            Workspace
          </p>
        )}

        <button
          type="button"
          onClick={() => navigate({ to: "/legacy-apps" })}
          title={collapsed ? "Legacy Apps" : undefined}
          aria-current={isLegacyApps ? "page" : undefined}
          className={navigationClass(isLegacyApps)}
        >
          <LayoutGrid className="h-5 w-5 shrink-0" />
          {!collapsed && <span>Legacy Apps</span>}
        </button>
      </nav>

      <div className="shrink-0 border-t p-3">
        {collapsed ? (
          <div className="flex justify-center" title="Concord Portal">
            <ChevronRight className="h-5 w-5 text-muted-foreground" />
          </div>
        ) : (
          <p className="text-xs text-muted-foreground">Concord Portal</p>
        )}
      </div>
    </aside>
  );
}
