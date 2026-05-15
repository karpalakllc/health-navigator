import Link from "next/link";
import { Card } from "@/components/ui/card";
import { cn } from "@/lib/cn";

export function HubLinkCard({
  href,
  title,
  description,
  className,
}: {
  href: string;
  title: string;
  description: string;
  className?: string;
}) {
  return (
    <Link href={href} className={cn("block h-full", className)}>
      <Card className="card-hover flex h-full flex-col gap-2 p-5">
        <p className="font-semibold text-foreground">{title}</p>
        <p className="text-sm text-muted-foreground">{description}</p>
      </Card>
    </Link>
  );
}
