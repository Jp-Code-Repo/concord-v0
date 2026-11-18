import { useCallback, useEffect, useState } from "react";
import {
  AppWindow,
  RefreshCw,
  ShieldAlert,
  Plus,
  Eye,
  Power,
  PowerOff,
} from "lucide-react";

import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";

import { CreateLegacyAppDialog } from "@/components/iam/CreateLegacyAppDialog";
import { LegacyAppDetailsDialog } from "@/components/iam/LegacyAppDetailsDialog";
import { EditLegacyAppDialog } from "@/components/iam/EditLegacyAppDialog";
import { LegacyAppStatusDialog } from "@/components/iam/LegacyAppStatusDialog";

import { useAuth } from "@/contexts/AuthContext";

import {
  IamApiError,
  listIamLegacyAppsForManagement,
  type IamManagedLegacyApp,
} from "@/lib/iam";

function StatusBadge({ active }: { active: boolean }) {
  return (
    <span
      className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ${
        active
          ? "bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300"
          : "bg-muted text-muted-foreground"
      }`}
    >
      {active ? "Active" : "Inactive"}
    </span>
  );
}

export function LegacyAppsManagementPage() {
  const { user } = useAuth();

  const isAdministrator = user?.platform_authority.is_administrator === true;

  const [applications, setApplications] = useState<IamManagedLegacyApp[]>([]);

  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [reloadKey, setReloadKey] = useState(0);

  const [createDialogOpen, setCreateDialogOpen] = useState(false);
  const [selectedAppId, setSelectedAppId] = useState<string | null>(null);
  const [editingApplication, setEditingApplication] =
    useState<IamManagedLegacyApp | null>(null);
  const [statusApplication, setStatusApplication] =
    useState<IamManagedLegacyApp | null>(null);

  const loadApplications = useCallback(async () => {
    setLoading(true);
    setError(null);

    try {
      const result = await listIamLegacyAppsForManagement();
      setApplications(result.data);
    } catch (requestError: unknown) {
      if (requestError instanceof IamApiError && requestError.status === 403) {
        setError(
          "You do not have permission to manage the application directory.",
        );
      } else {
        setError(
          requestError instanceof Error
            ? requestError.message
            : "Unable to load registered applications.",
        );
      }
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    if (!isAdministrator) {
      setLoading(false);
      return;
    }

    void loadApplications();
  }, [isAdministrator, loadApplications, reloadKey]);

  function handleEditApplication(application: IamManagedLegacyApp) {
    setSelectedAppId(null);
    setEditingApplication(application);
  }

  function handleEditClose() {
    setEditingApplication(null);
  }

  function handleApplicationUpdated() {
    setReloadKey((current) => current + 1);
  }

  function handleStatusClose() {
    setStatusApplication(null);
  }

  if (!isAdministrator) {
    return (
      <div className="mx-auto flex min-h-[50vh] max-w-xl flex-col items-center justify-center text-center">
        <ShieldAlert className="mb-4 h-12 w-12 text-muted-foreground" />

        <h1 className="text-xl font-semibold">Access Restricted</h1>

        <p className="mt-2 text-sm text-muted-foreground">
          Legacy App Directory Management is available only to the designated
          Concord Administrator.
        </p>
      </div>
    );
  }

  return (
    <div className="mx-auto w-full max-w-[1600px] space-y-6">
      {/* Page Header */}
      <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <div className="flex items-center gap-2 text-sm text-muted-foreground">
            <span>Administration</span>
            <span>/</span>
            <span className="text-foreground">Legacy App Directory</span>
          </div>

          <h1 className="mt-3 text-3xl font-bold tracking-tight">
            Legacy App Directory
          </h1>

          <p className="mt-1 text-sm text-muted-foreground md:text-base">
            Manage the applications registered in Concord.
          </p>
        </div>

        {/* Page Actions */}
        <div className="flex items-center gap-2 self-start sm:self-auto">
          <Button onClick={() => setCreateDialogOpen(true)} disabled={loading}>
            <Plus className="h-4 w-4" />
            Register Application
          </Button>

          <Button
            variant="outline"
            onClick={() => setReloadKey((current) => current + 1)}
            disabled={loading}
          >
            <RefreshCw className={loading ? "animate-spin" : ""} />
            Refresh
          </Button>
        </div>
      </div>

      {/* Applications Listing */}
      <Card>
        <CardContent className="pt-4">
          {loading ? (
            <div className="flex min-h-48 flex-col items-center justify-center gap-3 text-muted-foreground">
              <RefreshCw className="h-6 w-6 animate-spin" />

              <p className="text-sm">Loading registered applications...</p>
            </div>
          ) : error ? (
            <div className="flex min-h-48 flex-col items-center justify-center gap-3 text-center">
              <ShieldAlert className="h-8 w-8 text-destructive" />

              <p className="max-w-lg text-sm text-muted-foreground">{error}</p>

              <Button
                variant="outline"
                onClick={() => setReloadKey((current) => current + 1)}
              >
                <RefreshCw />
                Try Again
              </Button>
            </div>
          ) : applications.length === 0 ? (
            <div className="flex min-h-48 flex-col items-center justify-center gap-3 text-center">
              <AppWindow className="h-10 w-10 text-muted-foreground" />

              <h2 className="font-semibold">No registered applications</h2>

              <p className="text-sm text-muted-foreground">
                Applications registered in Concord will appear here.
              </p>
            </div>
          ) : (
            <div className="space-y-4">
              <div className="flex items-center justify-between">
                <p className="text-sm text-muted-foreground">
                  Registered applications
                </p>

                <span className="text-sm font-medium tabular-nums">
                  {applications.length}{" "}
                  {applications.length === 1 ? "application" : "applications"}
                </span>
              </div>

              <div className="overflow-x-auto">
                <table className="w-full min-w-[1050px] text-sm">
                  <thead>
                    <tr className="border-b text-left text-muted-foreground">
                      <th className="px-4 py-3 font-medium">Application</th>

                      <th className="px-4 py-3 font-medium">URL</th>

                      <th className="px-4 py-3 font-medium">Description</th>

                      <th className="px-4 py-3 text-center font-medium">
                        Order
                      </th>

                      <th className="px-4 py-3 font-medium">Status</th>

                      <th className="px-4 py-3 text-right font-medium">
                        Actions
                      </th>
                    </tr>
                  </thead>

                  <tbody>
                    {applications.map((app) => (
                      <tr
                        key={app.id}
                        className="border-b last:border-0 transition-colors hover:bg-muted/40"
                      >
                        <td className="px-4 py-4">
                          <div className="flex items-center gap-3">
                            <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border bg-muted/50">
                              <AppWindow className="h-4 w-4 text-muted-foreground" />
                            </div>

                            <div className="min-w-0">
                              <p className="font-medium">{app.name}</p>

                              <p className="truncate text-xs text-muted-foreground">
                                {app.icon || "No icon identifier"}
                              </p>
                            </div>
                          </div>
                        </td>

                        <td className="max-w-[220px] px-4 py-4">
                          <a
                            href={app.url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="block truncate text-primary hover:underline"
                            title={app.url}
                          >
                            {app.url}
                          </a>
                        </td>

                        <td className="max-w-[250px] px-4 py-4">
                          <p className="truncate text-muted-foreground">
                            {app.description || "No description"}
                          </p>
                        </td>

                        <td className="px-4 py-4 text-center tabular-nums">
                          {app.sort_order}
                        </td>

                        <td className="px-4 py-4">
                          <StatusBadge active={app.is_active} />
                        </td>

                        <td className="px-4 py-4">
                          <div className="flex justify-end gap-2">
                            <Button
                              variant="outline"
                              size="sm"
                              onClick={() => setSelectedAppId(app.id)}
                            >
                              <Eye className="mr-1 h-4 w-4" />
                              Details
                            </Button>

                            <Button
                              variant="outline"
                              size="sm"
                              onClick={() => setStatusApplication(app)}
                              className={
                                app.is_active
                                  ? "text-destructive hover:text-destructive"
                                  : "text-emerald-600 hover:text-emerald-700 dark:text-emerald-400"
                              }
                            >
                              {app.is_active ? (
                                <PowerOff className="mr-1 h-4 w-4" />
                              ) : (
                                <Power className="mr-1 h-4 w-4" />
                              )}

                              {app.is_active ? "Deactivate" : "Activate"}
                            </Button>
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}
        </CardContent>
      </Card>

      {/* Registration Dialog */}
      <CreateLegacyAppDialog
        open={createDialogOpen}
        onClose={() => setCreateDialogOpen(false)}
        onCreated={() => setReloadKey((current) => current + 1)}
      />

      {/* Application Details Dialog */}
      <LegacyAppDetailsDialog
        appId={selectedAppId}
        onClose={() => setSelectedAppId(null)}
        onEdit={handleEditApplication}
      />

      {/* Application Edit Dialog */}
      <EditLegacyAppDialog
        application={editingApplication}
        onClose={handleEditClose}
        onUpdated={handleApplicationUpdated}
      />

      {/* Application Status Dialog */}
      <LegacyAppStatusDialog
        application={statusApplication}
        onClose={handleStatusClose}
        onUpdated={handleApplicationUpdated}
      />
    </div>
  );
}
