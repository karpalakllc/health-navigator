import type { ReactNode } from "react";
import { Icon, type IconName } from "@/components/ui/icons";
import { cn } from "@/lib/cn";

/*
 * Inline notes. None of them is red: the forum/guidance safety note is the
 * soft chip-tint surface with ink text, and its 194/112 are bold ink tel:
 * links (use <NoticeTelLink>), so it never competes with the emergency pill.
 */
const TONES = {
  /** General information, e.g. „Цените се информативни…“. */
  info: { box: "bg-sand", icon: "info" },
  /** Safety: „Форумот е за искуства… Итно? 194 или 112.“ */
  safety: { box: "bg-chip-tint", icon: "info" },
  /** Confirmation („Рецензијата е испратена…“). */
  success: { box: "bg-care-tint", icon: "check" },
} as const satisfies Record<string, { box: string; icon: IconName }>;

export function Notice({
  tone = "info",
  title,
  icon,
  className,
  children,
  ...rest
}: Omit<React.HTMLAttributes<HTMLDivElement>, "title"> & {
  tone?: keyof typeof TONES;
  title?: ReactNode;
  icon?: IconName;
  children: ReactNode;
}) {
  const t = TONES[tone];

  return (
    <div
      className={cn("flex gap-3 rounded-[20px] p-4 text-ink", t.box, className)}
      {...rest}
    >
      <Icon
        name={icon ?? t.icon}
        size={24}
        className={cn("mt-0.5", tone === "success" ? "text-care" : "text-ink")}
      />
      <div className="flex min-w-0 flex-col gap-1 type-body">
        {title ? <p className="font-semibold">{title}</p> : null}
        <div>{children}</div>
      </div>
    </div>
  );
}

/** Bold ink tel: link for numbers inside a Notice (never red). */
export function NoticeTelLink({ number }: { number: "194" | "112" }) {
  return (
    <a
      href={`tel:${number}`}
      className="font-bold text-ink underline decoration-2 underline-offset-4"
    >
      {number}
    </a>
  );
}
