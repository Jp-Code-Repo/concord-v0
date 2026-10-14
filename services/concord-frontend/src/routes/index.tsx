import { createRoute } from "@tanstack/react-router";
import { rootRoute } from "./__root";
import { ConcordLayout } from "@/components/layout/ConcordLayout";
import { Button } from "@/components/ui/button";
import {
  ArrowRight,
  BookOpen,
  Clock3,
  Cross,
  GraduationCap,
  LayoutGrid,
  LibraryBig,
  Megaphone,
  Ticket,
} from "lucide-react";

import heroIllustration from "@/assets/illustrations/dashboard-hero-illustration.png";

export const concordRoute = createRoute({
  getParentRoute: () => rootRoute,
  id: "concord-layout",
  component: ConcordLayout,
});

export const indexRoute = createRoute({
  getParentRoute: () => concordRoute,
  path: "/",
  component: HomePage,
});

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

const activities = [
  {
    title: "Logged in to Concord",
    time: "Just now",
    color: "bg-blue-500",
  },
  {
    title: "Accessed Ticketing System",
    time: "2 hours ago",
    color: "bg-emerald-500",
  },
  {
    title: "Viewed student records",
    time: "5 hours ago",
    color: "bg-violet-500",
  },
  {
    title: "Generated a report",
    time: "Yesterday",
    color: "bg-orange-500",
  },
];

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

function HomePage() {
  return (
    <div className="mx-auto w-full max-w-[1600px] space-y-6">
      {/* Preview indicator */}
      <div className="flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-4 py-2 text-xs text-blue-800 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-200">
        <span className="h-2 w-2 rounded-full bg-blue-500" />
        Dashboard preview · Sample content, not live data
      </div>

      {/* Welcome banner */}
      <section className="relative isolate min-h-[280px] overflow-hidden rounded-2xl border border-blue-100 bg-gradient-to-r from-[#f7fbff] via-[#eaf4ff] to-[#b8d9ff] dark:border-blue-900 dark:from-slate-950 dark:via-slate-900 dark:to-blue-950">
        {/* Illustration background */}
        <img
          src={heroIllustration}
          alt=""
          aria-hidden="true"
          className="absolute inset-0 h-full w-full object-cover object-center"
        />

        {/* Gradient overlay for text readability */}

        <div className="absolute inset-0 bg-gradient-to-r from-white/10 via-white/65 to-white/5 dark:from-slate-950/90 dark:via-slate-950/60 dark:to-slate-950/10" />

        {/* Welcome content layered over the illustration */}
        <div className="relative z-10 flex min-h-[280px] max-w-[65%] flex-col items-start justify-center p-6 sm:p-8 lg:p-10">
          <p className="mb-3 text-sm font-semibold tracking-wider text-blue-600 dark:text-blue-400">
            YOUR CONNECTED CAMPUS
          </p>

          <h1 className="text-3xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-4xl">
            Welcome to Concord
          </h1>

          <p className="mt-4 max-w-lg text-sm leading-6 text-slate-600 dark:text-slate-300 sm:text-base">
            Your unified portal for a more connected and efficient campus
            experience. Access your available tools and services from one place.
          </p>

          <Button
            className="mt-6 gap-2 bg-blue-600 text-white hover:bg-blue-700"
            onClick={() =>
              document
                .getElementById("applications")
                ?.scrollIntoView({ behavior: "smooth" })
            }
          >
            Go to My Applications
            <ArrowRight className="h-4 w-4" />
          </Button>
        </div>
      </section>

      {/* Applications and recent activity */}
      <section
        id="applications"
        className="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1.7fr)_minmax(300px,1fr)]"
      >
        {/* Application cards */}
        <div className="min-w-0 rounded-2xl border bg-card">
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
        </div>

        {/* Recent activity */}
        <div className="min-w-0 rounded-2xl border bg-card">
          <div className="flex items-center justify-between border-b px-5 py-4">
            <div className="flex items-center gap-3">
              <Clock3 className="h-5 w-5 text-blue-600" />
              <h2 className="text-lg font-semibold">Recent Activity</h2>
            </div>

            <Button variant="outline" size="sm" className="gap-2">
              View All
              <ArrowRight className="h-4 w-4" />
            </Button>
          </div>

          <div className="px-5">
            {activities.map((activity, index) => (
              <div
                key={activity.title}
                className={`flex items-start gap-3 py-4 ${
                  index !== activities.length - 1 ? "border-b" : ""
                }`}
              >
                <span
                  className={`mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full ${activity.color}`}
                />

                <div className="min-w-0 flex-1">
                  <p className="text-sm font-medium">{activity.title}</p>
                  <p className="mt-1 text-xs text-muted-foreground">
                    Preview activity
                  </p>
                </div>

                <span className="shrink-0 text-xs text-muted-foreground">
                  {activity.time}
                </span>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Announcements */}
      <section className="rounded-2xl border bg-card">
        <div className="flex items-center justify-between border-b px-5 py-4">
          <div className="flex items-center gap-3">
            <Megaphone className="h-5 w-5 text-blue-600" />
            <h2 className="text-lg font-semibold">Announcements</h2>
          </div>

          <Button variant="outline" size="sm" className="gap-2">
            View All
            <ArrowRight className="h-4 w-4" />
          </Button>
        </div>

        <div className="p-5">
          <div className="flex flex-col gap-4 rounded-xl border border-blue-100 bg-gradient-to-r from-blue-50 to-sky-50 p-5 dark:border-blue-900 dark:from-blue-950/50 dark:to-slate-900 sm:flex-row sm:items-center">
            <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-blue-600 text-white">
              <Megaphone className="h-5 w-5" />
            </div>

            <div className="flex-1">
              <h3 className="font-semibold">Welcome to Concord!</h3>
              <p className="mt-1 text-sm leading-6 text-muted-foreground">
                Different systems. Different people. One connected experience.
              </p>
            </div>

            <BookOpen className="hidden h-8 w-8 text-blue-300 sm:block" />
          </div>
        </div>
      </section>

      <footer className="flex flex-col gap-2 border-t py-4 text-xs text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
        <span>Concord Portal · Dashboard preview</span>
        <span>
          Different systems. Different people. One connected experience.
        </span>
      </footer>
    </div>
  );
  
}

export const profileRoute = createRoute({
  getParentRoute: () => concordRoute,
  path: "/profile",
  component: () => <div>My Profile</div>,
});
