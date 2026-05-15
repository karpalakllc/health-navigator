import Link from "next/link";
import { redirect } from "next/navigation";
import { AccountLayout } from "@/components/account/account-layout";
import { PageHeader } from "@/components/directory/page-header";
import { Pagination } from "@/components/directory/pagination";
import { StarRating } from "@/components/ui/star-rating";
import { ModerationStatusBadge } from "@/components/ui/moderation-status-badge";
import { PageShell } from "@/components/ui/page-shell";
import { StackedList } from "@/components/ui/stacked-list";
import { getSessionToken } from "@/lib/auth/session";
import { fetchMyReviews } from "@/lib/api/me";
import { ApiRequestError } from "@/lib/api/server";
import { t } from "@/i18n/t";

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
    <PageShell>
      <AccountLayout current="reviews">
        <PageHeader
          title={t("auth.reviewsTitle")}
          description={t("reviews.pending")}
        />

        <StackedList>
          {reviews.data.length === 0 ? (
            <li className="p-4 text-sm text-muted-foreground">{t("account.noReviewsYet")}</li>
          ) : (
            reviews.data.map((review, index) => {
              const href = targetHref(review);

              return (
                <li key={`${review.created_at}-${index}`} className="space-y-2 p-4">
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <StarRating value={review.rating} />
                    <ModerationStatusBadge status={review.status} />
                  </div>
                  {review.reviewable ? (
                    <p className="text-sm text-muted-foreground">
                      {href ? (
                        <Link
                          href={href}
                          className="font-medium text-primary underline-offset-2 hover:underline"
                        >
                          {review.reviewable.name}
                        </Link>
                      ) : (
                        review.reviewable.name
                      )}
                    </p>
                  ) : null}
                  {review.body ? (
                    <p className="whitespace-pre-wrap text-sm text-foreground">{review.body}</p>
                  ) : null}
                </li>
              );
            })
          )}
        </StackedList>

        <Pagination
          basePath="/account/reviews"
          currentPage={reviews.meta.current_page}
          lastPage={reviews.meta.last_page}
          total={reviews.meta.total}
          searchParams={{}}
        />
      </AccountLayout>
    </PageShell>
  );
}
