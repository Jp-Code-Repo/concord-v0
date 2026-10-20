import { Outlet } from "@tanstack/react-router";
import { useState } from "react";

import { Sidebar } from "@/components/layout/Sidebar";
import { Topbar } from "@/components/layout/topbar/Topbar";
import { ThemeContext, type ConcordTheme } from "@/contexts/ThemeContext";

import { Navigate } from "@tanstack/react-router";
import { useAuth } from "@/contexts/AuthContext";

import { CorrectionRequestProvider } from "@/contexts/CorrectionRequestContext";

export function ConcordLayout() {
  const [theme, setTheme] = useState<ConcordTheme>("light");
  const [sidebarCollapsed, setSidebarCollapsed] = useState(false);

  function updateTheme(nextTheme: ConcordTheme) {
    setTheme(nextTheme);
    document.documentElement.classList.toggle("dark", nextTheme === "dark");
  }

  function toggleTheme() {
    updateTheme(theme === "dark" ? "light" : "dark");
  }

  const { status, refreshSession } = useAuth();

  if (status === "loading") {
    return (
      <div className="flex min-h-screen items-center justify-center bg-background">
        <p className="text-sm text-muted-foreground">
          Verifying your session...
        </p>
      </div>
    );
  }

  if (status === "unauthenticated") {
    return <Navigate to="/login" />;
  }

  if (status === "error") {
    return (
      <div className="flex min-h-screen flex-col items-center justify-center gap-4 bg-background p-6 text-center">
        <h1 className="text-lg font-semibold">Unable to verify your session</h1>
        <p className="max-w-md text-sm text-muted-foreground">
          Concord couldn't connect to IAM to verify your session. Please try
          again.
        </p>
        <button
          type="button"
          onClick={() => void refreshSession()}
          className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90"
        >
          Try again
        </button>
      </div>
    );
  }

  return (
    <CorrectionRequestProvider>
      <ThemeContext.Provider
        value={{
          theme,
          onThemeChange: updateTheme,
        }}
      >
        <div className="min-h-screen bg-background text-foreground">
          <Sidebar collapsed={sidebarCollapsed} />

          <div
            className={`min-h-screen transition-[margin] duration-200 ${
              sidebarCollapsed ? "ml-16" : "ml-64"
            }`}
          >
            <Topbar
              sidebarCollapsed={sidebarCollapsed}
              onSidebarToggle={() =>
                setSidebarCollapsed((collapsed) => !collapsed)
              }
              isDark={theme === "dark"}
              onThemeToggle={toggleTheme}
            />

            <main className="mx-auto w-full max-w-[1800px] p-5 md:p-8">
              <Outlet />
            </main>
          </div>
        </div>
      </ThemeContext.Provider>
    </CorrectionRequestProvider>
  );
}
