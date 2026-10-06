import { t } from "@/i18n/t";

const steps = [
  { titleKey: "home.howStep1Title", bodyKey: "home.howStep1Body" },
  { titleKey: "home.howStep2Title", bodyKey: "home.howStep2Body" },
  { titleKey: "home.howStep3Title", bodyKey: "home.howStep3Body" },
  { titleKey: "home.howStep4Title", bodyKey: "home.howStep4Body" },
] as const;

/**
 * „Како функционира“ — "Од пребарување до подобра одлука": the intro line
 * (informative directory, not a diagnosis or an emergency service) and four
 * numbered steps. An ordered list of white cards with apricot number discs:
 * stacked on mobile, 2 × 2 on tablets, one row of four on desktop.
 */
export function HomeHowItWorksSection({ className }: { className?: string }) {
  return (
    <section aria-labelledby="home-how-title" className={className}>
      <p className="type-meta font-semibold text-ink-2">
        {t("home.howItWorksEyebrow")}
      </p>
      <h2 id="home-how-title" className="type-h2 mt-1 text-ink">
        {t("home.howItWorksTitle")}
      </h2>
      <p className="type-reading measure mt-2 text-ink">
        {t("home.howItWorksDescription")}
      </p>
      <ol className="mt-5 grid gap-3 sm:grid-cols-2 lg:mt-6 lg:grid-cols-4 lg:gap-6">
        {steps.map((step, index) => (
          <li
            key={step.titleKey}
            className="card flex gap-4 p-5 lg:flex-col lg:p-6"
          >
            <span
              aria-hidden="true"
              className="flex size-11 flex-none items-center justify-center rounded-full bg-apricot font-ui text-lg font-bold text-ink"
            >
              {index + 1}
            </span>
            <div className="min-w-0">
              <h3 className="type-h3 text-ink">{t(step.titleKey)}</h3>
              <p className="type-body mt-1 text-ink-2 lg:mt-2">
                {t(step.bodyKey)}
              </p>
            </div>
          </li>
        ))}
      </ol>
    </section>
  );
}
