import { useState, type FormEvent } from "react";
import { Check, CheckCircle2, Copy, UserPlus, X } from "lucide-react";

import { Button } from "@/components/ui/button";
import { createIamUser, IamApiError, type IamPersonType } from "@/lib/iam";

interface CreateUserDialogProps {
  open: boolean;
  onClose: () => void;
  onCreated?: () => void;
}

interface CreateUserForm {
  username: string;
  first_name: string;
  middle_name: string;
  last_name: string;
  name_suffix: string;
  gender: string;
  email: string;
  types: IamPersonType[];
  student_number: string;
}

const initialForm: CreateUserForm = {
  username: "",
  first_name: "",
  middle_name: "",
  last_name: "",
  name_suffix: "",
  gender: "",
  email: "",
  types: [],
  student_number: "",
};

const personTypeOptions: {
  value: IamPersonType;
  label: string;
  description: string;
}[] = [
  {
    value: "employee",
    label: "Employee",
    description: "Faculty and staff members",
  },
  {
    value: "student",
    label: "Student",
    description: "Enrolled students",
  },
  {
    value: "parent_guardian",
    label: "Parent / Guardian",
    description: "Parents or legal guardians",
  },
  {
    value: "visitor",
    label: "Visitor",
    description: "Other institutional users",
  },
];

export function CreateUserDialog({
  open,
  onClose,
  onCreated,
}: CreateUserDialogProps) {
  const [form, setForm] = useState<CreateUserForm>(initialForm);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [submitError, setSubmitError] = useState("");
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [createdAccount, setCreatedAccount] = useState<{
    username: string;
    initialPassword: string;
  } | null>(null);
  const [copied, setCopied] = useState(false);

  if (!open) {
    return null;
  }

  function updateField(field: keyof CreateUserForm, value: string) {
    setForm((current) => ({ ...current, [field]: value }));

    setFieldErrors((current) => {
      const next = { ...current };
      delete next[field];
      return next;
    });

    setSubmitError("");
  }

  function togglePersonType(type: IamPersonType) {
    setForm((current) => ({
      ...current,
      types: current.types.includes(type)
        ? current.types.filter((item) => item !== type)
        : [...current.types, type],
    }));

    setFieldErrors((current) => {
      const next = { ...current };
      delete next.types;
      return next;
    });

    setSubmitError("");
  }

  function handleClose() {
    setForm(initialForm);
    setFieldErrors({});
    setSubmitError("");
    setCreatedAccount(null);
    setCopied(false);
    setIsSubmitting(false);
    onClose();
  }

    async function handleCopyPassword() {
    if (!createdAccount) return;

    const password = createdAccount.initialPassword;

    try {
        if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(password);
        } else {
        throw new Error("Clipboard API unavailable");
        }

        setCopied(true);
        setSubmitError("");
    } catch {
        try {
        const temporaryInput = document.createElement("textarea");

        temporaryInput.value = password;
        temporaryInput.style.position = "fixed";
        temporaryInput.style.opacity = "0";
        temporaryInput.style.pointerEvents = "none";

        document.body.appendChild(temporaryInput);
        temporaryInput.focus();
        temporaryInput.select();

        const successful = document.execCommand("copy");

        document.body.removeChild(temporaryInput);

        if (!successful) {
            throw new Error("Copy command failed");
        }

        setCopied(true);
        setSubmitError("");
        } catch {
        setSubmitError(
            "Automatic copying is unavailable in this browser. Please select and copy the password.",
        );
        }
    }
    }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    setFieldErrors({});
    setSubmitError("");

    if (form.types.length === 0) {
      setFieldErrors({
        types: "Select at least one person type.",
      });
      return;
    }

    if (form.types.includes("student") && !form.student_number.trim()) {
      setFieldErrors({
        student_number: "Student number is required for student accounts.",
      });
      return;
    }

    setIsSubmitting(true);

    try {
      const response = await createIamUser({
        username: form.username.trim(),
        first_name: form.first_name.trim(),
        middle_name: form.middle_name.trim() || null,
        last_name: form.last_name.trim(),
        name_suffix: form.name_suffix.trim() || null,
        gender: form.gender.trim() || null,
        email: form.email.trim() || null,
        types: form.types,
        student_number: form.student_number.trim() || null,
      });

      setCreatedAccount({
        username: response.user.username,
        initialPassword: response.initial_password,
      });

      onCreated?.();
    } catch (error) {
      if (error instanceof IamApiError) {
        if (error.status === 422 && error.errors) {
          const mappedErrors: Record<string, string> = {};

          Object.entries(error.errors).forEach(([key, messages]) => {
            const field = key.startsWith("types.") ? "types" : key;

            mappedErrors[field] = messages[0] ?? "Invalid value.";
          });

          setFieldErrors(mappedErrors);
        } else {
          setSubmitError(error.message);
        }
      } else {
        setSubmitError(
          "Unable to create the account. Please check your connection and try again.",
        );
      }
    } finally {
      setIsSubmitting(false);
    }
  }

  const inputClass =
    "w-full rounded-lg border bg-background px-3 py-2.5 text-sm outline-none transition-colors focus:border-blue-500 focus:ring-2 focus:ring-blue-500/10";

  const labelClass = "text-sm font-medium";

  if (createdAccount) {
    return (
      <div
        className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
        onMouseDown={(event) => {
          if (event.target === event.currentTarget) {
            handleClose();
          }
        }}
      >
        <section
          role="dialog"
          aria-modal="true"
          aria-labelledby="create-user-success-title"
          className="w-full max-w-md overflow-hidden rounded-2xl border bg-card shadow-xl"
        >
          <div className="flex justify-end border-b px-5 py-3">
            <button
              type="button"
              onClick={handleClose}
              aria-label="Close"
              className="rounded-lg p-2 text-muted-foreground hover:bg-muted"
            >
              <X className="h-4 w-4" />
            </button>
          </div>

          <div className="space-y-5 p-6">
            <div className="text-center">
              <CheckCircle2 className="mx-auto h-14 w-14 text-emerald-600" />
              <h2
                id="create-user-success-title"
                className="mt-4 text-xl font-semibold"
              >
                Account created successfully
              </h2>
              <p className="mt-2 text-sm text-muted-foreground">
                The account for{" "}
                <strong className="text-foreground">
                  @{createdAccount.username}
                </strong>{" "}
                is ready.
              </p>
            </div>

            <div className="space-y-3 rounded-xl border bg-muted/30 p-4">
              <div>
                <p className="text-sm font-medium">
                  Initial temporary password
                </p>
                <p className="mt-1 text-xs text-muted-foreground">
                  This password will only be shown here once.
                </p>
              </div>

              <div className="flex items-center gap-2">
                <code className="min-w-0 flex-1 break-all rounded-lg border bg-background px-3 py-3 text-sm font-semibold">
                  {createdAccount.initialPassword}
                </code>

                <Button
                  type="button"
                  size="icon"
                  variant="outline"
                  onClick={handleCopyPassword}
                  aria-label="Copy initial password"
                >
                  {copied ? (
                    <Check className="h-4 w-4 text-emerald-600" />
                  ) : (
                    <Copy className="h-4 w-4" />
                  )}
                </Button>
              </div>

              {copied && (
                <p className="text-xs text-emerald-600">
                  Password copied to clipboard.
                </p>
              )}
            </div>

            <div className="rounded-lg bg-blue-50 p-3 text-sm text-blue-800 dark:bg-blue-950/40 dark:text-blue-200">
              Give this password to the account holder securely. They must
              change it when they sign in for the first time.
            </div>

            {submitError && (
              <p role="alert" className="text-sm text-destructive">
                {submitError}
              </p>
            )}

            <Button className="w-full" onClick={handleClose}>
              Done
            </Button>
          </div>
        </section>
      </div>
    );
  }

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
      onMouseDown={(event) => {
        if (event.target === event.currentTarget && !isSubmitting) {
          handleClose();
        }
      }}
    >
      <section
        role="dialog"
        aria-modal="true"
        aria-labelledby="create-user-title"
        className="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl border bg-card shadow-xl"
      >
        <div className="flex items-start justify-between border-b px-5 py-4">
          <div className="flex items-start gap-3">
            <UserPlus className="mt-1 h-5 w-5 text-blue-600" />
            <div>
              <h2 id="create-user-title" className="font-semibold">
                Create User
              </h2>
              <p className="mt-1 text-sm text-muted-foreground">
                Create a new Concord account and assign its institutional
                identity.
              </p>
            </div>
          </div>

          <button
            type="button"
            onClick={handleClose}
            disabled={isSubmitting}
            aria-label="Close create user dialog"
            className="rounded-lg p-2 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground disabled:opacity-50"
          >
            <X className="h-4 w-4" />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="space-y-6 p-5">
          {submitError && (
            <div
              role="alert"
              className="rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive"
            >
              {submitError}
            </div>
          )}

          <section className="space-y-4">
            <div>
              <h3 className="text-sm font-semibold">Account Information</h3>
              <p className="mt-1 text-xs text-muted-foreground">
                The username is used to sign in to Concord.
              </p>
            </div>

            <div className="space-y-2">
              <label htmlFor="create-username" className={labelClass}>
                Username <span className="text-destructive">*</span>
              </label>
              <input
                id="create-username"
                required
                maxLength={100}
                autoComplete="off"
                value={form.username}
                onChange={(event) =>
                  updateField("username", event.target.value)
                }
                className={inputClass}
                placeholder="e.g. alex.santos"
              />
              {fieldErrors.username && (
                <p className="text-xs text-destructive">
                  {fieldErrors.username}
                </p>
              )}
            </div>
          </section>

          <section className="space-y-4">
            <h3 className="text-sm font-semibold">Personal Information</h3>

            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <label htmlFor="create-first-name" className={labelClass}>
                  First Name <span className="text-destructive">*</span>
                </label>
                <input
                  id="create-first-name"
                  required
                  maxLength={100}
                  value={form.first_name}
                  onChange={(event) =>
                    updateField("first_name", event.target.value)
                  }
                  className={inputClass}
                />
                {fieldErrors.first_name && (
                  <p className="text-xs text-destructive">
                    {fieldErrors.first_name}
                  </p>
                )}
              </div>

              <div className="space-y-2">
                <label htmlFor="create-middle-name" className={labelClass}>
                  Middle Name
                </label>
                <input
                  id="create-middle-name"
                  maxLength={100}
                  value={form.middle_name}
                  onChange={(event) =>
                    updateField("middle_name", event.target.value)
                  }
                  className={inputClass}
                />
                {fieldErrors.middle_name && (
                  <p className="text-xs text-destructive">
                    {fieldErrors.middle_name}
                  </p>
                )}
              </div>

              <div className="space-y-2">
                <label htmlFor="create-last-name" className={labelClass}>
                  Last Name <span className="text-destructive">*</span>
                </label>
                <input
                  id="create-last-name"
                  required
                  maxLength={100}
                  value={form.last_name}
                  onChange={(event) =>
                    updateField("last_name", event.target.value)
                  }
                  className={inputClass}
                />
                {fieldErrors.last_name && (
                  <p className="text-xs text-destructive">
                    {fieldErrors.last_name}
                  </p>
                )}
              </div>

              <div className="space-y-2">
                <label htmlFor="create-name-suffix" className={labelClass}>
                  Name Suffix
                </label>
                <input
                  id="create-name-suffix"
                  maxLength={30}
                  value={form.name_suffix}
                  onChange={(event) =>
                    updateField("name_suffix", event.target.value)
                  }
                  className={inputClass}
                  placeholder="e.g. Jr., III"
                />
                {fieldErrors.name_suffix && (
                  <p className="text-xs text-destructive">
                    {fieldErrors.name_suffix}
                  </p>
                )}
              </div>

              <div className="space-y-2">
                <label htmlFor="create-gender" className={labelClass}>
                  Gender
                </label>
                <select
                  id="create-gender"
                  value={form.gender}
                  onChange={(event) =>
                    updateField("gender", event.target.value)
                  }
                  className={inputClass}
                >
                  <option value="">Select gender</option>
                  <option value="male">Male</option>
                  <option value="female">Female</option>
                </select>
                {fieldErrors.gender && (
                  <p className="text-xs text-destructive">
                    {fieldErrors.gender}
                  </p>
                )}
              </div>

              <div className="space-y-2">
                <label htmlFor="create-email" className={labelClass}>
                  Email Address
                </label>
                <input
                  id="create-email"
                  type="email"
                  maxLength={255}
                  value={form.email}
                  onChange={(event) => updateField("email", event.target.value)}
                  className={inputClass}
                  placeholder="name@example.com"
                />
                {fieldErrors.email && (
                  <p className="text-xs text-destructive">
                    {fieldErrors.email}
                  </p>
                )}
              </div>
            </div>
          </section>

          <section className="space-y-3">
            <div>
              <h3 className="text-sm font-semibold">
                Institutional Person Type{" "}
                <span className="text-destructive">*</span>
              </h3>
              <p className="mt-1 text-xs text-muted-foreground">
                Select one or more types. A person may have multiple
                institutional identities.
              </p>
            </div>

            <div className="grid gap-3 sm:grid-cols-2">
              {personTypeOptions.map((option) => {
                const checked = form.types.includes(option.value);

                return (
                  <label
                    key={option.value}
                    className={`flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition-colors ${
                      checked
                        ? "border-blue-500 bg-blue-50/70 dark:bg-blue-950/30"
                        : "hover:bg-muted/50"
                    }`}
                  >
                    <input
                      type="checkbox"
                      checked={checked}
                      onChange={() => togglePersonType(option.value)}
                      className="mt-1 h-4 w-4 accent-blue-600"
                    />
                    <span className="min-w-0">
                      <span className="block text-sm font-medium">
                        {option.label}
                      </span>
                      <span className="mt-1 block text-xs text-muted-foreground">
                        {option.description}
                      </span>
                    </span>
                  </label>
                );
              })}
            </div>

            {fieldErrors.types && (
              <p className="text-xs text-destructive">{fieldErrors.types}</p>
            )}
          </section>

          <section className="space-y-2">
            <label htmlFor="create-student-number" className={labelClass}>
              Student Number
              {form.types.includes("student") && (
                <span className="text-destructive"> *</span>
              )}
            </label>
            <input
              id="create-student-number"
              maxLength={64}
              required={form.types.includes("student")}
              value={form.student_number}
              onChange={(event) =>
                updateField("student_number", event.target.value)
              }
              className={inputClass}
              placeholder="Required when Student is selected"
            />
            {fieldErrors.student_number && (
              <p className="text-xs text-destructive">
                {fieldErrors.student_number}
              </p>
            )}
            <p className="text-xs text-muted-foreground">
              If this person has a student identity, enter their student number.
              The value is retained if Student is temporarily deselected.
            </p>
          </section>

          <div className="flex flex-col-reverse gap-3 border-t pt-4 sm:flex-row sm:justify-end">
            <Button
              type="button"
              variant="outline"
              onClick={handleClose}
              disabled={isSubmitting}
            >
              Cancel
            </Button>

            <Button type="submit" disabled={isSubmitting}>
              {isSubmitting ? "Creating Account..." : "Create Account"}
            </Button>
          </div>
        </form>
      </section>
    </div>
  );
}
