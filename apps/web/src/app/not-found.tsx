import { Button } from "@/components/ui/button";
import { PageShell } from "@/components/ui/page-shell";
import { t } from "@/i18n/t";

export default function NotFound() {
  return (
    <PageShell className="py-16 text-center">
      <h1 className="text-2xl font-semibold text-foreground">{t("notFound.title")}</h1>
      <p className="mt-2 max-w-md mx-auto text-muted-foreground">{t("notFound.description")}</p>
      <div className="mt-8 flex flex-col flex-wrap items-center justify-center gap-3 sm:flex-row">
        <Button href="/">{t("notFound.home")}</Button>
        <Button href="/doctors" variant="outline">
          {t("nav.doctors")}
        </Button>
        <Button href="/guidance" variant="outline">
          {t("nav.guidance")}
        </Button>
      </div>
    </PageShell>
  );
}
