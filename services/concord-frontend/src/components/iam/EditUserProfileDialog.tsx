import { useEffect, useState } from "react";
import { X } from "lucide-react";

import { Button } from "@/components/ui/button";
import {
  IamApiError,
  updateIamUser,
  type IamPersonType,
  type IamUserDetail,
  type IamUpdateUserPayload,
} from "@/lib/iam";

interface EditUserProfileDialogProps {
  detail: IamUserDetail;
  onClose: () => void;
  onSaved: () => void;
}

const personTypeOptions: { value: IamPersonType; label: string }[] = [
  { value: "employee", label: "Employee" },
  { value: "student", label: "Student" },
  { value: "parent_guardian", label: "Parent / Guardian" },
  { value: "visitor", label: "Visitor" },
];

function isPersonType(value: string): value is IamPersonType {
  return personTypeOptions.some((option) => option.value === value);
}

export function EditUserProfileDialog({
  detail,
  onClose,
  onSaved,
}: EditUserProfileDialogProps) {
  const person = detail.person;

  const [username, setUsername] = useState(detail.user.username);
  const [firstName, setFirstName] = useState(person?.first_name ?? "");
  const [middleName, setMiddleName] = useState(person?.middle_name ?? "");
  const [lastName, setLastName] = useState(person?.last_name ?? "");
  const [nameSuffix, setNameSuffix] = useState(person?.name_suffix ?? "");
  const [gender, setGender] = useState(person?.gender ?? "");
  const [email, setEmail] = useState(person?.email ?? "");
  const [types, setTypes] = useState<IamPersonType[]>(
    (person?.types ?? []).filter(isPersonType),
  );
  const [studentNumber, setStudentNumber] = useState(
    person?.student_number ?? "",
  );

  const [errors, setErrors] = useState<Record<string, string>>({});
  const [generalError, setGeneralError] = useState("");
  const [saving, setSaving] = useState(false);

  const isStudent = types.includes("student");

  useEffect(() => {
    function handleEscape(event: KeyboardEvent) {
      if (event.key === "Escape" && !saving) onClose();
    }

    window.addEventListener("keydown", handleEscape);
    return () => window.removeEventListener("keydown", handleEscape);
  }, [onClose, saving]);

  function toggleType(type: IamPersonType) {
    setTypes((current) =>
      current.includes(type)
        ? current.filter((item) => item !== type)
        : [...current, type],
    );
  }

  function fieldError(field: string) {
    return errors[field];
  }

  async function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setErrors({});
    setGeneralError("");

    const nextErrors: Record<string, string> = {};

    if (!username.trim()) {
      nextErrors.username = "Username is required.";
    }

    if (person) {
      if (!firstName.trim()) {
        nextErrors.first_name = "First name is required.";
      }

      if (!lastName.trim()) {
        nextErrors.last_name = "Last name is required.";
      }

      if (types.length === 0) {
        nextErrors.types = "Select at least one person type.";
      }

      if (isStudent && !studentNumber.trim()) {
        nextErrors.student_number =
          "Student number is required for student accounts.";
      }
    }

    if (Object.keys(nextErrors).length > 0) {
      setErrors(nextErrors);
      return;
    }

    const payload: IamUpdateUserPayload = {
      username: username.trim(),
    };

    if (person) {
      Object.assign(payload, {
        first_name: firstName.trim(),
        middle_name: middleName.trim() || null,
        last_name: lastName.trim(),
        name_suffix: nameSuffix.trim() || null,
        gender: gender || null,
        email: email.trim() || null,
        types,
        ...(isStudent ? { student_number: studentNumber.trim() } : {}),
      });
    }

    setSaving(true);

    try {
      await updateIamUser(detail.user.id, payload);
      onSaved();
      onClose();
    } catch (error) {
      if (error instanceof IamApiError) {
        if (error.errors) {
          const mappedErrors: Record<string, string> = {};

          Object.entries(error.errors).forEach(([field, messages]) => {
            const normalizedField = field.split(".")[0];
            mappedErrors[normalizedField] = messages[0];
          });

          setErrors(mappedErrors);
        }

        setGeneralError(error.message);
      } else {
        setGeneralError("Unable to update this user. Please try again.");
      }
    } finally {
      setSaving(false);
    }
  }

  const inputClass =
    "mt-1 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20";

  function renderFieldError(field: string) {
    const message = fieldError(field);

    return message ? (
      <p className="mt-1 text-xs text-destructive">{message}</p>
    ) : null;
  }

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
      onMouseDown={(event) => {
        if (event.target === event.currentTarget && !saving) onClose();
      }}
    >
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="edit-user-title"
        className="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-xl border bg-background shadow-xl"
      >
        <div className="flex items-center justify-between border-b px-6 py-4">
          <div>
            <h2 id="edit-user-title" className="text-lg font-semibold">
              Edit User Profile
            </h2>
            <p className="mt-1 text-sm text-muted-foreground">
              Update account and personal information.
            </p>
          </div>

          <Button
            type="button"
            variant="ghost"
            size="icon"
            onClick={onClose}
            disabled={saving}
            aria-label="Close dialog"
          >
            <X className="h-4 w-4" />
          </Button>
        </div>

        <form onSubmit={handleSubmit} className="overflow-y-auto">
          <div className="space-y-6 p-6">
            {generalError && (
              <div className="rounded-lg border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive">
                {generalError}
              </div>
            )}

            <section className="space-y-4">
              <div>
                <h3 className="text-sm font-semibold">Account Information</h3>
                <p className="mt-1 text-xs text-muted-foreground">
                  Login credentials and account identification.
                </p>
              </div>

              <div>
                <label className="text-sm font-medium" htmlFor="edit-username">
                  Username
                </label>
                <input
                  id="edit-username"
                  className={inputClass}
                  value={username}
                  onChange={(event) => setUsername(event.target.value)}
                  maxLength={100}
                  required
                />
                {renderFieldError("username")}
              </div>
            </section>

            {person ? (
              <>
                <div className="border-t" />

                <section className="space-y-4">
                  <div>
                    <h3 className="text-sm font-semibold">
                      Personal Information
                    </h3>
                    <p className="mt-1 text-xs text-muted-foreground">
                      Basic information associated with this account.
                    </p>
                  </div>

                  <div className="grid gap-4 sm:grid-cols-2">
                    <div>
                      <label
                        className="text-sm font-medium"
                        htmlFor="edit-first-name"
                      >
                        First Name
                      </label>
                      <input
                        id="edit-first-name"
                        className={inputClass}
                        value={firstName}
                        onChange={(event) => setFirstName(event.target.value)}
                        maxLength={100}
                        required
                      />
                      {renderFieldError("first_name")}
                    </div>

                    <div>
                      <label
                        className="text-sm font-medium"
                        htmlFor="edit-middle-name"
                      >
                        Middle Name
                      </label>
                      <input
                        id="edit-middle-name"
                        className={inputClass}
                        value={middleName}
                        onChange={(event) => setMiddleName(event.target.value)}
                        maxLength={100}
                      />
                      {renderFieldError("middle_name")}
                    </div>

                    <div>
                      <label
                        className="text-sm font-medium"
                        htmlFor="edit-last-name"
                      >
                        Last Name
                      </label>
                      <input
                        id="edit-last-name"
                        className={inputClass}
                        value={lastName}
                        onChange={(event) => setLastName(event.target.value)}
                        maxLength={100}
                        required
                      />
                      {renderFieldError("last_name")}
                    </div>

                    <div>
                      <label
                        className="text-sm font-medium"
                        htmlFor="edit-suffix"
                      >
                        Name Suffix
                      </label>
                      <input
                        id="edit-suffix"
                        className={inputClass}
                        value={nameSuffix}
                        onChange={(event) => setNameSuffix(event.target.value)}
                        maxLength={30}
                        placeholder="Jr., Sr., III..."
                      />
                      {renderFieldError("name_suffix")}
                    </div>

                    <div>
                      <label
                        className="text-sm font-medium"
                        htmlFor="edit-gender"
                      >
                        Gender
                      </label>
                      <select
                        id="edit-gender"
                        className={inputClass}
                        value={gender}
                        onChange={(event) => setGender(event.target.value)}
                      >
                        <option value="">Not specified</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                      </select>
                      {renderFieldError("gender")}
                    </div>

                    <div>
                      <label
                        className="text-sm font-medium"
                        htmlFor="edit-email"
                      >
                        Email Address
                      </label>
                      <input
                        id="edit-email"
                        type="email"
                        className={inputClass}
                        value={email}
                        onChange={(event) => setEmail(event.target.value)}
                        maxLength={255}
                      />
                      {renderFieldError("email")}
                    </div>
                  </div>
                </section>

                <div className="border-t" />

                <section className="space-y-4">
                  <div>
                    <h3 className="text-sm font-semibold">
                      Institutional Person Types
                    </h3>
                    <p className="mt-1 text-xs text-muted-foreground">
                      A person may have more than one institutional role.
                    </p>
                  </div>

                  <div className="grid gap-3 sm:grid-cols-2">
                    {personTypeOptions.map((option) => (
                      <label
                        key={option.value}
                        className="flex cursor-pointer items-center gap-3 rounded-lg border p-3 text-sm transition hover:bg-muted/40"
                      >
                        <input
                          type="checkbox"
                          checked={types.includes(option.value)}
                          onChange={() => toggleType(option.value)}
                          className="h-4 w-4 rounded border-input accent-primary"
                        />
                        {option.label}
                      </label>
                    ))}
                  </div>
                  {renderFieldError("types")}

                  {isStudent && (
                    <div>
                      <label
                        className="text-sm font-medium"
                        htmlFor="edit-student-number"
                      >
                        Student Number
                      </label>
                      <input
                        id="edit-student-number"
                        className={inputClass}
                        value={studentNumber}
                        onChange={(event) =>
                          setStudentNumber(event.target.value)
                        }
                        maxLength={64}
                        required
                      />
                      {renderFieldError("student_number")}
                    </div>
                  )}
                </section>
              </>
            ) : (
              <div className="rounded-lg border border-amber-500/30 bg-amber-500/5 px-4 py-3 text-sm text-muted-foreground">
                Personal information is unavailable for this account. Only the
                username can be updated here.
              </div>
            )}
          </div>

          <div className="flex justify-end gap-2 border-t bg-muted/20 px-6 py-4">
            <Button
              type="button"
              variant="outline"
              onClick={onClose}
              disabled={saving}
            >
              Cancel
            </Button>

            <Button type="submit" disabled={saving}>
              {saving ? "Saving..." : "Save Changes"}
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
}
