import { useEffect, useState } from "react";
import {
  AppWindow,
  CalendarDays,
  Pencil,
  X,
  RefreshCw,
  ShieldAlert,
} from "lucide-react";

import { Button } from "@/components/ui/button";
import {
  getIamManagedLegacyApp,
  IamApiError,
  type IamManagedLegacyApp,
} from "@/lib/iam";

interface LegacyAppDetailsDialogProps {
  appId: string | null;
  onClose: () => void;
  onEdit: (application: IamManagedLegacyApp) => void;
}

function DetailItem({
  label,
  children,
}: {
  label: string;
  children: React.ReactNode;
}) {
  return (
    <div className="space-y-1">
      <p className="text-xs font-medium text-muted-foreground">{label}</p>
      <div className="break-words text-sm font-medium">
        {children || "Not provided"}
      </div>
    </div>
  );
}

function formatDate(value: string | null | undefined) {
  if (!value) return "Not available";

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return value;
  }

  return date.toLocaleString();
}

export function LegacyAppDetailsDialog({
  appId,
  onClose,
  onEdit,
}: LegacyAppDetailsDialogProps) {
  const [application, setApplication] = useState<IamManagedLegacyApp | null>(
    null,
  );

  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!appId) {
      setApplication(null);
      setError(null);
      return;
    }

    let cancelled = false;

    async function loadDetails() {
      setLoading(true);
      setError(null);
      setApplication(null);

      try {
        const result = await getIamManagedLegacyApp(appId!);

        if (!cancelled) {
          setApplication(result.data);
        }
      } catch (requestError: unknown) {
        if (cancelled) return;

        if (
          requestError instanceof IamApiError &&
          requestError.status === 403
        ) {
          setError("You do not have permission to view this application.");
        } else if (
          requestError instanceof IamApiError &&
          requestError.status === 404
        ) {
          setError("This application could not be found.");
        } else {
          setError(
            requestError instanceof Error
              ? requestError.message
              : "Unable to retrieve application details.",
          );
        }
      } finally {
        if (!cancelled) {
          setLoading(false);
        }
      }
    }

    void loadDetails();

    return () => {
      cancelled = true;
    };
  }, [appId]);

  if (!appId) return null;

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
      onMouseDown={(event) => {
        if (event.target === event.currentTarget) onClose();
      }}
    >
      <section
        role="dialog"
        aria-modal="true"
        aria-labelledby="legacy-app-details-title"
        className="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-xl border bg-background shadow-xl"
      >
        <div className="flex items-center justify-between border-b px-6 py-4">
          <div>
            <div className="flex items-center gap-2">
              <AppWindow className="h-5 w-5 text-muted-foreground" />

              <h2
                id="legacy-app-details-title"
                className="text-lg font-semibold"
              >
                Application Details
              </h2>
            </div>

            <p className="mt-1 text-sm text-muted-foreground">
              Complete information about this registered application.
            </p>
          </div>

          <Button
            type="button"
            variant="ghost"
            size="icon"
            onClick={onClose}
            aria-label="Close dialog"
          >
            <X className="h-4 w-4" />
          </Button>
        </div>

        <div className="overflow-y-auto p-6">
          {loading ? (
            <div className="flex min-h-48 flex-col items-center justify-center gap-3 text-muted-foreground">
              <RefreshCw className="h-6 w-6 animate-spin" />
              <p className="text-sm">Loading application details...</p>
            </div>
          ) : error ? (
            <div className="flex min-h-48 flex-col items-center justify-center gap-3 text-center">
              <ShieldAlert className="h-8 w-8 text-destructive" />

              <p className="max-w-md text-sm text-muted-foreground">{error}</p>
            </div>
          ) : application ? (
            <div className="space-y-6">
              <div className="flex items-center gap-4 rounded-lg border bg-muted/20 p-4">
                <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl border bg-background">
                  <AppWindow className="h-6 w-6 text-muted-foreground" />
                </div>

                <div className="min-w-0 flex-1">
                  <h3 className="font-semibold">{application.name}</h3>

                  <p className="text-sm text-muted-foreground">
                    {application.icon || "No icon identifier"}
                  </p>
                </div>

                <span
                  className={`shrink-0 rounded-full px-3 py-1 text-xs font-medium ${
                    application.is_active
                      ? "bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300"
                      : "bg-muted text-muted-foreground"
                  }`}
                >
                  {application.is_active ? "Active" : "Inactive"}
                </span>
              </div>

              <div className="grid gap-5 sm:grid-cols-2">
                <DetailItem label="Application ID">{application.id}</DetailItem>

                <DetailItem label="Display Order">
                  {application.sort_order}
                </DetailItem>

                <div className="sm:col-span-2">
                  <DetailItem label="Application URL">
                    <a
                      href={application.url}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="text-primary hover:underline"
                    >
                      {application.url}
                    </a>
                  </DetailItem>
                </div>

                <div className="sm:col-span-2">
                  <DetailItem label="Description">
                    {application.description || "No description provided"}
                  </DetailItem>
                </div>
              </div>

              <div className="border-t pt-5">
                <div className="mb-4 flex items-center gap-2">
                  <CalendarDays className="h-4 w-4 text-muted-foreground" />
                  <h3 className="text-sm font-semibold">Record Information</h3>
                </div>

                <div className="grid gap-5 sm:grid-cols-2">
                  <DetailItem label="Created At">
                    {formatDate(application.created_at)}
                  </DetailItem>

                  <DetailItem label="Last Updated">
                    {formatDate(application.updated_at)}
                  </DetailItem>
                </div>
              </div>
            </div>
          ) : null}
        </div>

        <div className="flex justify-end gap-2 border-t bg-muted/20 px-6 py-4">
          <Button variant="outline" onClick={onClose}>
            Close
          </Button>

          {application && (
            <Button onClick={() => onEdit(application)}>
              <Pencil className="mr-1 h-4 w-4" />
              Edit Application
            </Button>
          )}
        </div>
      </section>
    </div>
  );
}
