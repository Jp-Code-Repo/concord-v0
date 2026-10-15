import { useState } from "react";
import {
  Bell,
  ClipboardList,
  KeyRound,
  ShieldCheck,
  UserRoundPen,
} from "lucide-react";

import { CorrectionRequestDialog } from "@/components/profile/CorrectionRequestDialog";
import { CorrectionRequestsList } from "@/components/profile/CorrectionRequestsList";

const actions = [
  {
    label: "Request Profile Correction",
    description: "Request changes to your personal or employment records",
    icon: UserRoundPen,
  },
  {
    label: "My Correction Requests",
    description: "Track the status of your submitted correction requests",
    icon: ClipboardList,
  },
  {
    label: "Change Password",
    description: "Manage your login credentials",
    icon: KeyRound,
  },
  {
    label: "Notification Settings",
    description: "Manage your notification preferences",
    icon: Bell,
  },
  {
    label: "Privacy & Security",
    description: "Review your account protection",
    icon: ShieldCheck,
  },
];

type CorrectionSection = "Personal Information" | "Employment";

export function ProfileQuickActions() {
  const [correctionDialogOpen, setCorrectionDialogOpen] = useState(false);
  const [correctionSection, setCorrectionSection] = useState<CorrectionSection>(
    "Personal Information",
  );
  const [requestsDialogOpen, setRequestsDialogOpen] = useState(false);

    function handleAction(label: string) {
    if (label === "Request Profile Correction") {
        setCorrectionSection("Personal Information");
        setCorrectionDialogOpen(true);
        return;
    }

    if (label === "My Correction Requests") {
        setRequestsDialogOpen(true);
        return;
    }

    const messages: Record<string, string> = {
        "Change Password":
        "Password management will be connected to IAM in a later step.",
        "Notification Settings":
        "Notification preferences can be managed in the Preferences tab.",
        "Privacy & Security":
        "Account security settings can be managed in the Security tab.",
    };

    alert(messages[label] ?? "This action will be available in a later step.");
    }

  return (
    <>
      <section className="rounded-2xl border bg-card">
        <div className="border-b px-5 py-4">
          <h2 className="font-semibold">Quick Actions</h2>
          <p className="mt-1 text-sm text-muted-foreground">
            Shortcuts to common profile tasks.
          </p>
        </div>

        <div className="grid gap-3 p-5 sm:grid-cols-2">
          {actions.map(({ label, description, icon: Icon }) => (
            <button
              key={label}
              type="button"
              onClick={() => handleAction(label)}
              className="flex items-start gap-3 rounded-xl border p-4 text-left transition-colors hover:border-blue-300 hover:bg-blue-50/50 dark:hover:border-blue-900 dark:hover:bg-blue-950/20"
            >
              <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300">
                <Icon className="h-5 w-5" />
              </span>

              <span className="min-w-0">
                <span className="block text-sm font-medium">{label}</span>
                <span className="mt-1 block text-xs leading-5 text-muted-foreground">
                  {description}
                </span>
              </span>
            </button>
          ))}
        </div>
      </section>

      <CorrectionRequestDialog
        open={correctionDialogOpen}
        section={correctionSection}
        onClose={() => setCorrectionDialogOpen(false)}
        onViewRequests={() => setRequestsDialogOpen(true)}
      />

      {requestsDialogOpen && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
          onMouseDown={(event) => {
            if (event.target === event.currentTarget) {
              setRequestsDialogOpen(false);
            }
          }}
        >
          <div
            role="dialog"
            aria-modal="true"
            aria-label="My Correction Requests"
            className="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-background shadow-xl"
          >
            <div className="flex items-center justify-between border-b px-5 py-4">
              <h2 className="font-semibold">My Correction Requests</h2>

              <button
                type="button"
                onClick={() => setRequestsDialogOpen(false)}
                className="rounded-lg px-3 py-1.5 text-sm text-muted-foreground hover:bg-muted hover:text-foreground"
              >
                Close
              </button>
            </div>

            <div className="p-4 sm:p-5">
              <CorrectionRequestsList />
            </div>
          </div>
        </div>
      )}
    </>
  );
}
