import Link from "next/link";
import type { ReactNode } from "react";
import { cn } from "@/lib/cn";

type EyebrowVariant = "pill" | "plain" | "plain-with-icon";

type SectionHeadingProps = {
  eyebrow?: string;
  eyebrowVariant?: EyebrowVariant;
  title: string;
  description?: string;
  href?: string;
  linkLabel?: string;
  icon?: ReactNode;
  /** Icon circle tone when `eyebrowVariant` is `plain-with-icon` */
  iconTone?: "red" | "teal";
  className?: string;
};

export function SectionHeading({
  eyebrow,
  eyebrowVariant = "pill",
  title,
  description,
  href,
  linkLabel,
  icon,
  iconTone = "red",
  className,
}: SectionHeadingProps) {
  const showEyebrow = Boolean(eyebrow);
  const useIconRow = eyebrowVariant === "plain-with-icon" && icon;

  return (
    <div
      className={cn(
        "mb-7 flex flex-wrap items-end justify-between gap-4",
        className,
      )}
    >
      <div className="max-w-3xl space-y-3">
        {showEyebrow ? (
          <div className={cn("flex items-center", useIconRow ? "gap-2.5" : "")}>
            {useIconRow ? (
              <span
                className={cn(
                  "inline-flex h-[34px] w-[34px] shrink-0 items-center justify-center rounded-full",
                  iconTone === "teal" ? "icon-soft-teal" : "icon-soft-red",
                )}
              >
                {icon}
              </span>
            ) : null}
            <span
              className={cn(
                eyebrowVariant === "pill" &&
                  "inline-flex min-h-[34px] items-center rounded-full bg-[#fff1f1] px-3 text-[0.84rem] font-extrabold text-primary",
                eyebrowVariant === "plain" &&
                  "text-sm font-extrabold text-[#43515d]",
                eyebrowVariant === "plain-with-icon" &&
                  "text-sm font-extrabold text-[#43515d]",
              )}
            >
              {eyebrow}
            </span>
          </div>
        ) : null}
        <h2 className="text-3xl font-black tracking-tight text-foreground sm:text-4xl">
          {title}
        </h2>
        {description ? (
          <p className="text-base leading-relaxed text-muted-foreground">
            {description}
          </p>
        ) : null}
      </div>
      {href && linkLabel ? (
        <Link
          href={href}
          className="inline-flex shrink-0 items-center gap-2 text-sm font-extrabold text-primary hover:underline"
        >
          {linkLabel}
          <svg
            className="h-4 w-4"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            aria-hidden
          >
            <path
              d="M5 12h14M13 6l6 6-6 6"
              strokeLinecap="round"
              strokeLinejoin="round"
            />
          </svg>
        </Link>
      ) : null}
    </div>
  );
}
