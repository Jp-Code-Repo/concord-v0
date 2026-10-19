import { createContext, useContext, useState, type ReactNode } from "react";

export type CorrectionSection = "Personal Information" | "Employment";

export type CorrectionRequestStatus = "Pending" | "Approved" | "Rejected";

export interface CorrectionRequest {
  id: string;
  section: CorrectionSection;
  field: string;
  proposedValue: string;
  reason: string;
  status: CorrectionRequestStatus;
  submittedAt: string;
}

interface CorrectionRequestContextValue {
  requests: CorrectionRequest[];
  addRequest: (
    request: Omit<CorrectionRequest, "id" | "status" | "submittedAt">,
  ) => string;
}

const CorrectionRequestContext =
  createContext<CorrectionRequestContextValue | null>(null);

export function CorrectionRequestProvider({
  children,
}: {
  children: ReactNode;
}) {
  const [requests, setRequests] = useState<CorrectionRequest[]>([]);

  function addRequest(
    request: Omit<CorrectionRequest, "id" | "status" | "submittedAt">,
  ) {
    const id = `CR-${Date.now().toString(36).toUpperCase()}`;

    setRequests((current) => [
      {
        ...request,
        id,
        status: "Pending",
        submittedAt: new Date().toISOString(),
      },
      ...current,
    ]);

    return id;
  }

  return (
    <CorrectionRequestContext.Provider value={{ requests, addRequest }}>
      {children}
    </CorrectionRequestContext.Provider>
  );
}

export function useCorrectionRequests() {
  const context = useContext(CorrectionRequestContext);

  if (!context) {
    throw new Error(
      "useCorrectionRequests must be used within CorrectionRequestProvider",
    );
  }

  return context;
}

