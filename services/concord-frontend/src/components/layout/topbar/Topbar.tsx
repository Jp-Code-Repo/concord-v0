import {
  GlobalSearch,
  NotificationBell,
  ProfileDropdown,
  SidebarToggle,
  ThemeToggle,
} from "./components";

interface TopbarProps {
  sidebarCollapsed: boolean;
  onSidebarToggle: () => void;
  isDark: boolean;
  onThemeToggle: () => void;
}

export function Topbar({
  sidebarCollapsed,
  onSidebarToggle,
  isDark,
  onThemeToggle,
}: TopbarProps) {
  return (
    <header className="sticky top-0 z-30 flex h-16 items-center gap-4 border-b bg-background/95 px-4 backdrop-blur sm:px-6">
      <SidebarToggle collapsed={sidebarCollapsed} onToggle={onSidebarToggle} />

      <div className="h-6 w-px shrink-0 bg-border" />

      <GlobalSearch />

      <div className="ml-auto flex shrink-0 items-center gap-1">
        <ThemeToggle isDark={isDark} onToggle={onThemeToggle} />
        <NotificationBell />
        <ProfileDropdown />
      </div>
    </header>
  );
}
