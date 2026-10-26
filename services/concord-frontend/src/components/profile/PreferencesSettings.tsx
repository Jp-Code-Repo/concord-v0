import { useEffect, useState } from "react";
import { Bell, LayoutDashboard, Monitor, Palette, Save } from "lucide-react";

import { Button } from "@/components/ui/button";
import { useConcordTheme } from "@/contexts/ThemeContext";
import { useAuth } from "@/contexts/AuthContext";
import { updatePersonPreferences } from "@/lib/iam";

type Appearance = "System" | "Light" | "Dark";

export function PreferencesSettings() {
  const { theme, onThemeChange } = useConcordTheme();
  const { user, refreshSession } = useAuth();
  const preferences = user?.person?.preferences ?? {};
  const [appearance, setAppearance] = useState<Appearance>(
    (preferences.appearance as Appearance | undefined) ??
      (theme === "dark" ? "Dark" : "Light"),
  );
  const [emailNotifications, setEmailNotifications] = useState(
    (preferences.emailNotifications as boolean | undefined) ?? true,
  );
  const [announcementNotifications, setAnnouncementNotifications] = useState(
    (preferences.announcementNotifications as boolean | undefined) ?? true,
  );
  const [compactDashboard, setCompactDashboard] = useState(
    (preferences.compactDashboard as boolean | undefined) ?? false,
  );
  const [saved, setSaved] = useState(false);
  const [saving, setSaving] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);

  useEffect(() => {
    if (appearance === "System") {
      const mediaQuery = window.matchMedia("(prefers-color-scheme: dark)");

      function applySystemTheme() {
        onThemeChange(mediaQuery.matches ? "dark" : "light");
      }

      applySystemTheme();
      mediaQuery.addEventListener("change", applySystemTheme);

      return () => {
        mediaQuery.removeEventListener("change", applySystemTheme);
      };
    }

    onThemeChange(appearance === "Dark" ? "dark" : "light");
  }, [appearance, onThemeChange]);

  function handleAppearanceChange(value: Appearance) {
    setAppearance(value);
    setSaved(false);
  }

  function toggle(setter: (value: boolean) => void, value: boolean) {
    setter(!value);
    setSaved(false);
  }

  async function savePreferences() {
    setSaving(true);
    setSaved(false);
    setSaveError(null);

    try {
      await updatePersonPreferences({
        appearance,
        emailNotifications,
        announcementNotifications,
        compactDashboard,
      });
      await refreshSession();
      setSaved(true);
    } catch (error) {
      setSaveError(
        error instanceof Error ? error.message : "Unable to save preferences.",
      );
    } finally {
      setSaving(false);
    }
  }

  return (
    <section className="overflow-hidden rounded-2xl border bg-card">
      <div className="flex items-center gap-3 border-b px-5 py-4">
        <Palette className="h-5 w-5 text-blue-600" />
        <div>
          <h2 className="font-semibold">Preferences</h2>
          <p className="mt-1 text-sm text-muted-foreground">
            Customize your Concord experience.
          </p>
        </div>
      </div>

      <div className="space-y-6 p-5">
        {/* Appearance */}
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div className="flex items-start gap-3">
            <Monitor className="mt-0.5 h-5 w-5 text-blue-600" />
            <div>
              <h3 className="text-sm font-medium">Appearance</h3>
              <p className="mt-1 text-sm text-muted-foreground">
                Choose how Concord should look.
              </p>
            </div>
          </div>

          <select
            aria-label="Appearance"
            value={appearance}
            onChange={(event) =>
              handleAppearanceChange(event.target.value as Appearance)
            }
            className="rounded-lg border bg-background px-3 py-2 text-sm sm:min-w-40"
          >
            <option>System</option>
            <option>Light</option>
            <option>Dark</option>
          </select>
        </div>

        <div className="border-t" />

        {/* Notifications */}
        <div className="space-y-4">
          <div className="flex items-start gap-3">
            <Bell className="mt-0.5 h-5 w-5 text-blue-600" />
            <div>
              <h3 className="text-sm font-medium">Notifications</h3>
              <p className="mt-1 text-sm text-muted-foreground">
                Choose which updates you want to receive.
              </p>
            </div>
          </div>

          <PreferenceSwitch
            label="Email notifications"
            description="Receive updates through your email."
            checked={emailNotifications}
            onChange={(value) => toggle(setEmailNotifications, value)}
          />

          <PreferenceSwitch
            label="Announcements"
            description="Receive important school announcements."
            checked={announcementNotifications}
            onChange={(value) => toggle(setAnnouncementNotifications, value)}
          />
        </div>

        <div className="border-t" />

        {/* Dashboard */}
        <div className="flex items-start justify-between gap-4">
          <div className="flex items-start gap-3">
            <LayoutDashboard className="mt-0.5 h-5 w-5 text-blue-600" />
            <div>
              <h3 className="text-sm font-medium">Compact Dashboard</h3>
              <p className="mt-1 text-sm text-muted-foreground">
                Use a more condensed dashboard layout.
              </p>
            </div>
          </div>

          <Switch
            checked={compactDashboard}
            onChange={(value) => toggle(setCompactDashboard, value)}
            label="Compact dashboard"
          />
        </div>

        <div className="flex flex-col gap-3 border-t pt-5 sm:flex-row sm:items-center sm:justify-between">
          <p className="text-xs text-muted-foreground">
            Preferences are saved to your Concord person profile.
          </p>

          <Button onClick={() => void savePreferences()} disabled={saving} className="gap-2">
            <Save className="h-4 w-4" />
            {saving ? "Saving…" : "Save Preferences"}
          </Button>
        </div>

        {saved && (
          <p role="status" className="text-sm text-blue-600 dark:text-blue-400">
            Preferences saved to your Concord profile.
          </p>
        )}
        {saveError && (
          <p role="alert" className="text-sm text-destructive">{saveError}</p>
        )}
      </div>
    </section>
  );
}

function PreferenceSwitch({
  label,
  description,
  checked,
  onChange,
}: {
  label: string;
  description: string;
  checked: boolean;
  onChange: (value: boolean) => void;
}) {
  return (
    <div className="flex items-center justify-between gap-4 rounded-xl border p-4">
      <div>
        <p className="text-sm font-medium">{label}</p>
        <p className="mt-1 text-sm text-muted-foreground">{description}</p>
      </div>

      <Switch checked={checked} onChange={onChange} label={label} />
    </div>
  );
}

function Switch({
  checked,
  onChange,
  label,
}: {
  checked: boolean;
  onChange: (value: boolean) => void;
  label: string;
}) {
  return (
    <button
      type="button"
      role="switch"
      aria-checked={checked}
      aria-label={label}
      onClick={() => onChange(!checked)}
      className={`relative inline-flex h-6 w-11 shrink-0 items-center rounded-full border-2 border-transparent transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 ${
        checked ? "bg-blue-600" : "bg-input"
      }`}
    >
      <span
        className={`pointer-events-none block h-5 w-5 rounded-full bg-white shadow-sm transition-transform ${
          checked ? "translate-x-5" : "translate-x-0"
        }`}
      />
    </button>
  );
}
