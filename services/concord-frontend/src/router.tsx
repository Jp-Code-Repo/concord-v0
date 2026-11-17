import { createRouter } from "@tanstack/react-router";
import { rootRoute } from "./routes/__root";
import {
  concordRoute,
  indexRoute,
  loginRoute,
  profileRoute,
  legacyAppsRoute,
  userManagementRoute,
  userDetailRoute,
} from "./routes";

const routeTree = rootRoute.addChildren([
  loginRoute,
  concordRoute.addChildren([
    indexRoute, 
    profileRoute, 
    legacyAppsRoute, 
    userManagementRoute,
    userDetailRoute]),
]);

export const router = createRouter({
  routeTree,
});

declare module "@tanstack/react-router" {
  interface Register {
    router: typeof router;
  }
}
