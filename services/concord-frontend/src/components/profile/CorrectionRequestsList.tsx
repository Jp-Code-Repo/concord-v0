import { ClipboardList } from "lucide-react";

import { useCorrectionRequests } from "@/contexts/CorrectionRequestContext";

export function CorrectionRequestsList() {
  const { requests } = useCorrectionRequests();

  return (
    <section className="overflow-hidden rounded-2xl border bg-card">
      <div className="flex items-center gap-3 border-b px-5 py-4">
        <ClipboardList className="h-5 w-5 text-blue-600" />
        <div>
          <h2 className="font-semibold">My Correction Requests</h2>
          <p className="mt-1 text-sm text-muted-foreground">
            Track requests to correct your employee records.
          </p>
        </div>
      </div>

      <div className="space-y-4 p-5">
        {requests.length === 0 ? (
          <div className="rounded-xl border border-dashed p-8 text-center">
            <ClipboardList className="mx-auto h-8 w-8 text-muted-foreground" />
            <p className="mt-3 font-medium">No correction requests yet</p>
            <p className="mt-1 text-sm text-muted-foreground">
              Requests you submit will appear here.
            </p>
          </div>
        ) : (
          requests.map((request) => (
            <article
              key={request.id}
              className="space-y-3 rounded-xl border p-4"
            >
              <div className="flex flex-col justify-between gap-2 sm:flex-row sm:items-start">
                <div>
                  <p className="text-sm font-semibold">{request.field}</p>
                  <p className="mt-1 text-xs text-muted-foreground">
                    {request.section} · {request.id}
                  </p>
                </div>

                <span className="inline-flex w-fit rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                  {request.status}
                </span>
              </div>

              <div className="grid gap-3 sm:grid-cols-2">
                <div>
                  <p className="text-xs text-muted-foreground">
                    Proposed value
                  </p>
                  <p className="mt-1 break-words text-sm font-medium">
                    {request.proposedValue}
                  </p>
                </div>

                <div>
                  <p className="text-xs text-muted-foreground">Submitted</p>
                  <p className="mt-1 text-sm">
                    {new Date(request.submittedAt).toLocaleString()}
                  </p>
                </div>
              </div>

              <div>
                <p className="text-xs text-muted-foreground">
                  Reason for correction
                </p>
                <p className="mt-1 whitespace-pre-wrap break-words text-sm">
                  {request.reason}
                </p>
              </div>
            </article>
          ))
        )}

        <p className="border-t pt-4 text-xs text-muted-foreground">
          Preview only. Requests are stored in browser memory and will be lost
          when the application reloads.
        </p>
      </div>
    </section>
  );
}
