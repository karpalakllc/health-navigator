import Link from "next/link";
import { t } from "@/i18n/t";

export function FooterSearchButton({ className }: { className?: string }) {
  return (
    <Link href="/search" className={className}>
      {t("footer.openSearch")}
    </Link>
  );
}
