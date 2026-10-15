import { Outlet } from "@tanstack/react-router";
import { useState } from "react";

import { Sidebar } from "@/components/layout/Sidebar";
import { Topbar } from "@/components/layout/topbar/Topbar";
import { ThemeContext, type ConcordTheme } from "@/contexts/ThemeContext";

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
