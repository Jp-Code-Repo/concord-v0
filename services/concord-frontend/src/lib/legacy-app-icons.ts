import {
  Activity,
  AppWindow,
  Bell,
  BookMarked,
  BookOpen,
  BriefcaseBusiness,
  Building2,
  CalendarDays,
  ChartNoAxesColumn,
  ChartLine,
  ClipboardCheck,
  ClipboardList,
  CreditCard,
  Database,
  FileCheck,
  FileText,
  FolderOpen,
  Globe,
  GraduationCap,
  HardDrive,
  HeartPulse,
  House,
  Laptop,
  Library,
  Mail,
  MessageSquare,
  Monitor,
  Network,
  Package,
  Printer,
  Receipt,
  School,
  Settings,
  Shield,
  ShieldCheck,
  Stethoscope,
  Ticket,
  Truck,
  UserCheck,
  UserCog,
  UserRound,
  Users,
  UsersRound,
  Wallet,
  Wrench,
} from "lucide-react";

import type { LucideIcon } from "lucide-react";

export type LegacyAppIconCategory =
  | "Education"
  | "People & HR"
  | "Administration"
  | "Healthcare"
  | "Finance"
  | "IT & Systems"
  | "Operations"
  | "Communication"
  | "General";

export interface LegacyAppIconOption {
  value: string;
  label: string;
  category: LegacyAppIconCategory;
  icon: LucideIcon;
}

export const legacyAppIconOptions: LegacyAppIconOption[] = [
  // Education
  {
    value: "graduation-cap",
    label: "Graduation Cap",
    category: "Education",
    icon: GraduationCap,
  },
  { value: "school", label: "School", category: "Education", icon: School },
  { value: "books", label: "Books", category: "Education", icon: BookOpen },
  {
    value: "book-marked",
    label: "Book Marked",
    category: "Education",
    icon: BookMarked,
  },
  { value: "library", label: "Library", category: "Education", icon: Library },
  {
    value: "clipboard-list",
    label: "Clipboard List",
    category: "Education",
    icon: ClipboardList,
  },
  {
    value: "clipboard-check",
    label: "Clipboard Check",
    category: "Education",
    icon: ClipboardCheck,
  },
  {
    value: "chart-no-axes-column",
    label: "Academic Results",
    category: "Education",
    icon: ChartNoAxesColumn,
  },

  // People & HR
  { value: "users", label: "Users", category: "People & HR", icon: Users },
  {
    value: "users-round",
    label: "Users Round",
    category: "People & HR",
    icon: UsersRound,
  },
  {
    value: "user-round",
    label: "User",
    category: "People & HR",
    icon: UserRound,
  },
  {
    value: "user-cog",
    label: "User Settings",
    category: "People & HR",
    icon: UserCog,
  },
  {
    value: "user-check",
    label: "User Verified",
    category: "People & HR",
    icon: UserCheck,
  },

  // Administration
  {
    value: "building-2",
    label: "Building",
    category: "Administration",
    icon: Building2,
  },
  {
    value: "briefcase-business",
    label: "Briefcase",
    category: "Administration",
    icon: BriefcaseBusiness,
  },
  {
    value: "file-text",
    label: "Documents",
    category: "Administration",
    icon: FileText,
  },
  {
    value: "file-check",
    label: "Verified Document",
    category: "Administration",
    icon: FileCheck,
  },
  {
    value: "folder-open",
    label: "Folder",
    category: "Administration",
    icon: FolderOpen,
  },
  {
    value: "calendar-days",
    label: "Calendar",
    category: "Administration",
    icon: CalendarDays,
  },
  {
    value: "shield-check",
    label: "Verified",
    category: "Administration",
    icon: ShieldCheck,
  },
  {
    value: "shield",
    label: "Shield",
    category: "Administration",
    icon: Shield,
  },

  // Healthcare
  {
    value: "heart-pulse",
    label: "Healthcare",
    category: "Healthcare",
    icon: HeartPulse,
  },
  {
    value: "stethoscope",
    label: "Stethoscope",
    category: "Healthcare",
    icon: Stethoscope,
  },
  {
    value: "activity",
    label: "Activity",
    category: "Healthcare",
    icon: Activity,
  },

  // Finance
  { value: "wallet", label: "Wallet", category: "Finance", icon: Wallet },
  {
    value: "credit-card",
    label: "Credit Card",
    category: "Finance",
    icon: CreditCard,
  },
  { value: "receipt", label: "Receipt", category: "Finance", icon: Receipt },
  {
    value: "chart-line",
    label: "Financial Chart",
    category: "Finance",
    icon: ChartLine,
  },

  // IT & Systems
  {
    value: "monitor",
    label: "Monitor",
    category: "IT & Systems",
    icon: Monitor,
  },
  { value: "laptop", label: "Laptop", category: "IT & Systems", icon: Laptop },
  {
    value: "database",
    label: "Database",
    category: "IT & Systems",
    icon: Database,
  },
  {
    value: "hard-drive",
    label: "Hard Drive",
    category: "IT & Systems",
    icon: HardDrive,
  },
  {
    value: "network",
    label: "Network",
    category: "IT & Systems",
    icon: Network,
  },
  {
    value: "settings",
    label: "Settings",
    category: "IT & Systems",
    icon: Settings,
  },

  // Operations
  {
    value: "wrench",
    label: "Maintenance",
    category: "Operations",
    icon: Wrench,
  },
  { value: "printer", label: "Printer", category: "Operations", icon: Printer },
  { value: "package", label: "Package", category: "Operations", icon: Package },
  { value: "truck", label: "Truck", category: "Operations", icon: Truck },

  // Communication
  { value: "mail", label: "Email", category: "Communication", icon: Mail },
  {
    value: "bell",
    label: "Notifications",
    category: "Communication",
    icon: Bell,
  },
  {
    value: "message-square",
    label: "Messages",
    category: "Communication",
    icon: MessageSquare,
  },
  {
    value: "ticket",
    label: "Tickets",
    category: "Communication",
    icon: Ticket,
  },

  // General
  {
    value: "globe",
    label: "Web Application",
    category: "General",
    icon: Globe,
  },
  { value: "house", label: "Home", category: "General", icon: House },
  {
    value: "app-window",
    label: "Application",
    category: "General",
    icon: AppWindow,
  },
];

export const legacyAppIcons: Record<string, LucideIcon> = Object.fromEntries(
  legacyAppIconOptions.map((option) => [option.value, option.icon]),
);

export const getLegacyAppIcon = (identifier: string | null) =>
  legacyAppIcons[identifier ?? ""] ?? AppWindow;
