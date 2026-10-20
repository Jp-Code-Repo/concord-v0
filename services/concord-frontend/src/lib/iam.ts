const IAM_BASE_URL = "/api/iam";

export interface IamUser {
  id: string;
  username: string;
  email: string;
  display_name: string;
  is_it_admin: boolean;
}

export interface IamAppAccess {
  key: string;
  name: string;
}

export interface IamAccess {
  all_access: boolean;
  apps: IamAppAccess[];
  offices: unknown[];
}

export interface IamSession {
  user: IamUser;
  access: IamAccess;
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

export async function login(
  username: string,
  password: string,
): Promise<{ user: IamUser }> {
  return iamRequest<{ user: IamUser }>("/login", {
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

export async function logout(): Promise<void> {
  await iamRequest<void>("/logout", {
    method: "POST",
  });
}
