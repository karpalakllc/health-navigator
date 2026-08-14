import { t } from "@/i18n/t";

const steps = [
  {
    titleKey: "home.howStep1Title" as const,
    bodyKey: "home.howStep1Body" as const,
  },
  {
    titleKey: "home.howStep2Title" as const,
    bodyKey: "home.howStep2Body" as const,
  },
  {
    titleKey: "home.howStep3Title" as const,
    bodyKey: "home.howStep3Body" as const,
  },
  {
    titleKey: "home.howStep4Title" as const,
    bodyKey: "home.howStep4Body" as const,
  },
];

export function HomeHowItWorks() {
  return (
    <ul className="grid gap-[18px] md:grid-cols-2 xl:grid-cols-4">
      {steps.map((step, i) => (
        <li key={step.titleKey}>
          <article className="h-full rounded-3xl border border-border/60 bg-white/50 p-[22px]">
            <span className="step-no-gradient mb-3.5 inline-flex h-[42px] w-[42px] items-center justify-center rounded-2xl text-sm font-black text-white">
              {i + 1}
            </span>
            <h3 className="text-[1.08rem] font-extrabold tracking-tight text-foreground">
              {t(step.titleKey)}
            </h3>
            <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
              {t(step.bodyKey)}
            </p>
          </article>
        </li>
      ))}
    </ul>
  );
}
