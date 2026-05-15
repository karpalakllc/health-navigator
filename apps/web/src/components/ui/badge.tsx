import { cn } from "@/lib/cn";

const variants = {
  default: "bg-secondary text-secondary-foreground",
  secondary: "bg-secondary text-secondary-foreground",
  primary: "bg-primary/10 text-primary",
  accent: "bg-accent/10 text-accent",
  warning: "bg-warning/15 text-amber-800",
  outline: "border border-border bg-card text-foreground",
} as const;

export function Badge({
  variant = "default",
  className,
  children,
}: {
  variant?: keyof typeof variants;
  className?: string;
  children: React.ReactNode;
}) {
  return (
    <span
      className={cn(
        "inline-flex items-center rounded-lg px-2.5 py-0.5 text-xs font-medium",
        variants[variant],
        className,
      )}
    >
      {children}
    </span>
  );
}
