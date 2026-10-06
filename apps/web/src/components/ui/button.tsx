import Link from "next/link";
import type { ReactNode } from "react";
import { Icon, type IconName } from "@/components/ui/icons";
import { cn } from "@/lib/cn";

/*
 * The one button family. Every action is an ink pill (primary); secondary is
 * a white pill with a 1.5px ink ring; soft is sand; white sits on apricot
 * surfaces. Never coral, never emergency red (that is EmergencyPill).
 *
 * Styles are the .btn* classes in globals.css (components layer), so any
 * utility passed in `className` overrides them.
 */
const variantClass = {
  primary: "btn-primary",
  secondary: "btn-secondary",
  soft: "btn-soft",
  ghost: "btn-ghost",
  white: "btn-white",
  /** legacy — remove after page migration (= secondary) */
  outline: "btn-secondary",
} as const;

const sizeClass = {
  /** 44px — chips rows / dense desktop rows only. */
  sm: "btn-sm",
  /** 48px — the default; the mobile minimum. */
  md: "btn-md",
  /** 56px mobile / 52px desktop — the page's main action. */
  lg: "btn-lg",
} as const;

export type ButtonVariant = keyof typeof variantClass;
export type ButtonSize = keyof typeof sizeClass;

type CommonProps = {
  variant?: ButtonVariant;
  size?: ButtonSize;
  /** Full width (mobile primary actions). */
  fullWidth?: boolean;
  leadingIcon?: IconName;
  trailingIcon?: IconName;
  className?: string;
  children: ReactNode;
};

type ButtonAsButton = CommonProps &
  Omit<React.ButtonHTMLAttributes<HTMLButtonElement>, "children"> & {
    href?: undefined;
    /** Shows a spinner, sets aria-busy and blocks clicks; the label stays. */
    loading?: boolean;
  };

type ButtonAsLink = CommonProps &
  Omit<React.AnchorHTMLAttributes<HTMLAnchorElement>, "children" | "href"> & {
    href: string;
    type?: never;
    loading?: never;
  };

export type ButtonProps = ButtonAsButton | ButtonAsLink;

export function buttonClassName({
  variant = "primary",
  size = "md",
  fullWidth = false,
  className,
}: {
  variant?: ButtonVariant;
  size?: ButtonSize;
  fullWidth?: boolean;
  className?: string;
} = {}): string {
  return cn(
    "btn",
    variantClass[variant],
    sizeClass[size],
    fullWidth && "w-full",
    className,
  );
}

function Content({
  leadingIcon,
  trailingIcon,
  loading,
  children,
}: {
  leadingIcon?: IconName;
  trailingIcon?: IconName;
  loading?: boolean;
  children: ReactNode;
}) {
  return (
    <>
      {loading ? (
        <Spinner />
      ) : leadingIcon ? (
        <Icon name={leadingIcon} size={20} />
      ) : null}
      <span>{children}</span>
      {trailingIcon ? <Icon name={trailingIcon} size={20} /> : null}
    </>
  );
}

type LinkRest = Omit<
  React.AnchorHTMLAttributes<HTMLAnchorElement>,
  "children" | "href"
> & { href: string };
type ButtonRest = Omit<
  React.ButtonHTMLAttributes<HTMLButtonElement>,
  "children"
> & { loading?: boolean };

const PLAIN_HREF = /^(tel:|mailto:|https?:)/;

export function Button({
  variant,
  size,
  fullWidth,
  leadingIcon,
  trailingIcon,
  className,
  children,
  ...rest
}: ButtonProps) {
  const classes = buttonClassName({ variant, size, fullWidth, className });

  if (typeof rest.href === "string") {
    const { href, ...anchorProps } = rest as LinkRest;
    const content = (
      <Content leadingIcon={leadingIcon} trailingIcon={trailingIcon}>
        {children}
      </Content>
    );

    // tel:, mailto: and external links are plain anchors; site paths use Link.
    return PLAIN_HREF.test(href) ? (
      <a href={href} className={classes} {...anchorProps}>
        {content}
      </a>
    ) : (
      <Link href={href} className={classes} {...anchorProps}>
        {content}
      </Link>
    );
  }

  const {
    loading,
    type = "button",
    onClick,
    ...buttonProps
  } = rest as ButtonRest;

  return (
    <button
      type={type}
      className={classes}
      aria-busy={loading || undefined}
      onClick={loading ? (event) => event.preventDefault() : onClick}
      {...buttonProps}
    >
      <Content
        leadingIcon={leadingIcon}
        trailingIcon={trailingIcon}
        loading={loading}
      >
        {children}
      </Content>
    </button>
  );
}

function Spinner() {
  return (
    <svg
      width={20}
      height={20}
      viewBox="0 0 24 24"
      fill="none"
      aria-hidden="true"
      focusable="false"
      className="shrink-0 motion-safe:animate-spin"
    >
      <circle
        cx="12"
        cy="12"
        r="9"
        stroke="currentColor"
        strokeOpacity="0.3"
        strokeWidth="2.5"
      />
      <path
        d="M21 12a9 9 0 0 0-9-9"
        stroke="currentColor"
        strokeWidth="2.5"
        strokeLinecap="round"
      />
    </svg>
  );
}

type IconButtonCommon = {
  icon: IconName;
  /** Required: the accessible name (an icon alone has none). */
  label: string;
  variant?: "ghost" | "soft" | "secondary" | "primary" | "white";
  /** 48 (default) or 44 (dense rows) or 56 (the raised search tab). */
  size?: 44 | 48 | 56;
  className?: string;
};

type IconButtonProps =
  | (IconButtonCommon &
      Omit<
        React.ButtonHTMLAttributes<HTMLButtonElement>,
        "children" | "aria-label"
      > & { href?: undefined })
  | (IconButtonCommon &
      Omit<
        React.AnchorHTMLAttributes<HTMLAnchorElement>,
        "children" | "aria-label" | "href"
      > & { href: string });

const iconButtonSize = { 44: "size-11", 48: "size-12", 56: "size-14" } as const;

/** A round icon-only control. `label` becomes aria-label (required). */
export function IconButton({
  icon,
  label,
  variant = "ghost",
  size = 48,
  className,
  ...rest
}: IconButtonProps) {
  const classes = cn(
    "btn btn-icon",
    variantClass[variant],
    iconButtonSize[size],
    className,
  );

  if (typeof rest.href === "string") {
    const { href, ...anchorProps } = rest as LinkRest;
    return (
      <Link href={href} aria-label={label} className={classes} {...anchorProps}>
        <Icon name={icon} size={24} />
      </Link>
    );
  }

  const { type = "button", ...buttonProps } = rest as ButtonRest;

  return (
    <button type={type} aria-label={label} className={classes} {...buttonProps}>
      <Icon name={icon} size={24} />
    </button>
  );
}

/** Ink text link with the coral underline (decorative; ink carries contrast). */
export function TextLink({
  href,
  children,
  className,
  trailingIcon,
  ...rest
}: Omit<React.AnchorHTMLAttributes<HTMLAnchorElement>, "href"> & {
  href: string;
  trailingIcon?: IconName;
}) {
  const classes = cn(
    "link-underline inline-flex min-h-12 items-center gap-1.5 font-semibold text-ink hover:text-black",
    className,
  );
  const content = (
    <>
      {children}
      {trailingIcon ? <Icon name={trailingIcon} size={20} /> : null}
    </>
  );

  return PLAIN_HREF.test(href) ? (
    <a href={href} className={classes} {...rest}>
      {content}
    </a>
  ) : (
    <Link href={href} className={classes} {...rest}>
      {content}
    </Link>
  );
}
