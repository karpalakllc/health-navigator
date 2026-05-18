import { t } from "@/i18n/t";

/** Public-facing facility kind for cards (e.g. „Приватна клиника“). */
export function facilityKindLabel(type: string): string {
  switch (type) {
    case "clinic":
      return t("facilities.kindPrivateClinic");
    case "hospital":
      return t("facilities.kindPublicHospital");
    case "laboratory":
      return t("facilities.kindLaboratory");
    default:
      return facilityTypeLabel(type);
  }
}

export function facilityTypeLabel(type: string): string {
  switch (type) {
    case "clinic":
      return t("facilities.typeClinic");
    case "hospital":
      return t("facilities.typeHospital");
    case "laboratory":
      return t("facilities.typeLaboratory");
    case "pharmacy":
      return t("nav.pharmacies");
    default:
      return type;
  }
}

export function facilityPublicPath(type: string, slug: string): string {
  if (type === "pharmacy") {
    return `/pharmacies/${slug}`;
  }

  return `/facilities/${slug}`;
}
