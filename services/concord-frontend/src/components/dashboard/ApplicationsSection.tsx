import { useAuth } from "@/contexts/AuthContext";

import {
  AppWindow,
  ArrowRight,
  BookOpen,
  ChartNoAxesColumn,
  ClipboardList,
  GraduationCap,
  HeartPulse,
  ShieldCheck,
  Users,
  UsersRound,
  Wallet,
  Wrench,
} from "lucide-react";

import type { LucideIcon } from "lucide-react";

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
  const { legacyApps } = useAuth();

  if (legacyApps.length === 0) {
    return (
      <p className="text-sm text-muted-foreground">
        No legacy applications are currently available.
      </p>
    );
  }

  return (
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 2xl:grid-cols-4">
      {legacyApps.map((app, index) => {
        const Icon = legacyAppIcons[app.icon ?? ""] ?? AppWindow;
        const colorIndex = index % iconColors.length;

        return (
          <a
            key={app.id}
            href={app.url}
            target="_blank"
            rel="noopener noreferrer"
            aria-label={`Open ${app.name} in a new tab`}
            className="group flex min-h-[205px] flex-col rounded-xl border bg-card p-4 transition-all hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-md dark:hover:border-blue-900"
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
              <span
                className={`flex h-9 w-9 items-center justify-center rounded-full ${arrowColors[colorIndex]}`}
              >
                <ArrowRight className="h-4 w-4" />
              </span>
            </div>
          </a>
        );
      })}
    </div>
  );
}
