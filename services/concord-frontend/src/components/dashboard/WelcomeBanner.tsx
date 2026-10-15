import { ArrowRight } from "lucide-react";

import { Button } from "@/components/ui/button";
import heroIllustration from "@/assets/illustrations/dashboard-hero-illustration.png";

export function WelcomeBanner() {
  return (
    <section className="relative isolate overflow-hidden rounded-2xl border shadow-sm">
      <img
        src={heroIllustration}
        alt=""
        aria-hidden="true"
        className="absolute inset-0 h-full w-full object-cover object-center"
      />

      {/* Stronger overlay on the text side for readability */}
      <div className="absolute inset-0 bg-gradient-to-r from-white/20 via-white/80 via-55% to-white/10 dark:from-slate-950/95 dark:via-slate-950/80 dark:via-55% dark:to-slate-950/20" />

      <div className="relative z-10 max-w-[65%] p-6 md:p-8">
        <p className="mb-2 text-sm font-medium text-blue-700 dark:text-blue-300">
          Your school, connected.
        </p>

        <h1 className="text-2xl font-bold tracking-tight text-slate-950 md:text-3xl dark:text-white">
          Welcome to Concord
        </h1>

        <p className="mt-3 max-w-xl text-sm leading-6 text-slate-700 md:text-base dark:text-slate-200">
          Your central hub for school applications, services, and announcements.
          Everything you need, connected in one place.
        </p>

        <Button
          className="mt-6 bg-blue-600 text-white hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600"
          onClick={() => {
            document
              .getElementById("applications")
              ?.scrollIntoView({ behavior: "smooth", block: "start" });
          }}
        >
          Explore applications
          <ArrowRight className="ml-2 size-4" />
        </Button>
      </div>
    </section>
  );
}
