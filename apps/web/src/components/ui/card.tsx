import { cn } from "@/lib/cn";

export function Card({
  className,
  children,
}: {
  className?: string;
  children: React.ReactNode;
}) {
  return (
    <div
      className={cn("rounded-2xl border border-border bg-card p-6", className)}
    >
      {children}
    </div>
  );
}
