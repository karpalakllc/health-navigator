"use client";

import * as Sentry from "@sentry/nextjs";
import { useEffect } from "react";
import { Button } from "@/components/ui/button";
import { StatusPanel } from "@/components/system/status-panel";
import { t } from "@/i18n/t";
import { isSettingsUnavailable } from "@/lib/api/public-settings";

export default function Error({
  error,
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  useEffect(() => {
    // Without this the boundary swallows the error: nothing else reports
    // render failures that reach it. The exception is a settings outage: it
    // lands here on every page view while it lasts, and settings.ts already
    // reports it once a minute (recognised by digest — production masks the
    // name and message).
    if (!isSettingsUnavailable(error)) {
      Sentry.captureException(error);
    }
    console.error(error);
  }, [error]);

  return (
    <>
      {/* React hoists this into <head>. The status is already 200 once the
          page streams, so this is what keeps a transient failure (an API
          blip, unreadable settings) out of the index. */}
      <meta name="robots" content="noindex" />
      <StatusPanel
        icon="alert-triangle"
        title={t("errors.title")}
        description={t("errors.description")}
      >
        <Button type="button" leadingIcon="rotate-ccw" onClick={reset}>
          {t("errors.retry")}
        </Button>
        <Button href="/" variant="secondary" leadingIcon="home">
          {t("errors.home")}
        </Button>
        <Button href="/guidance" variant="secondary">
          {t("nav.guidance")}
        </Button>
      </StatusPanel>
    </>
  );
}
