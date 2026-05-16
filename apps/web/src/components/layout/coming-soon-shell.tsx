import type { ReactNode } from "react";
import { PageHeader } from "@/components/directory/page-header";
import { PageShell } from "@/components/ui/page-shell";
import { t } from "@/i18n/t";

type ComingSoonShellProps = {
  title: string;
  description: string;
  children?: ReactNode;
};

export function ComingSoonShell({ title, description, children }: ComingSoonShellProps) {
  return (
    <PageShell className="pb-20">
      <PageHeader title={title} description={description} />
      <div className="relative overflow-hidden rounded-3xl border border-border">
        <div className="pointer-events-none select-none blur-md brightness-95 saturate-50" aria-hidden>
          {children ?? (
            <div className="grid gap-4 p-8 sm:grid-cols-2 lg:grid-cols-3">
              {Array.from({ length: 6 }).map((_, index) => (
                <div
                  key={index}
                  className="h-32 rounded-2xl bg-gradient-to-br from-muted/80 to-muted/40"
                />
              ))}
            </div>
          )}
        </div>
        <div className="absolute inset-0 flex items-center justify-center bg-background/55 p-6 backdrop-blur-[2px]">
          <div className="max-w-lg rounded-2xl border border-border bg-card/95 px-6 py-8 text-center shadow-lg">
            <p className="text-xs font-semibold uppercase tracking-wide text-primary">
              {t("comingSoon.badge")}
            </p>
            <h2 className="mt-2 text-2xl font-bold tracking-tight">{t("comingSoon.title")}</h2>
            <p className="mt-3 text-sm text-muted-foreground">{t("comingSoon.body")}</p>
          </div>
        </div>
      </div>
    </PageShell>
  );
}
