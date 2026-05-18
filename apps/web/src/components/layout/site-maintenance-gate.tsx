import { SiteMaintenancePage } from "@/components/layout/site-maintenance-page";
import { fetchPublicSettingsServer } from "@/lib/api/settings";

export async function SiteMaintenanceGate({ children }: { children: React.ReactNode }) {
  const settings = await fetchPublicSettingsServer();

  if (settings.maintenance_mode) {
    return <SiteMaintenancePage message={settings.maintenance_message} />;
  }

  return children;
}
