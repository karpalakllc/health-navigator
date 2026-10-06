import Link from "next/link";
import { Notice } from "@/components/ui/notice";
import { chooseUsernameHref } from "@/lib/auth/login-href";
import { t } from "@/i18n/t";

/**
 * Shown instead of a review or forum form while the member still has a
 * temporary „clen-…“ name: the API would refuse the post, so they are sent to
 * choose a name first (and brought back here) rather than writing it in vain.
 */
export function ChooseUsernameNotice({
  returnTo,
  id,
  className,
}: {
  returnTo: string;
  id?: string;
  className?: string;
}) {
  return (
    <Notice tone="info" id={id} className={className}>
      <p>{t("usernames.accountNotice")}</p>
      <Link
        href={chooseUsernameHref(returnTo)}
        className="link-underline inline-flex min-h-12 items-center font-semibold text-ink"
      >
        {t("usernames.accountNoticeCta")}
      </Link>
    </Notice>
  );
}
