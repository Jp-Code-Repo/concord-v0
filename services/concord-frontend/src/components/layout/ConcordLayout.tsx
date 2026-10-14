import { Outlet, useNavigate } from "@tanstack/react-router";
import { useEffect, useRef, useState } from "react";
import {
  Bell,
  ChevronRight,
  LayoutDashboard,
  LogOut,
  Menu,
  Moon,
  Search,
  Settings,
  ShieldCheck,
  Sun,
  UserRound,
  Building2,
  ContactRound,
} from "lucide-react";

import emblem from "@/assets/brand/concord-emblem.png";
import avatar from "@/assets/illustrations/profile-avatar.png";

export function ConcordLayout() {
  const [isDark, setIsDark] = useState(false);
  const [sidebarCollapsed, setSidebarCollapsed] = useState(false);
  const [profileOpen, setProfileOpen] = useState(false);

  const navigate = useNavigate();

  function toggleTheme() {
    const nextIsDark = !isDark;
    setIsDark(nextIsDark);
    document.documentElement.classList.toggle("dark", nextIsDark);
  }

  function goToProfile() {
    setProfileOpen(false);
    navigate({ to: "/profile" });
  }

  const profileRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    function handleOutsideClick(event: MouseEvent) {
      if (
        profileRef.current &&
        !profileRef.current.contains(event.target as Node)
      ) {
        setProfileOpen(false);
      }
    }

    document.addEventListener("mousedown", handleOutsideClick);

    return () => {
      document.removeEventListener("mousedown", handleOutsideClick);
    };
  }, []);

  return (
    <div className="min-h-screen bg-background text-foreground">
      <aside
        className={`fixed inset-y-0 left-0 z-40 flex flex-col border-r bg-card transition-[width] duration-200 ${
          sidebarCollapsed ? "w-16" : "w-64"
        }`}
      >
        <div
          className={`flex h-16 shrink-0 items-center border-b ${
            sidebarCollapsed ? "justify-center px-2" : "gap-3 px-3"
          }`}
        >
          <img
            src={emblem}
            alt="Concord emblem"
            className="h-11 w-11 shrink-0 object-contain"
          />

          {!sidebarCollapsed && (
            <>
              <div className="min-w-0 flex-1">
                <div className="concord-wordmark text-sm">CONCORD</div>
                <div className="mt-1 text-xs text-muted-foreground">
                  Dashboard
                </div>
              </div>

              <span className="shrink-0 rounded-full bg-muted px-2 py-1 text-xs font-medium text-muted-foreground">
                V1.0
              </span>
            </>
          )}
        </div>

        <nav className="flex-1 space-y-2 overflow-y-auto p-3">
          {!sidebarCollapsed && (
            <p className="px-2 pb-1 pt-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
              Dashboard
            </p>
          )}

          <button
            type="button"
            onClick={() => navigate({ to: "/" })}
            title={sidebarCollapsed ? "Dashboard" : undefined}
            className={`flex w-full items-center rounded-lg py-3 text-sm font-medium ${
              sidebarCollapsed ? "justify-center px-0" : "gap-3 px-3"
            } bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300`}
          >
            <LayoutDashboard className="h-5 w-5 shrink-0" />
            {!sidebarCollapsed && <span>Dashboard</span>}
          </button>

          {!sidebarCollapsed && (
            <>
              <p className="px-2 pb-1 pt-5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                Workspace
              </p>

              <div className="rounded-lg border border-dashed p-3 text-sm text-muted-foreground">
                Your authorized applications will appear here.
              </div>
            </>
          )}
        </nav>

        <div className="shrink-0 border-t p-3">
          {sidebarCollapsed ? (
            <div className="flex justify-center" title="Concord Portal">
              <ChevronRight className="h-5 w-5 text-muted-foreground" />
            </div>
          ) : (
            <p className="text-xs text-muted-foreground">Concord Portal</p>
          )}
        </div>
      </aside>

      <div
        className={`min-h-screen transition-[margin] duration-200 ${
          sidebarCollapsed ? "ml-16" : "ml-64"
        }`}
      >
        <header className="sticky top-0 z-30 flex h-16 items-center gap-4 border-b bg-background/95 px-4 backdrop-blur sm:px-6">
          <button
            type="button"
            onClick={() => setSidebarCollapsed((collapsed) => !collapsed)}
            className="shrink-0 rounded-md p-2 hover:bg-muted"
            aria-label={
              sidebarCollapsed ? "Expand sidebar" : "Collapse sidebar"
            }
            title={sidebarCollapsed ? "Expand sidebar" : "Collapse sidebar"}
          >
            <Menu className="h-5 w-5" />
          </button>

          <div className="h-6 w-px shrink-0 bg-border" />

          <div className="relative w-full max-w-sm">
            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <input
              type="search"
              placeholder="Search..."
              aria-label="Search"
              className="h-9 w-full rounded-lg border border-input bg-background pl-9 pr-3 text-sm outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring"
            />
          </div>

          <div className="ml-auto flex shrink-0 items-center gap-1">
            <button
              type="button"
              onClick={toggleTheme}
              className="rounded-md p-2 hover:bg-muted"
              aria-label={
                isDark ? "Switch to light mode" : "Switch to dark mode"
              }
              title={isDark ? "Switch to light mode" : "Switch to dark mode"}
            >
              {isDark ? (
                <Sun className="h-5 w-5" />
              ) : (
                <Moon className="h-5 w-5" />
              )}
            </button>

            <button
              type="button"
              className="relative rounded-md p-2 hover:bg-muted"
              aria-label="Notifications"
              title="Notifications"
            >
              <Bell className="h-5 w-5" />
            </button>

            {/* Profile dropdown */}
            <div ref={profileRef} className="relative ml-2">
              <button
                type="button"
                onClick={() => setProfileOpen((open) => !open)}
                aria-haspopup="menu"
                aria-expanded={profileOpen}
                aria-label="Open profile menu"
                title="Profile menu"
                className="flex h-9 w-9 items-center justify-center overflow-hidden rounded-full border border-blue-100 bg-blue-50 ring-offset-background transition hover:ring-2 hover:ring-blue-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 dark:border-blue-900 dark:bg-blue-950"
              >
                <img
                  src={avatar}
                  alt=""
                  className="h-full w-full object-cover"
                />
              </button>

              {profileOpen && (
                <>

                  <div
                    role="menu"
                    className="absolute right-0 z-50 mt-2 w-72 overflow-hidden rounded-xl border bg-popover text-popover-foreground shadow-lg"
                  >
                    <div className="flex items-center gap-3 p-4">
                      <img
                        src={avatar}
                        alt=""
                        className="h-11 w-11 rounded-full border border-blue-100 object-cover dark:border-blue-900"
                      />

                      <div className="min-w-0">
                        <p className="truncate text-sm font-semibold">
                          Alex Santos
                        </p>
                        <p className="truncate text-xs text-muted-foreground">
                          alex.santos@example.edu
                        </p>
                        <p className="mt-1 text-xs text-blue-600 dark:text-blue-400">
                          Preview identity
                        </p>
                      </div>
                    </div>

                    <div className="border-t p-1.5">
                      <button
                        type="button"
                        role="menuitem"
                        onClick={goToProfile}
                        className="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm hover:bg-muted"
                      >
                        <UserRound className="h-4 w-4 text-muted-foreground" />
                        My Profile
                      </button>

                      <button
                        type="button"
                        role="menuitem"
                        onClick={() => setProfileOpen(false)}
                        className="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm hover:bg-muted"
                      >
                        <ContactRound className="h-4 w-4 text-muted-foreground" />
                        Personal Information
                      </button>

                      <button
                        type="button"
                        role="menuitem"
                        onClick={() => setProfileOpen(false)}
                        className="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm hover:bg-muted"
                      >
                        <Building2 className="h-4 w-4 text-muted-foreground" />
                        My Offices & Access
                      </button>
                    </div>

                    <div className="border-t p-1.5">
                      <button
                        type="button"
                        role="menuitem"
                        onClick={() => setProfileOpen(false)}
                        className="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm hover:bg-muted"
                      >
                        <Settings className="h-4 w-4 text-muted-foreground" />
                        Account Settings
                      </button>

                      <button
                        type="button"
                        role="menuitem"
                        onClick={() => setProfileOpen(false)}
                        className="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm hover:bg-muted"
                      >
                        <ShieldCheck className="h-4 w-4 text-muted-foreground" />
                        Security
                      </button>
                    </div>

                    <div className="border-t p-1.5">
                      <button
                        type="button"
                        role="menuitem"
                        onClick={() => setProfileOpen(false)}
                        className="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40"
                      >
                        <LogOut className="h-4 w-4" />
                        Sign Out
                      </button>
                    </div>
                  </div>
                </>
              )}
            </div>
          </div>
        </header>

        <main className="mx-auto w-full max-w-[1800px] p-5 md:p-8">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
