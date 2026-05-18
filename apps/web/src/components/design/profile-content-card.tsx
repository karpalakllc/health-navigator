import type { ReactNode } from "react";
import { cn } from "@/lib/cn";

export function ProfileContentCard({
  title,
  children,
  className,
  id,
}: {
  title: string;
  children: ReactNode;
  className?: string;
  id?: string;
}) {
  return (
    <section id={id} className={cn("content-card rounded-[1.625rem] p-6 transition-shadow hover:shadow-[0_22px_56px_rgb(16_30_36_/_0.1)]", className)}>
      <h2 className="mb-3.5 text-[1.45rem] font-black tracking-tight text-foreground">{title}</h2>
      {children}
    </section>
  );
}
