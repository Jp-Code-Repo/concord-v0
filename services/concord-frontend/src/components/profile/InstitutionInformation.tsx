import { Building2, MapPin, ShieldCheck } from "lucide-react";

const institutionDetails = [
  {
    label: "Institution",
    value: "Tay Tung School",
    icon: Building2,
  },
  {
    label: "Primary Office",
    value: "Information Technology Office",
    icon: MapPin,
  },
  {
    label: "Account Status",
    value: "Active · Preview",
    icon: ShieldCheck,
  },
];

export function InstitutionInformation() {
  return (
    <section className="rounded-2xl border bg-card">
      <div className="border-b px-5 py-4">
        <h2 className="font-semibold">Institution Information</h2>
        <p className="mt-1 text-sm text-muted-foreground">
          Your institution, primary office assignment, and account status.
        </p>
      </div>

      <div className="grid gap-5 p-5 sm:grid-cols-2">
        {institutionDetails.map(({ label, value, icon: Icon }) => (
          <div key={label} className="flex items-start gap-3">
            <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300">
              <Icon className="h-5 w-5" />
            </span>

            <div className="min-w-0">
              <p className="text-xs text-muted-foreground">{label}</p>
              <p className="mt-1 break-words text-sm font-medium">{value}</p>
            </div>
          </div>
        ))}
      </div>
    </section>
  );
}
