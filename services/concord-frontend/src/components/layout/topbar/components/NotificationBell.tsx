import { Bell } from "lucide-react";

export function NotificationBell() {
  return (
    <button
      type="button"
      className="relative rounded-md p-2 hover:bg-muted"
      aria-label="Notifications"
      title="Notifications"
    >
      <Bell className="h-5 w-5" />
    </button>
  );
}
