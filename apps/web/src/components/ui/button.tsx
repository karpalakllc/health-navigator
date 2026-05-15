import Link from "next/link";
import { cn } from "@/lib/cn";

const variants = {
  primary:
    "bg-primary text-primary-foreground hover:bg-primary/90 shadow-sm",
  secondary:
    "bg-secondary text-secondary-foreground hover:bg-secondary/80",
  ghost: "hover:bg-secondary text-foreground",
  outline:
    "border border-border bg-card hover:bg-secondary text-foreground",
} as const;

type ButtonVariant = keyof typeof variants;

type ButtonProps = {
  variant?: ButtonVariant;
  className?: string;
  children: React.ReactNode;
} & (
  | (React.ButtonHTMLAttributes<HTMLButtonElement> & { href?: undefined })
  | { href: string; type?: never }
);

export function Button({
  variant = "primary",
  className,
  children,
  href,
  ...props
}: ButtonProps) {
  const classes = cn(
    "inline-flex items-center justify-center rounded-xl px-4 py-2.5 text-sm font-semibold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring disabled:pointer-events-none disabled:opacity-50",
    variants[variant],
    className,
  );

  if (href) {
    return (
      <Link href={href} className={classes}>
        {children}
      </Link>
    );
  }

  return (
    <button className={classes} {...props}>
      {children}
    </button>
  );
}
