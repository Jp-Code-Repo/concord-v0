import { WelcomeBanner } from "@/components/dashboard/WelcomeBanner";
import { ApplicationsSection } from "@/components/dashboard/ApplicationsSection";
import { RecentActivity } from "@/components/dashboard/RecentActivity";
import { AnnouncementsSection } from "@/components/dashboard/AnnouncementsSection";

export function HomePage() {
  return (
    <div className="mx-auto w-full max-w-[1600px] space-y-6">
      {/* Preview indicator */}
      <div className="flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-4 py-2 text-xs text-blue-800 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-200">
        <span className="h-2 w-2 rounded-full bg-blue-500" />
        Dashboard preview · Sample content, not live data
      </div>

      {/* Welcome banner */}
      <WelcomeBanner />

      {/* Applications and recent activity */}
      <section className="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1.7fr)_minmax(300px,1fr)]">
        <ApplicationsSection />
        <RecentActivity />
      </section>

      {/* Announcements */}
      <AnnouncementsSection />

      {/* Footer */}
      <footer className="flex flex-col gap-2 border-t py-4 text-xs text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
        <span>Concord Portal · Dashboard preview</span>
        <span>
          Different systems. Different people. One connected experience.
        </span>
      </footer>
    </div>
  );
}
