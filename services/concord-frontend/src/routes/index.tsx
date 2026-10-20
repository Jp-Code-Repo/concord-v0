import { createRoute } from "@tanstack/react-router";

import { rootRoute } from "./__root";
import { ConcordLayout } from "@/components/layout/ConcordLayout";
import { HomePage } from "@/pages/HomePage";
import { LoginPage } from "@/pages/LoginPage";
import { ProfilePage } from "@/pages/ProfilePage";

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
