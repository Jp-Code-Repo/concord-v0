import { useEffect, useState } from "react";
import { Link } from "@tanstack/react-router";
import {
  ChevronDown,
  ChevronLeft,
  ChevronRight,
  RefreshCw,
  Search,
  ShieldAlert,
  AppWindow,
} from "lucide-react";

import { Button } from "@/components/ui/button";
import { useAuth } from "@/contexts/AuthContext";
import {
  IamApiError,
  listIamLegacyAppsAccess,
  type IamAccountStatus,
  type IamAccessUserListItem,
  type IamPagination,
} from "@/lib/iam";

type StatusFilter = "all" | IamAccountStatus;

const PAGE_SIZE = 25;

const statusStyles: Record<IamAccountStatus, string> = {
  active:
    "bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300",
  disabled:
    "bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300",
  deactivated: "bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300",
};

function formatStatus(status: IamAccountStatus) {
  return status.charAt(0).toUpperCase() + status.slice(1);
}

export function LegacyAppsAccessPage() {
  const { user } = useAuth();

  const isAdministrator = user?.platform_authority.is_administrator === true;

  const [users, setUsers] =
    useState<IamPagination<IamAccessUserListItem> | null>(null);

  const [search, setSearch] = useState("");
  const [status, setStatus] = useState<StatusFilter>("all");
  const [page, setPage] = useState(1);

  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [reloadKey, setReloadKey] = useState(0);
  const [expandedUserId, setExpandedUserId] = useState<string | null>(null);

  useEffect(() => {
    if (!isAdministrator) {
      setLoading(false);
      return;
    }

    let active = true;

    setLoading(true);
    setError(null);

    const timeout = window.setTimeout(
      () => {
        void listIamLegacyAppsAccess({
          search: search.trim() || undefined,
          status: status === "all" ? undefined : status,
          per_page: PAGE_SIZE,
          page,
        })
          .then((result) => {
            if (active) setUsers(result);
          })
          .catch((requestError: unknown) => {
            if (!active) return;

            if (
              requestError instanceof IamApiError &&
              requestError.status === 403
            ) {
              setError(
                "You do not have permission to access Legacy Apps Access.",
              );
              return;
            }

            setError(
              requestError instanceof Error
                ? requestError.message
                : "Unable to load application access.",
            );
          })
          .finally(() => {
            if (active) setLoading(false);
          });
      },
      search ? 300 : 0,
    );

    return () => {
      active = false;
      window.clearTimeout(timeout);
    };
  }, [isAdministrator, search, status, page, reloadKey]);

  if (!isAdministrator) {
    return (
      <div className="mx-auto flex min-h-[50vh] max-w-xl flex-col items-center justify-center text-center">
        <ShieldAlert className="mb-4 h-12 w-12 text-muted-foreground" />

        <h1 className="text-xl font-semibold">Access Restricted</h1>

        <p className="mt-2 text-sm text-muted-foreground">
          Legacy Apps Access is available only to the designated Concord
          Administrator.
        </p>
      </div>
    );
  }

  const pagination = users;

  return (
    <div className="mx-auto w-full max-w-[1600px] space-y-6">
      {/* Page heading */}
      <div>
        <div className="flex items-center gap-2 text-sm text-muted-foreground">
          <span>Administrator</span>
          <span>/</span>
          <span className="text-foreground">Legacy Apps Access</span>
        </div>

        <div className="mt-3 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
          <div>
            <h1 className="text-3xl font-bold tracking-tight">
              Legacy Apps Access
            </h1>

            <p className="mt-1 text-sm text-muted-foreground md:text-base">
              Review the applications assigned to Concord user accounts.
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

      {/* Access list */}
      <section className="overflow-hidden rounded-2xl border bg-card">
        {/* Filters */}
        <div className="flex flex-col gap-3 border-b p-4 sm:flex-row sm:items-center sm:justify-between">
          <div className="relative w-full sm:max-w-sm">
            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />

            <input
              type="search"
              value={search}
              onChange={(event) => {
                setSearch(event.target.value);
                setPage(1);
                setExpandedUserId(null);
              }}
              placeholder="Search name or username..."
              className="h-10 w-full rounded-lg border bg-background pl-9 pr-3 text-sm outline-none transition-colors placeholder:text-muted-foreground focus:border-ring focus:ring-2 focus:ring-ring/20"
            />
          </div>

          <select
            value={status}
            onChange={(event) => {
              setStatus(event.target.value as StatusFilter);
              setPage(1);
              setExpandedUserId(null);
            }}
            className="h-10 rounded-lg border bg-background px-3 text-sm outline-none focus:border-ring focus:ring-2 focus:ring-ring/20 sm:w-48"
            aria-label="Filter by account status"
          >
            <option value="all">All statuses</option>
            <option value="active">Active</option>
            <option value="disabled">Disabled</option>
            <option value="deactivated">Deactivated</option>
          </select>
        </div>

        {/* Table */}
        <div className="overflow-x-auto">
          <table className="w-full min-w-[850px] text-left text-sm">
            <thead className="border-b bg-muted/40 text-xs uppercase tracking-wide text-muted-foreground">
              <tr>
                <th className="px-5 py-3 font-medium">Person</th>
                <th className="px-5 py-3 font-medium">Username</th>
                <th className="px-5 py-3 font-medium">Account Status</th>
                <th className="px-5 py-3 font-medium">
                  Effective Applications
                </th>
                <th className="px-5 py-3 text-right font-medium">Action</th>
              </tr>
            </thead>

            <tbody className="divide-y">
              {loading ? (
                <tr>
                  <td
                    colSpan={5}
                    className="px-5 py-16 text-center text-muted-foreground"
                  >
                    Loading application access...
                  </td>
                </tr>
              ) : error ? (
                <tr>
                  <td colSpan={5} className="px-5 py-16 text-center">
                    <ShieldAlert className="mx-auto mb-3 h-8 w-8 text-destructive" />

                    <p className="font-medium">
                      Unable to load application access
                    </p>

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
              ) : !pagination?.data.length ? (
                <tr>
                  <td colSpan={5} className="px-5 py-16 text-center">
                    <AppWindow className="mx-auto mb-3 h-9 w-9 text-muted-foreground" />

                    <p className="font-medium">No users found</p>

                    <p className="mt-1 text-sm text-muted-foreground">
                      Try changing your search or status filter.
                    </p>
                  </td>
                </tr>
              ) : (
                pagination.data.map((item) => {
                  const expanded = expandedUserId === item.id;

                  return (
                    <>
                      <tr
                        key={item.id}
                        className="transition-colors hover:bg-muted/30"
                      >
                        <td className="px-5 py-4">
                          <div className="font-medium">
                            {item.person?.display_name || "Person unavailable"}
                          </div>
                        </td>

                        <td className="px-5 py-4 font-medium">
                          @{item.username}
                        </td>

                        <td className="px-5 py-4">
                          <span
                            className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${statusStyles[item.status]}`}
                          >
                            {formatStatus(item.status)}
                          </span>
                        </td>

                        <td className="px-5 py-4">
                          <span className="font-semibold tabular-nums">
                            {item.effective_application_count}
                          </span>
                          <span className="ml-1 text-muted-foreground">
                            {item.effective_application_count === 1
                              ? "application"
                              : "applications"}
                          </span>
                        </td>

                        <td className="px-5 py-4 text-right">
                          <Button
                            variant="outline"
                            size="sm"
                            className="gap-2"
                            aria-expanded={expanded}
                            onClick={() =>
                              setExpandedUserId(expanded ? null : item.id)
                            }
                          >
                            {expanded ? "Hide" : "View access"}
                            <ChevronDown
                              className={`h-4 w-4 transition-transform ${
                                expanded ? "rotate-180" : ""
                              }`}
                            />
                          </Button>
                          <Link
                            to="/admin/legacy-apps/access/$userId"
                            params={{ userId: item.id }}
                            className="ml-3 text-sm font-medium text-primary hover:underline"
                          >
                            Manage access
                          </Link>
                        </td>
                      </tr>

                      {expanded && (
                        <tr key={`${item.id}-access`} className="bg-muted/20">
                          <td colSpan={5} className="px-5 py-5">
                            <div className="grid gap-5 md:grid-cols-2">
                              {/* Effective applications */}
                              <div>
                                <h3 className="mb-3 text-sm font-semibold">
                                  Effective Applications (
                                  {item.effective_application_count})
                                </h3>

                                {item.applications.length ? (
                                  <ul className="space-y-2">
                                    {item.applications.map((app) => (
                                      <li
                                        key={app.id}
                                        className="flex items-center gap-2 text-sm"
                                      >
                                        <AppWindow className="h-4 w-4 text-muted-foreground" />
                                        {app.name}
                                      </li>
                                    ))}
                                  </ul>
                                ) : (
                                  <p className="text-sm text-muted-foreground">
                                    No active application access assigned.
                                  </p>
                                )}
                              </div>

                              {/* Inactive assignments */}
                              <div>
                                <h3 className="mb-3 text-sm font-semibold">
                                  Inactive Assignments (
                                  {item.inactive_assignments.length})
                                </h3>

                                {item.inactive_assignments.length ? (
                                  <ul className="space-y-2">
                                    {item.inactive_assignments.map((app) => (
                                      <li
                                        key={app.id}
                                        className="flex items-center gap-2 text-sm text-muted-foreground"
                                      >
                                        <AppWindow className="h-4 w-4 shrink-0" />
                                        <span>{app.name}</span>
                                        <span className="rounded-full bg-muted px-2 py-0.5 text-xs">
                                          Inactive
                                        </span>
                                      </li>
                                    ))}
                                  </ul>
                                ) : (
                                  <p className="text-sm text-muted-foreground">
                                    No inactive assignments.
                                  </p>
                                )}
                              </div>
                            </div>
                          </td>
                        </tr>
                      )}
                    </>
                  );
                })
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
        <div className="flex flex-col gap-3 border-t px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
          <p className="text-xs text-muted-foreground">
            {loading || !pagination
              ? "Loading results..."
              : `Showing ${pagination.from ?? 0}–${pagination.to ?? 0} of ${pagination.total} users`}
          </p>

          <div className="flex items-center gap-2">
            <span className="text-xs text-muted-foreground">
              Page {pagination?.current_page ?? page} of{" "}
              {pagination?.last_page ?? 1}
            </span>

            <Button
              variant="outline"
              size="icon"
              aria-label="Previous page"
              disabled={loading || !pagination || pagination.current_page <= 1}
              onClick={() => {
                setPage((current) => Math.max(1, current - 1));
                setExpandedUserId(null);
              }}
            >
              <ChevronLeft />
            </Button>

            <Button
              variant="outline"
              size="icon"
              aria-label="Next page"
              disabled={
                loading ||
                !pagination ||
                pagination.current_page >= pagination.last_page
              }
              onClick={() => {
                setPage((current) => current + 1);
                setExpandedUserId(null);
              }}
            >
              <ChevronRight />
            </Button>
          </div>
        </div>
      </section>
    </div>
  );
}
