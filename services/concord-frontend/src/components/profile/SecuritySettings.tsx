import { useState } from "react";
import {
  Clock,
  KeyRound,
  Laptop,
  LockKeyhole,
  ShieldCheck,
  Smartphone,
} from "lucide-react";

import { Button } from "@/components/ui/button";

export function SecuritySettings() {
  const [showSessions, setShowSessions] = useState(false);

  return (
    <div className="space-y-6">
      {/* Password and authentication */}
      <section className="overflow-hidden rounded-2xl border bg-card">
        <div className="flex items-center gap-3 border-b px-5 py-4">
          <ShieldCheck className="h-5 w-5 text-blue-600" />
          <div>
            <h2 className="font-semibold">Security</h2>
            <p className="mt-1 text-sm text-muted-foreground">
              Review your account protection settings.
            </p>
          </div>
        </div>

        <div className="divide-y">
          <div className="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex items-start gap-3">
              <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300">
                <KeyRound className="h-5 w-5" />
              </span>
              <div>
                <h3 className="text-sm font-medium">Password</h3>
                <p className="mt-1 text-sm text-muted-foreground">
                  Manage your account password.
                </p>
                <p className="mt-2 text-xs text-muted-foreground">
                  Password details are not displayed here.
                </p>
              </div>
            </div>

            <Button
              variant="outline"
              onClick={() =>
                alert("Password management will be connected to IAM later.")
              }
            >
              Change Password
            </Button>
          </div>

          <div className="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex items-start gap-3">
              <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300">
                <Smartphone className="h-5 w-5" />
              </span>
              <div>
                <h3 className="text-sm font-medium">
                  Multi-Factor Authentication
                </h3>
                <p className="mt-1 text-sm text-muted-foreground">
                  Add another verification step when signing in.
                </p>
                <span className="mt-2 inline-flex rounded-full bg-muted px-2.5 py-1 text-xs text-muted-foreground">
                  Status unavailable · Preview
                </span>
              </div>
            </div>

            <Button
              variant="outline"
              onClick={() =>
                alert(
                  "Multi-factor authentication will be configured through IAM.",
                )
              }
            >
              Manage MFA
            </Button>
          </div>
        </div>
      </section>

      {/* Sessions */}
      <section className="overflow-hidden rounded-2xl border bg-card">
        <div className="flex items-center gap-3 border-b px-5 py-4">
          <Laptop className="h-5 w-5 text-blue-600" />
          <div>
            <h2 className="font-semibold">Active Sessions</h2>
            <p className="mt-1 text-sm text-muted-foreground">
              Review devices signed in to your account.
            </p>
          </div>
        </div>

        <div className="flex items-start gap-3 p-5">
          <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300">
            <Laptop className="h-5 w-5" />
          </span>

          <div className="min-w-0 flex-1">
            <p className="text-sm font-medium">Current browser session</p>
            <p className="mt-1 text-sm text-muted-foreground">
              This is a sample session entry. Actual device and session details
              will come from IAM.
            </p>
            <span className="mt-2 inline-flex items-center gap-2 text-xs text-emerald-600 dark:text-emerald-400">
              <span className="h-2 w-2 rounded-full bg-emerald-500" />
              Current session · Preview
            </span>
          </div>
        </div>

        <div className="flex flex-col gap-3 border-t p-5 sm:flex-row sm:items-center sm:justify-between">
          <div className="flex items-center gap-2 text-sm text-muted-foreground">
            <Clock className="h-4 w-4" />
            Session management is not connected yet.
          </div>

          <Button
            variant="outline"
            onClick={() => setShowSessions((current) => !current)}
          >
            {showSessions ? "Hide details" : "View session details"}
          </Button>
        </div>

        {showSessions && (
          <div className="border-t bg-muted/20 p-5">
            <p className="text-sm font-medium">Session details</p>
            <p className="mt-1 text-sm text-muted-foreground">
              IP address, sign-in time, device information, and session
              revocation will be displayed when IAM integration is available.
            </p>
          </div>
        )}
      </section>

      {/* Security guidance */}
      <div className="flex items-start gap-3 rounded-xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-900 dark:bg-blue-950/30">
        <LockKeyhole className="mt-0.5 h-5 w-5 shrink-0 text-blue-600" />
        <div>
          <p className="text-sm font-medium">
            Security controls are in preview
          </p>
          <p className="mt-1 text-sm leading-5 text-muted-foreground">
            These controls do not change your credentials, authentication
            methods, or sessions. Those operations will be handled by IAM.
          </p>
        </div>
      </div>
    </div>
  );
}
