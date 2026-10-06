"use client";

import { forwardRef } from "react";
import { focusField } from "@/lib/form-validation";
import { Icon } from "@/components/ui/icons";
import { t } from "@/i18n/t";

export type ErrorSummaryItem = {
  /** The field's id: the link points at it and focuses it. */
  id: string;
  label: string;
  message: string;
};

/**
 * The form-level error summary (GOV.UK pattern): an alert that names every
 * rejected field with its message, each a link that moves focus to the field.
 * The same messages also sit under each field (FieldError). Ink + icon, never
 * red. Focusable (tabIndex -1) so the form can move focus to it on submit.
 */
export const ErrorSummary = forwardRef<
  HTMLDivElement,
  { title?: string; items: ErrorSummaryItem[] }
>(function ErrorSummary({ title, items }, ref) {
  return (
    <div
      ref={ref}
      tabIndex={-1}
      role="alert"
      className="flex flex-col gap-3 rounded-input border-2 border-ink bg-white p-4"
    >
      <p className="flex items-start gap-2 type-body font-semibold text-ink">
        <Icon name="alert-triangle" size={20} className="mt-0.5" />
        <span>
          {`${t("ui.errorPrefix")} `}
          {title ?? t("ui.errorSummaryTitle")}
        </span>
      </p>
      {items.length > 0 ? (
        <ul className="flex flex-col gap-1 pl-7">
          {items.map((item) => (
            <li key={item.id} className="flex flex-col pb-1">
              {/* The link is named by the label alone; the message follows. */}
              <a
                href={`#${item.id}`}
                onClick={(event) => {
                  event.preventDefault();
                  focusField(item.id);
                }}
                className="link-underline inline-flex min-h-12 items-center self-start type-body font-semibold text-ink"
              >
                {item.label}
              </a>
              {item.message ? (
                <span className="type-meta text-ink-2">{item.message}</span>
              ) : null}
            </li>
          ))}
        </ul>
      ) : null}
    </div>
  );
});
