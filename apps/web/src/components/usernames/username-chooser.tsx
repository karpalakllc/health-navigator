"use client";

import { useRouter } from "next/navigation";
import { TextLink } from "@/components/ui/button";
import { AccountUsernameForm } from "@/components/usernames/account-username-form";
import { t, tFormat } from "@/i18n/t";

/**
 * The first-sign-in chooser's body: what the member is shown as today, the
 * one field, and a way to carry on reading without choosing.
 */
export function UsernameChooser({
  temporary,
  redirectTo,
}: {
  temporary: string;
  redirectTo: string;
}) {
  const router = useRouter();

  return (
    <div className="flex flex-col gap-5">
      <p className="type-body text-ink-2">
        {tFormat("usernames.chooserTemporary", { username: temporary })}
      </p>
      <AccountUsernameForm
        username={temporary}
        mustChoose
        changeAvailableAt={null}
        variant="chooser"
        onSaved={() => router.push(redirectTo)}
      />
      <div className="flex flex-col gap-1 border-t border-line pt-4">
        <TextLink href={redirectTo} className="self-start">
          {t("usernames.chooserLater")}
        </TextLink>
        <p className="type-meta text-ink-2">
          {t("usernames.chooserLaterHint")}
        </p>
      </div>
    </div>
  );
}
