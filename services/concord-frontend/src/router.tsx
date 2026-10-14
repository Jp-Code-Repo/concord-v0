import { createRouter } from "@tanstack/react-router";
import { rootRoute } from "./routes/__root";
import { concordRoute, indexRoute, profileRoute } from "./routes";

const routeTree = rootRoute.addChildren([
  concordRoute.addChildren([indexRoute, profileRoute]),
]);

export const router = createRouter({
  routeTree,
});

declare module "@tanstack/react-router" {
  interface Register {
    router: typeof router;
  }
}
