import { useState } from "react";
import { CheckCircle2, ClipboardEdit, ClipboardList, X } from "lucide-react";

import { Button } from "@/components/ui/button";
import { useCorrectionRequests } from "@/contexts/CorrectionRequestContext";

interface CorrectionRequestDialogProps {
  open: boolean;
  section: "Personal Information" | "Employment";
  onClose: () => void;
  onViewRequests?: () => void;
}

export function CorrectionRequestDialog({
  open,
  section,
  onClose,
  onViewRequests,
}: CorrectionRequestDialogProps) {
  const [field, setField] = useState("");
  const [proposedValue, setProposedValue] = useState("");
  const [reason, setReason] = useState("");
  const [submittedReference, setSubmittedReference] = useState<string | null>(
    null,
  );

  const { addRequest } = useCorrectionRequests();

  if (!open) {
    return null;
  }

  const fields =
    section === "Personal Information"
      ? [
          "Full Name",
          "Date of Birth",
          "Gender",
          "Civil Status",
          "Nationality",
          "Phone Number",
          "Address",
        ]
      : [
          "Employee ID",
          "Position",
          "Department",
          "Employment Type",
          "Date Hired",
          "Employment Status",
        ];

  function handleClose() {
    setField("");
    setProposedValue("");
    setReason("");
    setSubmittedReference(null);
    onClose();
  }

  function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (!field || !proposedValue.trim() || !reason.trim()) {
      return;
    }

    const reference = addRequest({
      section,
      field,
      proposedValue: proposedValue.trim(),
      reason: reason.trim(),
    });

    setSubmittedReference(reference);
  }

  function handleViewRequests() {
    handleClose();
    onViewRequests?.();
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
        aria-labelledby="correction-dialog-title"
        className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl border bg-card shadow-xl"
      >
        {submittedReference ? (
          <div className="space-y-5 p-6">
            <div className="flex justify-end">
              <button
                type="button"
                onClick={handleClose}
                aria-label="Close confirmation"
                className="rounded-lg p-2 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <div className="text-center">
              <CheckCircle2 className="mx-auto h-14 w-14 text-emerald-600" />

              <h2
                id="correction-dialog-title"
                className="mt-4 text-xl font-semibold"
              >
                Request submitted
              </h2>

              <p className="mt-2 text-sm text-muted-foreground">
                Your preview request has been added to My Correction Requests.
              </p>
            </div>

            <div className="space-y-3 rounded-xl border bg-muted/30 p-4">
              <div className="flex items-center justify-between gap-3">
                <span className="text-sm text-muted-foreground">
                  Reference number
                </span>
                <span className="break-all text-sm font-semibold">
                  {submittedReference}
                </span>
              </div>

              <div className="flex items-center justify-between gap-3">
                <span className="text-sm text-muted-foreground">Status</span>
                <span className="rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                  Pending
                </span>
              </div>
            </div>

            <p className="text-sm text-muted-foreground">
              This is a preview only. No request was sent to HR, and no actual
              employee record was changed.
            </p>

            <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
              <Button variant="outline" onClick={handleClose}>
                Close
              </Button>

              <Button onClick={handleViewRequests} className="gap-2">
                <ClipboardList className="h-4 w-4" />
                View My Requests
              </Button>
            </div>
          </div>
        ) : (
          <>
            <div className="flex items-start justify-between border-b px-5 py-4">
              <div className="flex items-start gap-3">
                <ClipboardEdit className="mt-1 h-5 w-5 text-blue-600" />

                <div>
                  <h2 id="correction-dialog-title" className="font-semibold">
                    Request Profile Correction
                  </h2>

                  <p className="mt-1 text-sm text-muted-foreground">
                    Request a correction to your {section.toLowerCase()} record.
                  </p>
                </div>
              </div>

              <button
                type="button"
                onClick={handleClose}
                aria-label="Close correction request dialog"
                className="rounded-lg p-2 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <form onSubmit={handleSubmit} className="space-y-5 p-5">
              <div className="space-y-2">
                <label
                  htmlFor="correction-field"
                  className="text-sm font-medium"
                >
                  Information to correct
                </label>

                <select
                  id="correction-field"
                  required
                  value={field}
                  onChange={(event) => setField(event.target.value)}
                  className="w-full rounded-lg border bg-background px-3 py-2.5 text-sm"
                >
                  <option value="">Select a field</option>
                  {fields.map((item) => (
                    <option key={item} value={item}>
                      {item}
                    </option>
                  ))}
                </select>
              </div>

              <div className="space-y-2">
                <label
                  htmlFor="correction-proposed-value"
                  className="text-sm font-medium"
                >
                  Proposed correct value
                </label>

                <input
                  id="correction-proposed-value"
                  required
                  value={proposedValue}
                  onChange={(event) => setProposedValue(event.target.value)}
                  placeholder="Enter the correct information"
                  className="w-full rounded-lg border bg-background px-3 py-2.5 text-sm"
                />
              </div>

              <div className="space-y-2">
                <label
                  htmlFor="correction-reason"
                  className="text-sm font-medium"
                >
                  Reason for correction
                </label>

                <textarea
                  id="correction-reason"
                  required
                  rows={4}
                  value={reason}
                  onChange={(event) => setReason(event.target.value)}
                  placeholder="Explain why this information needs to be corrected."
                  className="w-full resize-y rounded-lg border bg-background px-3 py-2.5 text-sm"
                />
              </div>

              <div className="rounded-xl bg-muted/50 p-3 text-sm text-muted-foreground">
                Your request will require review by HR or an explicitly
                authorized IT/Administrator reviewer. The official employee
                record remains managed by HRIS.
              </div>

              <div className="flex justify-end gap-3 border-t pt-4">
                <Button type="button" variant="outline" onClick={handleClose}>
                  Cancel
                </Button>

                <Button type="submit">Submit Request</Button>
              </div>

              <p className="text-xs text-muted-foreground">
                Preview only. This form does not submit or change actual
                employee records.
              </p>
            </form>
          </>
        )}
      </section>
    </div>
  );
}
