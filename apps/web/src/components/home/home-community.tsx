import { CommunityTopicCard } from "@/components/home/community-topic-card";
import { SectionHeader } from "@/components/ui/section-header";
import { ChipLink } from "@/components/ui/chip";
import { Icon } from "@/components/ui/icons";
import type { ForumTopicSearchItem } from "@/lib/api/forum";
import type { Specialty } from "@/lib/api/types";
import { t } from "@/i18n/t";

/** „Од заедницата“: the latest published forum topics. */
export function HomeCommunity({
  topics,
  className,
}: {
  topics: ForumTopicSearchItem[];
  className?: string;
}) {
  if (topics.length === 0) {
    return null;
  }

  return (
    <section aria-labelledby="home-community-title" className={className}>
      <SectionHeader
        id="home-community-title"
        title={t("home.communityHeading")}
        action={{ href: "/forum", label: t("home.communityAll") }}
        className="items-center"
      />
      <ul className="mt-3 flex flex-col gap-3 lg:mt-4 lg:gap-4">
        {topics.map((topic) => (
          <li key={`${topic.category.slug}/${topic.slug}`}>
            <CommunityTopicCard topic={topic} />
          </li>
        ))}
      </ul>
    </section>
  );
}

/** „Популарни специјалности“: specialties with the most doctors, as chips. */
export function HomePopularSpecialties({
  specialties,
  className,
}: {
  specialties: Specialty[];
  className?: string;
}) {
  if (specialties.length === 0) {
    return null;
  }

  return (
    <section aria-labelledby="home-specialties-title" className={className}>
      <h2 id="home-specialties-title" className="type-h3 text-ink">
        {t("home.popularSpecialties")}
      </h2>
      <ul className="mt-4 flex flex-wrap gap-2">
        {specialties.map((specialty) => (
          <li key={specialty.slug}>
            <ChipLink
              href={`/doctors?specialty=${encodeURIComponent(specialty.slug)}`}
            >
              {specialty.name}
            </ChipLink>
          </li>
        ))}
      </ul>
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
