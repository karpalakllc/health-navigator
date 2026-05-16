import Link from "next/link";
import type { ReactNode } from "react";
import { Card } from "@/components/ui/card";
import { cn } from "@/lib/cn";

export function HubLinkCard({
  href,
  title,
  description,
  icon,
  iconClassName,
  className,
}: {
  href: string;
  title: string;
  description: string;
  icon?: ReactNode;
  iconClassName?: string;
  className?: string;
}) {
  return (
    <Link href={href} className={cn("block h-full", className)}>
      <Card className="card-hover flex h-full gap-4 p-5">
        {icon ? (
          <span
            className={cn(
              "flex h-11 w-11 shrink-0 items-center justify-center rounded-xl",
              iconClassName,
            )}
          >
            {icon}
          </span>
        ) : null}
        <div className="min-w-0 space-y-1">
          <p className="font-semibold text-foreground">{title}</p>
          <p className="text-sm text-muted-foreground">{description}</p>
        </div>
      </Card>
    </Link>
  );
}
