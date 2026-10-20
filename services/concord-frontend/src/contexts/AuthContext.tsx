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
  type IamAccess,
  type IamSession,
  type IamUser,
} from "@/lib/iam";

export type AuthStatus =
  | "loading"
  | "authenticated"
  | "unauthenticated"
  | "error";

export interface AuthContextValue {
  status: AuthStatus;
  user: IamUser | null;
  access: IamAccess | null;
  error: string | null;
  signIn: (username: string, password: string) => Promise<void>;
  signOut: () => Promise<void>;
  refreshSession: () => Promise<void>;
}

export const AuthContext = createContext<AuthContextValue | null>(null);

interface AuthProviderProps {
  children: ReactNode;
}

export function AuthProvider({ children }: AuthProviderProps) {
  const [status, setStatus] = useState<AuthStatus>("loading");
  const [user, setUser] = useState<IamUser | null>(null);
  const [access, setAccess] = useState<IamAccess | null>(null);
  const [error, setError] = useState<string | null>(null);

  const applySession = useCallback((session: IamSession) => {
    setUser(session.user);
    setAccess(session.access);
    setStatus("authenticated");
    setError(null);
  }, []);

  const clearSession = useCallback(() => {
    setUser(null);
    setAccess(null);
  }, []);

  const refreshSession = useCallback(async () => {
    setStatus("loading");
    setError(null);

    try {
      const session = await getCurrentUser();
      applySession(session);
    } catch (requestError) {
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

  const signIn = useCallback(
    async (username: string, password: string) => {
      setError(null);

      try {
        await iamLogin(username, password);

        const session = await getCurrentUser();
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
        access,
        error,
        signIn,
        signOut,
        refreshSession,
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
