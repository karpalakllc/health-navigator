import Link from "next/link";
import { t } from "@/i18n/t";

type ForumCategoryToolbarProps = {
  categorySlug: string;
  currentSort: "latest" | "active";
  searchQuery?: string;
  isLoggedIn: boolean;
};

export function ForumCategoryToolbar({
  categorySlug,
  currentSort,
  searchQuery,
  isLoggedIn,
}: ForumCategoryToolbarProps) {
  const base = `/forum/${categorySlug}`;
  const q = searchQuery?.trim();

  function href(sort: "latest" | "active") {
    const params = new URLSearchParams();
    if (q) params.set("q", q);
    if (sort !== "latest") params.set("sort", sort);
    const query = params.toString();
    return query ? `${base}?${query}` : base;
  }

  const newTopicHref = isLoggedIn
    ? `/forum/new?category=${encodeURIComponent(categorySlug)}`
    : `/login?redirect=${encodeURIComponent(`/forum/new?category=${categorySlug}`)}`;

  return (
    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div className="inline-flex flex-wrap gap-2 rounded-full border border-border bg-card p-1">
        <SortLink
          href={href("latest")}
          active={currentSort === "latest"}
          label={t("forum.sortLatest")}
        />
        <SortLink
          href={href("active")}
          active={currentSort === "active"}
          label={t("forum.sortActive")}
        />
      </div>
      <Link
        href={newTopicHref}
        className="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-full border-2 border-primary bg-white px-5 text-sm font-extrabold text-primary shadow-[0_8px_24px_rgb(16_30_36_/_0.06)] hover:bg-primary/5"
      >
        <PlusIcon />
        {t("forum.newTopic")}
      </Link>
    </div>
  );
}

function PlusIcon() {
  return (
    <svg
      className="h-4 w-4"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2.5"
      aria-hidden
    >
      <path d="M12 5v14M5 12h14" strokeLinecap="round" />
    </svg>
  );
}

function SortLink({
  href,
  active,
  label,
}: {
  href: string;
  active: boolean;
  label: string;
}) {
  return (
    <Link
      href={href}
      className={`inline-flex min-h-[36px] items-center rounded-full px-4 text-sm font-semibold transition ${
        active
          ? "bg-primary text-primary-foreground"
          : "text-muted-foreground hover:text-foreground"
      }`}
    >
      {label}
    </Link>
  );
}
