import { useEffect, useRef, useState } from "react";
import { useNavigate } from "@tanstack/react-router";
import {
  Building2,
  ContactRound,
  LogOut,
  Settings,
  ShieldCheck,
  UserRound,
} from "lucide-react";

import { useAuth } from "@/contexts/AuthContext";

import avatar from "@/assets/illustrations/profile-avatar.png";

export function ProfileDropdown() {
  const [profileOpen, setProfileOpen] = useState(false);
  const profileRef = useRef<HTMLDivElement>(null);
  const navigate = useNavigate();

  const { user, signOut } = useAuth();
  const [isSigningOut, setIsSigningOut] = useState(false);
  const [signOutError, setSignOutError] = useState("");

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

  function goToProfile() {
    setProfileOpen(false);
    navigate({ to: "/profile" });
  }

  async function handleSignOut() {
    setProfileOpen(false);
    setSignOutError("");
    setIsSigningOut(true);

    try {
      await signOut();
      await navigate({ to: "/login" });
    } catch (error) {
      setSignOutError(
        error instanceof Error
          ? error.message
          : "Unable to sign out. Please try again.",
      );
    } finally {
      setIsSigningOut(false);
    }
  }

  return (
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
        <img src={avatar} alt="" className="h-full w-full object-cover" />
      </button>

      {profileOpen && (
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
                {user?.display_name ?? user?.username ?? "Concord User"}
              </p>
              <p className="truncate text-xs text-muted-foreground">
                {user?.email ?? ""}
              </p>
              <p className="mt-1 text-xs text-blue-600 dark:text-blue-400">
                {user?.is_it_admin ? "IT Administrator" : "Concord Account"}
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
              onClick={() => void handleSignOut()}
              disabled={isSigningOut}
              className="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-red-600 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-60 dark:text-red-400 dark:hover:bg-red-950/40"
            >
              <LogOut className="h-4 w-4" />
              {isSigningOut ? "Signing out..." : "Sign Out"}
            </button>
          </div>
        </div>
      )}

      {signOutError && (
        <p role="alert" className="border-t px-3 py-2 text-xs text-destructive">
          {signOutError}
        </p>
      )}
    </div>
  );
}
