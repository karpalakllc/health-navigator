import { t } from "@/i18n/t";

export function DisclaimerPageContent() {
  return (
    <div className="mx-auto max-w-3xl space-y-10 px-4 py-12 sm:px-6">
      <header className="space-y-4 text-center">
        <p className="inline-flex rounded-full border border-border bg-muted/40 px-4 py-1 text-xs font-medium text-muted-foreground">
          {t("disclaimerPage.badge")}
        </p>
        <h1 className="text-3xl font-bold tracking-tight sm:text-4xl">{t("disclaimerPage.title")}</h1>
        <p className="text-pretty text-muted-foreground">{t("disclaimerPage.intro")}</p>
      </header>

      <div className="space-y-4">
        <InfoCard
          title={t("disclaimerPage.notDoctorTitle")}
          body={t("disclaimerPage.notDoctorBody")}
          icon="shield"
        />
        <InfoCard
          title={t("disclaimerPage.emergencyTitle")}
          body={t("disclaimerPage.emergencyBody")}
          icon="warning"
        />
        <InfoCard
          title={t("disclaimerPage.illustrativeTitle")}
          body={t("disclaimerPage.illustrativeBody")}
          icon="heart"
        />
      </div>

      <section className="rounded-3xl border border-primary/20 bg-primary/5 px-6 py-10 text-center">
        <p className="text-3xl" aria-hidden>
          📞
        </p>
        <h2 className="mt-3 text-xl font-bold">{t("disclaimerPage.emergencyHeading")}</h2>
        <p className="mt-1 text-sm text-muted-foreground">{t("disclaimerPage.emergencySubheading")}</p>
        <div className="mt-6 grid gap-4 sm:grid-cols-2">
          <EmergencyBox number="194" label={t("disclaimerPage.emergencyMedical")} />
          <EmergencyBox number="112" label={t("disclaimerPage.emergencyGeneral")} />
        </div>
      </section>
    </div>
  );
}

function InfoCard({
  title,
  body,
  icon,
}: {
  title: string;
  body: string;
  icon: "shield" | "warning" | "heart";
}) {
  return (
    <article className="flex gap-4 rounded-2xl border border-border bg-card p-5 shadow-sm">
      <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
        <InfoIcon kind={icon} />
      </span>
      <div>
        <h2 className="font-semibold text-foreground">{title}</h2>
        <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{body}</p>
      </div>
    </article>
  );
}

function EmergencyBox({ number, label }: { number: string; label: string }) {
  return (
    <div className="rounded-2xl border border-primary/15 bg-card px-4 py-5">
      <p className="text-3xl font-bold text-primary">{number}</p>
      <p className="mt-1 text-sm text-muted-foreground">{label}</p>
    </div>
  );
}

function InfoIcon({ kind }: { kind: "shield" | "warning" | "heart" }) {
  if (kind === "warning") {
    return (
      <svg className="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2} aria-hidden>
        <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
      </svg>
    );
  }

  if (kind === "heart") {
    return (
      <svg className="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2} aria-hidden>
        <path strokeLinecap="round" strokeLinejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
      </svg>
    );
  }

  return (
    <svg className="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2} aria-hidden>
      <path strokeLinecap="round" strokeLinejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
    </svg>
  );
}
