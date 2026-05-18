import { t } from "@/i18n/t";

export function SiteMaintenancePage({ message }: { message: string | null }) {
  return (
    <div className="flex min-h-[70vh] flex-col items-center justify-center px-6 py-16 text-center">
      <p className="rounded-full border border-border bg-muted/50 px-4 py-1 text-xs font-medium text-muted-foreground">
        {t("maintenance.badge")}
      </p>
      <h1 className="mt-6 max-w-lg text-3xl font-bold tracking-tight">{t("maintenance.title")}</h1>
      <p className="mt-4 max-w-xl text-pretty text-muted-foreground">
        {message?.trim() || t("maintenance.defaultMessage")}
      </p>
      <p className="mt-6 text-sm text-muted-foreground">{t("maintenance.backSoon")}</p>
    </div>
  );
}
