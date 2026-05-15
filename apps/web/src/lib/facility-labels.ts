import { t } from "@/i18n/t";

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
