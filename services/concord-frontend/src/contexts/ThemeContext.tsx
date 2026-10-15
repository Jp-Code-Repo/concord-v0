import { createContext, useContext } from "react";

export type ConcordTheme = "light" | "dark";

export interface ThemeContextValue {
  theme: ConcordTheme;
  onThemeChange: (theme: ConcordTheme) => void;
}

export const ThemeContext = createContext<ThemeContextValue | null>(null);

export function useConcordTheme() {
  const context = useContext(ThemeContext);

  if (!context) {
    throw new Error(
      "useConcordTheme must be used within ThemeContext.Provider",
    );
  }

  return context;
}
