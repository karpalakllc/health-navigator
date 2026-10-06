import { redirect } from "next/navigation";
import {
  AccountLayout,
  AccountPage,
} from "@/components/account/account-layout";
import { AccountPageHero } from "@/components/account/account-page-hero";
import { ChangeList } from "@/components/doctor-dashboard/change-list";
import { DoctorChangeRequestForm } from "@/components/doctor-dashboard/doctor-change-request-form";
import { DoctorDashboardStats } from "@/components/doctor-dashboard/doctor-dashboard-stats";
import { DoctorPendingChange } from "@/components/doctor-dashboard/doctor-pending-change";
import { DoctorPhotoUpload } from "@/components/doctor-dashboard/doctor-photo-upload";
import { DoctorPracticeForm } from "@/components/doctor-dashboard/doctor-practice-form";
import { DoctorReviewCard } from "@/components/doctor-dashboard/doctor-review-card";
import { Pagination } from "@/components/directory/pagination";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { ChipLink } from "@/components/ui/chip";
import { Notice } from "@/components/ui/notice";
import { SectionHeader } from "@/components/ui/section-header";
import { Tag } from "@/components/ui/tag";
import { getSessionToken } from "@/lib/auth/session";
import {
  fetchDoctorDashboard,
  fetchDoctorReviews,
  type ReviewFilter,
} from "@/lib/api/doctor-dashboard";
import { ApiRequestError } from "@/lib/api/server";
import { parseListPage } from "@/lib/api/directory-cache-policy";
import { formatMkDate } from "@/lib/mk-date";
import { pageMetadata } from "@/lib/metadata";
import { t, tFormat } from "@/i18n/t";

export const metadata = pageMetadata(
  t("doctorDashboard.metaTitle"),
  undefined,
  { noIndex: true },
);

const PATH = "/account/doctor";

type DoctorDashboardPageProps = {
  searchParams: Promise<{ page?: string; filter?: string }>;
};

/**
 * „Мој профил“: only for an account staff linked to a doctor profile; anyone
 * else is sent back to the account overview.
 */
export default async function DoctorDashboardPage({
  searchParams,
}: DoctorDashboardPageProps) {
  const token = await getSessionToken();

  if (!token) {
    redirect(`/login?redirect=${PATH}`);
  }

  const params = await searchParams;
  const page = parseListPage(params.page);
  const filter: ReviewFilter =
    params.filter === "unanswered" ? "unanswered" : "all";

  let dashboard;
  let reviews;

  try {
    [dashboard, reviews] = await Promise.all([
      fetchDoctorDashboard(),
      fetchDoctorReviews(page, filter),
    ]);
  } catch (error) {
    if (error instanceof ApiRequestError && error.status === 401) {
      redirect(`/login?redirect=${PATH}`);
    }

    if (
      error instanceof ApiRequestError &&
      (error.status === 404 || error.status === 403)
    ) {
      redirect("/account");
    }

    throw error;
  }

  const { doctor } = dashboard;
  const filterHref = (value: ReviewFilter) =>
    value === "unanswered"
      ? `${PATH}?filter=unanswered#reviews`
      : `${PATH}#reviews`;

  return (
    <AccountPage>
      <AccountPageHero
        badge={`${t("doctorDashboard.heroBadge")} · ${doctor.full_name}`}
        title={t("doctorDashboard.heroTitle")}
        description={t("doctorDashboard.heroDescription")}
      />
      <AccountLayout current="doctor">
        <Card as="section" aria-labelledby="doctor-overview">
          <div className="flex flex-col gap-5">
            <SectionHeader
              id="doctor-overview"
              title={t("doctorDashboard.statsTitle")}
              level={2}
              action={
                doctor.is_published ? (
                  <Button
                    href={`/doctors/${encodeURIComponent(doctor.slug)}`}
                    variant="secondary"
                    size="sm"
                    trailingIcon="arrow-right"
                  >
                    {t("doctorDashboard.viewPublic")}
                  </Button>
                ) : null
              }
            />
            {doctor.is_published ? null : (
              <Notice tone="info">{t("doctorDashboard.unpublished")}</Notice>
            )}
            <DoctorDashboardStats stats={dashboard.stats} />
          </div>
        </Card>

        <Card as="section" aria-labelledby="doctor-practice">
          <div className="flex flex-col gap-6">
            <SectionHeader
              id="doctor-practice"
              title={t("doctorDashboard.practiceTitle")}
              level={2}
              description={t("doctorDashboard.practiceHint")}
            />
            <DoctorPhotoUpload
              name={doctor.full_name}
              avatarUrl={doctor.avatar_url}
            />
            <div className="border-t border-line pt-6">
              <DoctorPracticeForm doctor={doctor} options={dashboard.options} />
            </div>
          </div>
        </Card>

        <Card as="section" aria-labelledby="doctor-sensitive">
          <div className="flex flex-col gap-6">
            <SectionHeader
              id="doctor-sensitive"
              title={t("doctorDashboard.sensitiveTitle")}
              level={2}
              description={t("doctorDashboard.sensitiveHint")}
            />
            {dashboard.pending_change_request ? (
              <DoctorPendingChange request={dashboard.pending_change_request} />
            ) : (
              <DoctorChangeRequestForm
                doctor={doctor}
                options={dashboard.options}
              />
            )}
            {dashboard.recent_change_requests.length > 0 ? (
              <div className="flex flex-col gap-4 border-t border-line pt-6">
                <h3 className="type-h3 text-ink">
                  {t("doctorDashboard.historyTitle")}
                </h3>
                <ul className="flex flex-col gap-4">
                  {dashboard.recent_change_requests.map((request) => (
                    <li
                      key={request.id}
                      className="flex flex-col gap-3 rounded-2xl bg-sand p-4"
                    >
                      <div className="flex flex-wrap items-center gap-2">
                        {request.status === "approved" ? (
                          <Tag tone="care" icon="check">
                            {t("doctorDashboard.statusApproved")}
                          </Tag>
                        ) : (
                          <Tag tone="outline" icon="x">
                            {t("doctorDashboard.statusRejected")}
                          </Tag>
                        )}
                        {formatMkDate(request.reviewed_at) ? (
                          <span className="type-meta text-ink-2">
                            {formatMkDate(request.reviewed_at)}
                          </span>
                        ) : null}
                      </div>
                      <ChangeList changes={request.changes} />
                      {request.status === "rejected" &&
                      request.rejection_reason ? (
                        <p className="type-body text-ink">
                          {tFormat("doctorDashboard.rejectionReason", {
                            reason: request.rejection_reason,
                          })}
                        </p>
                      ) : null}
                    </li>
                  ))}
                </ul>
              </div>
            ) : null}
          </div>
        </Card>

        <section
          id="reviews"
          aria-labelledby="doctor-reviews"
          className="flex scroll-mt-[calc(var(--header-h)+1rem)] flex-col gap-4"
        >
          <SectionHeader
            id="doctor-reviews"
            title={t("doctorDashboard.reviewsTitle")}
            level={2}
            description={t("doctorDashboard.reviewsHint")}
          />
          <nav
            aria-label={t("doctorDashboard.reviewsFilterAria")}
            className="flex flex-wrap gap-2"
          >
            <ChipLink href={filterHref("all")} current={filter === "all"}>
              {t("doctorDashboard.filterAll")}
            </ChipLink>
            <ChipLink
              href={filterHref("unanswered")}
              current={filter === "unanswered"}
            >
              {t("doctorDashboard.filterUnanswered")}
            </ChipLink>
          </nav>
          {reviews.data.length === 0 ? (
            <Card padding="md">
              <p className="type-body text-ink-2">
                {filter === "unanswered"
                  ? t("doctorDashboard.noUnanswered")
                  : t("doctorDashboard.noReviews")}
              </p>
            </Card>
          ) : (
            <ul className="flex flex-col gap-4">
              {reviews.data.map((review) => (
                <li key={review.id}>
                  <DoctorReviewCard review={review} />
                </li>
              ))}
            </ul>
          )}
          <Pagination
            basePath={PATH}
            currentPage={reviews.meta.current_page}
            lastPage={reviews.meta.last_page}
            total={reviews.meta.total}
            searchParams={filter === "unanswered" ? { filter } : {}}
          />
        </section>
      </AccountLayout>
    </AccountPage>
  );
}
