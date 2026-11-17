import { useEffect, useState } from "react";
import { Check, Copy, KeyRound, X } from "lucide-react";

import { Button } from "@/components/ui/button";
import { IamApiError, resetIamUserPassword } from "@/lib/iam";

interface ResetUserPasswordDialogProps {
  userId: string;
  onClose: () => void;
  onSuccess: () => void;
}

type ResetMode = "generate" | "custom";

export function ResetUserPasswordDialog({
  userId,
  onClose,
  onSuccess,
}: ResetUserPasswordDialogProps) {
  const [mode, setMode] = useState<ResetMode>("generate");
  const [password, setPassword] = useState("");
  const [confirmation, setConfirmation] = useState("");
  const [temporaryPassword, setTemporaryPassword] = useState<string | null>(
    null,
  );
  const [error, setError] = useState<string | null>(null);
  const [passwordError, setPasswordError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [copied, setCopied] = useState(false);

  useEffect(() => {
    function handleKeyDown(event: KeyboardEvent) {
      if (event.key === "Escape" && !saving) {
        onClose();
      }
    }

    window.addEventListener("keydown", handleKeyDown);

    return () => window.removeEventListener("keydown", handleKeyDown);
  }, [onClose, saving]);

  async function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();

    setError(null);
    setPasswordError(null);

    if (mode === "custom") {
      if (password.length < 12) {
        setPasswordError("Password must be at least 12 characters.");
        return;
      }

      if (password !== confirmation) {
        setPasswordError("Passwords do not match.");
        return;
      }
    }

    setSaving(true);

    try {
      const response = await resetIamUserPassword(
        userId,
        mode === "generate"
          ? { mode: "generate" }
          : { mode: "custom", password },
      );

      setTemporaryPassword(response.temporary_password);
      onSuccess();
    } catch (err) {
      if (err instanceof IamApiError) {
        const serverPasswordError = err.errors?.password?.[0];

        if (serverPasswordError) {
          setPasswordError(serverPasswordError);
        } else {
          setError(err.message);
        }
      } else {
        setError("Unable to reset the password. Please try again.");
      }
    } finally {
      setSaving(false);
    }
  }

  async function handleCopyPassword() {
    if (!temporaryPassword) return;

    try {
      await navigator.clipboard.writeText(temporaryPassword);
      setCopied(true);
    } catch {
      const textArea = document.createElement("textarea");
      textArea.value = temporaryPassword;
      textArea.style.position = "fixed";
      textArea.style.opacity = "0";

      document.body.appendChild(textArea);
      textArea.select();

      const successful = document.execCommand("copy");
      document.body.removeChild(textArea);

      setCopied(successful);
    }
  }

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
      onMouseDown={(event) => {
        if (event.target === event.currentTarget && !saving) {
          onClose();
        }
      }}
    >
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="reset-password-title"
        className="w-full max-w-md rounded-xl border bg-background shadow-xl"
      >
        <div className="flex items-center justify-between border-b p-5">
          <div className="flex items-center gap-3">
            <div className="rounded-lg bg-muted p-2">
              <KeyRound className="h-5 w-5" />
            </div>

            <div>
              <h2 id="reset-password-title" className="font-semibold">
                Reset Password
              </h2>
              <p className="text-sm text-muted-foreground">
                Set a new temporary password for this account.
              </p>
            </div>
          </div>

          <Button
            type="button"
            variant="ghost"
            size="icon"
            onClick={onClose}
            disabled={saving}
            aria-label="Close dialog"
          >
            <X className="h-4 w-4" />
          </Button>
        </div>

        {temporaryPassword ? (
          <div className="space-y-5 p-5">
            <div className="rounded-lg border border-green-500/30 bg-green-500/5 p-4">
              <p className="text-sm font-medium text-green-700 dark:text-green-400">
                Password reset successfully.
              </p>
              <p className="mt-1 text-sm text-muted-foreground">
                This temporary password is shown only once. Copy it and provide
                it securely to the user.
              </p>
            </div>

            <div className="space-y-2">
              <label className="text-sm font-medium">Temporary Password</label>

              <div className="flex gap-2">
                <input
                  readOnly
                  value={temporaryPassword}
                  className="min-w-0 flex-1 rounded-md border bg-muted px-3 py-2 font-mono text-sm"
                />

                <Button
                  type="button"
                  variant="outline"
                  onClick={handleCopyPassword}
                  className="shrink-0 gap-2"
                >
                  {copied ? (
                    <Check className="h-4 w-4" />
                  ) : (
                    <Copy className="h-4 w-4" />
                  )}
                  {copied ? "Copied" : "Copy"}
                </Button>
              </div>
            </div>

            <p className="text-sm text-muted-foreground">
              The user must sign in again and change this password before
              continuing. All existing sessions have been revoked.
            </p>

            <div className="flex justify-end">
              <Button type="button" onClick={onClose}>
                Done
              </Button>
            </div>
          </div>
        ) : (
          <form onSubmit={handleSubmit} className="space-y-5 p-5">
            <div className="space-y-2">
              <label className="text-sm font-medium">Reset Method</label>

              <div className="grid grid-cols-2 gap-2">
                <button
                  type="button"
                  onClick={() => {
                    setMode("generate");
                    setPasswordError(null);
                    setError(null);
                  }}
                  className={`rounded-lg border p-3 text-left text-sm transition-colors ${
                    mode === "generate"
                      ? "border-primary bg-primary/5"
                      : "hover:bg-muted"
                  }`}
                  aria-pressed={mode === "generate"}
                >
                  <span className="block font-medium">Generate Password</span>
                  <span className="mt-1 block text-xs text-muted-foreground">
                    Let Concord generate one.
                  </span>
                </button>

                <button
                  type="button"
                  onClick={() => {
                    setMode("custom");
                    setPasswordError(null);
                    setError(null);
                  }}
                  className={`rounded-lg border p-3 text-left text-sm transition-colors ${
                    mode === "custom"
                      ? "border-primary bg-primary/5"
                      : "hover:bg-muted"
                  }`}
                  aria-pressed={mode === "custom"}
                >
                  <span className="block font-medium">Custom Password</span>
                  <span className="mt-1 block text-xs text-muted-foreground">
                    Set your own password.
                  </span>
                </button>
              </div>
            </div>

            {mode === "custom" && (
              <div className="space-y-3">
                <div className="space-y-1.5">
                  <label
                    htmlFor="reset-password"
                    className="text-sm font-medium"
                  >
                    New Temporary Password
                  </label>
                  <input
                    id="reset-password"
                    type="password"
                    autoComplete="new-password"
                    minLength={12}
                    value={password}
                    onChange={(event) => setPassword(event.target.value)}
                    className="w-full rounded-md border bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring"
                    required
                  />
                </div>

                <div className="space-y-1.5">
                  <label
                    htmlFor="reset-password-confirmation"
                    className="text-sm font-medium"
                  >
                    Confirm Password
                  </label>
                  <input
                    id="reset-password-confirmation"
                    type="password"
                    autoComplete="new-password"
                    value={confirmation}
                    onChange={(event) => setConfirmation(event.target.value)}
                    className="w-full rounded-md border bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring"
                    required
                  />
                </div>

                <p className="text-xs text-muted-foreground">
                  Minimum 12 characters. Confirmation is checked here and is not
                  sent to the API.
                </p>
              </div>
            )}

            {passwordError && (
              <p className="text-sm text-destructive">{passwordError}</p>
            )}

            {error && <p className="text-sm text-destructive">{error}</p>}

            <div className="flex justify-end gap-2 border-t pt-4">
              <Button
                type="button"
                variant="outline"
                onClick={onClose}
                disabled={saving}
              >
                Cancel
              </Button>

              <Button type="submit" disabled={saving}>
                {saving ? "Resetting..." : "Reset Password"}
              </Button>
            </div>
          </form>
        )}
      </div>
    </div>
  );
}
