import { useState } from "react";
import {
  BriefcaseBusiness,
  Building2,
  LockKeyhole,
  MapPin,
  Pencil,
  Settings,
  ShieldCheck,
  UserRound,
} from "lucide-react";

import { Button } from "@/components/ui/button";
import { ProfileQuickActions } from "@/components/profile/ProfileQuickActions";
import { InstitutionInformation } from "@/components/profile/InstitutionInformation";
import { AccountSettings } from "@/components/profile/AccountSettings";
import { SecuritySettings } from "@/components/profile/SecuritySettings";
import { PreferencesSettings } from "@/components/profile/PreferencesSettings";
import { CorrectionRequestDialog } from "@/components/profile/CorrectionRequestDialog";
import { CorrectionRequestsList } from "@/components/profile/CorrectionRequestsList";

const tabs = [
  { label: "Personal Information", icon: UserRound },
  { label: "Employment", icon: BriefcaseBusiness },
  { label: "Account Settings", icon: Settings },
  { label: "Security", icon: ShieldCheck },
  { label: "Preferences", icon: LockKeyhole },
];

const personalInformation = [
  [
    { label: "Full Name", value: "Alex Santos" },
    { label: "Date of Birth", value: "January 15, 1996" },
    { label: "Gender", value: "Male" },
    { label: "Civil Status", value: "Single" },
  ],
  [
    { label: "Nationality", value: "Filipino" },
    { label: "Phone Number", value: "+63 912 345 6789" },
    { label: "Email Address", value: "alex.santos@example.edu" },
    {
      label: "Address",
      value:
        "123 Sampaguita Street, Brgy. San Isidro, Quezon City, Philippines 1100",
    },
  ],
];

const employmentInformation = [
  [
    { label: "Employee ID", value: "EMP-2023-0042" },
    { label: "Position", value: "System Administrator" },
    { label: "Department", value: "Information Technology Office" },
  ],
  [
    { label: "Employment Type", value: "Regular" },
    { label: "Date Hired", value: "June 1, 2023" },
    { label: "Status", value: "Active" },
  ],
];

function InformationCard({
  title,
  icon: Icon,
  onEdit,
  children,
}: {
  title: string;
  icon: typeof UserRound;
  onEdit: () => void;
  children: React.ReactNode;
}) {
  return (
    <section className="overflow-hidden rounded-2xl border bg-card">
      <div className="flex items-center justify-between border-b px-5 py-4">
        <div className="flex items-center gap-3">
          <Icon className="h-5 w-5 text-blue-600" />
          <h2 className="font-semibold">{title}</h2>
        </div>

        <Button variant="outline" size="sm" className="gap-2" onClick={onEdit}>
          <Pencil className="h-3.5 w-3.5" />
          Edit
        </Button>
      </div>

      <div className="p-5">{children}</div>
    </section>
  );
}

function InformationColumns({
  columns,
}: {
  columns: { label: string; value: string }[][];
}) {
  return (
    <div className="grid gap-x-8 gap-y-5 md:grid-cols-2">
      {columns.map((column, columnIndex) => (
        <dl key={columnIndex} className="space-y-5">
          {column.map((item) => (
            <div
              key={item.label}
              className="grid grid-cols-[minmax(105px,0.8fr)_minmax(0,1.2fr)] gap-3 text-sm"
            >
              <dt className="text-muted-foreground">{item.label}</dt>
              <dd className="break-words font-medium">{item.value}</dd>
            </div>
          ))}
        </dl>
      ))}
    </div>
  );
}

export function ProfilePage() {
  const [activeTab, setActiveTab] = useState("Personal Information");

  const [correctionDialogOpen, setCorrectionDialogOpen] = useState(false);
  const [correctionSection, setCorrectionSection] = useState<
    "Personal Information" | "Employment"
  >("Personal Information");

  const [requestsDialogOpen, setRequestsDialogOpen] = useState(false);

  function handleEdit(section: string) {
    if (
      section !== "Personal Information" &&
      section !== "Employment Information"
    ) {
      return;
    }

    setCorrectionSection(
      section === "Employment Information"
        ? "Employment"
        : "Personal Information",
    );

    setCorrectionDialogOpen(true);
  }

  return (
    <div className="mx-auto w-full max-w-[1600px] space-y-6">
      {/* Breadcrumb */}
      <div className="flex items-center gap-2 text-sm text-muted-foreground">
        <span>Home</span>
        <span>/</span>
        <span className="text-foreground">My Profile</span>
      </div>

      {/* Page heading */}
      <div>
        <h1 className="text-3xl font-bold tracking-tight">My Profile</h1>
        <p className="mt-1 text-sm text-muted-foreground md:text-base">
          View and manage your personal information, account settings, and
          preferences.
        </p>
      </div>

      {/* Profile overview */}
      <section className="relative isolate overflow-hidden rounded-2xl border bg-card">
        <div className="absolute inset-0 -z-10 bg-gradient-to-r from-blue-50 via-background to-sky-100 dark:from-blue-950/40 dark:via-background dark:to-slate-900" />

        <div className="flex flex-col gap-6 p-6 md:flex-row md:items-center md:justify-between md:p-8">
          <div className="flex flex-col gap-5 sm:flex-row sm:items-center">
            <div className="flex h-24 w-24 shrink-0 items-center justify-center rounded-full border-4 border-background bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300">
              <UserRound className="h-12 w-12" />
            </div>

            <div>
              <h2 className="text-2xl font-bold tracking-tight md:text-3xl">
                Alex Santos
              </h2>
              <p className="mt-1 font-medium">System Administrator</p>
              <p className="mt-1 text-sm text-muted-foreground">
                Information Technology Office
              </p>

              <span className="mt-3 inline-flex rounded-full bg-blue-100 px-3 py-1 text-xs font-medium text-blue-700 dark:bg-blue-950 dark:text-blue-300">
                Employee · Preview
              </span>
            </div>
          </div>

          <div className="grid gap-4 border-t pt-5 text-sm md:min-w-[300px] md:border-l md:border-t-0 md:pl-6 md:pt-0">
            <div className="flex items-start gap-3">
              <UserRound className="mt-0.5 h-4 w-4 shrink-0 text-blue-600" />
              <span className="break-all">alex.santos@example.edu</span>
            </div>

            <div className="flex items-start gap-3">
              <Building2 className="mt-0.5 h-4 w-4 shrink-0 text-blue-600" />
              <span>Information Technology Office</span>
            </div>

            <div className="flex items-start gap-3">
              <MapPin className="mt-0.5 h-4 w-4 shrink-0 text-blue-600" />
              <span>Main Campus · Preview</span>
            </div>
          </div>
        </div>
      </section>

      {/* Profile tabs */}
      <div className="overflow-x-auto border-b">
        <nav aria-label="Profile sections" className="flex min-w-max gap-2">
          {tabs.map(({ label, icon: Icon }) => {
            const isActive = activeTab === label;

            return (
              <button
                key={label}
                type="button"
                onClick={() => setActiveTab(label)}
                aria-current={isActive ? "page" : undefined}
                className={`flex items-center gap-2 border-b-2 px-4 py-3 text-sm font-medium transition-colors ${
                  isActive
                    ? "border-blue-600 text-blue-600 dark:text-blue-400"
                    : "border-transparent text-muted-foreground hover:text-foreground"
                }`}
              >
                <Icon className="h-4 w-4" />
                {label}
              </button>
            );
          })}
        </nav>
      </div>

      {/* Personal Information */}
      {activeTab === "Personal Information" && (
        <InformationCard
          title="Personal Information"
          icon={UserRound}
          onEdit={() => handleEdit("Personal Information")}
        >
          <InformationColumns columns={personalInformation} />
        </InformationCard>
      )}

      {/* Employment */}
      {activeTab === "Employment" && (
        <InformationCard
          title="Employment Information"
          icon={BriefcaseBusiness}
          onEdit={() => handleEdit("Employment Information")}
        >
          <InformationColumns columns={employmentInformation} />
        </InformationCard>
      )}

      {/* Account Settings */}
      {activeTab === "Account Settings" && <AccountSettings />}

      {/* Security */}
      {activeTab === "Security" && <SecuritySettings />}

      {/* Preferences */}
      {activeTab === "Preferences" && <PreferencesSettings />}

      {/* Quick actions and institution information */}
      <div className="grid gap-6 xl:grid-cols-2">
        <ProfileQuickActions />
        <InstitutionInformation />
      </div>

      {/* Correction request form */}
      <CorrectionRequestDialog
        open={correctionDialogOpen}
        section={correctionSection}
        onClose={() => setCorrectionDialogOpen(false)}
        onViewRequests={() => setRequestsDialogOpen(true)}
      />

      {/* My Correction Requests modal */}
      {requestsDialogOpen && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
          onMouseDown={(event) => {
            if (event.target === event.currentTarget) {
              setRequestsDialogOpen(false);
            }
          }}
        >
          <div
            role="dialog"
            aria-modal="true"
            aria-label="My Correction Requests"
            className="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-background shadow-xl"
          >
            <div className="flex items-center justify-between border-b px-5 py-4">
              <h2 className="font-semibold">My Correction Requests</h2>

              <button
                type="button"
                onClick={() => setRequestsDialogOpen(false)}
                className="rounded-lg px-3 py-1.5 text-sm text-muted-foreground hover:bg-muted hover:text-foreground"
              >
                Close
              </button>
            </div>

            <div className="p-4 sm:p-5">
              <CorrectionRequestsList />
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
