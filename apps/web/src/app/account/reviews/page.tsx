import Link from "next/link";
import { redirect } from "next/navigation";
import {
  AccountLayout,
  AccountPage,
} from "@/components/account/account-layout";
import { AccountPageHero } from "@/components/account/account-page-hero";
import { ModerationStatusTag } from "@/components/account/moderation-status-tag";
import { Pagination } from "@/components/directory/pagination";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Icon } from "@/components/ui/icons";
import { Notice } from "@/components/ui/notice";
import { StarRating } from "@/components/ui/star-rating";
import { Tag } from "@/components/ui/tag";
import { getSessionToken } from "@/lib/auth/session";
import { fetchMyReviews, type MyReviewImpact } from "@/lib/api/me";
import { ApiRequestError } from "@/lib/api/server";
import { t, tCount, tFormat } from "@/i18n/t";
import { pageMetadata } from "@/lib/metadata";
import { parseListPage } from "@/lib/api/directory-cache-policy";

export const metadata = pageMetadata(t("auth.reviewsTitle"), undefined, {
  noIndex: true,
});

type AccountReviewsPageProps = {
  searchParams: Promise<{ page?: string }>;
};

function targetHref(review: {
  reviewable: { kind: string; slug: string } | null;
}): string | null {
  if (!review.reviewable) {
    return null;
  }

  if (review.reviewable.kind === "doctor") {
    return `/doctors/${review.reviewable.slug}`;
  }

  if (review.reviewable.kind === "pharmacy") {
    return `/pharmacies/${review.reviewable.slug}`;
  }

  return `/facilities/${review.reviewable.slug}`;
}

/** „12 септември 2026“: a date, not a relative time, for one's own record. */
function reviewDate(iso: string | null): string | null {
  if (!iso) {
    return null;
  }

  const date = new Date(iso);

  return Number.isNaN(date.getTime())
    ? null
    : new Intl.DateTimeFormat("mk-MK", {
        day: "numeric",
        month: "long",
        year: "numeric",
      }).format(date);
}

export default async function AccountReviewsPage({
  searchParams,
}: AccountReviewsPageProps) {
  const token = await getSessionToken();

  if (!token) {
    redirect("/login?redirect=/account/reviews");
  }

  const params = await searchParams;
  const page = parseListPage(params.page);

  let reviews;

  try {
    reviews = await fetchMyReviews(page);
  } catch (error) {
    if (error instanceof ApiRequestError && error.status === 401) {
      redirect("/login?redirect=/account/reviews");
    }

    throw error;
  }

  return (
    <AccountPage>
      <AccountPageHero
        badge={t("account.hubBadge")}
        title={t("auth.reviewsTitle")}
        description={t("account.reviewsHeroDescription")}
      />
      <AccountLayout current="reviews">
        {reviews.meta.impact && reviews.meta.impact.published > 0 ? (
          <ImpactSummary impact={reviews.meta.impact} />
        ) : null}
        {reviews.data.length === 0 ? (
          <Card className="flex flex-col items-start gap-4">
            <span className="inline-flex size-14 items-center justify-center rounded-full bg-apricot text-ink">
              <Icon name="star" size={28} />
            </span>
            <p className="type-h3 text-ink">{t("account.noReviewsYet")}</p>
            <Button href="/doctors" leadingIcon="search">
              {t("account.noReviewsCta")}
            </Button>
          </Card>
        ) : (
          <ul className="flex flex-col gap-4">
            {reviews.data.map((review, index) => {
              const href = targetHref(review);
              const date = reviewDate(review.created_at);

              return (
                <Card
                  as="li"
                  key={`${review.created_at}-${index}`}
                  padding="md"
                  className="flex flex-col gap-3"
                >
                  <div className="flex flex-wrap items-start justify-between gap-x-4 gap-y-2">
                    {review.reviewable ? (
                      <h2 className="type-h3 text-ink">
                        {href ? (
                          <Link
                            href={href}
                            className="link-underline hover:text-black"
                          >
                            {review.reviewable.name}
                          </Link>
                        ) : (
                          review.reviewable.name
                        )}
                      </h2>
                    ) : null}
                    <ModerationStatusTag status={review.status} />
                  </div>
                  <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <StarRating value={review.rating} />
                    {date ? (
                      <span className="type-meta text-ink-2">{date}</span>
                    ) : null}
                  </div>
                  {review.body ? (
                    <p className="measure whitespace-pre-wrap type-reading text-ink">
                      {review.body}
                    </p>
                  ) : null}
                  {review.impact ? (
                    <ul
                      aria-label={t("reviewFlow.impactTitle")}
                      className="m-0 flex list-none flex-wrap gap-2 p-0"
                    >
                      <li>
                        <Tag icon="eye">
                          {tCount("reviewFlow.viewsCount", review.impact.views)}
                        </Tag>
                      </li>
                      <li>
                        <Tag icon="heart">
                          {tFormat("reviewFlow.helpfulCount", {
                            count: review.impact.helpful,
                          })}
                        </Tag>
                      </li>
                    </ul>
                  ) : null}
                  {review.reply ? (
                    <section className="flex flex-col gap-1.5 rounded-2xl border-l-4 border-line-strong bg-sand p-4">
                      <h3 className="type-label text-ink">
                        {review.reply.source === "doctor"
                          ? t("reviewFlow.replyFromDoctor")
                          : t("reviewFlow.replyFromProfile")}
                      </h3>
                      <p className="whitespace-pre-line break-words type-reading text-ink">
                        {review.reply.body}
                      </p>
                    </section>
                  ) : null}
                  {review.status === "rejected" && review.rejection_note ? (
                    <Notice tone="info" title={t("account.rejectionNote")}>
                      {review.rejection_note}
                    </Notice>
                  ) : null}
                  {review.can_resubmit && href ? (
                    <Link
                      href={`${href}#review-form`}
                      className="link-underline inline-flex min-h-12 items-center self-start type-body font-semibold text-ink"
                    >
                      {t("account.resubmitLink")}
                    </Link>
                  ) : null}
                </Card>
              );
            })}
          </ul>
        )}
        <Pagination
          basePath="/account/reviews"
          currentPage={reviews.meta.current_page}
          lastPage={reviews.meta.last_page}
          total={reviews.meta.total}
          searchParams={{}}
        />
      </AccountLayout>
    </AccountPage>
  );
}

/**
 * W8-B „Вашиот придонес“: totals over the member's published reviews, with
 * the honest definition of a view right under the numbers.
 */
function ImpactSummary({ impact }: { impact: MyReviewImpact }) {
  const figures = [
    { label: t("reviewFlow.impactViews"), value: impact.views, icon: "eye" },
    {
      label: t("reviewFlow.impactHelpful"),
      value: impact.helpful,
      icon: "heart",
    },
    {
      label: t("reviewFlow.impactReplies"),
      value: impact.replies,
      icon: "reply",
    },
  ] as const;

  return (
    <Card
      as="section"
      aria-labelledby="impact-title"
      padding="md"
      className="flex flex-col gap-4"
    >
      <h2 id="impact-title" className="type-h3 text-ink">
        {t("reviewFlow.impactTitle")}
      </h2>
      <dl className="m-0 grid grid-cols-3 gap-3">
        {figures.map((figure) => (
          <div
            key={figure.label}
            className="flex flex-col gap-1 rounded-2xl bg-sand p-3 lg:p-4"
          >
            <dt className="flex items-center gap-1.5 type-meta text-ink-2">
              <Icon name={figure.icon} size={16} className="shrink-0" />
              <span className="truncate">{figure.label}</span>
            </dt>
            <dd className="m-0 type-h2 text-ink tabular-nums">
              {figure.value.toLocaleString("mk-MK")}
            </dd>
          </div>
        ))}
      </dl>
      <p className="type-meta text-ink-2">{t("reviewFlow.impactExplain")}</p>
    </Card>
  );
}
