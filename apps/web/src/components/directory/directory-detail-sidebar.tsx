import type { ReactNode } from "react";
import { Card } from "@/components/ui/card";
import { ContactBlock } from "@/components/ui/contact-block";
import { addressMapUrl } from "@/lib/maps";
import { t } from "@/i18n/t";

type DirectoryDetailSidebarProps = {
  phone?: string | null;
  email?: string | null;
  website?: string | null;
  mapQuery?: {
    name?: string | null;
    address?: string | null;
    city?: string | null;
  };
  extra?: ReactNode;
};

export function DirectoryDetailSidebar({
  phone,
  email,
  website,
  mapQuery,
  extra,
}: DirectoryDetailSidebarProps) {
  const mapUrl = mapQuery ? addressMapUrl(mapQuery) : null;

  return (
    <aside className="space-y-4 lg:sticky lg:top-24 lg:self-start">
      <Card className="space-y-4 p-6">
        <h2 className="font-semibold">{t("directory.contactAndLocation")}</h2>
        <ContactBlock phone={phone} email={email} />
        {website ? (
          <p className="text-sm">
            <a
              href={website}
              target="_blank"
              rel="noopener noreferrer"
              className="font-medium text-primary hover:underline"
            >
              {t("directory.website")}
            </a>
          </p>
        ) : null}
        {mapUrl ? (
          <p className="text-sm">
            <a
              href={mapUrl}
              target="_blank"
              rel="noopener noreferrer"
              className="font-medium text-primary hover:underline"
            >
              {t("directory.viewOnMap")}
            </a>
          </p>
        ) : null}
        {extra}
      </Card>
      <p className="rounded-xl bg-secondary/50 p-4 text-xs leading-relaxed text-muted-foreground">
        {t("footer.informational")}
      </p>
    </aside>
  );
}
