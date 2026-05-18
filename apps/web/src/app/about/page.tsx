import type { Metadata } from "next";
import Link from "next/link";
import { PageShell } from "@/components/ui/page-shell";
import { pageMetadata } from "@/lib/metadata";
import { t } from "@/i18n/t";

export const metadata: Metadata = pageMetadata(t("about.title"), t("about.description"));

export default function AboutPage() {
  return (
    <PageShell className="gap-12 py-10">
      <section className="relative overflow-hidden rounded-3xl border border-border bg-gradient-to-br from-primary/8 via-accent/5 to-transparent px-8 py-14 text-center">
        <div className="pointer-events-none absolute -right-8 -top-8 h-40 w-40 rounded-full bg-primary/10 blur-2xl" aria-hidden />
        <div className="pointer-events-none absolute -bottom-10 -left-10 h-48 w-48 rounded-full bg-accent/10 blur-2xl" aria-hidden />
        <p className="text-sm font-medium uppercase tracking-wider text-primary">{t("about.title")}</p>
        <h1 className="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">{t("about.missionTitle")}</h1>
        <p className="mx-auto mt-4 max-w-2xl text-pretty text-lg text-muted-foreground">
          {t("about.missionBody")}
        </p>
      </section>

      <section className="grid gap-6 lg:grid-cols-2">
        <ValueCard title={t("about.goalTitle")} body={t("about.goalBody")} accent="primary" />
        <ValueCard title={t("about.valuesTitle")} body="" accent="accent" list />
      </section>

      <section className="rounded-2xl border border-border bg-card p-8 text-center">
        <p className="text-muted-foreground">{t("about.goalBody")}</p>
        <Link
          href="/disclaimer"
          className="mt-6 inline-flex min-h-[44px] items-center justify-center rounded-xl bg-primary px-6 text-sm font-semibold text-primary-foreground"
        >
          {t("legal.disclaimerTitle")}
        </Link>
      </section>
    </PageShell>
  );
}

function ValueCard({
  title,
  body,
  accent,
  list = false,
}: {
  title: string;
  body: string;
  accent: "primary" | "accent";
  list?: boolean;
}) {
  const values = [
    t("about.valueTrust"),
    t("about.valueClarity"),
    t("about.valueCommunity"),
    t("about.valueAccess"),
  ];

  return (
    <article
      className={`rounded-2xl border p-6 shadow-sm ${
        accent === "primary" ? "border-primary/20 bg-primary/5" : "border-accent/20 bg-accent/5"
      }`}
    >
      <h2 className="text-xl font-semibold">{title}</h2>
      {list ? (
        <ul className="mt-4 space-y-2 text-sm text-muted-foreground">
          {values.map((item) => (
            <li key={item} className="flex gap-2">
              <span className="text-primary" aria-hidden>
                ✓
              </span>
              <span>{item}</span>
            </li>
          ))}
        </ul>
      ) : (
        <p className="mt-3 text-sm leading-relaxed text-muted-foreground">{body}</p>
      )}
    </article>
  );
}
