import type { ReactNode } from "react";
import { t } from "@/i18n/t";

function IconWrap({
  children,
  className,
}: {
  children: ReactNode;
  className?: string;
}) {
  return (
    <span
      className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-border bg-card ${className ?? ""}`}
    >
      {children}
    </span>
  );
}

export function HomeTrustStrip() {
  const items = [
    {
      key: "moderated",
      text: t("home.trustModerated"),
      icon: (
        <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5} aria-hidden>
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"
          />
        </svg>
      ),
      tone: "text-primary",
    },
    {
      key: "info",
      text: t("home.trustInformational"),
      icon: (
        <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5} aria-hidden>
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"
          />
        </svg>
      ),
      tone: "text-accent",
    },
    {
      key: "emergency",
      text: t("home.trustEmergency"),
      icon: (
        <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5} aria-hidden>
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z"
          />
        </svg>
      ),
      tone: "text-amber-700 dark:text-amber-500",
    },
  ] as const;

  return (
    <section
      aria-label={t("home.trustAriaLabel")}
      className="rounded-2xl border border-border bg-muted/40 px-4 py-5 sm:px-6"
    >
      <ul className="grid gap-5 sm:grid-cols-3 sm:gap-6">
        {items.map((item) => (
          <li key={item.key} className="flex gap-3">
            <IconWrap className={item.tone}>{item.icon}</IconWrap>
            <p className="text-sm leading-snug text-muted-foreground">{item.text}</p>
          </li>
        ))}
      </ul>
    </section>
  );
}
