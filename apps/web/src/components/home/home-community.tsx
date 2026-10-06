import { CommunityTopicCard } from "@/components/home/community-topic-card";
import { CountUp } from "@/components/home/count-up";
import { CommunityIllustration } from "@/components/home/home-spot-illustrations";
import { Button } from "@/components/ui/button";
import { Icon } from "@/components/ui/icons";
import type { ForumTopicSearchItem } from "@/lib/api/forum";
import { cn } from "@/lib/cn";
import { isMacedonianOne, t } from "@/i18n/t";

/**
 * „Од заедницата“: the latest published forum topics on their own sage band
 * (care tint), so the community stands apart from the directory sections
 * around it. Left: title, one line about the forum, the topic count and
 * „Сите теми“, with a small illustration; right: the topic cards (white,
 * each with its relative „пред 2 часа“). Stacked on phones.
 *
 * Contrast on the care tint #e4f1ea: ink 13.4:1, ink-2 6.6:1 (AA).
 */
export function HomeCommunity({
  topics,
  totalTopics,
  className,
}: {
  topics: ForumTopicSearchItem[];
  /** All published topics; the count line is left out when unknown. */
  totalTopics?: number;
  className?: string;
}) {
  if (topics.length === 0) {
    return null;
  }

  return (
    <section
      aria-labelledby="home-community-title"
      data-band="community"
      className={cn(
        "relative mx-3 overflow-hidden rounded-sheet bg-care-tint px-5 pb-7 pt-7 lg:mx-0 lg:px-12 lg:py-12",
        className,
      )}
    >
      <div className="grid gap-6 lg:grid-cols-12 lg:gap-10">
        <div className="flex flex-col lg:col-span-5">
          <div className="flex items-start gap-4">
            <div className="min-w-0 flex-1">
              <h2 id="home-community-title" className="type-h2 text-ink">
                {t("home.communityHeading")}
              </h2>
              <p className="type-body mt-2 text-ink-2">
                {t("homeSearch.communityLead")}
              </p>
            </div>
            <CommunityIllustration className="w-28 shrink-0 lg:hidden" />
          </div>
          {totalTopics ? (
            <p className="mt-4 flex items-center gap-2 font-ui text-base font-semibold text-ink">
              <span className="flex size-9 items-center justify-center rounded-full bg-white">
                <Icon name="message-circle" size={18} />
              </span>
              <span>
                <CountUp value={totalTopics} />{" "}
                {isMacedonianOne(totalTopics)
                  ? t("homeSearch.communityTopicsUnitOne")
                  : t("homeSearch.communityTopicsUnit")}
              </span>
            </p>
          ) : null}
          <CommunityIllustration className="mt-8 hidden w-full max-w-[18rem] lg:block" />
          <div className="mt-5 hidden lg:mt-auto lg:block lg:pt-8">
            <Button
              href="/forum"
              variant="secondary"
              trailingIcon="arrow-right"
            >
              {t("home.communityAll")}
            </Button>
          </div>
        </div>

        <ul className="flex flex-col gap-3 lg:col-span-7 lg:gap-4">
          {topics.map((topic) => (
            <li key={`${topic.category.slug}/${topic.slug}`}>
              <CommunityTopicCard topic={topic} />
            </li>
          ))}
        </ul>

        <div className="lg:hidden">
          <Button
            href="/forum"
            variant="secondary"
            fullWidth
            trailingIcon="arrow-right"
          >
            {t("home.communityAll")}
          </Button>
        </div>
      </div>
    </section>
  );
}

/** Three short trust statements; sand band on desktop, a list on mobile. */
export function HomeTrustRow({
  items,
  className,
}: {
  items: string[];
  className?: string;
}) {
  return (
    <section aria-label={t("home.trustAriaLabel")} className={className}>
      <ul className="flex flex-col gap-3 lg:grid lg:grid-cols-3 lg:gap-6 lg:rounded-sheet lg:bg-sand lg:p-8">
        {items.map((text) => (
          <li key={text} className="flex min-h-11 items-center gap-3 lg:gap-4">
            <span className="flex size-11 flex-none items-center justify-center rounded-full bg-care-tint text-care">
              <Icon name="shield-check" />
            </span>
            <span className="text-base leading-[1.375rem] text-ink lg:text-lg lg:font-medium lg:leading-[1.625rem]">
              {text}
            </span>
          </li>
        ))}
      </ul>
    </section>
  );
}
