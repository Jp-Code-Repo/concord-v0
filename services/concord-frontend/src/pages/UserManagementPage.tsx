import { useEffect, useState } from "react";
import { useNavigate } from "@tanstack/react-router";
import {
  ChevronLeft,
  ChevronRight,
  RefreshCw,
  Search,
  ShieldAlert,
  Users,
} from "lucide-react";

import { Button } from "@/components/ui/button";
import { CreateUserDialog } from "@/components/iam/CreateUserDialog";
import { useAuth } from "@/contexts/AuthContext";
import {
  IamApiError,
  listIamUsers,
  type IamAccountStatus,
  type IamPagination,
  type IamUserListItem,
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

function formatPersonTypes(types: string[]) {
  if (!types.length) return "Not assigned";

  return types
    .map((type) =>
      type
        .split("_")
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(" "),
    )
    .join(", ");
}

function formatDate(value: string) {
  return new Intl.DateTimeFormat("en-PH", {
    year: "numeric",
    month: "short",
    day: "numeric",
  }).format(new Date(value));
}

export function UserManagementPage() {
  const navigate = useNavigate();
  const { user } = useAuth();

  const isAdministrator = user?.platform_authority.is_administrator === true;

  const [users, setUsers] = useState<IamPagination<IamUserListItem> | null>(
    null,
  );

  const [search, setSearch] = useState("");
  const [status, setStatus] = useState<StatusFilter>("all");
  const [page, setPage] = useState(1);

  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [reloadKey, setReloadKey] = useState(0);
  const [createDialogOpen, setCreateDialogOpen] = useState(false);

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
        void listIamUsers({
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
              setError("You do not have permission to access User Management.");
              return;
            }

            setError(
              requestError instanceof Error
                ? requestError.message
                : "Unable to load user accounts.",
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
          User Management is available only to the designated Concord
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
          <span className="text-foreground">User Management</span>
        </div>

        <div className="mt-3 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
          <div>
            <h1 className="text-3xl font-bold tracking-tight">
              User Management
            </h1>

            <p className="mt-1 text-sm text-muted-foreground md:text-base">
              Manage user accounts and institutional identity information.
            </p>
          </div>

          <div className="flex items-center gap-2 self-start sm:self-auto">
            <Button className="gap-2" onClick={() => setCreateDialogOpen(true)}>
              <Users className="h-4 w-4" />
              Create User
            </Button>

            <Button
              variant="outline"
              className="gap-2"
              onClick={() => setReloadKey((value) => value + 1)}
              disabled={loading}
            >
              <RefreshCw
                className={`h-4 w-4 ${loading ? "animate-spin" : ""}`}
              />
              Refresh
            </Button>
          </div>
        </div>
      </div>

      {/* User list */}
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
                <th className="px-5 py-3 font-medium">Person Types</th>
                <th className="px-5 py-3 font-medium">Student Number</th>
                <th className="px-5 py-3 font-medium">Account Status</th>
                <th className="px-5 py-3 font-medium">Created</th>
              </tr>
            </thead>

            <tbody className="divide-y">
              {loading ? (
                <tr>
                  <td
                    colSpan={6}
                    className="px-5 py-16 text-center text-muted-foreground"
                  >
                    Loading user accounts...
                  </td>
                </tr>
              ) : error ? (
                <tr>
                  <td colSpan={6} className="px-5 py-16 text-center">
                    <ShieldAlert className="mx-auto mb-3 h-8 w-8 text-destructive" />

                    <p className="font-medium">Unable to load users</p>

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
                  <td colSpan={6} className="px-5 py-16 text-center">
                    <Users className="mx-auto mb-3 h-9 w-9 text-muted-foreground" />

                    <p className="font-medium">No users found</p>

                    <p className="mt-1 text-sm text-muted-foreground">
                      Try changing your search or status filter.
                    </p>
                  </td>
                </tr>
              ) : (
                pagination.data.map((item) => (
                <tr
                  key={item.id}
                  tabIndex={0}
                  aria-label={`View ${item.person?.display_name || item.username}`}
                  className="cursor-pointer transition-colors hover:bg-muted/30 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                  onClick={() =>
                    navigate({
                      to: "/admin/users/$userId",
                      params: { userId: item.id },
                    })
                  }
                  onKeyDown={(event) => {
                    if (event.key === "Enter" || event.key === " ") {
                      event.preventDefault();
                      navigate({
                        to: "/admin/users/$userId",
                        params: { userId: item.id },
                      });
                    }
                  }}
                >
                  
                    <td className="px-5 py-4">
                      <div className="font-medium">
                        {item.person?.display_name || "Person unavailable"}
                      </div>
                    </td>

                    <td className="px-5 py-4 font-medium">@{item.username}</td>

                    <td className="px-5 py-4 text-muted-foreground">
                      {formatPersonTypes(item.person?.types ?? [])}
                    </td>

                    <td className="px-5 py-4 text-muted-foreground">
                      {item.person?.student_number || "—"}
                    </td>

                    <td className="px-5 py-4">
                      <span
                        className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${statusStyles[item.status]}`}
                      >
                        {formatStatus(item.status)}
                      </span>
                    </td>

                    <td className="px-5 py-4 text-muted-foreground">
                      {formatDate(item.created_at)}
                    </td>
                  </tr>
                ))
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
              onClick={() => setPage((current) => Math.max(1, current - 1))}
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
              onClick={() => setPage((current) => current + 1)}
            >
              <ChevronRight />
            </Button>
          </div>
        </div>
      </section>

      <CreateUserDialog
        open={createDialogOpen}
        onClose={() => setCreateDialogOpen(false)}
        onCreated={() => setReloadKey((value) => value + 1)}
      />
    </div>
  );
}
