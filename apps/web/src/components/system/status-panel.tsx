import type { ReactNode } from "react";
import { Card } from "@/components/ui/card";
import { Icon, type IconName } from "@/components/ui/icons";

/**
 * The friendly system-page panel (404, error, maintenance, switched-off
 * module): a centred white card on cream with an apricot icon disc, the h1, a
 * line of explanation and the next actions.
 */
export function StatusPanel({
  icon,
  eyebrow,
  title,
  description,
  details,
  children,
  footer,
}: {
  icon: IconName;
  eyebrow?: ReactNode;
  title: ReactNode;
  description?: ReactNode;
  /** More explanation between the description and the actions. */
  details?: ReactNode;
  /** The actions (buttons), laid out as a row from `sm`. */
  children?: ReactNode;
  /** Anything under the actions, e.g. a search form or a note. */
  footer?: ReactNode;
}) {
  return (
    <div className="mx-auto w-full max-w-[44rem] px-5 pb-14 pt-6 lg:px-6 lg:pb-24 lg:pt-16">
      <Card className="flex flex-col items-start gap-5 lg:p-12">
        <span className="inline-flex size-16 items-center justify-center rounded-full bg-apricot text-ink">
          <Icon name={icon} size={32} />
        </span>
        <div className="flex flex-col gap-2">
          {eyebrow ? (
            <div className="type-meta font-semibold text-ink-2">{eyebrow}</div>
          ) : null}
          <h1 className="type-h1 text-ink">{title}</h1>
          {description ? (
            <p className="type-body text-ink-2">{description}</p>
          ) : null}
        </div>
        {details ?? null}
        {children ? (
          <div className="flex w-full flex-col gap-3 sm:w-auto sm:flex-row sm:flex-wrap">
            {children}
          </div>
        ) : null}
        {footer ? (
          <div className="w-full border-t border-line pt-5">{footer}</div>
        ) : null}
      </Card>
    </div>
  );
}
