import { StatusPanel } from "@/components/system/status-panel";
import { Button } from "@/components/ui/button";
import { Tag } from "@/components/ui/tag";
import { fetchPublicSettings } from "@/lib/api/settings";
import { t } from "@/i18n/t";

type ComingSoonModule = "products" | "pharmacies" | "forum";

type ComingSoonShellProps = {
  title: string;
  description: string;
  module?: ComingSoonModule;
};

/**
 * Stands in for a module the admin has switched off. It answers 200, so every
 * page that renders it must also mark itself noindex in its metadata — a
 * placeholder must not be indexed in place of the real page.
 */
export async function ComingSoonShell({
  title,
  description,
  module = "products",
}: ComingSoonShellProps) {
  const settings = await fetchPublicSettings();
  const body =
    module === "pharmacies"
      ? t("comingSoon.pharmaciesBody")
      : module === "forum"
        ? t("comingSoon.forumBody")
        : t("comingSoon.productsBody");
  // The forum is not a feature in the works: when it is off, the admin turned
  // it off, so it gets neutral "not available" copy instead of "coming soon".
  const unavailable = module === "forum";
  const badge = unavailable
    ? t("comingSoon.unavailableBadge")
    : t("comingSoon.badge");

  return (
    <StatusPanel
      icon={unavailable ? "info" : "clock"}
      eyebrow={<Tag tone="tint">{badge}</Tag>}
      title={title}
      description={description || body}
      details={
        <div className="flex flex-col gap-1 rounded-[20px] bg-sand p-4">
          <h2 className="type-h3 text-ink">
            {unavailable
              ? t("comingSoon.unavailableTitle")
              : t("comingSoon.title")}
          </h2>
          <p className="type-body text-ink">
            {unavailable
              ? t("comingSoon.unavailableBody")
              : settings.public_forum
                ? t("comingSoon.body")
                : t("comingSoon.bodyWithoutForum")}
          </p>
        </div>
      }
    >
      <Button href="/doctors" leadingIcon="stethoscope">
        {t("comingSoon.exploreDoctors")}
      </Button>
      <Button href="/facilities" variant="secondary" leadingIcon="building">
        {t("comingSoon.exploreFacilities")}
      </Button>
      {settings.public_forum ? (
        <Button href="/forum" variant="secondary" leadingIcon="message-circle">
          {t("comingSoon.exploreForum")}
        </Button>
      ) : null}
    </StatusPanel>
  );
}
