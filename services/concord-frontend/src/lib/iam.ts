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

export interface IamUser {
  id: string;
  person_id: string;
  username: string;
  is_it_admin: boolean;
  status: "active" | "deactivated" | "disabled";
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
  access?: IamAccess;
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

export async function updatePersonPreferences(
  preferences: Record<string, unknown>,
): Promise<{ preferences: Record<string, unknown> }> {
  return iamRequest<{ preferences: Record<string, unknown> }>("/me/preferences", {
    method: "PATCH",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ preferences }),
  });
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
