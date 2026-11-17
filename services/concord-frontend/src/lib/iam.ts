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

// NEW: Legacy application registry item returned by IAM

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
}

export class IamApiError extends Error {
  public readonly status: number;
  public readonly errors?: Record<string, string[]>;
  constructor(
    message: string,
    status: number,
    errors?: Record<string, string[]>,
  ) {
    super(message);
    this.name = "IamApiError";
    this.status = status;
    this.errors = errors;
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
    throw new IamApiError(message, response.status, errorData.errors);
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
