import type { ReactNode } from "react";
import Link from "next/link";
import { DirectoryHero } from "@/components/design/directory-hero";
import { TrustRibbon } from "@/components/design/trust-ribbon";
import { PageShell } from "@/components/ui/page-shell";
import { PageHeroBleed } from "@/components/design/page-hero-bleed";
import { t } from "@/i18n/t";

type ComingSoonModule = "products" | "pharmacies";

type ComingSoonShellProps = {
  title: string;
  description: string;
  module?: ComingSoonModule;
};

export function ComingSoonShell({ title, description, module = "products" }: ComingSoonShellProps) {
  const body =
    module === "pharmacies" ? t("comingSoon.pharmaciesBody") : t("comingSoon.productsBody");
  const moduleTitle =
    module === "pharmacies" ? t("comingSoon.pharmaciesTitle") : t("comingSoon.productsTitle");

  return (
    <>
      <PageHeroBleed>
        <DirectoryHero
          badge={t("comingSoon.badge")}
          title={title || moduleTitle}
          description={description || body}
        />
        <TrustRibbon
          variant="compact"
          columns={3}
          items={[
            { text: t("home.trustInformational"), icon: <InfoIcon />, tone: "red" },
            { text: t("home.trustModerated"), icon: <ShieldIcon />, tone: "teal" },
            { text: t("home.trustEmergency"), icon: <AlertIcon />, tone: "red" },
          ]}
        />
      </PageHeroBleed>

      <PageShell className="pb-16">
        <div className="content-card rounded-[1.625rem] p-8 text-center">
          <p className="text-xs font-extrabold uppercase tracking-wide text-primary">
            {t("comingSoon.badge")}
          </p>
          <h2 className="mt-2 text-2xl font-black tracking-tight">{t("comingSoon.title")}</h2>
          <p className="mx-auto mt-3 max-w-xl text-sm leading-relaxed text-muted-foreground">
            {t("comingSoon.body")}
          </p>
          <div className="mt-8 flex flex-wrap justify-center gap-3">
            <QuickLink href="/doctors">{t("comingSoon.exploreDoctors")}</QuickLink>
            <QuickLink href="/facilities">{t("comingSoon.exploreFacilities")}</QuickLink>
            <QuickLink href="/forum">{t("comingSoon.exploreForum")}</QuickLink>
          </div>
        </div>
      </PageShell>
    </>
  );
}

function QuickLink({ href, children }: { href: string; children: React.ReactNode }) {
  return (
    <Link
      href={href}
      className="inline-flex min-h-[44px] items-center justify-center rounded-full border border-border bg-card px-5 text-sm font-extrabold text-foreground hover:border-primary/30 hover:text-primary"
    >
      {children}
    </Link>
  );
}

function InfoIcon() {
  return (
    <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
      <circle cx="12" cy="12" r="9" />
      <path d="M12 10v6M12 7h.01" strokeLinecap="round" />
    </svg>
  );
}

function ShieldIcon() {
  return (
    <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
      <path d="M12 3l8 4v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7l8-4z" strokeLinejoin="round" />
    </svg>
  );
}

function AlertIcon() {
  return (
    <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
      <path d="M12 9v4M12 17h.01" strokeLinecap="round" />
      <path d="M10.3 4.3h3.4L20 18H4L10.3 4.3z" strokeLinejoin="round" />
    </svg>
  );
}
