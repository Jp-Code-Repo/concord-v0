import { useEffect, useState } from "react";
import { useNavigate, useParams } from "@tanstack/react-router";
import { ArrowLeft, RefreshCw, ShieldAlert } from "lucide-react";
import { AlertDialog } from "@base-ui/react/alert-dialog";

import { Button } from "@/components/ui/button";
import { useAuth } from "@/contexts/AuthContext";

import {
  getIamUser,
  getIamUserLegacyAppsAccess,
  IamApiError,
  listIamLegacyAppsDirectory,
  grantIamLegacyAppAccess,
  revokeIamLegacyAppAccess,
  applyIamGrantSet,
  listIamGrantSets,
  type IamUserDetail,
  type IamUserLegacyAppsAccess,
  type IamLegacyApp,
  type IamGrantSet,
} from "@/lib/iam";

export function LegacyAppsAccessDetailPage() {
  const { userId } = useParams({ strict: false }) as {
    userId?: string;
  };

  const navigate = useNavigate();
  const { user } = useAuth();

  const isAdministrator = user?.platform_authority.is_administrator === true;

  const [userDetail, setUserDetail] = useState<IamUserDetail | null>(null);

  const [accessDetail, setAccessDetail] =
    useState<IamUserLegacyAppsAccess | null>(null);

  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [reloadKey, setReloadKey] = useState(0);
  const [availableApps, setAvailableApps] = useState<IamLegacyApp[]>([]);

  const [selectedAppId, setSelectedAppId] = useState("");
  const [granting, setGranting] = useState(false);
  const [grantError, setGrantError] = useState<string | null>(null);
  const [revokingAppId, setRevokingAppId] = useState<string | null>(null);
  const [revokeError, setRevokeError] = useState<string | null>(null);
  const [revokeTarget, setRevokeTarget] = useState<{
    id: string;
    name: string;
  } | null>(null);

  //Grant Set application state
  const [grantSets, setGrantSets] = useState<IamGrantSet[]>([]);
  const [grantSetsLoading, setGrantSetsLoading] = useState(true);
  const [grantSetsError, setGrantSetsError] = useState<string | null>(null);
  const [selectedGrantSetId, setSelectedGrantSetId] = useState("");
  const [grantSetTarget, setGrantSetTarget] = useState<IamGrantSet | null>(
    null,
  );
  const [applyingGrantSet, setApplyingGrantSet] = useState(false);
  const [applyGrantSetError, setApplyGrantSetError] = useState<string | null>(
    null,
  );

  const grantableApps = availableApps.filter(
    (app) =>
      !accessDetail?.applications.some(
        (assignedApp) => assignedApp.id === app.id,
      ),
  );

  const handleGrantAccess = async () => {
    if (!userId || !selectedAppId) return;

    setGranting(true);
    setGrantError(null);

    try {
      await grantIamLegacyAppAccess(userId, selectedAppId);

      setSelectedAppId("");
      setReloadKey((current) => current + 1);
    } catch (requestError: unknown) {
      if (requestError instanceof IamApiError) {
        if (requestError.status === 403) {
          setGrantError(
            "You do not have permission to manage Legacy Apps access.",
          );
          return;
        }

        if (requestError.status === 422) {
          setGrantError(
            "This application may already be assigned. Refresh the page and try again.",
          );
          setReloadKey((current) => current + 1);
          return;
        }
      }

      setGrantError(
        requestError instanceof Error
          ? requestError.message
          : "Unable to grant Legacy Apps access.",
      );
    } finally {
      setGranting(false);
    }
  };

  // Apply a Grant Set to this user
  const handleApplyGrantSet = async () => {
    if (!userId || !grantSetTarget) return;

    setApplyingGrantSet(true);
    setApplyGrantSetError(null);

    try {
      await applyIamGrantSet(userId, grantSetTarget.id);

      setGrantSetTarget(null);
      setSelectedGrantSetId("");
      setReloadKey((current) => current + 1);
    } catch (requestError: unknown) {
      if (requestError instanceof IamApiError) {
        if (requestError.status === 403) {
          setApplyGrantSetError(
            "You do not have permission to apply Grant Sets.",
          );
          return;
        }

        if (requestError.status === 422) {
          setApplyGrantSetError(
            "This Grant Set cannot currently be applied. It may have been archived. Refresh and try again.",
          );
          return;
        }
      }

      setApplyGrantSetError(
        requestError instanceof Error
          ? requestError.message
          : "Unable to apply Grant Set.",
      );
    } finally {
      setApplyingGrantSet(false);
    }
  };

  const handleRevokeAccess = async (appId: string) => {
    if (!userId) return;

    setRevokingAppId(appId);
    setRevokeError(null);

    try {
      await revokeIamLegacyAppAccess(userId, appId);

      setReloadKey((current) => current + 1);
    } catch (requestError: unknown) {
      if (requestError instanceof IamApiError) {
        if (requestError.status === 403) {
          setRevokeError(
            "You do not have permission to manage Legacy Apps access.",
          );
          return;
        }

        if (requestError.status === 422) {
          setRevokeError(
            "This assignment may already be revoked. Refresh the access details.",
          );
          setReloadKey((current) => current + 1);
          return;
        }
      }

      setRevokeError(
        requestError instanceof Error
          ? requestError.message
          : "Unable to revoke Legacy Apps access.",
      );
    } finally {
      setRevokingAppId(null);
    }
  };

  useEffect(() => {
    if (!isAdministrator || !userId) {
      setLoading(false);
      return;
    }

    let active = true;

    setLoading(true);
    setError(null);

    Promise.all([
      getIamUser(userId),
      getIamUserLegacyAppsAccess(userId),
      listIamLegacyAppsDirectory(),
    ])
      .then(([userResult, accessResult, directoryResult]) => {
        if (!active) return;

        setUserDetail(userResult);
        setAccessDetail(accessResult.data);
        setAvailableApps(directoryResult.data);
      })
      .catch((requestError: unknown) => {
        if (!active) return;

        if (requestError instanceof IamApiError) {
          if (requestError.status === 403) {
            setError(
              "You do not have permission to manage Legacy Apps access.",
            );
            return;
          }

          if (requestError.status === 404) {
            setError("The requested user account could not be found.");
            return;
          }
        }

        setError(
          requestError instanceof Error
            ? requestError.message
            : "Unable to load Legacy Apps access.",
        );
      })
      .finally(() => {
        if (active) setLoading(false);
      });

    return () => {
      active = false;
    };
  }, [isAdministrator, userId, reloadKey]);

  // Load active Grant Sets independently
  useEffect(() => {
    if (!isAdministrator) {
      setGrantSetsLoading(false);
      return;
    }

    let active = true;

    setGrantSetsLoading(true);
    setGrantSetsError(null);

    listIamGrantSets()
      .then((result) => {
        if (active) {
          setGrantSets(result.data);
        }
      })
      .catch((requestError: unknown) => {
        if (!active) return;

        setGrantSetsError(
          requestError instanceof Error
            ? requestError.message
            : "Unable to load Grant Sets.",
        );
      })
      .finally(() => {
        if (active) setGrantSetsLoading(false);
      });

    return () => {
      active = false;
    };
  }, [isAdministrator, reloadKey]);

  if (!isAdministrator) {
    return (
      <div className="mx-auto flex min-h-[50vh] max-w-xl flex-col items-center justify-center text-center">
        <ShieldAlert className="mb-4 h-12 w-12 text-muted-foreground" />
        <h1 className="text-xl font-semibold">Access Restricted</h1>
        <p className="mt-2 text-sm text-muted-foreground">
          Legacy Apps access management is available only to the designated
          Concord Administrator.
        </p>
      </div>
    );
  }

  const person = userDetail?.person;
  const account = userDetail?.user;

  return (
    <div className="mx-auto w-full max-w-[1600px] space-y-6">
      <div>
        <button
          type="button"
          onClick={() => navigate({ to: "/admin/legacy-apps/access" })}
          className="mb-4 inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
        >
          <ArrowLeft className="h-4 w-4" />
          Back to Legacy Apps Access
        </button>

        <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
          <div>
            <h1 className="text-3xl font-bold tracking-tight">
              Legacy Apps Access
            </h1>
            <p className="mt-1 text-sm text-muted-foreground">
              Manage application access assigned to this user.
            </p>
          </div>

          <Button
            variant="outline"
            className="gap-2"
            onClick={() => setReloadKey((value) => value + 1)}
            disabled={loading}
          >
            <RefreshCw className={`h-4 w-4 ${loading ? "animate-spin" : ""}`} />
            Refresh
          </Button>
        </div>
      </div>

      {loading ? (
        <section className="rounded-2xl border bg-card px-5 py-16 text-center text-sm text-muted-foreground">
          Loading user and access information...
        </section>
      ) : error ? (
        <section className="rounded-2xl border bg-card px-5 py-16 text-center">
          <ShieldAlert className="mx-auto mb-3 h-9 w-9 text-destructive" />
          <h2 className="font-semibold">Unable to load access details</h2>
          <p className="mt-2 text-sm text-muted-foreground">{error}</p>

          <Button
            className="mt-5"
            onClick={() => setReloadKey((value) => value + 1)}
          >
            Try Again
          </Button>
        </section>
      ) : account && accessDetail ? (
        <>
          <section className="rounded-2xl border bg-card p-6">
            <h2 className="text-xl font-semibold">
              {person?.display_name || account.username}
            </h2>
            <p className="mt-1 text-sm text-muted-foreground">
              @{account.username}
            </p>
            <p className="mt-3 text-xs text-muted-foreground">
              User ID: {account.id}
            </p>
          </section>

          <section className="grid gap-4 sm:grid-cols-3">
            <div className="rounded-xl border bg-card p-5">
              <p className="text-sm text-muted-foreground">
                Active Applications
              </p>
              <p className="mt-2 text-2xl font-bold">
                {accessDetail.applications.length}
              </p>
            </div>

            <div className="rounded-xl border bg-card p-5">
              <p className="text-sm text-muted-foreground">
                Inactive Assignments
              </p>
              <p className="mt-2 text-2xl font-bold">
                {accessDetail.inactive_assignments.length}
              </p>
            </div>

            <div className="rounded-xl border bg-card p-5">
              <p className="text-sm text-muted-foreground">Grant Set History</p>
              <p className="mt-2 text-2xl font-bold">
                {accessDetail.grant_sets.length}
              </p>
            </div>
          </section>

          {/* Apply Grant Set */}
          <section className="rounded-2xl border bg-card p-5">
            <div className="mb-4">
              <h2 className="font-semibold">Apply Grant Set</h2>
              <p className="mt-1 text-sm text-muted-foreground">
                Assign a reusable collection of applications to this user.
              </p>
            </div>

            <div className="flex flex-col gap-3 sm:flex-row">
              <select
                value={selectedGrantSetId}
                onChange={(event) => {
                  setSelectedGrantSetId(event.target.value);
                  setApplyGrantSetError(null);
                }}
                disabled={grantSetsLoading || grantSets.length === 0}
                className="h-10 w-full rounded-md border bg-background px-3 text-sm sm:flex-1"
              >
                <option value="">
                  {grantSetsLoading
                    ? "Loading Grant Sets..."
                    : "Select a Grant Set"}
                </option>

                {grantSets.map((grantSet) => (
                  <option key={grantSet.id} value={grantSet.id}>
                    {grantSet.name} ({grantSet.items.length} applications)
                  </option>
                ))}
              </select>

              <Button
                disabled={!selectedGrantSetId || grantSetsLoading}
                onClick={() => {
                  const target = grantSets.find(
                    (item) => item.id === selectedGrantSetId,
                  );

                  if (target) {
                    setGrantSetTarget(target);
                    setApplyGrantSetError(null);
                  }
                }}
              >
                Apply Grant Set
              </Button>
            </div>

            {grantSetsError && (
              <p className="mt-3 text-sm text-destructive">{grantSetsError}</p>
            )}

            {!grantSetsLoading && !grantSetsError && grantSets.length === 0 && (
              <p className="mt-3 text-sm text-muted-foreground">
                No active Grant Sets are available. Create one in Grant Set
                management first.
              </p>
            )}

            {applyGrantSetError && (
              <p className="mt-3 text-sm text-destructive">
                {applyGrantSetError}
              </p>
            )}
          </section>

          {/* Grant application access */}
          <section className="rounded-2xl border bg-card p-5">
            <div className="mb-4">
              <h2 className="font-semibold">Grant Application Access</h2>
              <p className="mt-1 text-sm text-muted-foreground">
                Assign an active Legacy App to this user.
              </p>
            </div>

            <div className="flex flex-col gap-3 sm:flex-row">
              <select
                value={selectedAppId}
                onChange={(event) => {
                  setSelectedAppId(event.target.value);
                  setGrantError(null);
                }}
                disabled={granting || grantableApps.length === 0}
                className="h-10 w-full rounded-md border bg-background px-3 text-sm sm:flex-1"
              >
                <option value="">Select an application</option>

                {grantableApps.map((app) => (
                  <option key={app.id} value={app.id}>
                    {app.name}
                  </option>
                ))}
              </select>

              <Button
                onClick={handleGrantAccess}
                disabled={!selectedAppId || granting}
              >
                {granting ? "Granting..." : "Grant Access"}
              </Button>
            </div>

            {grantableApps.length === 0 && (
              <p className="mt-3 text-sm text-muted-foreground">
                No additional active applications are available to assign.
              </p>
            )}

            {grantError && (
              <p className="mt-3 text-sm text-destructive">{grantError}</p>
            )}
          </section>

          {/* Active application assignments */}
          <section className="overflow-hidden rounded-2xl border bg-card">
            <div className="border-b px-5 py-4">
              <h2 className="font-semibold">Active Applications</h2>
              <p className="mt-1 text-sm text-muted-foreground">
                Applications currently assigned to this user.
              </p>
            </div>

            {accessDetail.applications.length === 0 ? (
              <p className="p-5 text-sm text-muted-foreground">
                No active application assignments.
              </p>
            ) : (
              <div className="divide-y">
                {accessDetail.applications.map((app) => (
                  <div
                    key={app.id}
                    className="flex flex-col justify-between gap-2 p-5 sm:flex-row sm:items-center"
                  >
                    <div>
                      <p className="font-medium">{app.name}</p>
                      <p className="text-sm text-muted-foreground">{app.url}</p>
                    </div>

                    <div className="flex flex-col gap-3 sm:items-end">
                      <div className="text-sm sm:text-right">
                        <p className="font-medium capitalize">
                          {app.grant_source.replace("_", " ")}
                        </p>
                        <p className="text-muted-foreground">
                          Granted:{" "}
                          {new Intl.DateTimeFormat("en-PH", {
                            dateStyle: "medium",
                          }).format(new Date(app.granted_at))}
                        </p>
                      </div>

                      <Button
                        variant="destructive"
                        size="sm"
                        disabled={revokingAppId !== null}
                        onClick={() => {
                          setRevokeTarget({ id: app.id, name: app.name });
                          setRevokeError(null);
                        }}
                      >
                        {revokingAppId === app.id
                          ? "Revoking..."
                          : "Revoke Access"}
                      </Button>
                    </div>
                  </div>
                ))}
              </div>
            )}
            {revokeError && (
              <p className="border-t px-5 py-3 text-sm text-destructive">
                {revokeError}
              </p>
            )}
          </section>

          {/* Inactive application assignments */}
          <section className="overflow-hidden rounded-2xl border bg-card">
            <div className="border-b px-5 py-4">
              <h2 className="font-semibold">Inactive Assignments</h2>
              <p className="mt-1 text-sm text-muted-foreground">
                Existing assignments for applications that are currently
                inactive.
              </p>
            </div>

            {accessDetail.inactive_assignments.length === 0 ? (
              <p className="p-5 text-sm text-muted-foreground">
                No inactive application assignments.
              </p>
            ) : (
              <div className="divide-y">
                {accessDetail.inactive_assignments.map((app) => (
                  <div
                    key={app.id}
                    className="flex flex-col justify-between gap-2 p-5 sm:flex-row sm:items-center"
                  >
                    <div>
                      <p className="font-medium">{app.name}</p>
                      <p className="text-sm text-muted-foreground">{app.url}</p>
                    </div>

                    <span className="self-start rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-800 dark:bg-amber-950/40 dark:text-amber-300 sm:self-center">
                      Inactive application
                    </span>
                  </div>
                ))}
              </div>
            )}
          </section>

          {/* NEW: Grant Set application history */}
          <section className="overflow-hidden rounded-2xl border bg-card">
            <div className="border-b px-5 py-4">
              <h2 className="font-semibold">Grant Set History</h2>
              <p className="mt-1 text-sm text-muted-foreground">
                Historical records of Grant Sets applied to this user. This is
                not a list of current application access.
              </p>
            </div>

            {accessDetail.grant_sets.length === 0 ? (
              <p className="p-5 text-sm text-muted-foreground">
                No Grant Set history.
              </p>
            ) : (
              <div className="divide-y">
                {accessDetail.grant_sets.map((grantSet) => (
                  <div key={grantSet.grant_set_id} className="p-5">
                    <p className="font-medium">
                      {grantSet.name || "Unnamed Grant Set"}
                    </p>
                    <p className="mt-1 text-sm text-muted-foreground">
                      First applied:{" "}
                      {new Intl.DateTimeFormat("en-PH", {
                        dateStyle: "medium",
                        timeStyle: "short",
                      }).format(new Date(grantSet.first_applied_at))}
                    </p>
                    <p className="text-sm text-muted-foreground">
                      Last applied:{" "}
                      {new Intl.DateTimeFormat("en-PH", {
                        dateStyle: "medium",
                        timeStyle: "short",
                      }).format(new Date(grantSet.last_applied_at))}
                    </p>
                  </div>
                ))}
              </div>
            )}
          </section>
        </>
      ) : (
        <section className="rounded-2xl border bg-card px-5 py-16 text-center text-sm text-muted-foreground">
          No access information is available.
        </section>
      )}

      <AlertDialog.Root
        open={revokeTarget !== null}
        onOpenChange={(open) => {
          if (!open && revokingAppId === null) {
            setRevokeTarget(null);
          }
        }}
      >
        <AlertDialog.Portal>
          <AlertDialog.Backdrop className="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm transition-opacity" />

          <AlertDialog.Popup className="fixed left-1/2 top-1/2 z-50 w-[calc(100%-2rem)] max-w-md -translate-x-1/2 -translate-y-1/2 rounded-xl border bg-card p-6 text-card-foreground shadow-xl">
            <div className="mb-5">
              <div className="mb-4 flex h-10 w-10 items-center justify-center rounded-full bg-destructive/10 text-destructive">
                <ShieldAlert className="h-5 w-5" />
              </div>

              <AlertDialog.Title className="text-lg font-semibold">
                Revoke application access?
              </AlertDialog.Title>

              <AlertDialog.Description className="mt-2 text-sm leading-relaxed text-muted-foreground">
                You're about to remove this user's access to{" "}
                <span className="font-medium text-foreground">
                  {revokeTarget?.name}
                </span>
                . They will no longer be able to access this application through
                Concord.
              </AlertDialog.Description>
            </div>

            <div className="flex justify-end gap-2">
              <Button
                variant="outline"
                disabled={revokingAppId !== null}
                onClick={() => setRevokeTarget(null)}
              >
                Cancel
              </Button>

              <Button
                variant="destructive"
                disabled={revokingAppId !== null || !revokeTarget}
                onClick={async () => {
                  if (!revokeTarget) return;

                  await handleRevokeAccess(revokeTarget.id);
                  setRevokeTarget(null);
                }}
              >
                {revokingAppId !== null ? "Revoking..." : "Revoke Access"}
              </Button>
            </div>
          </AlertDialog.Popup>
        </AlertDialog.Portal>
      </AlertDialog.Root>

      {/* NEW: Apply Grant Set confirmation */}
      <AlertDialog.Root
        open={grantSetTarget !== null}
        onOpenChange={(open) => {
          if (!open && !applyingGrantSet) {
            setGrantSetTarget(null);
          }
        }}
      >
        <AlertDialog.Portal>
          <AlertDialog.Backdrop className="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm transition-opacity" />

          <AlertDialog.Popup className="fixed left-1/2 top-1/2 z-50 w-[calc(100%-2rem)] max-w-md -translate-x-1/2 -translate-y-1/2 rounded-xl border bg-card p-6 text-card-foreground shadow-xl">
            <div className="mb-5">
              <div className="mb-4 flex h-10 w-10 items-center justify-center rounded-full bg-primary/10 text-primary">
                <ShieldAlert className="h-5 w-5" />
              </div>

              <AlertDialog.Title className="text-lg font-semibold">
                Apply Grant Set?
              </AlertDialog.Title>

              <AlertDialog.Description className="mt-2 text-sm leading-relaxed text-muted-foreground">
                You are about to apply{" "}
                <span className="font-medium text-foreground">
                  {grantSetTarget?.name}
                </span>{" "}
                to this user. Existing active application access will not be
                duplicated or removed.
              </AlertDialog.Description>
            </div>

            <div className="flex justify-end gap-2">
              <Button
                variant="outline"
                disabled={applyingGrantSet}
                onClick={() => setGrantSetTarget(null)}
              >
                Cancel
              </Button>

              <Button
                disabled={applyingGrantSet || !grantSetTarget}
                onClick={handleApplyGrantSet}
              >
                {applyingGrantSet ? "Applying..." : "Confirm Apply"}
              </Button>
            </div>
          </AlertDialog.Popup>
        </AlertDialog.Portal>
      </AlertDialog.Root>
      
    </div>
  );
}
