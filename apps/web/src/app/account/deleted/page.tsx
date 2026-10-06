import { AccountPage } from "@/components/account/account-layout";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Icon } from "@/components/ui/icons";
import { pageMetadata } from "@/lib/metadata";
import { t } from "@/i18n/t";

export const metadata = pageMetadata(
  t("account.data.deletedTitle"),
  undefined,
  {
    noIndex: true,
  },
);

/**
 * Where account deletion lands. Needs no session (there is none any more) and
 * says nothing about the account, so it is safe to reach directly.
 */
export default function AccountDeletedPage() {
  return (
    <AccountPage>
      <Card
        as="section"
        aria-labelledby="account-deleted"
        className="max-w-2xl"
      >
        <div className="flex flex-col items-start gap-5">
          <span className="inline-flex size-12 items-center justify-center rounded-full bg-care-tint text-care">
            <Icon name="check" size={24} />
          </span>
          <h1 id="account-deleted" className="type-h1 text-ink">
            {t("account.data.deletedTitle")}
          </h1>
          <p className="type-body text-ink-2 measure">
            {t("account.data.deletedBody")}
          </p>
          <Button href="/" className="w-full sm:w-auto">
            {t("account.data.deletedHome")}
          </Button>
        </div>
      </Card>
    </AccountPage>
  );
}
