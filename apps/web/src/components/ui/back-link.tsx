import Link from "next/link";
import { Icon } from "@/components/ui/icons";

type BackLinkProps = {
  href: string;
  label: string;
};

/** Soft „‹ Назад“ pill (44px) above a page title. */
export function BackLink({ href, label }: BackLinkProps) {
  return (
    <Link href={href} className="btn btn-soft btn-sm self-start pl-3">
      <Icon name="chevron-left" size={20} />
      <span>{label}</span>
    </Link>
  );
}
