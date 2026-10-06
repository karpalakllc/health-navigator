import { Tag, type TagTone } from "@/components/ui/tag";

/*
 * legacy — remove after page migration. The old Badge API mapped onto Tag so
 * un-migrated pages pick up D2a colours. New code: use <Tag tone=…>.
 */
const legacyTone = {
  default: "sand",
  secondary: "sand",
  primary: "tint",
  accent: "care",
  warning: "sand",
  outline: "outline",
} as const satisfies Record<string, TagTone>;

export function Badge({
  variant = "default",
  className,
  children,
}: {
  variant?: keyof typeof legacyTone;
  className?: string;
  children: React.ReactNode;
}) {
  return (
    <Tag tone={legacyTone[variant]} className={className}>
      {children}
    </Tag>
  );
}
