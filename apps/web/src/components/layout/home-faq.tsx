"use client";

import { useState } from "react";
import { SectionHeading } from "@/components/design/section-heading";
import { t } from "@/i18n/t";

const faqKeys = [
  { q: "home.faq1Question" as const, a: "home.faq1Answer" as const },
  { q: "home.faq2Question" as const, a: "home.faq2Answer" as const },
  { q: "home.faq3Question" as const, a: "home.faq3Answer" as const },
] as const;

export function HomeFaq() {
  const [openIndex, setOpenIndex] = useState<number | null>(null);

  return (
    <section>
      <SectionHeading eyebrow={t("home.faqEyebrow")} eyebrowVariant="pill" title={t("home.faqTitle")} />
      <div className="grid gap-3.5">
        {faqKeys.map((item, index) => (
          <details
            key={item.q}
            open={openIndex === index}
            className="surface-glass card-lift group overflow-hidden rounded-3xl"
            onToggle={(event) => {
              const details = event.currentTarget;
              if (details.open) {
                setOpenIndex(index);
              } else if (openIndex === index) {
                setOpenIndex(null);
              }
            }}
          >
            <summary className="flex cursor-pointer list-none items-center justify-between gap-3 px-6 py-5 text-base font-extrabold marker:content-none [&::-webkit-details-marker]:hidden">
              {t(item.q)}
              <PlusIcon className="h-5 w-5 shrink-0 text-muted-foreground transition group-open:rotate-45" />
            </summary>
            <p className="px-6 pb-5 text-sm leading-relaxed text-muted-foreground">{t(item.a)}</p>
          </details>
        ))}
      </div>
    </section>
  );
}

function PlusIcon({ className }: { className?: string }) {
  return (
    <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
      <path d="M12 5v14M5 12h14" strokeLinecap="round" />
    </svg>
  );
}
