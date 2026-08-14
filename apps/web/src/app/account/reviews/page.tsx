import Link from "next/link";
import { redirect } from "next/navigation";
import { AccountLayout } from "@/components/account/account-layout";
import { AccountPageHero } from "@/components/account/account-page-hero";
import { ProfileContentCard } from "@/components/design/profile-content-card";
import { Pagination } from "@/components/directory/pagination";
import { StarRating } from "@/components/ui/star-rating";
import { ModerationStatusBadge } from "@/components/ui/moderation-status-badge";
import { PageShell } from "@/components/ui/page-shell";
import { PageHeroBleed } from "@/components/design/page-hero-bleed";
import { getSessionToken } from "@/lib/auth/session";
import { fetchMyReviews } from "@/lib/api/me";
import { ApiRequestError } from "@/lib/api/server";
import { t } from "@/i18n/t";
import { pageMetadata } from "@/lib/metadata";

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

export default async function AccountReviewsPage({
  searchParams,
}: AccountReviewsPageProps) {
  const token = await getSessionToken();

  if (!token) {
    redirect("/login?redirect=/account/reviews");
  }

  const params = await searchParams;
  const page = params.page ? Number(params.page) : 1;

  let reviews;

  try {
    reviews = await fetchMyReviews(Number.isFinite(page) ? page : 1);
  } catch (error) {
    if (error instanceof ApiRequestError && error.status === 401) {
      redirect("/login?redirect=/account/reviews");
    }

    throw error;
  }

  return (
    <>
      <PageHeroBleed>
        <AccountPageHero
          badge={t("nav.myReviews")}
          title={t("auth.reviewsTitle")}
          description={t("account.reviewsHeroDescription")}
        />
      </PageHeroBleed>

      <PageShell className="pb-16">
        <AccountLayout current="reviews">
          <ProfileContentCard title={t("auth.reviewsTitle")}>
            <ul className="divide-y divide-border/80">
              {reviews.data.length === 0 ? (
                <li className="py-4 text-sm text-muted-foreground">
                  {t("account.noReviewsYet")}
                </li>
              ) : (
                reviews.data.map((review, index) => {
                  const href = targetHref(review);

                  return (
                    <li
                      key={`${review.created_at}-${index}`}
                      className="space-y-2 py-4 first:pt-0 last:pb-0"
                    >
                      <div className="flex flex-wrap items-center justify-between gap-2">
                        <StarRating value={review.rating} />
                        <ModerationStatusBadge status={review.status} />
                      </div>
                      {review.reviewable ? (
                        <p className="text-sm text-muted-foreground">
                          {href ? (
                            <Link
                              href={href}
                              className="font-semibold text-primary underline-offset-2 hover:underline"
                            >
                              {review.reviewable.name}
                            </Link>
                          ) : (
                            review.reviewable.name
                          )}
                        </p>
                      ) : null}
                      {review.body ? (
                        <p className="whitespace-pre-wrap text-sm text-foreground">
                          {review.body}
                        </p>
                      ) : null}
                      {review.status === "rejected" && review.rejection_note ? (
                        <p className="rounded-xl border border-destructive/20 bg-destructive/5 px-3 py-2 text-sm text-muted-foreground">
                          <span className="font-medium text-foreground">
                            {t("account.rejectionNote")}:{" "}
                          </span>
                          {review.rejection_note}
                        </p>
                      ) : null}
                    </li>
                  );
                })
              )}
            </ul>
            <Pagination
              basePath="/account/reviews"
              currentPage={reviews.meta.current_page}
              lastPage={reviews.meta.last_page}
              total={reviews.meta.total}
              searchParams={{}}
            />
          </ProfileContentCard>
        </AccountLayout>
      </PageShell>
    </>
  );
}
