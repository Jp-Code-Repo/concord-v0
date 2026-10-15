import { ArrowRight, Clock3 } from "lucide-react";

import { Button } from "@/components/ui/button";

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

export function RecentActivity() {
  return (
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
  );
}
