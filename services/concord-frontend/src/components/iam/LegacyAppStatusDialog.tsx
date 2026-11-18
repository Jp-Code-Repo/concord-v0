import { useState } from "react";
import { Power, PowerOff, X } from "lucide-react";
import { toast } from "sonner";

import { Button } from "@/components/ui/button";
import {
  activateIamLegacyApp,
  deactivateIamLegacyApp,
  IamApiError,
  type IamManagedLegacyApp,
} from "@/lib/iam";

interface LegacyAppStatusDialogProps {
  application: IamManagedLegacyApp | null;
  onClose: () => void;
  onUpdated?: () => void;
}

export function LegacyAppStatusDialog({
  application,
  onClose,
  onUpdated,
}: LegacyAppStatusDialogProps) {
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState("");

  if (!application) return null;

  const isCurrentlyActive = application.is_active;
  const actionLabel = isCurrentlyActive ? "Deactivate" : "Activate";
  const Icon = isCurrentlyActive ? PowerOff : Power;

  function handleClose() {
    if (isSubmitting) return;
    setError("");
    onClose();
  }

  async function handleConfirm() {
    if (!application) return;

    setIsSubmitting(true);
    setError("");

    try {
      if (application.is_active) {
        await deactivateIamLegacyApp(application.id);
        toast.success("Application deactivated successfully.");
      } else {
        await activateIamLegacyApp(application.id);
        toast.success("Application activated successfully.");
      }

      onUpdated?.();
      onClose();
    } catch (requestError: unknown) {
      if (requestError instanceof IamApiError) {
        if (requestError.status === 403) {
          setError(
            "You do not have permission to change this application's status.",
          );
        } else if (requestError.status === 404) {
          setError(
            "This application could not be found. Refresh the directory and try again.",
          );
        } else {
          setError(requestError.message);
        }
      } else {
        setError(
          "Unable to update the application's status. Please check your connection and try again.",
        );
      }
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <div
      className="fixed inset-0 z-[70] flex items-center justify-center bg-black/50 p-4"
      onMouseDown={(event) => {
        if (event.target === event.currentTarget) {
          handleClose();
        }
      }}
    >
      <section
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="legacy-app-status-title"
        aria-describedby="legacy-app-status-description"
        className="w-full max-w-md overflow-hidden rounded-xl border bg-background shadow-xl"
      >
        <div className="flex items-center justify-between border-b px-6 py-4">
          <h2 id="legacy-app-status-title" className="text-lg font-semibold">
            {actionLabel} Application
          </h2>

          <Button
            type="button"
            variant="ghost"
            size="icon"
            onClick={handleClose}
            disabled={isSubmitting}
            aria-label="Close dialog"
          >
            <X className="h-4 w-4" />
          </Button>
        </div>

        <div className="space-y-4 p-6">
          <div className="flex items-start gap-3">
            <div
              className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-lg ${
                isCurrentlyActive
                  ? "bg-destructive/10 text-destructive"
                  : "bg-emerald-500/10 text-emerald-600"
              }`}
            >
              <Icon className="h-5 w-5" />
            </div>

            <div className="min-w-0">
              <p className="font-semibold">{application.name}</p>

              <p
                id="legacy-app-status-description"
                className="mt-1 text-sm text-muted-foreground"
              >
                {isCurrentlyActive
                  ? "Are you sure you want to deactivate this application? It will no longer appear in the public application directory."
                  : "Are you sure you want to activate this application? It will become available in the public application directory again."}
              </p>
            </div>
          </div>

          {isCurrentlyActive && (
            <div className="rounded-lg border bg-muted/30 px-4 py-3">
              <p className="text-sm text-muted-foreground">
                Deactivation does not delete the application or remove its
                existing individual grants and Grant Set memberships.
              </p>
            </div>
          )}

          {error && (
            <div
              role="alert"
              className="rounded-lg border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive"
            >
              {error}
            </div>
          )}
        </div>

        <div className="flex justify-end gap-2 border-t bg-muted/20 px-6 py-4">
          <Button
            type="button"
            variant="outline"
            onClick={handleClose}
            disabled={isSubmitting}
          >
            Cancel
          </Button>

          <Button
            type="button"
            variant={isCurrentlyActive ? "destructive" : "default"}
            onClick={handleConfirm}
            disabled={isSubmitting}
          >
            {isSubmitting ? "Processing..." : actionLabel}
          </Button>
        </div>
      </section>
    </div>
  );
}
