import { useState } from "react";
import { Globe, KeyRound, Save, Settings2 } from "lucide-react";

import { Button } from "@/components/ui/button";
import { useAuth } from "@/contexts/AuthContext";
import { updateAccountSettings, updatePersonPreferences } from "@/lib/iam";

export function AccountSettings() {
  const { user, refreshSession } = useAuth();
  const personPreferences = user?.person?.preferences ?? {};
  const accountSettings = user?.settings ?? {};
  const [language, setLanguage] = useState(
    (personPreferences.language as string | undefined) ?? "English",
  );
  const [timeZone, setTimeZone] = useState(
    (personPreferences.timeZone as string | undefined) ?? "Asia/Manila",
  );
  const [sessionWarning, setSessionWarning] = useState(
    (accountSettings.session_warning as boolean | undefined) ?? true,
  );
  const [saved, setSaved] = useState(false);
  const [saving, setSaving] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);

  async function saveSettings() {
    setSaving(true);
    setSaved(false);
    setSaveError(null);

    try {
      await updatePersonPreferences({ language, timeZone });
      await updateAccountSettings({ session_warning: sessionWarning });
      await refreshSession();
      setSaved(true);
    } catch (error) {
      setSaveError(
        error instanceof Error ? error.message : "Unable to save account settings.",
      );
    } finally {
      setSaving(false);
    }
  }

  return (
    <section className="overflow-hidden rounded-2xl border bg-card">
      <div className="flex items-center gap-3 border-b px-5 py-4">
        <Settings2 className="h-5 w-5 text-blue-600" />
        <div>
          <h2 className="font-semibold">Account Settings</h2>
          <p className="mt-1 text-sm text-muted-foreground">
            Manage your account preferences.
          </p>
        </div>
      </div>

      <div className="space-y-6 p-5">
        <div className="space-y-4">
          <h3 className="text-sm font-semibold">Account Details</h3>

          <div className="grid gap-4 sm:grid-cols-2">
            <div className="space-y-2">
              <label className="text-sm text-muted-foreground">
                Login Email
              </label>
              <div className="rounded-lg border bg-muted/30 px-3 py-2.5 text-sm">
                {user?.person?.email ?? "No email on file"}
              </div>
            </div>

            <div className="space-y-2">
              <label className="text-sm text-muted-foreground">
                Account Status
              </label>
              <div className="flex items-center gap-2 rounded-lg border px-3 py-2.5 text-sm">
                <span className={`h-2 w-2 rounded-full ${user?.status === "active" ? "bg-emerald-500" : "bg-red-500"}`} />
                {user?.status ?? "Unknown"}
              </div>
            </div>
          </div>
        </div>

        <div className="border-t" />

        <div className="space-y-4">
          <div className="flex items-center gap-2">
            <Globe className="h-4 w-4 text-blue-600" />
            <h3 className="text-sm font-semibold">Regional Settings</h3>
          </div>

          <div className="grid gap-4 sm:grid-cols-2">
            <div className="space-y-2">
              <label htmlFor="profile-language" className="text-sm">
                Display Language
              </label>
              <select
                id="profile-language"
                value={language}
                onChange={(event) => {
                  setLanguage(event.target.value);
                  setSaved(false);
                }}
                className="w-full rounded-lg border bg-background px-3 py-2.5 text-sm"
              >
                <option>English</option>
                <option>Filipino</option>
              </select>
            </div>

            <div className="space-y-2">
              <label htmlFor="profile-timezone" className="text-sm">
                Time Zone
              </label>
              <select
                id="profile-timezone"
                value={timeZone}
                onChange={(event) => {
                  setTimeZone(event.target.value);
                  setSaved(false);
                }}
                className="w-full rounded-lg border bg-background px-3 py-2.5 text-sm"
              >
                <option value="Asia/Manila">Asia/Manila (UTC+08:00)</option>
                <option value="UTC">UTC</option>
                <option value="Asia/Singapore">
                  Asia/Singapore (UTC+08:00)
                </option>
              </select>
            </div>
          </div>
        </div>

        <div className="border-t" />

        <div className="flex items-start justify-between gap-4">
          <div className="flex gap-3">
            <KeyRound className="mt-0.5 h-5 w-5 text-blue-600" />
            <div>
              <h3 className="text-sm font-semibold">Session Preferences</h3>
              <p className="mt-1 text-sm leading-5 text-muted-foreground">
                Show a warning before your session expires.
              </p>
            </div>
          </div>

          <button
            type="button"
            role="switch"
            aria-checked={sessionWarning}
            aria-label="Show session expiration warning"
            onClick={() => {
              setSessionWarning((current) => !current);
              setSaved(false);
            }}
            className={`relative inline-flex h-6 w-11 shrink-0 items-center rounded-full border-2 border-transparent transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 ${
              sessionWarning ? "bg-blue-600" : "bg-input"
            }`}
          >
            <span
              className={`pointer-events-none block h-5 w-5 rounded-full bg-white shadow-sm ring-0 transition-transform ${
                sessionWarning ? "translate-x-5" : "translate-x-0"
              }`}
            />
          </button>
        </div>

        <div className="flex flex-col gap-3 border-t pt-5 sm:flex-row sm:items-center sm:justify-between">
          <p className="text-xs text-muted-foreground">
            Regional preferences are stored on your person profile. Session warning is stored with your account settings.
          </p>

          <Button className="gap-2" onClick={() => void saveSettings()} disabled={saving}>
            <Save className="h-4 w-4" />
            {saving ? "Saving…" : "Save Changes"}
          </Button>
        </div>

        {saved && (
          <p role="status" className="text-sm text-blue-600 dark:text-blue-400">
            Account settings saved.
          </p>
        )}
        {saveError && (
          <p role="alert" className="text-sm text-destructive">{saveError}</p>
        )}
      </div>
    </section>
  );
}
