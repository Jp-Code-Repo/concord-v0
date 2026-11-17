import { createRoute } from "@tanstack/react-router";

import { rootRoute } from "./__root";
import { ConcordLayout } from "@/components/layout/ConcordLayout";
import { HomePage } from "@/pages/HomePage";
import { LoginPage } from "@/pages/LoginPage";
import { ProfilePage } from "@/pages/ProfilePage";
import { LegacyAppsPage } from "@/pages/LegacyAppsPage";
import { UserManagementPage } from "@/pages/UserManagementPage";
import { UserDetailPage } from "@/pages/UserDetailPage";

export const concordRoute = createRoute({
  getParentRoute: () => rootRoute,
  id: "concord-layout",
  component: ConcordLayout,
});

export const loginRoute = createRoute({
  getParentRoute: () => rootRoute,
  path: "/login",
  component: LoginPage,
});

export const indexRoute = createRoute({
  getParentRoute: () => concordRoute,
  path: "/",
  component: HomePage,
});

export const profileRoute = createRoute({
  getParentRoute: () => concordRoute,
  path: "/profile",
  component: ProfilePage,
});

export const legacyAppsRoute = createRoute({
  getParentRoute: () => concordRoute,
  path: "/legacy-apps",
  component: LegacyAppsPage,
});

export const userManagementRoute = createRoute({
  getParentRoute: () => concordRoute,
  path: "/admin/users",
  component: UserManagementPage,
});

export const userDetailRoute = createRoute({
  getParentRoute: () => concordRoute,
  path: "/admin/users/$userId",
  component: UserDetailPage,
});
