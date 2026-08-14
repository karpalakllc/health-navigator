"use client";

import * as Sentry from "@sentry/nextjs";
import { useEffect } from "react";
import { Button } from "@/components/ui/button";
import { PageShell } from "@/components/ui/page-shell";
import { t } from "@/i18n/t";

export default function Error({
  error,
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  useEffect(() => {
    // Without this the boundary swallows the error: nothing else reports
    // render failures that reach it.
    Sentry.captureException(error);
    console.error(error);
  }, [error]);

  return (
    <PageShell className="py-16 text-center">
      <h1 className="text-2xl font-semibold text-foreground">
        {t("errors.title")}
      </h1>
      <p className="mt-2 text-muted-foreground">{t("errors.description")}</p>
      <div className="mt-8 flex flex-wrap justify-center gap-3">
        <Button type="button" onClick={reset}>
          {t("errors.retry")}
        </Button>
        <Button href="/" variant="outline">
          {t("errors.home")}
        </Button>
        <Button href="/guidance" variant="outline">
          {t("nav.guidance")}
        </Button>
      </div>
    </PageShell>
  );
}
