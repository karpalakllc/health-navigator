import type { DoctorDashboard } from "@/lib/api/doctor-dashboard-types";
import { t } from "@/i18n/t";

/**
 * Four numbers about the profile's reviews. Page views are not shown: the
 * platform does not count them per profile.
 */
export function DoctorDashboardStats({
  stats,
}: {
  stats: DoctorDashboard["stats"];
}) {
  const average =
    stats.average_rating === null
      ? t("doctorDashboard.empty")
      : new Intl.NumberFormat("mk-MK", {
          minimumFractionDigits: 1,
          maximumFractionDigits: 1,
        }).format(stats.average_rating);

  const items = [
    { label: t("doctorDashboard.statReviews"), value: stats.review_count },
    { label: t("doctorDashboard.statAverage"), value: average },
    {
      label: t("doctorDashboard.statUnanswered"),
      value: stats.unanswered_reviews,
    },
    {
      label: t("doctorDashboard.statPendingReplies"),
      value: stats.pending_replies,
    },
  ];

  return (
    <dl className="grid grid-cols-2 gap-3 lg:grid-cols-4">
      {items.map((item) => (
        <div
          key={item.label}
          className="flex flex-col-reverse gap-1 rounded-2xl bg-sand p-4"
        >
          <dt className="type-meta text-ink-2">{item.label}</dt>
          <dd className="text-2xl font-bold leading-8 text-ink">
            {item.value}
          </dd>
        </div>
      ))}
    </dl>
  );
}
