import { useEffect, useMemo, useState } from "react";
import {
  Archive,
  AppWindow,
  RefreshCw,
  Search,
  ShieldAlert,
  Pencil,
} from "lucide-react";

import { Button } from "@/components/ui/button";
import { useAuth } from "@/contexts/AuthContext";
import {
  IamApiError,
  createIamGrantSet,
  listIamGrantSets,
  listIamLegacyAppsDirectory,
  updateIamGrantSet,
  type IamGrantSet,
  type IamLegacyApp,
} from "@/lib/iam";

export function GrantSetsPage() {
  const { user } = useAuth();
  const isAdministrator = user?.platform_authority.is_administrator === true;

  const [grantSets, setGrantSets] = useState<IamGrantSet[]>([]);
  const [applications, setApplications] = useState<IamLegacyApp[]>([]);
  const [search, setSearch] = useState("");
  const [includeArchived, setIncludeArchived] = useState(false);

  // Create Grant Set form state
  const [createOpen, setCreateOpen] = useState(false);
  const [newGrantSetName, setNewGrantSetName] = useState("");
  const [newGrantSetDescription, setNewGrantSetDescription] = useState("");
  const [selectedLegacyAppIds, setSelectedLegacyAppIds] = useState<string[]>(
    [],
  );
  const [creatingGrantSet, setCreatingGrantSet] = useState(false);
  const [createGrantSetError, setCreateGrantSetError] = useState<string | null>(
    null,
  );

  // Edit Grant Set form state
  const [editingGrantSet, setEditingGrantSet] = useState<IamGrantSet | null>(
    null,
  );
  const [editGrantSetName, setEditGrantSetName] = useState("");
  const [editGrantSetDescription, setEditGrantSetDescription] = useState("");
  const [editLegacyAppIds, setEditLegacyAppIds] = useState<string[]>([]);
  const [editGrantSetError, setEditGrantSetError] = useState<string | null>(
    null,
  );
  const [updatingGrantSet, setUpdatingGrantSet] = useState(false);

  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [reloadKey, setReloadKey] = useState(0);

  useEffect(() => {
    if (!isAdministrator) {
      setLoading(false);
      return;
    }

    let active = true;

    setLoading(true);
    setError(null);

    Promise.all([
      listIamGrantSets(includeArchived),
      listIamLegacyAppsDirectory(),
    ])
      .then(([grantSetResult, appResult]) => {
        if (!active) return;

        setGrantSets(grantSetResult.data);
        setApplications(appResult.data);
      })
      .catch((requestError: unknown) => {
        if (!active) return;

        if (
          requestError instanceof IamApiError &&
          requestError.status === 403
        ) {
          setError("You do not have permission to manage Grant Sets.");
          return;
        }

        setError(
          requestError instanceof Error
            ? requestError.message
            : "Unable to load Grant Sets.",
        );
      })
      .finally(() => {
        if (active) setLoading(false);
      });

    return () => {
      active = false;
    };
  }, [isAdministrator, includeArchived, reloadKey]);

  const applicationNames = useMemo(
    () => new Map(applications.map((app) => [app.id, app.name])),
    [applications],
  );

  const filteredGrantSets = useMemo(() => {
    const normalizedSearch = search.trim().toLowerCase();

    if (!normalizedSearch) return grantSets;

    return grantSets.filter((grantSet) => {
      const memberNames = grantSet.items.map(
        (item) =>
          applicationNames.get(item.legacy_app_id) ?? item.legacy_app_id,
      );

      return [grantSet.name, grantSet.description ?? "", ...memberNames]
        .join(" ")
        .toLowerCase()
        .includes(normalizedSearch);
    });
  }, [grantSets, search, applicationNames]);

  // Create Grant Set
  const handleCreateGrantSet = async (
    event: React.FormEvent<HTMLFormElement>,
  ) => {
    event.preventDefault();

    if (!newGrantSetName.trim()) return;

    setCreatingGrantSet(true);
    setCreateGrantSetError(null);

    try {
      await createIamGrantSet({
        name: newGrantSetName.trim(),
        description: newGrantSetDescription.trim() || null,
        legacy_app_ids: selectedLegacyAppIds,
      });

      setNewGrantSetName("");
      setNewGrantSetDescription("");
      setSelectedLegacyAppIds([]);
      setCreateOpen(false);
      setReloadKey((current) => current + 1);
    } catch (requestError: unknown) {
      if (requestError instanceof IamApiError && requestError.status === 403) {
        setCreateGrantSetError(
          "You do not have permission to create Grant Sets.",
        );
        return;
      }

      setCreateGrantSetError(
        requestError instanceof Error
          ? requestError.message
          : "Unable to create Grant Set.",
      );
    } finally {
      setCreatingGrantSet(false);
    }
  };

  // Update Grant Set
  const handleUpdateGrantSet = async (
    event: React.FormEvent<HTMLFormElement>,
  ) => {
    event.preventDefault();

    if (!editingGrantSet || !editGrantSetName.trim()) return;

    setUpdatingGrantSet(true);
    setEditGrantSetError(null);

    try {
      await updateIamGrantSet(editingGrantSet.id, {
        name: editGrantSetName.trim(),
        description: editGrantSetDescription.trim() || null,
        legacy_app_ids: editLegacyAppIds,
      });

      setEditingGrantSet(null);
      setEditGrantSetName("");
      setEditGrantSetDescription("");
      setEditLegacyAppIds([]);

      setReloadKey((current) => current + 1);
    } catch (requestError: unknown) {
      if (requestError instanceof IamApiError && requestError.status === 403) {
        setEditGrantSetError(
          "You do not have permission to update Grant Sets.",
        );
        return;
      }

      setEditGrantSetError(
        requestError instanceof Error
          ? requestError.message
          : "Unable to update Grant Set.",
      );
    } finally {
      setUpdatingGrantSet(false);
    }
  };

  if (!isAdministrator) {
    return (
      <div className="mx-auto flex min-h-[50vh] max-w-xl flex-col items-center justify-center text-center">
        <ShieldAlert className="mb-4 h-12 w-12 text-muted-foreground" />

        <h1 className="text-xl font-semibold">Access Restricted</h1>

        <p className="mt-2 text-sm text-muted-foreground">
          Grant Set management is available only to the designated Concord
          Administrator.
        </p>
      </div>
    );
  }

  return (
    <div className="mx-auto w-full max-w-[1600px] space-y-6">
      {/* Page heading */}
      <div>
        <div className="flex items-center gap-2 text-sm text-muted-foreground">
          <span>Administrator</span>
          <span>/</span>
          <span className="text-foreground">Grant Sets</span>
        </div>

        <div className="mt-3 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
          <div>
            <h1 className="text-3xl font-bold tracking-tight">Grant Sets</h1>

            <p className="mt-1 text-sm text-muted-foreground md:text-base">
              Manage reusable application access templates.
            </p>
          </div>

          <Button
            variant="outline"
            className="gap-2 self-start sm:self-auto"
            onClick={() => setReloadKey((value) => value + 1)}
            disabled={loading}
          >
            <RefreshCw className={`h-4 w-4 ${loading ? "animate-spin" : ""}`} />
            Refresh
          </Button>
        </div>
      </div>

      {/* Create Grant Set form */}
      <section className="rounded-2xl border bg-card p-5">
        <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
          <div>
            <h2 className="font-semibold">Create Grant Set</h2>
            <p className="mt-1 text-sm text-muted-foreground">
              Define a reusable collection of legacy applications.
            </p>
          </div>

          <Button
            type="button"
            onClick={() => {
              setCreateOpen((open) => !open);
              setCreateGrantSetError(null);
            }}
          >
            {createOpen ? "Close form" : "New Grant Set"}
          </Button>
        </div>

        {createOpen && (
          <form
            className="mt-5 space-y-5 border-t pt-5"
            onSubmit={handleCreateGrantSet}
          >
            <div className="space-y-2">
              <label htmlFor="grant-set-name" className="text-sm font-medium">
                Grant Set name <span className="text-destructive">*</span>
              </label>
              <input
                id="grant-set-name"
                type="text"
                maxLength={150}
                required
                value={newGrantSetName}
                onChange={(event) => setNewGrantSetName(event.target.value)}
                placeholder="e.g. Teaching Personnel"
                className="h-10 w-full rounded-lg border bg-background px-3 text-sm outline-none focus:border-ring focus:ring-2 focus:ring-ring/20"
              />
              <p className="text-xs text-muted-foreground">
                Maximum 150 characters.
              </p>
            </div>

            <div className="space-y-2">
              <label
                htmlFor="grant-set-description"
                className="text-sm font-medium"
              >
                Description
              </label>
              <textarea
                id="grant-set-description"
                rows={3}
                value={newGrantSetDescription}
                onChange={(event) =>
                  setNewGrantSetDescription(event.target.value)
                }
                placeholder="Describe the intended users or purpose of this Grant Set."
                className="w-full rounded-lg border bg-background px-3 py-2 text-sm outline-none focus:border-ring focus:ring-2 focus:ring-ring/20"
              />
            </div>

            <div className="space-y-3">
              <div>
                <h3 className="text-sm font-medium">Applications</h3>
                <p className="mt-1 text-xs text-muted-foreground">
                  Select the applications included in this Grant Set.
                </p>
              </div>

              {applications.length === 0 ? (
                <p className="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                  No applications are available in the application directory.
                </p>
              ) : (
                <div className="grid gap-2 sm:grid-cols-2">
                  {applications.map((app) => (
                    <label
                      key={app.id}
                      className="flex cursor-pointer items-center gap-3 rounded-lg border p-3 text-sm transition-colors hover:bg-muted/40"
                    >
                      <input
                        type="checkbox"
                        checked={selectedLegacyAppIds.includes(app.id)}
                        onChange={(event) => {
                          setSelectedLegacyAppIds((current) =>
                            event.target.checked
                              ? [...current, app.id]
                              : current.filter((id) => id !== app.id),
                          );
                        }}
                        className="h-4 w-4 rounded border-input accent-primary"
                      />
                      <span>{app.name}</span>
                    </label>
                  ))}
                </div>
              )}

              <p className="text-xs text-muted-foreground">
                {selectedLegacyAppIds.length} application(s) selected.
              </p>
            </div>

            {createGrantSetError && (
              <p className="text-sm text-destructive">{createGrantSetError}</p>
            )}

            <div className="flex justify-end gap-2">
              <Button
                type="button"
                variant="outline"
                onClick={() => setCreateOpen(false)}
                disabled={creatingGrantSet}
              >
                Cancel
              </Button>

              <Button
                type="submit"
                disabled={!newGrantSetName.trim() || creatingGrantSet}
              >
                {creatingGrantSet ? "Creating..." : "Create Grant Set"}
              </Button>
            </div>
          </form>
        )}
      </section>

      {/* NEW: Edit Grant Set form */}
      {editingGrantSet && (
        <section className="rounded-lg border bg-card p-5">
          <div className="mb-5">
            <h2 className="text-lg font-semibold">Edit Grant Set</h2>
            <p className="text-sm text-muted-foreground">
              Update the details and application membership of{" "}
              <span className="font-medium text-foreground">
                {editingGrantSet.name}
              </span>
              .
            </p>
          </div>

          <form onSubmit={handleUpdateGrantSet} className="space-y-5">
            {editGrantSetError && (
              <div className="rounded-md border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive">
                {editGrantSetError}
              </div>
            )}

            {/* Grant Set name */}
            <div className="space-y-2">
              <label
                htmlFor="edit-grant-set-name"
                className="text-sm font-medium"
              >
                Name
              </label>
              <input
                id="edit-grant-set-name"
                type="text"
                maxLength={150}
                required
                value={editGrantSetName}
                onChange={(event) => setEditGrantSetName(event.target.value)}
                className="w-full rounded-md border bg-background px-3 py-2 text-sm"
              />
            </div>

            {/* Description */}
            <div className="space-y-2">
              <label
                htmlFor="edit-grant-set-description"
                className="text-sm font-medium"
              >
                Description
              </label>
              <textarea
                id="edit-grant-set-description"
                rows={3}
                value={editGrantSetDescription}
                onChange={(event) =>
                  setEditGrantSetDescription(event.target.value)
                }
                className="w-full rounded-md border bg-background px-3 py-2 text-sm"
              />
            </div>

            {/* Application membership */}
            <div className="space-y-3">
              <div>
                <h3 className="text-sm font-medium">Applications</h3>
                <p className="text-sm text-muted-foreground">
                  Select the applications included in this Grant Set.
                </p>
              </div>

              <div className="grid gap-3 rounded-md border p-4 sm:grid-cols-2">
                {applications.map((application) => (
                  <label
                    key={application.id}
                    className="flex cursor-pointer items-center gap-3 text-sm"
                  >
                    <input
                      type="checkbox"
                      checked={editLegacyAppIds.includes(application.id)}
                      onChange={(event) => {
                        setEditLegacyAppIds((current) =>
                          event.target.checked
                            ? [...current, application.id]
                            : current.filter((id) => id !== application.id),
                        );
                      }}
                      className="h-4 w-4 rounded border-input"
                    />
                    <span>{application.name}</span>
                  </label>
                ))}

                {/* Preserve selected apps missing from the active directory */}
                {editLegacyAppIds
                  .filter(
                    (id) =>
                      !applications.some(
                        (application) => application.id === id,
                      ),
                  )
                  .map((id) => (
                    <label
                      key={id}
                      className="flex cursor-pointer items-center gap-3 text-sm"
                    >
                      <input
                        type="checkbox"
                        checked
                        onChange={() =>
                          setEditLegacyAppIds((current) =>
                            current.filter((currentId) => currentId !== id),
                          )
                        }
                        className="h-4 w-4 rounded border-input"
                      />
                      <span>
                        Inactive / unavailable app
                        <span className="ml-2 text-xs text-muted-foreground">
                          ({id})
                        </span>
                      </span>
                    </label>
                  ))}

                {applications.length === 0 && editLegacyAppIds.length === 0 && (
                  <p className="text-sm text-muted-foreground">
                    No applications available.
                  </p>
                )}
              </div>

              <p className="text-xs text-muted-foreground">
                {editLegacyAppIds.length} application(s) selected.
              </p>
            </div>

            {/* Form actions */}
            <div className="flex justify-end gap-2">
              <Button
                type="button"
                variant="outline"
                onClick={() => {
                  setEditingGrantSet(null);
                  setEditGrantSetError(null);
                }}
              >
                Cancel
              </Button>

              <Button
                type="submit"
                disabled={!editGrantSetName.trim() || updatingGrantSet}
              >
                {updatingGrantSet ? "Saving..." : "Save Changes"}
              </Button>
            </div>
          </form>
        </section>
      )}

      {/* Grant Set list */}
      <section className="overflow-hidden rounded-2xl border bg-card">
        {/* Filters */}
        <div className="flex flex-col gap-3 border-b p-4 sm:flex-row sm:items-center sm:justify-between">
          <div className="relative w-full sm:max-w-sm">
            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />

            <input
              type="search"
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder="Search Grant Sets or applications..."
              className="h-10 w-full rounded-lg border bg-background pl-9 pr-3 text-sm outline-none transition-colors placeholder:text-muted-foreground focus:border-ring focus:ring-2 focus:ring-ring/20"
            />
          </div>

          <label className="flex items-center gap-2 text-sm text-muted-foreground">
            <input
              type="checkbox"
              checked={includeArchived}
              onChange={(event) => setIncludeArchived(event.target.checked)}
              className="h-4 w-4 rounded border-input accent-primary"
            />
            Include archived
          </label>
        </div>

        {/* Table */}
        <div className="overflow-x-auto">
          <table className="w-full min-w-[720px] text-left text-sm">
            <thead className="border-b bg-muted/40 text-xs uppercase tracking-wide text-muted-foreground">
              <tr>
                <th className="px-5 py-3 font-medium">Grant Set</th>
                <th className="px-5 py-3 font-medium">Applications</th>
                <th className="px-5 py-3 font-medium">Last Updated</th>
                <th className="px-5 py-3 font-medium">Status</th>
                <th className="px-5 py-3 text-right font-medium">Actions</th>
              </tr>
            </thead>

            <tbody className="divide-y">
              {loading ? (
                <tr>
                  <td
                    colSpan={5}
                    className="px-5 py-16 text-center text-muted-foreground"
                  >
                    Loading Grant Sets...
                  </td>
                </tr>
              ) : error ? (
                <tr>
                  <td colSpan={5} className="px-5 py-16 text-center">
                    <ShieldAlert className="mx-auto mb-3 h-8 w-8 text-destructive" />

                    <p className="font-medium">Unable to load Grant Sets</p>

                    <p className="mt-1 text-sm text-muted-foreground">
                      {error}
                    </p>

                    <Button
                      variant="outline"
                      size="sm"
                      className="mt-4"
                      onClick={() => setReloadKey((value) => value + 1)}
                    >
                      Try again
                    </Button>
                  </td>
                </tr>
              ) : filteredGrantSets.length === 0 ? (
                <tr>
                  <td colSpan={5} className="px-5 py-16 text-center">
                    <Archive className="mx-auto mb-3 h-9 w-9 text-muted-foreground" />

                    <p className="font-medium">
                      {search
                        ? "No matching Grant Sets"
                        : "No Grant Sets found"}
                    </p>

                    <p className="mt-1 text-sm text-muted-foreground">
                      {search
                        ? "Try another search term."
                        : "Create a Grant Set to define a reusable collection of applications."}
                    </p>
                  </td>
                </tr>
              ) : (
                filteredGrantSets.map((grantSet) => {
                  const archived = grantSet.deleted_at !== null;

                  return (
                    <tr
                      key={grantSet.id}
                      className="transition-colors hover:bg-muted/30"
                    >
                      <td className="px-5 py-4">
                        <div className="font-medium">{grantSet.name}</div>

                        <p className="mt-1 max-w-md text-sm text-muted-foreground">
                          {grantSet.description || "No description"}
                        </p>
                      </td>

                      <td className="px-5 py-4">
                        <div className="flex flex-wrap gap-1.5">
                          {grantSet.items.length === 0 ? (
                            <span className="text-muted-foreground">
                              No applications
                            </span>
                          ) : (
                            grantSet.items.map((item) => {
                              const appName = applicationNames.get(
                                item.legacy_app_id,
                              );

                              return (
                                <span
                                  key={item.id}
                                  className="inline-flex items-center gap-1 rounded-md border bg-muted/40 px-2 py-1 text-xs"
                                  title={item.legacy_app_id}
                                >
                                  <AppWindow className="h-3 w-3 text-muted-foreground" />
                                  {appName ?? "Inactive / unavailable app"}
                                </span>
                              );
                            })
                          )}
                        </div>
                      </td>

                      <td className="px-5 py-4 text-muted-foreground">
                        {grantSet.updated_at
                          ? new Intl.DateTimeFormat("en-PH", {
                              year: "numeric",
                              month: "short",
                              day: "numeric",
                            }).format(new Date(grantSet.updated_at))
                          : "—"}
                      </td>

                      <td className="px-5 py-4">
                        <span
                          className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${
                            archived
                              ? "bg-muted text-muted-foreground"
                              : "bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300"
                          }`}
                        >
                          {archived ? "Archived" : "Active"}
                        </span>
                      </td>

                      {/* Actions */}
                      <td className="px-5 py-4 text-right">
                        {!archived && (
                          <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="gap-2"
                            onClick={() => {
                              setEditingGrantSet(grantSet);
                              setEditGrantSetName(grantSet.name);
                              setEditGrantSetDescription(
                                grantSet.description ?? "",
                              );
                              setEditLegacyAppIds(
                                grantSet.items.map(
                                  (item) => item.legacy_app_id,
                                ),
                              );
                              setEditGrantSetError(null);
                            }}
                          >
                            <Pencil className="h-4 w-4" />
                            Edit
                          </Button>
                        )}
                      </td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>

        <div className="border-t px-4 py-3">
          <p className="text-xs text-muted-foreground">
            {loading
              ? "Loading results..."
              : `Showing ${filteredGrantSets.length} of ${grantSets.length} Grant Sets`}
          </p>
        </div>
      </section>
    </div>
  );
}
