import { Outlet, Navigate } from "@tanstack/react-router";
import { useState } from "react";

import { Sidebar } from "@/components/layout/Sidebar";
import { Topbar } from "@/components/layout/topbar/Topbar";
import { ThemeContext, type ConcordTheme } from "@/contexts/ThemeContext";
import { useAuth } from "@/contexts/AuthContext";
import { CorrectionRequestProvider } from "@/contexts/CorrectionRequestContext";
import { SessionLoader } from "@/components/layout/SessionLoader";

import { changeMyPassword } from "@/lib/iam";

export function ConcordLayout() {
  const [theme, setTheme] = useState<ConcordTheme>("light");
  const [sidebarCollapsed, setSidebarCollapsed] = useState(false);

    const {
      status,
      refreshSession,
      accountRevocation,
      acknowledgeAccountRevocation,
    } = useAuth();

  function updateTheme(nextTheme: ConcordTheme) {
    setTheme(nextTheme);
    document.documentElement.classList.toggle("dark", nextTheme === "dark");
  }

  function toggleTheme() {
    updateTheme(theme === "dark" ? "light" : "dark");
  }

  if (status === "loading") {
    return <SessionLoader />;
  }

  if (status === "unauthenticated") {
    return <Navigate to="/login" />;
  }

  if (status === "error") {
    return (
      <div className="flex min-h-screen flex-col items-center justify-center gap-4 bg-background p-6 text-center">
        <h1 className="text-lg font-semibold">Unable to verify your session</h1>

        <p className="max-w-md text-sm text-muted-foreground">
          Concord couldn't connect to IAM to verify your session. Please try
          again.
        </p>

        <button
          type="button"
          onClick={() => void refreshSession()}
          className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90"
        >
          Try again
        </button>
      </div>
    );
  }

  if (status === "password_change_required") {
    return <PasswordChangeRequiredScreen />;
  }

  return (
    <CorrectionRequestProvider>
      <ThemeContext.Provider
        value={{
          theme,
          onThemeChange: updateTheme,
        }}
      >
        <div className="min-h-screen bg-background text-foreground">
          <Sidebar collapsed={sidebarCollapsed} />

          <div
            className={`min-h-screen transition-[margin] duration-200 ${
              sidebarCollapsed ? "ml-16" : "ml-64"
            }`}
          >
            <Topbar
              sidebarCollapsed={sidebarCollapsed}
              onSidebarToggle={() =>
                setSidebarCollapsed((collapsed) => !collapsed)
              }
              isDark={theme === "dark"}
              onThemeToggle={toggleTheme}
            />

            <main className="mx-auto w-full max-w-[1800px] p-5 md:p-8">
              <Outlet />
            </main>
          </div>

          {/* Blocking dialog for revoked accounts */}
          {accountRevocation && (
            <div
              className="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 p-4"
              role="alertdialog"
              aria-modal="true"
              aria-labelledby="account-revocation-title"
              aria-describedby="account-revocation-description"
            >
              <div className="w-full max-w-md rounded-xl border bg-background p-6 shadow-xl">
                <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-destructive/10 text-destructive">
                  <span className="text-2xl font-bold">!</span>
                </div>

                <h2
                  id="account-revocation-title"
                  className="text-xl font-semibold"
                >
                  Account access revoked
                </h2>

                <p
                  id="account-revocation-description"
                  className="mt-3 text-sm leading-relaxed text-muted-foreground"
                >
                  {accountRevocation === "ACCOUNT_DISABLED"
                    ? "Your account has been disabled."
                    : "Your account has been deactivated."}{" "}
                  Your access to Concord is no longer available. Please contact
                  your administrator if you believe this is a mistake.
                </p>

                <button
                  type="button"
                  onClick={acknowledgeAccountRevocation}
                  className="mt-6 w-full rounded-md bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground hover:opacity-90"
                >
                  Return to Login
                </button>
              </div>
            </div>
          )}
          
        </div>
      </ThemeContext.Provider>
    </CorrectionRequestProvider>
  );
}

function PasswordChangeRequiredScreen() {
  const { signOut, refreshSession } = useAuth();

  const [currentPassword, setCurrentPassword] = useState("");
  const [newPassword, setNewPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");

  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handlePasswordChange(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError(null);

    if (!currentPassword || !newPassword || !confirmPassword) {
      setError("Please complete all password fields.");
      return;
    }

    if (newPassword !== confirmPassword) {
      setError("The new password and confirmation do not match.");
      return;
    }

    if (currentPassword === newPassword) {
      setError(
        "Your new password must be different from your current password.",
      );
      return;
    }

    setSubmitting(true);

    try {
      await changeMyPassword({
        current_password: currentPassword,
        new_password: newPassword,
        new_password_confirmation: confirmPassword,
      });

      // Recheck the session. Once IAM reports must_change_password=false,
      // AuthContext will restore normal authenticated access.
      await refreshSession();
    } catch (requestError) {
      setError(
        requestError instanceof Error
          ? requestError.message
          : "Unable to change your password. Please try again.",
      );
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-background p-5">
      <div className="w-full max-w-md rounded-xl border border-border bg-card p-6 shadow-sm sm:p-8">
        <div className="mb-6">
          <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10 text-primary">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="24"
              height="24"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              strokeWidth="2"
              strokeLinecap="round"
              strokeLinejoin="round"
            >
              <rect x="3" y="11" width="18" height="11" rx="2" />
              <path d="M7 11V7a5 5 0 0 1 10 0v4" />
              <path d="M12 15v3" />
            </svg>
          </div>

          <h1 className="text-xl font-semibold tracking-tight">
            Change your password
          </h1>

          <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
            Your account requires a password change before you can continue
            using Concord. Please create a new password to proceed.
          </p>
        </div>

        <form onSubmit={handlePasswordChange} className="space-y-4">
          <div className="space-y-1.5">
            <label htmlFor="current-password" className="text-sm font-medium">
              Current password
            </label>

            <input
              id="current-password"
              type="password"
              autoComplete="current-password"
              value={currentPassword}
              onChange={(event) => setCurrentPassword(event.target.value)}
              disabled={submitting}
              required
              className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 disabled:opacity-60"
            />
          </div>

          <div className="space-y-1.5">
            <label htmlFor="new-password" className="text-sm font-medium">
              New password
            </label>

            <input
              id="new-password"
              type="password"
              autoComplete="new-password"
              value={newPassword}
              onChange={(event) => setNewPassword(event.target.value)}
              disabled={submitting}
              required
              className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 disabled:opacity-60"
            />
          </div>

          <div className="space-y-1.5">
            <label htmlFor="confirm-password" className="text-sm font-medium">
              Confirm new password
            </label>

            <input
              id="confirm-password"
              type="password"
              autoComplete="new-password"
              value={confirmPassword}
              onChange={(event) => setConfirmPassword(event.target.value)}
              disabled={submitting}
              required
              className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 disabled:opacity-60"
            />
          </div>

          {error && (
            <div
              role="alert"
              className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2.5 text-sm text-destructive"
            >
              {error}
            </div>
          )}

          <button
            type="submit"
            disabled={submitting}
            className="w-full rounded-md bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
          >
            {submitting ? "Changing password..." : "Change password"}
          </button>
        </form>

        <div className="mt-6 border-t border-border pt-4">
          <button
            type="button"
            onClick={() => void signOut()}
            disabled={submitting}
            className="w-full rounded-md px-4 py-2 text-sm font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground disabled:opacity-60"
          >
            Sign out
          </button>
        </div>
      </div>
    </div>
  );
}
