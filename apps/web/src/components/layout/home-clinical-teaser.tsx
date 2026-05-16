import Link from "next/link";
import { Card } from "@/components/ui/card";
import { t } from "@/i18n/t";

export function HomeClinicalTeaser() {
  return (
    <ul className="grid gap-4 sm:grid-cols-2">
      <li>
        <Link href="/facilities?type=hospital" className="block h-full">
          <Card className="card-hover h-full p-5">
            <h3 className="font-semibold text-foreground">{t("home.clinicalHospitalsTitle")}</h3>
            <p className="mt-2 text-sm text-muted-foreground">{t("home.clinicalHospitalsDesc")}</p>
          </Card>
        </Link>
      </li>
      <li>
        <Link href="/facilities?type=clinic" className="block h-full">
          <Card className="card-hover h-full p-5">
            <h3 className="font-semibold text-foreground">{t("home.clinicalClinicsTitle")}</h3>
            <p className="mt-2 text-sm text-muted-foreground">{t("home.clinicalClinicsDesc")}</p>
          </Card>
        </Link>
      </li>
    </ul>
  );
}
