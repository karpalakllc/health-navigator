"use client";

import * as Sentry from "@sentry/nextjs";
import { useEffect } from "react";
import { t } from "@/i18n/t";

/*
 * D2a colours, written out: this page gets no global CSS (see below). The
 * <style> element is allowed by the CSP (style-src keeps 'unsafe-inline').
 */
const CSS = `
  .ge-body { margin: 0; min-height: 100vh; display: flex; flex-direction: column;
    background: #fbf6f1; color: #2a2220;
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
    font-size: 18px; line-height: 1.5; }
  .ge-header { display: flex; align-items: center; gap: 10px; padding: 16px 20px;
    font-weight: 700; font-size: 20px; }
  .ge-mark { display: inline-flex; width: 32px; height: 32px; border-radius: 999px;
    background: #ff5757; align-items: center; justify-content: center; }
  .ge-main { flex: 1; display: flex; justify-content: center; padding: 8px 20px 56px; }
  .ge-card { box-sizing: border-box; width: 100%; max-width: 40rem; align-self: flex-start;
    margin-top: 24px; background: #fff; border-radius: 20px; padding: 24px;
    box-shadow: 0 1px 2px rgb(42 34 32 / 0.06), 0 8px 24px rgb(42 34 32 / 0.08); }
  .ge-card h1 { margin: 0; font-size: 28px; line-height: 34px; font-weight: 700; }
  .ge-card p { margin: 8px 0 0; color: #5e514b; }
  .ge-actions { margin-top: 24px; display: flex; flex-wrap: wrap; gap: 12px; }
  .ge-btn { display: inline-flex; align-items: center; justify-content: center;
    min-height: 48px; padding: 0 20px; border-radius: 999px; border: 0;
    font: inherit; font-weight: 600; font-size: 17px; text-decoration: none; cursor: pointer; }
  .ge-primary { background: #2a2220; color: #fff; }
  .ge-primary:hover { background: #000; }
  .ge-secondary { background: #fff; color: #2a2220; box-shadow: inset 0 0 0 1.5px #2a2220; }
  .ge-secondary:hover { background: #f6ebe2; }
  .ge-card .ge-note { margin-top: 24px; padding-top: 16px; border-top: 1px solid #eaded5;
    font-size: 16px; color: #5e514b; }
  .ge-card .ge-note a { color: #2a2220; font-weight: 700; text-decoration: underline;
    text-decoration-thickness: 2px; text-underline-offset: 4px; }
  .ge-body :focus-visible { outline: 3px solid #0b0c0c; outline-offset: 0;
    box-shadow: 0 0 0 6px #ffdd00; }
  @media (min-width: 1024px) {
    .ge-header { padding: 24px; }
    .ge-card { margin-top: 64px; padding: 48px; }
    .ge-card h1 { font-size: 40px; line-height: 46px; }
  }
`;

/**
 * Last-resort boundary for errors thrown by the root layout itself (header,
 * footer, maintenance gate), which app/error.tsx cannot catch because it sits
 * inside that layout. Without this file such an error showed Next's bare,
 * unbranded error page on every route.
 *
 * It replaces the root layout, so it brings its own <html>/<body> and gets no
 * global CSS — hence the inline <style>.
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
      <body className="ge-body">
        <title>{`${t("errors.title")} · ${t("meta.title")}`}</title>
        <style>{CSS}</style>
        <header className="ge-header">
          <span className="ge-mark" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
              <path
                d="M12 4v16M4 12h16"
                stroke="#fff"
                strokeWidth="4"
                strokeLinecap="round"
              />
            </svg>
          </span>
          <span>{t("nav.wordmark")}</span>
        </header>
        <main className="ge-main">
          <div className="ge-card">
            <h1>{t("errors.title")}</h1>
            <p>{t("errors.description")}</p>
            <div className="ge-actions">
              <button
                type="button"
                onClick={retry}
                className="ge-btn ge-primary"
              >
                {t("errors.retry")}
              </button>
              {/* A plain link on purpose: a full reload is the point here. */}
              {/* eslint-disable-next-line @next/next/no-html-link-for-pages */}
              <a href="/" className="ge-btn ge-secondary">
                {t("errors.home")}
              </a>
            </div>
            {/* The header's „Итно 194“ pill is gone with the layout. */}
            <p className="ge-note">
              {t("footer.emergency")} <a href="tel:194">194</a>{" "}
              {t("footer.emergencyOr")} <a href="tel:112">112</a>{" "}
              {t("footer.emergencyEnd")}
            </p>
          </div>
        </main>
      </body>
    </html>
  );
}
