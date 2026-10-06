import { Button } from "@/components/ui/button";
import { Icon } from "@/components/ui/icons";
import { cn } from "@/lib/cn";
import { t } from "@/i18n/t";

/**
 * „Не сте сигурни каде да се обратите?“: the guidance teaser. White card,
 * apricot compass circle, reading-face body and the ink „Започни“. No 194
 * line here: the emergency numbers live inside the guidance flow itself.
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
      <Button
        href="/guidance"
        size="lg"
        trailingIcon="arrow-right"
        className="mt-5 lg:mt-6"
      >
        {t("home.guideCta")}
      </Button>
    </section>
  );
}
