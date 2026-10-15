import { ArrowRight, BookOpen, Megaphone } from "lucide-react";

import { Button } from "@/components/ui/button";

export function AnnouncementsSection() {
  return (
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
  );
}
