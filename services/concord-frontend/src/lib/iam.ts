const IAM_BASE_URL = "/api/iam";

export interface IamPerson {
  id: string;
  first_name: string;
  middle_name: string | null;
  last_name: string;
  name_suffix: string | null;
  gender: string | null;
  email: string | null;
  display_name: string;
  preferences: Record<string, unknown>;
  types: string[];
  student_number?: string | null;
}

// User Management types

export type IamAccountStatus = "active" | "disabled" | "deactivated";

export type IamPersonType =
  | "employee"
  | "student"
  | "parent_guardian"
  | "visitor";

export interface IamPagination<T> {
  current_page: number;
  data: T[];
  first_page_url: string | null;
  from: number | null;
  last_page: number;
  last_page_url: string | null;
  next_page_url: string | null;
  path: string;
  per_page: number;
  prev_page_url: string | null;
  to: number | null;
  total: number;
}

export interface IamUserListPerson {
  id: string;
  first_name: string;
  middle_name: string | null;
  last_name: string;
  name_suffix: string | null;
  gender: string | null;
  email: string | null;
  display_name: string;
  types: IamPersonType[];
  student_number: string | null;
}

export interface IamUserListItem {
  id: string;
  person_id: string;
  username: string;
  status: IamAccountStatus;
  is_it_admin: boolean;
  created_at: string;
  person: IamUserListPerson | null;
}

//Legacy Apps Access listing types

export interface IamAccessSummaryItem {
  id: string;
  name: string;
}

export interface IamAccessUserPerson {
  id: string;
  first_name: string;
  middle_name: string | null;
  last_name: string;
  name_suffix: string | null;
  display_name: string;
  types: IamPersonType[];
  student_number: string | null;
}

export interface IamAccessUserListItem {
  id: string;
  person_id: string;
  username: string;
  status: IamAccountStatus;
  created_at: string;
  person: IamAccessUserPerson | null;
  effective_application_count: number;
  applications: IamAccessSummaryItem[];
  inactive_assignments: IamAccessSummaryItem[];
}

//Individual user's Legacy Apps access detail

export type IamLegacyAppGrantSource =
  | "individual"
  | "grant_set"
  | "batch";

export interface IamLegacyAppAssignment extends IamLegacyApp {
  granted_at: string;
  grant_source: IamLegacyAppGrantSource;
  is_active: boolean;
}

export interface IamLegacyAppGrantSetHistory {
  grant_set_id: string;
  name: string | null;
  first_applied_at: string;
  last_applied_at: string;
}

export interface IamUserLegacyAppsAccess {
  applications: IamLegacyAppAssignment[];
  inactive_assignments: IamLegacyAppAssignment[];
  grant_sets: IamLegacyAppGrantSetHistory[];
}

export interface IamUserLegacyAppsAccessResponse {
  data: IamUserLegacyAppsAccess;
}

export interface IamLegacyAppGrantRecord {
  id: string;
  user_id: string;
  legacy_app_id: string;
  granted_at: string;
  granted_by: string;
  revoked_at: string | null;
  revoked_by: string | null;
}

export interface IamLegacyAppGrantResponse {
  data: IamLegacyAppGrantRecord;
}

export interface IamLegacyAppsDirectoryResponse {
  data: IamLegacyApp[];
}

// Administrator Legacy App Directory Management

export interface IamManagedLegacyApp {
  id: string;
  name: string;
  url: string;
  icon: string | null;
  description: string | null;
  is_active: boolean;
  sort_order: number;
  created_at: string;
  updated_at: string;
}

export interface IamManagedLegacyAppsResponse {
  data: IamManagedLegacyApp[];
}

export interface IamManagedLegacyAppResponse {
  data: IamManagedLegacyApp;
}

export interface IamCreateLegacyAppPayload {
  name: string;
  url: string;
  icon?: string | null;
  description?: string | null;
  sort_order?: number;
}

export type IamUpdateLegacyAppPayload =
  Partial<IamCreateLegacyAppPayload>;


// Grant Set types

export interface IamGrantSetItem {
  id: string;
  grant_set_id: string;
  legacy_app_id: string;
}

export interface IamGrantSet {
  id: string;
  name: string;
  description: string | null;
  created_at?: string;
  updated_at?: string;
  deleted_at?: string | null;
  items: IamGrantSetItem[];
}

export interface IamGrantSetListResponse {
  data: IamGrantSet[];
}

export interface IamGrantSetResponse {
  data: IamGrantSet;
}

export interface IamCreateGrantSetPayload {
  name: string;
  description?: string | null;
  legacy_app_ids: string[];
}

export interface IamUpdateGrantSetPayload {
  name?: string;
  description?: string | null;
  legacy_app_ids?: string[];
}

export interface IamApplyGrantSetResponse {
  data: {
    id: string;
    grant_set_id: string;
    user_id: string;
    actor_id: string;
    legacy_app_ids: string[];
    occurred_at: string;
  };
}

export interface IamBatchGrantSetPayload {
  user_ids: string[];
  method: "grant_set";
  grant_set_id: string;
}

export interface IamBatchIndividualAppsPayload {
  user_ids: string[];
  method: "individual_apps";
  legacy_app_ids: string[];
}

export type IamBatchAssignmentPayload =
  | IamBatchGrantSetPayload
  | IamBatchIndividualAppsPayload;

export interface IamBatchAssignmentResponse {
  data: {
    batch_id: string;
    method: "grant_set" | "individual_apps";
    users: Array<{
      user_id: string;
      application_event_id?: string;
      granted_app_ids?: string[];
      skipped_app_ids?: string[];
    }>;
  };
}

// User Management detail and mutation types

export interface IamUserDetail {
  user: {
    id: string;
    person_id: string;
    username: string;
    status: IamAccountStatus;
    created_at: string;
    disabled_at: string | null;
    deactivated_at: string | null;
  };
  person: IamPerson | null;
}

export interface IamCreateUserPayload {
  username: string;
  first_name: string;
  middle_name?: string | null;
  last_name: string;
  name_suffix?: string | null;
  gender?: string | null;
  email?: string | null;
  types: IamPersonType[];
  student_number?: string | null;
}

export interface IamCreateUserResponse {
  message: string;
  person: {
    id: string;
    first_name: string;
    middle_name: string | null;
    last_name: string;
    name_suffix: string | null;
    gender: string | null;
    email: string | null;
    preferences: Record<string, unknown>;
    types: IamPersonType[];
    student_number: string | null;
  };
  user: {
    id: string;
    person_id: string;
    username: string;
    status: IamAccountStatus;
  };
  initial_password: string;
}

export interface IamUpdateUserPayload {
  username?: string;
  first_name?: string;
  middle_name?: string | null;
  last_name?: string;
  name_suffix?: string | null;
  gender?: string | null;
  email?: string | null;
  types?: IamPersonType[];
  student_number?: string | null;
}

export interface IamUpdateUserResponse {
  message: string;
  user: {
    id: string;
    person_id: string;
    username: string;
    status: IamAccountStatus;
  };
  person: {
    id: string;
    first_name: string;
    middle_name: string | null;
    last_name: string;
    name_suffix: string | null;
    gender: string | null;
    email: string | null;
    display_name: string;
    types: IamPersonType[];
    student_number: string | null;
  };
}

export interface IamPasswordResetPayload {
  mode: "generate" | "custom";
  password?: string;
}

export interface IamPasswordResetResponse {
  message: string;
  temporary_password: string;
}

export interface IamMessageResponse {
  message: string;
}

export interface IamUser {
  id: string;
  person_id: string;
  username: string;
  is_it_admin: boolean;
  platform_authority: {
    is_administrator: boolean;
  };
  status: "active" | "deactivated" | "disabled";
  must_change_password: boolean;
  settings: Record<string, unknown>;
  person: IamPerson | null;
}

//Legacy application registry item returned by IAM

export interface IamLegacyApp {
  id: string;
  name: string;
  url: string;
  icon: string | null;
  description: string | null;
}

// NEW: Typed IAM access payload

export interface IamAccess {
  all_access: boolean;
  legacy_apps: IamLegacyApp[];
  apps: unknown[];
  offices: unknown[];
  [key: string]: unknown;
}

export interface IamSession {
  user: IamUser;
  access?: IamAccess | null;
}
interface IamErrorResponse {
  message?: string;
  errors?: Record<string, string[]>;
  code?: string;
}

export class IamApiError extends Error {
  public readonly status: number;
  public readonly errors?: Record<string, string[]>;
  public readonly code?: string; 

  constructor(
    message: string,
    status: number,
    errors?: Record<string, string[]>,
    code?: string, 
  ) {
    super(message);
    this.name = "IamApiError";
    this.status = status;
    this.errors = errors;
    this.code = code; 
  }
}

async function iamRequest<T>(
  endpoint: string,
  options: RequestInit = {},
): Promise<T> {
  const response = await fetch(`${IAM_BASE_URL}${endpoint}`, {
    ...options,
    credentials: "include",
    headers: {
      Accept: "application/json",
      ...options.headers,
    },
  });
  if (!response.ok) {
    let errorData: IamErrorResponse = {};
    try {
      errorData = (await response.json()) as IamErrorResponse;
    } catch {
      // The response may not contain JSON.
    }
    const message =
      errorData.message ??
      (response.status === 401
        ? "Your session is invalid or has expired."
        : `IAM request failed (${response.status}).`);

    //Notify the application when an authenticated session was revoked.
      if (
        endpoint !== "/login" &&
        (errorData.code === "ACCOUNT_DISABLED" ||
          errorData.code === "ACCOUNT_DEACTIVATED") &&
        typeof window !== "undefined"
      ) {
        window.dispatchEvent(
          new CustomEvent("iam:account-revoked", {
            detail: { code: errorData.code },
          }),
        );
      }

    throw new IamApiError(
      message,
      response.status,
      errorData.errors,
      errorData.code,
    );
  }
  if (response.status === 204) {
    return undefined as T;
  }
  return (await response.json()) as T;
}

// The login response carries the same payload as GET /me (user + access), so the

// caller does not need a second request right after signing in.

export async function login(
  username: string,
  password: string,
): Promise<IamSession> {
  return iamRequest<IamSession>("/login", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ username, password }),
  });
}

export async function getCurrentUser(): Promise<IamSession> {
  return iamRequest<IamSession>("/me");
}

// Self-service password change. Used when must_change_password is true.

export interface IamChangePasswordPayload {
  current_password: string;
  new_password: string;
  new_password_confirmation: string;
}

export interface IamChangePasswordResponse {
  message: string;
  must_change_password: boolean;
}

export async function changeMyPassword(
  payload: IamChangePasswordPayload,
): Promise<IamChangePasswordResponse> {
  return iamRequest<IamChangePasswordResponse>("/me/password", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify(payload),
  });
}

export async function updatePersonPreferences(
  preferences: Record<string, unknown>,
): Promise<{ preferences: Record<string, unknown> }> {
  return iamRequest<{ preferences: Record<string, unknown> }>(
    "/me/preferences",
    {
      method: "PATCH",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ preferences }),
    },
  );
}

export async function updateAccountSettings(
  settings: Record<string, unknown>,
): Promise<{ settings: Record<string, unknown> }> {
  return iamRequest<{ settings: Record<string, unknown> }>("/me/settings", {
    method: "PATCH",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ settings }),
  });
}

export async function logout(): Promise<void> {
  await iamRequest<void>("/logout", {
    method: "POST",
  });
}

// User Management API

export interface IamUserListParams {
  search?: string;
  status?: IamAccountStatus;
  per_page?: number;
  page?: number;
}

export async function listIamUsers(
  params: IamUserListParams = {},
): Promise<IamPagination<IamUserListItem>> {
  const query = new URLSearchParams();
  if (params.search) query.set("search", params.search);
  if (params.status) query.set("status", params.status);
  if (params.per_page !== undefined) {
    query.set("per_page", String(params.per_page));
  }
  if (params.page !== undefined) {
    query.set("page", String(params.page));
  }
  const suffix = query.toString();
  return iamRequest<IamPagination<IamUserListItem>>(
    `/admin/users${suffix ? `?${suffix}` : ""}`,
  );
}

// Legacy Apps Access API

export interface IamLegacyAppsAccessListParams {
  search?: string;
  status?: IamAccountStatus;
  per_page?: number;
  page?: number;
}

export async function listIamLegacyAppsAccess(
  params: IamLegacyAppsAccessListParams = {},
): Promise<IamPagination<IamAccessUserListItem>> {
  const query = new URLSearchParams();

  if (params.search) query.set("search", params.search);
  if (params.status) query.set("status", params.status);
  if (params.per_page !== undefined) {
    query.set("per_page", String(params.per_page));
  }
  if (params.page !== undefined) {
    query.set("page", String(params.page));
  }

  const suffix = query.toString();

  return iamRequest<IamPagination<IamAccessUserListItem>>(
    `/admin/legacy-apps/access${suffix ? `?${suffix}` : ""}`,
  );
}

//Individual Legacy Apps access API

export async function getIamUserLegacyAppsAccess(
  userId: string,
): Promise<IamUserLegacyAppsAccessResponse> {
  return iamRequest<IamUserLegacyAppsAccessResponse>(
    `/admin/users/${encodeURIComponent(userId)}/legacy-apps`,
  );
}

export async function listIamLegacyAppsDirectory():
  Promise<IamLegacyAppsDirectoryResponse> {
  return iamRequest<IamLegacyAppsDirectoryResponse>("/legacy-apps");
}

// Administrator Legacy App Directory Management API

export async function listIamLegacyAppsForManagement():
  Promise<IamManagedLegacyAppsResponse> {
  return iamRequest<IamManagedLegacyAppsResponse>(
    "/admin/legacy-apps",
  );
}

export async function getIamManagedLegacyApp(
  appId: string,
): Promise<IamManagedLegacyAppResponse> {
  return iamRequest<IamManagedLegacyAppResponse>(
    `/admin/legacy-apps/${encodeURIComponent(appId)}`,
  );
}

export async function createIamLegacyApp(
  payload: IamCreateLegacyAppPayload,
): Promise<IamManagedLegacyAppResponse> {
  return iamRequest<IamManagedLegacyAppResponse>(
    "/admin/legacy-apps",
    {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    },
  );
}

export async function updateIamManagedLegacyApp(
  appId: string,
  payload: IamUpdateLegacyAppPayload,
): Promise<IamManagedLegacyAppResponse> {
  return iamRequest<IamManagedLegacyAppResponse>(
    `/admin/legacy-apps/${encodeURIComponent(appId)}`,
    {
      method: "PATCH",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    },
  );
}

export async function activateIamLegacyApp(
  appId: string,
): Promise<IamManagedLegacyAppResponse> {
  return iamRequest<IamManagedLegacyAppResponse>(
    `/admin/legacy-apps/${encodeURIComponent(appId)}/activate`,
    { method: "POST" },
  );
}

export async function deactivateIamLegacyApp(
  appId: string,
): Promise<IamManagedLegacyAppResponse> {
  return iamRequest<IamManagedLegacyAppResponse>(
    `/admin/legacy-apps/${encodeURIComponent(appId)}/deactivate`,
    { method: "POST" },
  );
}

export async function grantIamLegacyAppAccess(
  userId: string,
  legacyAppId: string,
): Promise<IamLegacyAppGrantResponse> {
  return iamRequest<IamLegacyAppGrantResponse>(
    `/admin/users/${encodeURIComponent(userId)}/legacy-apps/${encodeURIComponent(legacyAppId)}/grant`,
    {
      method: "POST",
    },
  );
}

export async function revokeIamLegacyAppAccess(
  userId: string,
  legacyAppId: string,
): Promise<IamLegacyAppGrantResponse> {
  return iamRequest<IamLegacyAppGrantResponse>(
    `/admin/users/${encodeURIComponent(userId)}/legacy-apps/${encodeURIComponent(legacyAppId)}/revoke`,
    {
      method: "POST",
    },
  );
}

// Grant Set API

export async function listIamGrantSets(
  includeArchived = false,
): Promise<IamGrantSetListResponse> {
  const query = includeArchived ? "?include_archived=true" : "";

  return iamRequest<IamGrantSetListResponse>(
    `/admin/legacy-apps/grant-sets${query}`,
  );
}

export async function getIamGrantSet(
  grantSetId: string,
  includeArchived = false,
): Promise<IamGrantSetResponse> {
  const query = includeArchived ? "?include_archived=true" : "";

  return iamRequest<IamGrantSetResponse>(
    `/admin/legacy-apps/grant-sets/${encodeURIComponent(grantSetId)}${query}`,
  );
}

export async function createIamGrantSet(
  payload: IamCreateGrantSetPayload,
): Promise<IamGrantSetResponse> {
  return iamRequest<IamGrantSetResponse>(
    "/admin/legacy-apps/grant-sets",
    {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(payload),
    },
  );
}

export async function updateIamGrantSet(
  grantSetId: string,
  payload: IamUpdateGrantSetPayload,
): Promise<IamGrantSetResponse> {
  return iamRequest<IamGrantSetResponse>(
    `/admin/legacy-apps/grant-sets/${encodeURIComponent(grantSetId)}`,
    {
      method: "PATCH",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(payload),
    },
  );
}

export async function archiveIamGrantSet(
  grantSetId: string,
): Promise<IamMessageResponse> {
  return iamRequest<IamMessageResponse>(
    `/admin/legacy-apps/grant-sets/${encodeURIComponent(grantSetId)}/archive`,
    {
      method: "POST",
    },
  );
}

export async function restoreIamGrantSet(
  grantSetId: string,
): Promise<IamGrantSetResponse> {
  return iamRequest<IamGrantSetResponse>(
    `/admin/legacy-apps/grant-sets/${encodeURIComponent(grantSetId)}/restore`,
    {
      method: "POST",
    },
  );
}

export async function applyIamGrantSet(
  userId: string,
  grantSetId: string,
): Promise<IamApplyGrantSetResponse> {
  return iamRequest<IamApplyGrantSetResponse>(
    `/admin/users/${encodeURIComponent(userId)}/legacy-apps/grant-sets/${encodeURIComponent(grantSetId)}/apply`,
    {
      method: "POST",
    },
  );
}

export async function createIamBatchAssignment(
  payload: IamBatchAssignmentPayload,
): Promise<IamBatchAssignmentResponse> {
  return iamRequest<IamBatchAssignmentResponse>(
    "/admin/legacy-apps/batch-assignments",
    {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(payload),
    },
  );
}

export async function createIamUser(
  payload: IamCreateUserPayload,
): Promise<IamCreateUserResponse> {
  return iamRequest<IamCreateUserResponse>("/admin/users", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify(payload),
  });
}

export async function getIamUser(userId: string): Promise<IamUserDetail> {
  return iamRequest<IamUserDetail>(
    `/admin/users/${encodeURIComponent(userId)}`,
  );
}

export async function updateIamUser(
  userId: string,
  payload: IamUpdateUserPayload,
): Promise<IamUpdateUserResponse> {
  return iamRequest<IamUpdateUserResponse>(
    `/admin/users/${encodeURIComponent(userId)}`,
    {
      method: "PATCH",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(payload),
    },
  );
}

export async function resetIamUserPassword(
  userId: string,
  payload: IamPasswordResetPayload,
): Promise<IamPasswordResetResponse> {
  return iamRequest<IamPasswordResetResponse>(
    `/admin/users/${encodeURIComponent(userId)}/password`,
    {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(payload),
    },
  );
}

async function updateIamUserLifecycle(
  userId: string,
  action: "disable" | "deactivate" | "reactivate" | "reinstate",
): Promise<IamMessageResponse> {
  return iamRequest<IamMessageResponse>(
    `/admin/users/${encodeURIComponent(userId)}/${action}`,
    {
      method: "POST",
    },
  );
}

export const disableIamUser = (userId: string) =>
  updateIamUserLifecycle(userId, "disable");

export const deactivateIamUser = (userId: string) =>
  updateIamUserLifecycle(userId, "deactivate");

export const reactivateIamUser = (userId: string) =>
  updateIamUserLifecycle(userId, "reactivate");

export const reinstateIamUser = (userId: string) =>
  updateIamUserLifecycle(userId, "reinstate");
