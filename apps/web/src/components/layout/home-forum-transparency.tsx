import { Button } from "@/components/ui/button";
import { Icon, type IconName } from "@/components/ui/icons";
import { cn } from "@/lib/cn";
import { t } from "@/i18n/t";

const forumPoints = [
  "home.forumPointModerated",
  "home.forumPointWarnings",
  "home.forumPointClarity",
] as const;

const transparencyItems: Array<{
  icon: IconName;
  titleKey:
    | "home.transparencyItem1Title"
    | "home.transparencyItem2Title"
    | "home.transparencyItem3Title";
  bodyKey:
    | "home.transparencyItem1Body"
    | "home.transparencyItem2Body"
    | "home.transparencyItem3Body";
}> = [
  {
    icon: "shield-check",
    titleKey: "home.transparencyItem1Title",
    bodyKey: "home.transparencyItem1Body",
  },
  {
    icon: "sliders",
    titleKey: "home.transparencyItem2Title",
    bodyKey: "home.transparencyItem2Body",
  },
  {
    icon: "map-pin",
    titleKey: "home.transparencyItem3Title",
    bodyKey: "home.transparencyItem3Body",
  },
];

/**
 * „Форум и искуства“ (community rules, three check chips, „Отвори форум“)
 * beside „Зошто ова е важно“ (three icon rows). White cards on cream: 7/5 on
 * desktop, stacked on mobile. `showForum` drops the forum card when the forum
 * module is switched off.
 */
export function HomeForumTransparency({
  showForum = true,
  className,
}: {
  showForum?: boolean;
  className?: string;
}) {
  return (
    <div
      className={cn(
        "grid gap-4 lg:grid-cols-12 lg:items-start lg:gap-6",
        className,
      )}
    >
      {showForum ? (
        <section
          aria-labelledby="home-forum-band-title"
          className="card p-6 lg:col-span-7 lg:p-8"
        >
          <p className="type-meta font-semibold text-ink-2">
            {t("home.forumBandEyebrow")}
          </p>
          <h2 id="home-forum-band-title" className="type-h2 mt-1 text-ink">
            {t("home.forumBandTitle")}
          </h2>
          <p className="type-reading mt-2 text-ink">
            {t("home.forumBandDescription")}
          </p>
          <ul className="mt-5 flex flex-wrap gap-2">
            {forumPoints.map((key) => (
              <li
                key={key}
                className="tag min-h-11 bg-chip-tint px-4 text-base text-ink"
              >
                <Icon name="check" size={20} />
                <span>{t(key)}</span>
              </li>
            ))}
          </ul>
          <Button
            href="/forum"
            size="lg"
            trailingIcon="arrow-right"
            className="mt-6"
          >
            {t("home.communityForumCta")}
          </Button>
        </section>
      ) : null}

      <section
        aria-labelledby="home-transparency-title"
        className={cn(
          "card p-6 lg:p-8",
          showForum ? "lg:col-span-5" : "lg:col-span-12",
        )}
      >
        <p className="type-meta font-semibold text-ink-2">
          {t("home.transparencyEyebrow")}
        </p>
        <h2 id="home-transparency-title" className="type-h3 mt-1 text-ink">
          {t("home.transparencyTitle")}
        </h2>
        <ul className="mt-5 flex flex-col gap-4">
          {transparencyItems.map((item) => (
            <li key={item.titleKey} className="flex gap-3">
              <span className="flex size-11 flex-none items-center justify-center rounded-full bg-care-tint text-care">
                <Icon name={item.icon} />
              </span>
              <div className="min-w-0">
                <h3 className="font-ui text-[1.0625rem] font-semibold leading-6 text-ink">
                  {t(item.titleKey)}
                </h3>
                <p className="type-meta mt-0.5 text-ink-2">{t(item.bodyKey)}</p>
              </div>
            </li>
          ))}
        </ul>
      </section>
    </div>
  );
}
