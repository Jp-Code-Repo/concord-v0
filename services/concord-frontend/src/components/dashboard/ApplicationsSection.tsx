import {
  ArrowRight,
  Cross,
  GraduationCap,
  LayoutGrid,
  LibraryBig,
  Ticket,
} from "lucide-react";

import { Button } from "@/components/ui/button";

const applications = [
  {
    name: "Ticketing System",
    description: "Create and manage support tickets for your office.",
    icon: Ticket,
    color: "blue",
  },
  {
    name: "Clinic System",
    description: "Manage clinic records and services.",
    icon: Cross,
    color: "green",
  },
  {
    name: "Registrar System",
    description: "Access student records and enrollment tools.",
    icon: GraduationCap,
    color: "purple",
  },
  {
    name: "Library System",
    description: "Search and manage library resources.",
    icon: LibraryBig,
    color: "orange",
  },
] as const;

const iconColors: Record<(typeof applications)[number]["color"], string> = {
  blue: "bg-blue-600 text-white",
  green: "bg-emerald-500 text-white",
  purple: "bg-violet-600 text-white",
  orange: "bg-orange-500 text-white",
};

const arrowColors: Record<(typeof applications)[number]["color"], string> = {
  blue: "bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300",
  green:
    "bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300",
  purple:
    "bg-violet-100 text-violet-700 dark:bg-violet-950 dark:text-violet-300",
  orange:
    "bg-orange-100 text-orange-700 dark:bg-orange-950 dark:text-orange-300",
};

export function ApplicationsSection() {
  return (
    <section id="applications" className="min-w-0 rounded-2xl border bg-card">
      <div className="flex items-center justify-between border-b px-5 py-4">
        <div className="flex items-center gap-3">
          <LayoutGrid className="h-5 w-5 text-blue-600" />
          <h2 className="text-lg font-semibold">My Applications</h2>
        </div>

        <Button variant="outline" size="sm" className="gap-2">
          View All
          <ArrowRight className="h-4 w-4" />
        </Button>
      </div>

      <div className="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2 2xl:grid-cols-4">
        {applications.map((app) => {
          const Icon = app.icon;

          return (
            <article
              key={app.name}
              className="group flex min-h-[205px] flex-col rounded-xl border p-4 transition-all hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-md dark:hover:border-blue-900"
            >
              <div
                className={`flex h-12 w-12 items-center justify-center rounded-xl ${iconColors[app.color]}`}
              >
                <Icon className="h-6 w-6" />
              </div>

              <h3 className="mt-4 font-semibold">{app.name}</h3>

              <p className="mt-2 text-sm leading-5 text-muted-foreground">
                {app.description}
              </p>

              <div className="mt-auto flex justify-end pt-4">
                <button
                  type="button"
                  aria-label={`Open ${app.name} preview`}
                  title={`${app.name} preview`}
                  className={`flex h-9 w-9 items-center justify-center rounded-full transition-transform group-hover:translate-x-1 ${arrowColors[app.color]}`}
                >
                  <ArrowRight className="h-4 w-4" />
                </button>
              </div>
            </article>
          );
        })}
      </div>
    </section>
  );
}
