import { SEARCH_DIRECTORY_SECTIONS } from "@/components/layout/search-directory-sections";
import { Button } from "@/components/ui/button";
import { ChipLink } from "@/components/ui/chip";
import { Icon, type IconName } from "@/components/ui/icons";
import { fetchPublicSettings } from "@/lib/api/settings";
import { directorySearchHref } from "@/lib/search";
import { isPathEnabled, moduleFlags } from "@/lib/site-modules";
import { t, tFormat } from "@/i18n/t";

/** The directories a visitor can search one at a time (enabled modules only). */
export async function enabledDirectorySections() {
  const flags = moduleFlags(await fetchPublicSettings());
  return SEARCH_DIRECTORY_SECTIONS.filter((section) =>
    isPathEnabled(section.basePath, flags),
  );
}

function StateCard({
  icon,
  title,
  body,
  children,
}: {
  icon: IconName;
  title: string;
  body: string;
  children?: React.ReactNode;
}) {
  return (
    <section
      aria-labelledby="search-state-title"
      className="card flex flex-col items-start p-6 lg:p-10"
    >
      <span className="flex size-14 items-center justify-center rounded-full bg-sand text-ink">
        <Icon name={icon} size={28} />
      </span>
      <h2 id="search-state-title" className="type-h2 mt-4 text-ink">
        {title}
      </h2>
      <p className="type-body measure mt-2 text-ink-2">{body}</p>
      {children}
    </section>
  );
}

/** No hits in any vertical: say so, and offer each directory on its own. */
export async function SearchEmptyState({
  q,
  city,
}: {
  q: string;
  city?: string;
}) {
  const sections = await enabledDirectorySections();

  return (
    <StateCard
      icon="search"
      title={tFormat("search.emptyTitle", { q })}
      body={t("search.emptyBody")}
    >
      <ul className="mt-6 flex flex-wrap gap-2">
        {sections.map((section) => (
          <li key={section.basePath}>
            <ChipLink href={directorySearchHref(section.basePath, q, city)}>
              {t(section.titleKey)}
            </ChipLink>
          </li>
        ))}
      </ul>
    </StateCard>
  );
}

/** The search API failed: an ink (never red) message and a retry link. */
export function SearchErrorState({ q, city }: { q: string; city?: string }) {
  return (
    <div role="alert">
      <StateCard
        icon="alert-triangle"
        title={t("search.errorTitle")}
        body={t("search.errorBody")}
      >
        <Button
          href={directorySearchHref("/search", q, city)}
          leadingIcon="search"
          className="mt-6"
        >
          {t("search.retry")}
        </Button>
      </StateCard>
    </div>
  );
}
