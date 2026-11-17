import { useEffect, useState } from "react";
import { useNavigate, useParams } from "@tanstack/react-router";
import {
  ArrowLeft,
  Ban,
  CalendarDays,
  KeyRound,
  Mail,
  RefreshCw,
  RotateCcw,
  ShieldAlert,
  UserCheck,
  UserRound,
  UserX,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { toast } from "sonner";
import { EditUserProfileDialog } from "@/components/iam/EditUserProfileDialog";
import { ResetUserPasswordDialog } from "@/components/iam/ResetUserPasswordDialog";
import { useAuth } from "@/contexts/AuthContext";
import {
  deactivateIamUser,
  disableIamUser,
  getIamUser,
  IamApiError,
  reinstateIamUser,
  reactivateIamUser,
  type IamAccountStatus,
  type IamUserDetail,
} from "@/lib/iam";

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

function formatDate(value: string | null) {
  if (!value) return "Not available";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "Not available";
  return new Intl.DateTimeFormat("en-PH", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(date);
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

type LifecycleAction = "disable" | "deactivate" | "reactivate" | "reinstate";

const lifecycleActionDetails: Record<
  LifecycleAction,
  { title: string; description: string; confirmLabel: string }
> = {
  disable: {
    title: "Disable this account?",
    description:
      "The user will lose access to Concord immediately. All active sessions will be revoked. You can reactivate this account later.",
    confirmLabel: "Disable Account",
  },
  deactivate: {
    title: "Deactivate this account?",
    description:
      "The account will be deactivated and all active sessions will be revoked. This is a separate lifecycle action from disabling an account.",
    confirmLabel: "Deactivate Account",
  },
  reactivate: {
    title: "Reactivate this account?",
    description:
      "The account status will return to Active. Previously revoked sessions will not be restored. The user must sign in again.",
    confirmLabel: "Reactivate Account",
  },
  reinstate: {
    title: "Reinstate this account?",
    description:
      "The deactivated account will return to Active. Previously revoked sessions will not be restored. The user must sign in again.",
    confirmLabel: "Reinstate Account",
  },
};

function InformationItem({ label, value }: { label: string; value: string }) {
  return (
    <div className="space-y-1">
      <dt className="text-sm text-muted-foreground">{label}</dt>
      <dd className="break-words text-sm font-medium">
        {value || "Not provided"}
      </dd>
    </div>
  );
}

export function UserDetailPage() {
  const { userId } = useParams({ strict: false }) as {
    userId?: string;
  };
  const navigate = useNavigate();
  const { user } = useAuth();

  const isAdministrator = user?.platform_authority.is_administrator === true;

  const [detail, setDetail] = useState<IamUserDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [reloadKey, setReloadKey] = useState(0);
  const [editDialogOpen, setEditDialogOpen] = useState(false);
  const [resetPasswordDialogOpen, setResetPasswordDialogOpen] = useState(false);
  const [pendingLifecycleAction, setPendingLifecycleAction] =
    useState<LifecycleAction | null>(null);
  const [lifecycleSubmitting, setLifecycleSubmitting] = useState(false);
  const [lifecycleError, setLifecycleError] = useState<string | null>(null);

  useEffect(() => {
    if (!isAdministrator || !userId) {
      setLoading(false);
      return;
    }

    let active = true;

    setLoading(true);
    setError(null);

    getIamUser(userId)
      .then((result) => {
        if (active) setDetail(result);
      })
      .catch((requestError: unknown) => {
        if (!active) return;

        if (requestError instanceof IamApiError) {
          if (requestError.status === 403) {
            setError(
              "You do not have permission to view this user's information.",
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
            : "Unable to load user information.",
        );
      })
      .finally(() => {
        if (active) setLoading(false);
      });

    return () => {
      active = false;
    };
  }, [isAdministrator, userId, reloadKey]);

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

  const person = detail?.person;
  const account = detail?.user;

  // Never infer designated Administrator authority from is_it_admin.
  const isDesignatedAdministrator =
    account?.id === user?.id &&
    user?.platform_authority.is_administrator === true;

  async function handleLifecycleAction() {
    if (!userId || !pendingLifecycleAction || lifecycleSubmitting) return;

    setLifecycleSubmitting(true);
    setLifecycleError(null);

    try {
      let response: Awaited<ReturnType<typeof disableIamUser>>;

      switch (pendingLifecycleAction) {
        case "disable":
          response = await disableIamUser(userId);
          break;
        case "deactivate":
          response = await deactivateIamUser(userId);
          break;
        case "reactivate":
          response = await reactivateIamUser(userId);
          break;
        case "reinstate":
          response = await reinstateIamUser(userId);
          break;
      }

      toast.success(response.message);
      setPendingLifecycleAction(null);
      setReloadKey((value) => value + 1);
    } catch (requestError) {
      if (requestError instanceof IamApiError) {
        if (requestError.status === 403) {
          setLifecycleError(
            "You do not have permission to change this account's status.",
          );
        } else if (requestError.status === 409) {
          setLifecycleError(
            "This account can no longer undergo that action. Its status may have changed. Refresh the account and try again.",
          );
        } else {
          setLifecycleError(requestError.message);
        }
      } else {
        setLifecycleError(
          requestError instanceof Error
            ? requestError.message
            : "Unable to update this account. Please try again.",
        );
      }
    } finally {
      setLifecycleSubmitting(false);
    }
  }

  return (
    <div className="mx-auto w-full max-w-[1600px] space-y-6">
      <div>
        <button
          type="button"
          onClick={() => navigate({ to: "/admin/users" })}
          className="mb-4 inline-flex items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-foreground"
        >
          <ArrowLeft className="h-4 w-4" />
          Back to User Management
        </button>

        <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
          <div>
            <div className="flex items-center gap-2 text-sm text-muted-foreground">
              <span>Administrator</span>
              <span>/</span>
              <button
                type="button"
                onClick={() => navigate({ to: "/admin/users" })}
                className="hover:text-foreground"
              >
                User Management
              </button>
              <span>/</span>
              <span className="text-foreground">User Details</span>
            </div>

            <h1 className="mt-3 text-3xl font-bold tracking-tight">
              User Details
            </h1>
            <p className="mt-1 text-sm text-muted-foreground md:text-base">
              View account information and institutional identity.
            </p>
          </div>

          <div className="flex items-center gap-2 self-start sm:self-auto">
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

            <Button
              className="gap-2"
              onClick={() => setEditDialogOpen(true)}
              disabled={loading || !detail}
            >
              Edit Profile
            </Button>

            <Button
              type="button"
              variant="outline"
              className="gap-2"
              onClick={() => setResetPasswordDialogOpen(true)}
              disabled={loading || !detail}
            >
              <KeyRound className="h-4 w-4" />
              Reset Password
            </Button>
          </div>
        </div>
      </div>

      {loading ? (
        <section className="rounded-2xl border bg-card px-5 py-16 text-center text-sm text-muted-foreground">
          Loading user information...
        </section>
      ) : error ? (
        <section className="rounded-2xl border bg-card px-5 py-16 text-center">
          <ShieldAlert className="mx-auto mb-3 h-9 w-9 text-destructive" />
          <h2 className="font-semibold">Unable to load user</h2>
          <p className="mt-2 text-sm text-muted-foreground">{error}</p>

          <div className="mt-5 flex justify-center gap-2">
            <Button
              variant="outline"
              onClick={() => navigate({ to: "/admin/users" })}
            >
              Back to Users
            </Button>
            <Button onClick={() => setReloadKey((value) => value + 1)}>
              Try Again
            </Button>
          </div>
        </section>
      ) : detail && account ? (
        <>
          <section className="relative isolate overflow-hidden rounded-2xl border bg-card">
            <div className="absolute inset-0 -z-10 bg-gradient-to-r from-blue-50 via-background to-sky-100 dark:from-blue-950/40 dark:via-background dark:to-slate-900" />

            <div className="flex flex-col gap-5 p-6 sm:flex-row sm:items-center sm:justify-between md:p-8">
              <div className="flex items-center gap-5">
                <div className="flex h-20 w-20 shrink-0 items-center justify-center rounded-full border-4 border-background bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300">
                  <UserRound className="h-10 w-10" />
                </div>

                <div className="min-w-0">
                  <h2 className="text-2xl font-bold tracking-tight">
                    {person?.display_name || account.username}
                  </h2>
                  <p className="mt-1 font-medium">@{account.username}</p>
                  <p className="mt-1 text-sm text-muted-foreground">
                    {formatPersonTypes(person?.types ?? [])}
                  </p>
                </div>
              </div>

              <div className="flex flex-col items-start gap-3 sm:items-end">
                <span
                  className={`inline-flex rounded-full px-3 py-1 text-xs font-medium ${statusStyles[account.status]}`}
                >
                  {formatStatus(account.status)}
                </span>
                <p className="text-xs text-muted-foreground">
                  Account ID: {account.id}
                </p>
              </div>
            </div>
          </section>

          <div className="grid gap-6 xl:grid-cols-2">
            <section className="overflow-hidden rounded-2xl border bg-card">
              <div className="flex items-center gap-3 border-b px-5 py-4">
                <UserRound className="h-5 w-5 text-blue-600" />
                <h2 className="font-semibold">Personal Information</h2>
              </div>

              <dl className="grid gap-5 p-5 sm:grid-cols-2">
                <InformationItem
                  label="First Name"
                  value={person?.first_name ?? ""}
                />
                <InformationItem
                  label="Middle Name"
                  value={person?.middle_name ?? ""}
                />
                <InformationItem
                  label="Last Name"
                  value={person?.last_name ?? ""}
                />
                <InformationItem
                  label="Name Suffix"
                  value={person?.name_suffix ?? ""}
                />
                <InformationItem label="Gender" value={person?.gender ?? ""} />
                <InformationItem
                  label="Email Address"
                  value={person?.email ?? ""}
                />
                <InformationItem
                  label="Student Number"
                  value={person?.student_number ?? ""}
                />
                <InformationItem
                  label="Person Types"
                  value={formatPersonTypes(person?.types ?? [])}
                />
              </dl>

              {!person && (
                <p className="border-t px-5 py-3 text-sm text-muted-foreground">
                  No linked personal information is available for this account.
                </p>
              )}
            </section>

            <section className="overflow-hidden rounded-2xl border bg-card">
              <div className="flex items-center gap-3 border-b px-5 py-4">
                <Mail className="h-5 w-5 text-blue-600" />
                <h2 className="font-semibold">Account Information</h2>
              </div>

              <dl className="grid gap-5 p-5 sm:grid-cols-2">
                <InformationItem label="Username" value={account.username} />
                <InformationItem
                  label="Account Status"
                  value={formatStatus(account.status)}
                />
                <InformationItem
                  label="Created At"
                  value={formatDate(account.created_at)}
                />
                <InformationItem
                  label="Disabled At"
                  value={formatDate(account.disabled_at)}
                />
                <InformationItem
                  label="Deactivated At"
                  value={formatDate(account.deactivated_at)}
                />
                <InformationItem label="Person ID" value={account.person_id} />
              </dl>
            </section>
          </div>

          <section className="space-y-4 rounded-2xl border bg-card p-5">
            <div>
              <h2 className="font-semibold">Account Lifecycle</h2>
              <p className="mt-1 text-sm text-muted-foreground">
                Manage whether this account can access Concord. Each action has
                a distinct lifecycle meaning.
              </p>
            </div>

            {isDesignatedAdministrator ? (
              <p className="rounded-lg border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-sm text-amber-800 dark:text-amber-300">
                The designated Concord Administrator account cannot be disabled
                or deactivated.
              </p>
            ) : account.status === "active" ? (
              <div className="flex flex-wrap gap-2">
                <Button
                  type="button"
                  variant="outline"
                  className="gap-2"
                  onClick={() => {
                    setLifecycleError(null);
                    setPendingLifecycleAction("disable");
                  }}
                >
                  <Ban className="h-4 w-4" />
                  Disable Account
                </Button>
                <Button
                  type="button"
                  variant="destructive"
                  className="gap-2"
                  onClick={() => {
                    setLifecycleError(null);
                    setPendingLifecycleAction("deactivate");
                  }}
                >
                  <UserX className="h-4 w-4" />
                  Deactivate Account
                </Button>
              </div>
            ) : account.status === "disabled" ? (
              <Button
                type="button"
                className="gap-2"
                onClick={() => {
                  setLifecycleError(null);
                  setPendingLifecycleAction("reactivate");
                }}
              >
                <UserCheck className="h-4 w-4" />
                Reactivate Account
              </Button>
            ) : (
              <Button
                type="button"
                className="gap-2"
                onClick={() => {
                  setLifecycleError(null);
                  setPendingLifecycleAction("reinstate");
                }}
              >
                <RotateCcw className="h-4 w-4" />
                Reinstate Account
              </Button>
            )}

            {lifecycleError && (
              <p
                role="alert"
                className="rounded-lg border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive"
              >
                {lifecycleError}
              </p>
            )}
          </section>

          <section className="flex items-center gap-3 rounded-xl border bg-card p-4">
            <CalendarDays className="h-5 w-5 text-muted-foreground" />
            <p className="text-sm text-muted-foreground">
              Account information is retrieved directly from the IAM service.
            </p>
          </section>
        </>
      ) : (
        <section className="rounded-2xl border bg-card px-5 py-16 text-center text-sm text-muted-foreground">
          No user information is available.
        </section>
      )}

      {pendingLifecycleAction && detail && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
          role="presentation"
          onMouseDown={(event) => {
            if (event.target === event.currentTarget && !lifecycleSubmitting) {
              setPendingLifecycleAction(null);
            }
          }}
        >
          <section
            role="dialog"
            aria-modal="true"
            aria-labelledby="lifecycle-dialog-title"
            className="w-full max-w-md rounded-xl border bg-card p-6 shadow-xl"
          >
            <div className="flex items-start gap-3">
              <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-500/10 text-amber-600">
                <ShieldAlert className="h-5 w-5" />
              </div>
              <div>
                <h2
                  id="lifecycle-dialog-title"
                  className="text-lg font-semibold"
                >
                  {lifecycleActionDetails[pendingLifecycleAction].title}
                </h2>
                <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
                  {lifecycleActionDetails[pendingLifecycleAction].description}
                </p>
              </div>
            </div>

            <div className="mt-4 rounded-lg bg-muted px-3 py-2 text-sm">
              <span className="text-muted-foreground">Account: </span>
              <span className="font-medium">
                {detail.person?.display_name || detail.user.username}
              </span>
              <span className="text-muted-foreground">
                {" "}
                (@{detail.user.username})
              </span>
            </div>

            {lifecycleError && (
              <p
                role="alert"
                className="mt-4 rounded-lg border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive"
              >
                {lifecycleError}
              </p>
            )}

            <div className="mt-6 flex justify-end gap-2">
              <Button
                type="button"
                variant="outline"
                disabled={lifecycleSubmitting}
                onClick={() => setPendingLifecycleAction(null)}
              >
                Cancel
              </Button>
              <Button
                type="button"
                variant={
                  pendingLifecycleAction === "deactivate"
                    ? "destructive"
                    : "default"
                }
                disabled={lifecycleSubmitting}
                onClick={() => void handleLifecycleAction()}
              >
                {lifecycleSubmitting
                  ? "Processing..."
                  : lifecycleActionDetails[pendingLifecycleAction].confirmLabel}
              </Button>
            </div>
          </section>
        </div>
      )}

      {editDialogOpen && detail && (
        <EditUserProfileDialog
          detail={detail}
          onClose={() => setEditDialogOpen(false)}
          onSaved={() => setReloadKey((value) => value + 1)}
        />
      )}

      {resetPasswordDialogOpen && detail && (
        <ResetUserPasswordDialog
          userId={detail.user.id}
          onClose={() => setResetPasswordDialogOpen(false)}
          onSuccess={() => setReloadKey((value) => value + 1)}
        />
      )}
    </div>
  );
}
