import { SiteMaintenancePage } from "@/components/layout/site-maintenance-page";
import { fetchPublicSettings } from "@/lib/api/settings";

export async function SiteMaintenanceGate({
  children,
}: {
  children: React.ReactNode;
}) {
  const settings = await fetchPublicSettings();

  if (settings.maintenance_mode) {
    return (
      <SiteMaintenancePage
        message={settings.maintenance_message}
        logoUrl={settings.logo_url}
      />
    );
  }

  return children;
}
