import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useState,
  type ReactNode,
} from "react";
import {
  getCurrentUser,
  IamApiError,
  login as iamLogin,
  logout as iamLogout,
  type IamSession,
  type IamUser,
  type IamLegacyApp,
} from "@/lib/iam";

export type AuthStatus =
  | "loading"
  | "authenticated"
  | "password_change_required"
  | "unauthenticated"
  | "error";

export interface AuthContextValue {
  status: AuthStatus;
  user: IamUser | null;
  error: string | null;
  signIn: (username: string, password: string) => Promise<void>;
  signOut: () => Promise<void>;
  refreshSession: () => Promise<void>;
  legacyApps: IamLegacyApp[];
  accountRevocation: "ACCOUNT_DISABLED" | "ACCOUNT_DEACTIVATED" | null;
  acknowledgeAccountRevocation: () => void;
}

export const AuthContext = createContext<AuthContextValue | null>(null);

interface AuthProviderProps {
  children: ReactNode;
}

export function AuthProvider({ children }: AuthProviderProps) {
  const [status, setStatus] = useState<AuthStatus>("loading");
  const [user, setUser] = useState<IamUser | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [legacyApps, setLegacyApps] = useState<IamLegacyApp[]>([]);
  //Stores why the authenticated account's access was revoked.
  const [accountRevocation, setAccountRevocation] = useState<
    "ACCOUNT_DISABLED" | "ACCOUNT_DEACTIVATED" | null
  >(null);

  const applySession = useCallback((session: IamSession) => {
    setUser(session.user);

    setStatus(
      session.user.must_change_password
        ? "password_change_required"
        : "authenticated",
    );

    setError(null);
    setLegacyApps(session.access?.legacy_apps ?? []);
  }, []);

  const clearSession = useCallback(() => {
    setUser(null);
    setLegacyApps([]);
  }, []);

  const refreshSession = useCallback(async () => {
    setStatus("loading");
    setError(null);

    try {
      const session = await getCurrentUser();
      applySession(session);
    } catch (requestError) {
      
      if (
        requestError instanceof IamApiError &&
        (requestError.code === "ACCOUNT_DISABLED" ||
          requestError.code === "ACCOUNT_DEACTIVATED")
      ) {
        setStatus("authenticated");
        setError(null);
        return;
      }

      clearSession();

      if (requestError instanceof IamApiError && requestError.status === 401) {
        setStatus("unauthenticated");
        setError(null);
        return;
      }

      setStatus("error");
      setError(
        requestError instanceof Error
          ? requestError.message
          : "Unable to verify your session.",
      );
    }
  }, [applySession, clearSession]);

  useEffect(() => {
    void refreshSession();
  }, [refreshSession]);

  //Listen for account revocation events from IAM requests.
  useEffect(() => {
    function handleAccountRevoked(event: Event) {
      const customEvent = event as CustomEvent<{
        code: "ACCOUNT_DISABLED" | "ACCOUNT_DEACTIVATED";
      }>;

      setAccountRevocation(customEvent.detail.code);
    }

    window.addEventListener("iam:account-revoked", handleAccountRevoked);

    return () => {
      window.removeEventListener("iam:account-revoked", handleAccountRevoked);
    };
  }, []);

  // NEW: Clear the local session after the user acknowledges the dialog.
  const acknowledgeAccountRevocation = useCallback(() => {
    clearSession();
    setAccountRevocation(null);
    setStatus("unauthenticated");
  }, [clearSession]);

  const signIn = useCallback(
    async (username: string, password: string) => {
      setError(null);

      try {
        // /login already returns the full session payload.
        const session = await iamLogin(username, password);
        applySession(session);
      } catch (requestError) {
        clearSession();

        if (
          requestError instanceof IamApiError &&
          requestError.status === 401
        ) {
          setStatus("unauthenticated");
        } else {
          setStatus("error");
        }

        const message =
          requestError instanceof Error
            ? requestError.message
            : "Unable to sign in. Please try again.";

        setError(message);
        throw requestError;
      }
    },
    [applySession, clearSession],
  );

  const signOut = useCallback(async () => {
    setError(null);

    try {
      await iamLogout();
    } finally {
      clearSession();
      setStatus("unauthenticated");
    }
  }, [clearSession]);

  return (
    <AuthContext.Provider
      value={{
        status,
        user,
        error,
        legacyApps,
        signIn,
        signOut,
        refreshSession,
        accountRevocation,
        acknowledgeAccountRevocation,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const context = useContext(AuthContext);

  if (!context) {
    throw new Error("useAuth must be used within AuthProvider");
  }

  return context;
}
