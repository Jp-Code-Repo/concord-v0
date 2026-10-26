import { useState } from "react";
import { useAuth } from "@/contexts/AuthContext";

import {
  AppWindow,
  ArrowRight,
  BookOpen,
  ChartNoAxesColumn,
  ChevronDown,
  ClipboardList,
  GraduationCap,
  HeartPulse,
  LayoutGrid,
  ShieldCheck,
  Users,
  UsersRound,
  Wallet,
  Wrench,
} from "lucide-react";

import type { LucideIcon } from "lucide-react";

import { Button } from "@/components/ui/button";

const legacyAppIcons: Record<string, LucideIcon> = {
  books: BookOpen,
  users: Users,
  "graduation-cap": GraduationCap,
  "clipboard-list": ClipboardList,
  "heart-pulse": HeartPulse,
  wallet: Wallet,
  "chart-no-axes-column": ChartNoAxesColumn,
  "shield-check": ShieldCheck,
  "users-round": UsersRound,
  wrench: Wrench,
};

const iconColors = [
  "bg-blue-600 text-white",
  "bg-emerald-500 text-white",
  "bg-violet-600 text-white",
  "bg-orange-500 text-white",
  "bg-cyan-600 text-white",
  "bg-rose-600 text-white",
];

const arrowColors = [
  "bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300",
  "bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300",
  "bg-violet-100 text-violet-700 dark:bg-violet-950 dark:text-violet-300",
  "bg-orange-100 text-orange-700 dark:bg-orange-950 dark:text-orange-300",
  "bg-cyan-100 text-cyan-700 dark:bg-cyan-950 dark:text-cyan-300",
  "bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300",
];

export function ApplicationsSection() {
  const [showAll, setShowAll] = useState(false);
  const { legacyApps } = useAuth();

  const visibleApplications = showAll ? legacyApps : legacyApps.slice(0, 4);

  return (
    <section id="applications" className="min-w-0 rounded-2xl border bg-card">
      <div className="flex items-center justify-between border-b px-5 py-4">
        <div className="flex items-center gap-3">
          <LayoutGrid className="h-5 w-5 text-blue-600" />
          <h2 className="text-lg font-semibold">Legacy Apps</h2>
        </div>

        {legacyApps.length > 4 && (
          <Button
            variant="outline"
            size="sm"
            className="gap-2"
            onClick={() => setShowAll((current) => !current)}
            aria-expanded={showAll}
            aria-controls="legacy-apps-grid"
          >
            {showAll ? "Show Less" : "View All"}
            {showAll ? (
              <ChevronDown className="h-4 w-4 rotate-180" />
            ) : (
              <ArrowRight className="h-4 w-4" />
            )}
          </Button>
        )}
      </div>

      {legacyApps.length === 0 ? (
        <p className="p-5 text-sm text-muted-foreground">
          No legacy applications are currently available.
        </p>
      ) : (
        <div
          id="legacy-apps-grid"
          className="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2 2xl:grid-cols-4"
        >
          {visibleApplications.map((app, index) => {
            const Icon = legacyAppIcons[app.icon ?? ""] ?? AppWindow;
            const colorIndex = index % iconColors.length;

            return (
              <article
                key={app.id}
                className="group flex min-h-[205px] flex-col rounded-xl border p-4 transition-all hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-md dark:hover:border-blue-900"
              >
                <div
                  className={`flex h-12 w-12 items-center justify-center rounded-xl ${iconColors[colorIndex]}`}
                >
                  <Icon className="h-6 w-6" />
                </div>

                <h3 className="mt-4 font-semibold">{app.name}</h3>

                <p className="mt-2 text-sm leading-5 text-muted-foreground">
                  {app.description ?? "No description available."}
                </p>

                <div className="mt-auto flex justify-end pt-4">
                  <button
                    type="button"
                    aria-label={`${app.name} destination`}
                    title={app.url}
                    disabled
                    className={`flex h-9 w-9 items-center justify-center rounded-full opacity-70 ${arrowColors[colorIndex]}`}
                  >
                    <ArrowRight className="h-4 w-4" />
                  </button>
                </div>
              </article>
            );
          })}
        </div>
      )}
    </section>
  );
}
