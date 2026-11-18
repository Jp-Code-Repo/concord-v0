import { useMemo, useState, type FormEvent } from "react";
import { AppWindow, Check, Search, X } from "lucide-react";
import { toast } from "sonner";

import { Button } from "@/components/ui/button";
import { createIamLegacyApp, IamApiError } from "@/lib/iam";
import { legacyAppIconOptions } from "@/lib/legacy-app-icons";

interface CreateLegacyAppDialogProps {
  open: boolean;
  onClose: () => void;
  onCreated?: () => void;
}

interface FormData {
  name: string;
  url: string;
  icon: string;
  description: string;
  sort_order: string;
}

const initialForm: FormData = {
  name: "",
  url: "",
  icon: "",
  description: "",
  sort_order: "0",
};

export function CreateLegacyAppDialog({
  open,
  onClose,
  onCreated,
}: CreateLegacyAppDialogProps) {
  const [form, setForm] = useState<FormData>(initialForm);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [submitError, setSubmitError] = useState("");
  const [isSubmitting, setIsSubmitting] = useState(false);

  const [iconSearch, setIconSearch] = useState("");
  const [iconCategory, setIconCategory] = useState("All");

  const categories = useMemo(
    () => [
      "All",
      ...Array.from(
        new Set(legacyAppIconOptions.map((option) => option.category)),
      ),
    ],
    [],
  );

  const filteredIcons = useMemo(() => {
    const search = iconSearch.trim().toLowerCase();

    return legacyAppIconOptions.filter((option) => {
      const matchesCategory =
        iconCategory === "All" || option.category === iconCategory;

      const matchesSearch =
        !search ||
        option.label.toLowerCase().includes(search) ||
        option.value.toLowerCase().includes(search);

      return matchesCategory && matchesSearch;
    });
  }, [iconSearch, iconCategory]);

  if (!open) return null;

  function updateField(field: keyof FormData, value: string) {
    setForm((current) => ({
      ...current,
      [field]: value,
    }));

    setFieldErrors((current) => {
      const next = { ...current };
      delete next[field];
      return next;
    });

    setSubmitError("");
  }

  function handleClose() {
    if (isSubmitting) return;

    setForm(initialForm);
    setFieldErrors({});
    setSubmitError("");
    setIconSearch("");
    setIconCategory("All");
    onClose();
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    setFieldErrors({});
    setSubmitError("");

    const sortOrder = Number(form.sort_order);

    if (
      !Number.isInteger(sortOrder) ||
      sortOrder < 0 ||
      sortOrder > 4294967295
    ) {
      setFieldErrors({
        sort_order: "Enter a valid non-negative integer.",
      });
      return;
    }

    setIsSubmitting(true);

    try {
      await createIamLegacyApp({
        name: form.name.trim(),
        url: form.url.trim(),
        icon: form.icon.trim() || null,
        description: form.description.trim() || null,
        sort_order: sortOrder,
      });

      toast.success("Application registered successfully.");

      setForm(initialForm);
      setFieldErrors({});
      setIconSearch("");
      setIconCategory("All");
      onCreated?.();
      onClose();
    } catch (error) {
      if (error instanceof IamApiError) {
        if (error.status === 422 && error.errors) {
          const mappedErrors: Record<string, string> = {};

          Object.entries(error.errors).forEach(([key, messages]) => {
            mappedErrors[key] = messages[0] ?? "Invalid value.";
          });

          setFieldErrors(mappedErrors);
        } else {
          setSubmitError(error.message);
        }
      } else {
        setSubmitError(
          "Unable to register the application. Please check your connection and try again.",
        );
      }
    } finally {
      setIsSubmitting(false);
    }
  }

  const inputClass =
    "w-full rounded-lg border bg-background px-3 py-2.5 text-sm outline-none transition-colors focus:border-blue-500 focus:ring-2 focus:ring-blue-500/10";

  function renderFieldError(field: string) {
    return fieldErrors[field] ? (
      <p className="mt-1 text-xs text-destructive">{fieldErrors[field]}</p>
    ) : null;
  }

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
        aria-labelledby="create-legacy-app-title"
        className="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-xl border bg-background shadow-xl"
      >
        <div className="flex items-center justify-between border-b px-6 py-4">
          <div>
            <div className="flex items-center gap-2">
              <AppWindow className="h-5 w-5 text-muted-foreground" />

              <h2
                id="create-legacy-app-title"
                className="text-lg font-semibold"
              >
                Register Application
              </h2>
            </div>

            <p className="mt-1 text-sm text-muted-foreground">
              Add a new application to the Concord directory.
            </p>
          </div>

          <Button
            type="button"
            variant="ghost"
            size="icon"
            onClick={handleClose}
            disabled={isSubmitting}
            aria-label="Close dialog"
          >
            <X className="h-4 w-4" />
          </Button>
        </div>

        <form onSubmit={handleSubmit} className="overflow-y-auto">
          <div className="space-y-5 p-6">
            {submitError && (
              <div
                role="alert"
                className="rounded-lg border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive"
              >
                {submitError}
              </div>
            )}

            <div>
              <label htmlFor="legacy-app-name" className="text-sm font-medium">
                Application Name *
              </label>

              <input
                id="legacy-app-name"
                className={inputClass}
                value={form.name}
                onChange={(event) => updateField("name", event.target.value)}
                maxLength={150}
                placeholder="e.g. Student Portal"
                required
                autoFocus
              />

              {renderFieldError("name")}
            </div>

            <div>
              <label htmlFor="legacy-app-url" className="text-sm font-medium">
                Application URL *
              </label>

              <input
                id="legacy-app-url"
                type="url"
                className={inputClass}
                value={form.url}
                onChange={(event) => updateField("url", event.target.value)}
                maxLength={2048}
                placeholder="https://example.com"
                required
              />

              {renderFieldError("url")}
            </div>

            <div className="space-y-3">
              <div>
                <label className="text-sm font-medium">Application Icon</label>

                <p className="mt-1 text-xs text-muted-foreground">
                  Choose an icon that represents this application.
                </p>
              </div>

              <div className="flex flex-col gap-3 sm:flex-row">
                <div className="relative flex-1">
                  <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />

                  <input
                    type="search"
                    value={iconSearch}
                    onChange={(event) => setIconSearch(event.target.value)}
                    placeholder="Search icons..."
                    className={`${inputClass} pl-9`}
                    aria-label="Search application icons"
                  />
                </div>

                <select
                  value={iconCategory}
                  onChange={(event) => setIconCategory(event.target.value)}
                  className={`${inputClass} sm:max-w-48`}
                  aria-label="Filter icons by category"
                >
                  {categories.map((category) => (
                    <option key={category} value={category}>
                      {category === "All" ? "All categories" : category}
                    </option>
                  ))}
                </select>
              </div>

              <div className="max-h-64 overflow-y-auto rounded-lg border p-3">
                <div className="grid grid-cols-3 gap-2 sm:grid-cols-4">
                  <button
                    type="button"
                    onClick={() => updateField("icon", "")}
                    aria-pressed={!form.icon}
                    className={`flex min-h-20 flex-col items-center justify-center gap-2 rounded-lg border p-2 text-center transition-colors ${
                      !form.icon
                        ? "border-primary bg-primary/5"
                        : "border-transparent hover:border-border hover:bg-muted/50"
                    }`}
                  >
                    <X className="h-5 w-5 text-muted-foreground" />
                    <span className="text-xs">No icon</span>
                  </button>

                  {filteredIcons.map((option) => {
                    const Icon = option.icon;
                    const selected = form.icon === option.value;

                    return (
                      <button
                        key={option.value}
                        type="button"
                        onClick={() => updateField("icon", option.value)}
                        aria-pressed={selected}
                        title={`${option.label} (${option.value})`}
                        className={`relative flex min-h-20 flex-col items-center justify-center gap-2 rounded-lg border p-2 text-center transition-colors ${
                          selected
                            ? "border-primary bg-primary/5"
                            : "border-transparent hover:border-border hover:bg-muted/50"
                        }`}
                      >
                        {selected && (
                          <Check className="absolute right-1 top-1 h-3.5 w-3.5 text-primary" />
                        )}

                        <Icon className="h-5 w-5" />

                        <span className="text-xs leading-tight">
                          {option.label}
                        </span>
                      </button>
                    );
                  })}
                </div>

                {filteredIcons.length === 0 && (
                  <p className="py-8 text-center text-sm text-muted-foreground">
                    No icons found. Try another search.
                  </p>
                )}
              </div>

              <p className="text-xs text-muted-foreground">
                Selected identifier:{" "}
                <span className="font-mono font-medium text-foreground">
                  {form.icon || "None"}
                </span>
              </p>

              {renderFieldError("icon")}
            </div>

            <div>
              <label
                htmlFor="legacy-app-description"
                className="text-sm font-medium"
              >
                Description
              </label>

              <textarea
                id="legacy-app-description"
                className={`${inputClass} min-h-24 resize-y`}
                value={form.description}
                onChange={(event) =>
                  updateField("description", event.target.value)
                }
                placeholder="Briefly describe this application..."
                rows={3}
              />

              {renderFieldError("description")}
            </div>

            <div>
              <label
                htmlFor="legacy-app-sort-order"
                className="text-sm font-medium"
              >
                Display Order
              </label>

              <input
                id="legacy-app-sort-order"
                type="number"
                min={0}
                max={4294967295}
                step={1}
                className={inputClass}
                value={form.sort_order}
                onChange={(event) =>
                  updateField("sort_order", event.target.value)
                }
              />

              <p className="mt-1 text-xs text-muted-foreground">
                Lower numbers appear first in the directory.
              </p>

              {renderFieldError("sort_order")}
            </div>
          </div>

          <div className="flex justify-end gap-2 border-t bg-muted/20 px-6 py-4">
            <Button
              type="button"
              variant="outline"
              onClick={handleClose}
              disabled={isSubmitting}
            >
              Cancel
            </Button>

            <Button type="submit" disabled={isSubmitting}>
              {isSubmitting ? "Registering..." : "Register Application"}
            </Button>
          </div>
        </form>
      </section>
    </div>
  );
}
