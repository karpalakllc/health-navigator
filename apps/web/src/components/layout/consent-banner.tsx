"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useEffect, useId, useRef, useState } from "react";
import { Button } from "@/components/ui/button";
import {
  onConsentSettingsOpen,
  readConsent,
  saveConsent,
} from "@/lib/consent/consent";
import { useConsent } from "@/lib/consent/use-consent";
import { t } from "@/i18n/t";

/*
 * The cookie and statistics consent, in two layers.
 *
 * Layer 1: a calm card at the bottom (role="dialog", aria-modal="false"), the
 * page stays usable behind it. Accept and reject are the same size and weight;
 * „Прилагоди“ opens layer 2.
 *
 * Layer 2: a centred preferences modal (aria-modal="true", focus trap, Escape
 * closes back to where it came from, focus returns) with one switch per
 * category. Necessary is always on; Statistics is off by default, also when
 * the browser sends Global Privacy Control or Do Not Track. More categories
 * are added to CATEGORIES.
 *
 * Shown only after hydration, never on /unsubscribe. The footer link
 * „Поставки за колачиња“ opens layer 2 directly. The banner sits early in the
 * page so keyboard users meet it first; it does not take focus on page load.
 */

type CategoryId = "necessary" | "statistics";

const CATEGORIES: ReadonlyArray<{
  id: CategoryId;
  title: string;
  text: string;
  locked: boolean;
}> = [
  {
    id: "necessary",
    title: t("consent.necessaryTitle"),
    text: t("consent.necessaryText"),
    locked: true,
  },
  {
    id: "statistics",
    title: t("consent.statisticsTitle"),
    text: t("consent.statisticsText"),
    locked: false,
  },
];

const FOCUSABLE =
  'a[href], button:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])';

export function ConsentBanner() {
  const consent = useConsent();
  const pathname = usePathname();
  const opener = useRef<Element | null>(null);
  const [modal, setModal] = useState(false);
  const [statistics, setStatistics] = useState(false);

  useEffect(
    () =>
      onConsentSettingsOpen(() => {
        opener.current = document.activeElement;
        // The switches start from what is stored (off when nothing is).
        setStatistics(readConsent()?.statistics === true);
        setModal(true);
      }),
    [],
  );

  if (consent === undefined || pathname === "/unsubscribe") {
    return null;
  }

  function restoreFocus() {
    const target = opener.current;
    opener.current = null;
    if (target instanceof HTMLElement && document.contains(target)) {
      target.focus();
    }
  }

  function decide(choice: boolean) {
    saveConsent(choice);
    setModal(false);
    restoreFocus();
  }

  function closeModal() {
    setModal(false);
    restoreFocus();
  }

  return (
    <>
      {consent === null ? (
        <Banner
          inert={modal}
          onAccept={() => decide(true)}
          onReject={() => decide(false)}
          onCustomize={(button) => {
            opener.current = button;
            setModal(true);
          }}
        />
      ) : null}
      {modal ? (
        <PreferencesModal
          statistics={statistics}
          onStatistics={setStatistics}
          onSave={() => decide(statistics)}
          onAccept={() => decide(true)}
          onReject={() => decide(false)}
          onClose={closeModal}
        />
      ) : null}
    </>
  );
}

function Banner({
  inert,
  onAccept,
  onReject,
  onCustomize,
}: {
  inert: boolean;
  onAccept: () => void;
  onReject: () => void;
  onCustomize: (button: HTMLElement) => void;
}) {
  const titleId = useId();

  return (
    <div
      role="dialog"
      aria-modal="false"
      aria-labelledby={titleId}
      inert={inert}
      className="fixed inset-x-3 bottom-[calc(var(--tabbar-space,0px)+0.75rem)] z-[60] mx-auto max-w-[720px] rounded-card bg-white p-5 shadow-card ring-1 ring-line lg:bottom-4"
    >
      <h2 id={titleId} className="type-h3 text-ink">
        {t("consent.title")}
      </h2>
      <p className="mt-2 type-body text-ink-2">
        {t("consent.lead")}{" "}
        <Link href="/privacy" className="link-underline">
          {t("consent.more")}
        </Link>
      </p>
      <div className="mt-4 flex flex-col gap-3 sm:flex-row">
        <Button
          variant="primary"
          size="md"
          className="sm:flex-1"
          onClick={onAccept}
        >
          {t("consent.acceptAll")}
        </Button>
        <Button
          variant="primary"
          size="md"
          className="sm:flex-1"
          onClick={onReject}
        >
          {t("consent.decline")}
        </Button>
        <Button
          variant="secondary"
          size="md"
          className="sm:flex-1"
          onClick={(event) => onCustomize(event.currentTarget)}
        >
          {t("consent.customize")}
        </Button>
      </div>
    </div>
  );
}

function PreferencesModal({
  statistics,
  onStatistics,
  onSave,
  onAccept,
  onReject,
  onClose,
}: {
  statistics: boolean;
  onStatistics: (on: boolean) => void;
  onSave: () => void;
  onAccept: () => void;
  onReject: () => void;
  onClose: () => void;
}) {
  const titleId = useId();
  const leadId = useId();
  const dialog = useRef<HTMLDivElement>(null);
  const values: Record<CategoryId, boolean> = { necessary: true, statistics };

  useEffect(() => {
    dialog.current?.focus();
    const previous = document.body.style.overflow;
    document.body.style.overflow = "hidden";

    return () => {
      document.body.style.overflow = previous;
    };
  }, []);

  function onKeyDown(event: React.KeyboardEvent) {
    if (event.key === "Escape") {
      event.stopPropagation();
      onClose();
      return;
    }

    if (event.key !== "Tab" || !dialog.current) return;

    const items = Array.from(
      dialog.current.querySelectorAll<HTMLElement>(FOCUSABLE),
    );
    const first = items[0];
    const last = items[items.length - 1];
    const active = document.activeElement;

    if (event.shiftKey && (active === first || active === dialog.current)) {
      event.preventDefault();
      last?.focus();
    } else if (!event.shiftKey && active === last) {
      event.preventDefault();
      first?.focus();
    }
  }

  return (
    <div className="fixed inset-0 z-[70] flex items-end justify-center bg-ink/40 p-3 sm:items-center">
      <div
        ref={dialog}
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        aria-describedby={leadId}
        tabIndex={-1}
        onKeyDown={onKeyDown}
        className="max-h-full w-full max-w-[560px] overflow-y-auto rounded-sheet bg-white p-5 shadow-card outline-none lg:p-8"
      >
        <h2 id={titleId} className="type-h3 text-ink">
          {t("consent.modalTitle")}
        </h2>
        <p id={leadId} className="mt-2 type-body text-ink-2">
          {t("consent.modalLead")}
        </p>

        <ul className="mt-4 flex flex-col divide-y divide-line">
          {CATEGORIES.map((category) => (
            <li key={category.id} className="py-3">
              <label className="flex min-h-11 items-start justify-between gap-4">
                <span>
                  <span className="block type-label text-ink">
                    {category.title}
                  </span>
                  <span className="block type-meta text-ink-2">
                    {category.text}
                  </span>
                </span>
                <input
                  type="checkbox"
                  role="switch"
                  checked={values[category.id]}
                  disabled={category.locked}
                  onChange={(event) => {
                    if (category.id === "statistics") {
                      onStatistics(event.target.checked);
                    }
                  }}
                  className="mt-1 size-6 shrink-0 accent-ink"
                />
              </label>
            </li>
          ))}
        </ul>

        <div className="mt-5 flex flex-col gap-3">
          <Button variant="primary" size="md" fullWidth onClick={onSave}>
            {t("consent.save")}
          </Button>
          <div className="flex flex-col gap-3 sm:flex-row">
            <Button
              variant="secondary"
              size="md"
              className="sm:flex-1"
              onClick={onAccept}
            >
              {t("consent.acceptAll")}
            </Button>
            <Button
              variant="secondary"
              size="md"
              className="sm:flex-1"
              onClick={onReject}
            >
              {t("consent.declineAll")}
            </Button>
          </div>
        </div>
      </div>
    </div>
  );
}
