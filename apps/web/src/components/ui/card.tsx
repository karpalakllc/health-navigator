import type { ElementType, ReactNode } from "react";
import { cn } from "@/lib/cn";

/*
 * White card, radius 20, the card shadow, no border. `edge` adds the 4px
 * coral top edge (OP post, profile/contact card) — decorative, not a
 * left-border accent. `tone` swaps the surface for sand/apricot bands (no
 * shadow on those).
 */
const paddingClass = {
  none: "",
  sm: "p-4",
  md: "p-5",
  lg: "p-6",
} as const;

const toneClass = {
  white: "",
  sand: "bg-sand shadow-none",
  apricot: "bg-apricot shadow-none",
} as const;

export function Card({
  as: Element = "div",
  edge = false,
  tone = "white",
  padding = "lg",
  className,
  children,
  ...rest
}: {
  /** Element to render: "section", "article", "li"… (default div). */
  as?: ElementType;
  edge?: boolean;
  tone?: keyof typeof toneClass;
  padding?: keyof typeof paddingClass;
  className?: string;
  children: ReactNode;
} & Omit<React.HTMLAttributes<HTMLElement>, "className" | "children">) {
  return (
    <Element
      className={cn(
        "card",
        edge && "card-edge",
        toneClass[tone],
        paddingClass[padding],
        className,
      )}
      {...rest}
    >
      {children}
    </Element>
  );
}
