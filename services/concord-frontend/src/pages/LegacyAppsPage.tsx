import { ApplicationsSection } from "@/components/dashboard/ApplicationsSection";

export function LegacyAppsPage() {
  return (
    <div className="mx-auto w-full max-w-[1600px] space-y-6">
      {/* Breadcrumb */}
      <div className="flex items-center gap-2 text-sm text-muted-foreground">
        <span>Dashboard</span>
        <span>/</span>
        <span className="text-foreground">Legacy Apps</span>
      </div>

      {/* Page heading */}
      <div>
        <h1 className="text-3xl font-bold tracking-tight">
          Legacy Applications
        </h1>

        <p className="mt-1 text-sm text-muted-foreground md:text-base">
          Access the existing applications and systems available to you through
          Concord. Select an application to open it in a new browser tab.
        </p>
      </div>

      {/* Available applications */}
      <ApplicationsSection />
    </div>
  );
}
