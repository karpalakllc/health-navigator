import { Button } from "@/components/ui/button";
import { Icon } from "@/components/ui/icons";
import { cn } from "@/lib/cn";
import { t } from "@/i18n/t";

/**
 * „Не сте сигурни каде да се обратите?“: the guidance teaser. White card,
 * apricot compass circle, reading-face body, ink „Започни“ and the 194 line.
 */
export function HomeGuidanceCard({ className }: { className?: string }) {
  return (
    <section
      aria-labelledby="home-guide-title"
      className={cn("card p-6 lg:p-8", className)}
    >
      <span className="flex size-11 items-center justify-center rounded-full bg-apricot text-ink">
        <Icon name="compass" />
      </span>
      <h2 id="home-guide-title" className="type-h2 mt-4 text-ink lg:mt-5">
        {t("home.guideTitle")}
      </h2>
      <p className="type-reading mt-2 text-ink lg:mt-3">
        {t("home.guideBody")}
      </p>
      <div className="mt-5 flex flex-col items-start gap-4 lg:mt-6 lg:flex-row lg:items-center lg:gap-5">
        <Button href="/guidance" size="lg" trailingIcon="arrow-right">
          {t("home.guideCta")}
        </Button>
        <p className="type-meta text-ink-2">
          {t("home.guideEmergencyLead")}{" "}
          <a
            href="tel:194"
            className="inline-flex min-h-12 items-center font-semibold text-ink underline underline-offset-[3px] lg:min-h-0"
          >
            194
          </a>
          .
        </p>
      </div>
    </section>
  );
}
