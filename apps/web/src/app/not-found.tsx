import { StatusPanel } from "@/components/system/status-panel";
import { Button } from "@/components/ui/button";
import { t } from "@/i18n/t";

export default function NotFound() {
  return (
    <StatusPanel
      icon="compass"
      eyebrow={t("notFound.eyebrow")}
      title={t("notFound.title")}
      description={t("notFound.description")}
      footer={
        // A plain GET form: the search works before (and without) hydration.
        <form
          action="/search"
          method="get"
          role="search"
          aria-label={t("search.title")}
          className="flex flex-col gap-3"
        >
          <label htmlFor="not-found-q" className="type-label text-ink">
            {t("notFound.searchHint")}
          </label>
          <div className="flex flex-col gap-3 sm:flex-row">
            <input
              id="not-found-q"
              type="search"
              name="q"
              autoComplete="off"
              placeholder={t("nav.searchWhatPlaceholder")}
              className="field-control w-full sm:flex-1"
            />
            <Button type="submit" size="lg" leadingIcon="search">
              {t("nav.searchSubmit")}
            </Button>
          </div>
        </form>
      }
    >
      <Button href="/" leadingIcon="home">
        {t("notFound.home")}
      </Button>
      <Button href="/doctors" variant="secondary">
        {t("nav.doctors")}
      </Button>
      <Button href="/guidance" variant="secondary">
        {t("nav.guidance")}
      </Button>
    </StatusPanel>
  );
}
