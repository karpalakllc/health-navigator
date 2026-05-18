import type { ReactNode } from "react";
import Link from "next/link";
import { t } from "@/i18n/t";

export function HomeForumTransparency() {
  return (
    <div className="grid gap-5 lg:grid-cols-[1.05fr_0.95fr]">
      <div className="forum-band-gradient rounded-[1.875rem] p-7 text-white sm:p-8">
        <div>
          <span className="inline-flex min-h-[34px] items-center rounded-full bg-white/12 px-3 text-sm font-extrabold">
            {t("home.forumBandEyebrow")}
          </span>
          <h2 className="mt-3 text-3xl font-black tracking-tight sm:text-4xl">{t("home.forumBandTitle")}</h2>
          <p className="mt-3 max-w-xl text-base leading-relaxed text-white/85">{t("home.forumBandDescription")}</p>
        </div>
        <ul className="mt-6 grid gap-3">
          {[t("home.forumPointModerated"), t("home.forumPointWarnings"), t("home.forumPointClarity")].map(
            (label) => (
              <li
                key={label}
                className="inline-flex min-h-12 w-fit items-center gap-2.5 rounded-full bg-white/12 px-4 text-sm font-bold"
              >
                <CheckIcon />
                <span>{label}</span>
              </li>
            ),
          )}
        </ul>
        <Link
          href="/forum"
          className="mt-6 inline-flex min-h-11 items-center rounded-full bg-white px-5 text-sm font-extrabold text-accent transition hover:bg-white/95"
        >
          {t("home.communityForumCta")} →
        </Link>
      </div>

      <aside className="surface-glass card-lift rounded-[1.875rem] p-7 sm:p-8">
        <span className="text-sm font-extrabold tracking-wide text-[#43515d]">{t("home.transparencyEyebrow")}</span>
        <h3 className="mt-3 text-2xl font-black tracking-tight sm:text-3xl">{t("home.transparencyTitle")}</h3>
        <ul className="mt-6 grid gap-4">
          <TransparencyItem
            icon={<ShieldIcon />}
            title={t("home.transparencyItem1Title")}
            body={t("home.transparencyItem1Body")}
          />
          <TransparencyItem
            icon={<ScaleIcon />}
            title={t("home.transparencyItem2Title")}
            body={t("home.transparencyItem2Body")}
          />
          <TransparencyItem
            icon={<MapIcon />}
            title={t("home.transparencyItem3Title")}
            body={t("home.transparencyItem3Body")}
          />
        </ul>
      </aside>
    </div>
  );
}

function TransparencyItem({
  icon,
  title,
  body,
}: {
  icon: ReactNode;
  title: string;
  body: string;
}) {
  return (
    <li className="grid grid-cols-[42px_1fr] gap-3.5">
      <span className="text-accent">{icon}</span>
      <div>
        <strong className="block text-[0.98rem] font-extrabold text-foreground">{title}</strong>
        <p className="mt-1 text-sm leading-relaxed text-muted-foreground">{body}</p>
      </div>
    </li>
  );
}

function CheckIcon() {
  return (
    <svg className="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" aria-hidden>
      <path d="M5 12l4 4L19 7" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

function ShieldIcon() {
  return (
    <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
      <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" strokeLinecap="round" />
    </svg>
  );
}

function ScaleIcon() {
  return (
    <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
      <path d="M12 3v18M5 8h14M7 8l-2 6h4L7 8zm10 0l-2 6h4l-2-6z" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

function MapIcon() {
  return (
    <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
      <path d="M12 21s-7-4-7-10a7 7 0 1114 0c0 6-7 10-7 10z" strokeLinecap="round" />
      <circle cx="12" cy="11" r="2.5" />
    </svg>
  );
}
