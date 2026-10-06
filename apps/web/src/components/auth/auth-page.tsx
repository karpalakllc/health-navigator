import type { ReactNode } from "react";
import { Card } from "@/components/ui/card";
import { Icon, type IconName } from "@/components/ui/icons";
import { t } from "@/i18n/t";

/**
 * The one auth layout: a centred white card on the cream page. The title sits
 * inside the card, so on a phone the first field is reached without scrolling
 * past a hero. Two quiet reassurance lines follow the card.
 */
export function AuthPage({
  title,
  description,
  children,
}: {
  title: string;
  description?: ReactNode;
  children: ReactNode;
}) {
  return (
    <div className="mx-auto flex w-full max-w-[36rem] flex-col gap-6 px-5 pb-14 pt-4 lg:px-6 lg:pb-20 lg:pt-12">
      <Card
        as="section"
        aria-labelledby="auth-title"
        padding="md"
        className="lg:p-10"
      >
        <header className="mb-6 flex flex-col gap-2">
          <h1 id="auth-title" className="type-h1 text-ink">
            {title}
          </h1>
          {description ? (
            <p className="type-body text-ink-2">{description}</p>
          ) : null}
        </header>
        {children}
      </Card>
      <AuthTrustNote />
    </div>
  );
}

const TRUST: Array<{
  icon: IconName;
  key: "home.trustModerated" | "home.trustInformational";
}> = [
  { icon: "shield-check", key: "home.trustModerated" },
  { icon: "info", key: "home.trustInformational" },
];

function AuthTrustNote() {
  return (
    <ul className="flex flex-col gap-3 px-1">
      {TRUST.map((item) => (
        <li
          key={item.key}
          className="flex items-start gap-3 type-meta text-ink-2"
        >
          <Icon name={item.icon} size={20} className="mt-0.5 text-ink" />
          <span>{t(item.key)}</span>
        </li>
      ))}
    </ul>
  );
}

/** A sub-heading inside the auth card when the form gives way to a message. */
export function AuthStateHeader({
  icon,
  title,
  headingRef,
}: {
  icon: IconName;
  title: string;
  headingRef?: React.Ref<HTMLHeadingElement>;
}) {
  return (
    <div className="flex items-center gap-3">
      <span className="inline-flex size-12 shrink-0 items-center justify-center rounded-full bg-apricot text-ink">
        <Icon name={icon} size={24} />
      </span>
      <h2
        ref={headingRef}
        tabIndex={headingRef ? -1 : undefined}
        className="type-h3 text-ink"
      >
        {title}
      </h2>
    </div>
  );
}
