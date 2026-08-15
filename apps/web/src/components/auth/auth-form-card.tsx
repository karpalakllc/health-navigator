import type { ReactNode } from "react";
import { cn } from "@/lib/cn";

export function AuthFormCard({
  children,
  className,
}: {
  children: ReactNode;
  className?: string;
}) {
  return (
    <div
      className={cn("content-card rounded-[1.625rem] p-5 sm:p-7", className)}
    >
      {children}
    </div>
  );
}
