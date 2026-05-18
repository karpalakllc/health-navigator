"use client";

import { createContext, useContext } from "react";
import type { PublicSettings } from "@/lib/api/settings";

const SitePlaceholdersContext = createContext<{
  doctor: string | null;
  facility: string | null;
  pharmacy: string | null;
}>({
  doctor: null,
  facility: null,
  pharmacy: null,
});

export function SitePlaceholdersProvider({
  settings,
  children,
}: {
  settings: PublicSettings;
  children: React.ReactNode;
}) {
  return (
    <SitePlaceholdersContext.Provider
      value={{
        doctor: settings.placeholder_doctor_url,
        facility: settings.placeholder_facility_url,
        pharmacy: settings.placeholder_pharmacy_url,
      }}
    >
      {children}
    </SitePlaceholdersContext.Provider>
  );
}

export function useSitePlaceholders() {
  return useContext(SitePlaceholdersContext);
}
