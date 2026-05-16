import { Card } from "@/components/ui/card";
import { t } from "@/i18n/t";

const steps = [
  { titleKey: "home.howStep1Title" as const, bodyKey: "home.howStep1Body" as const },
  { titleKey: "home.howStep2Title" as const, bodyKey: "home.howStep2Body" as const },
  { titleKey: "home.howStep3Title" as const, bodyKey: "home.howStep3Body" as const },
  { titleKey: "home.howStep4Title" as const, bodyKey: "home.howStep4Body" as const },
];

export function HomeHowItWorks() {
  return (
    <ul className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
      {steps.map((step, i) => (
        <li key={step.titleKey}>
          <Card className="h-full border-border/80 bg-card/80 p-5">
            <span className="text-xs font-semibold uppercase tracking-wide text-primary">
              {i + 1}
            </span>
            <h3 className="mt-2 font-semibold text-foreground">{t(step.titleKey)}</h3>
            <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{t(step.bodyKey)}</p>
          </Card>
        </li>
      ))}
    </ul>
  );
}
