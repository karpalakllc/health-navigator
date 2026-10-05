"use client";

import * as Sentry from "@sentry/nextjs";
import { useEffect } from "react";
import { t } from "@/i18n/t";

/**
 * Last-resort boundary for errors thrown by the root layout itself (header,
 * footer, maintenance gate), which app/error.tsx cannot catch because it sits
 * inside that layout. Without this file such an error showed Next's bare,
 * unbranded error page on every route.
 *
 * It replaces the root layout, so it brings its own <html>/<body> and gets no
 * global CSS — hence the inline styles (style-src allows 'unsafe-inline').
 */
export default function GlobalError({
  error,
  retry,
}: {
  error: Error & { digest?: string };
  retry: () => void;
}) {
  useEffect(() => {
    Sentry.captureException(error);
  }, [error]);

  return (
    <html lang="mk">
      <body
        style={{
          margin: 0,
          minHeight: "100vh",
          display: "flex",
          alignItems: "center",
          justifyContent: "center",
          padding: "1.5rem",
          fontFamily:
            "system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif",
          background: "#f7f9fa",
          color: "#1d2730",
          textAlign: "center",
        }}
      >
        <title>{`${t("errors.title")} · ${t("meta.title")}`}</title>
        <main style={{ maxWidth: "32rem" }}>
          <p
            style={{
              margin: 0,
              fontSize: "0.875rem",
              fontWeight: 800,
              color: "#ff5757",
            }}
          >
            {t("meta.title")}
          </p>
          <h1 style={{ margin: "0.75rem 0 0", fontSize: "1.5rem" }}>
            {t("errors.title")}
          </h1>
          <p style={{ margin: "0.5rem 0 0", color: "#5a6773" }}>
            {t("errors.description")}
          </p>
          <div
            style={{
              marginTop: "2rem",
              display: "flex",
              flexWrap: "wrap",
              gap: "0.75rem",
              justifyContent: "center",
            }}
          >
            <button
              type="button"
              onClick={retry}
              style={{
                minHeight: 44,
                padding: "0 1.25rem",
                border: 0,
                borderRadius: 999,
                background: "#ff5757",
                color: "#fff",
                fontWeight: 700,
                cursor: "pointer",
              }}
            >
              {t("errors.retry")}
            </button>
            {/* A plain link on purpose: a full reload is the point here. */}
            {/* eslint-disable-next-line @next/next/no-html-link-for-pages */}
            <a
              href="/"
              style={{
                display: "inline-flex",
                alignItems: "center",
                minHeight: 44,
                padding: "0 1.25rem",
                borderRadius: 999,
                border: "1px solid #d9e0e5",
                color: "#1d2730",
                fontWeight: 700,
                textDecoration: "none",
              }}
            >
              {t("errors.home")}
            </a>
          </div>
        </main>
      </body>
    </html>
  );
}
