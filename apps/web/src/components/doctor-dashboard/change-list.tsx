import type {
  ChangeValue,
  DoctorChangeRequest,
  SensitiveField,
} from "@/lib/api/doctor-dashboard-types";
import { t, type MessageKey } from "@/i18n/t";

const FIELD_LABELS: Record<SensitiveField, MessageKey> = {
  full_name: "doctorDashboard.fullName",
  title: "doctorDashboard.title",
  subspecialty: "doctorDashboard.subspecialty",
  education: "doctorDashboard.education",
  years_experience: "doctorDashboard.yearsExperience",
  city: "doctorDashboard.city",
  specialties: "doctorDashboard.specialties",
  facilities: "doctorDashboard.facilities",
};

/** A value of a change as one line of text („Кардиологија (главна), …“). */
export function formatChangeValue(value: ChangeValue): string {
  if (value === null || value === "") {
    return t("doctorDashboard.empty");
  }

  if (Array.isArray(value)) {
    if (value.length === 0) {
      return t("doctorDashboard.empty");
    }

    return value
      .map((link) =>
        link.is_primary
          ? `${link.name} (${t("doctorDashboard.primaryMark")})`
          : link.name,
      )
      .join(", ");
  }

  return String(value);
}

/**
 * The fields of a change request, each with what the profile shows now and
 * what was asked for. A definition list, so a screen reader hears the field
 * name before its two values.
 */
export function ChangeList({
  changes,
}: {
  changes: DoctorChangeRequest["changes"];
}) {
  const fields = (Object.keys(FIELD_LABELS) as SensitiveField[]).filter(
    (field) => changes[field] !== undefined,
  );

  return (
    <dl className="flex flex-col gap-3">
      {fields.map((field) => {
        const change = changes[field]!;

        return (
          <div
            key={field}
            className="flex flex-col gap-1 border-t border-line pt-3 first:border-t-0 first:pt-0"
          >
            <dt className="type-label text-ink">{t(FIELD_LABELS[field])}</dt>
            <dd className="type-body text-ink-2">
              <span className="font-semibold text-ink">
                {t("doctorDashboard.changeFrom")}:
              </span>{" "}
              {formatChangeValue(change.old)}
            </dd>
            <dd className="type-body text-ink-2">
              <span className="font-semibold text-ink">
                {t("doctorDashboard.changeTo")}:
              </span>{" "}
              {formatChangeValue(change.new)}
            </dd>
          </div>
        );
      })}
    </dl>
  );
}
