import type { ReactNode } from "react";
import { Card } from "@/components/ui/card";

/**
 * A form on a white card. The auth pages now use AuthPage (the card holds the
 * title too); kept for other forms that still wrap themselves in it (the forum
 * new-topic composer).
 */
export function AuthFormCard({
  children,
  className,
}: {
  children: ReactNode;
  className?: string;
}) {
  return (
    <Card padding="md" className={className ? `lg:p-8 ${className}` : "lg:p-8"}>
      {children}
    </Card>
  );
}
