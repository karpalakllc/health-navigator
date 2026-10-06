import { redirect } from "next/navigation";
import { AuthPage } from "@/components/auth/auth-page";
import { UsernameChooser } from "@/components/usernames/username-chooser";
import { getSessionToken } from "@/lib/auth/session";
import { safeRedirectTarget } from "@/lib/auth/login-href";
import { fetchMe } from "@/lib/api/me";
import { ApiRequestError } from "@/lib/api/server";
import { pageMetadata } from "@/lib/metadata";
import { t } from "@/i18n/t";

export const metadata = pageMetadata(t("usernames.chooserTitle"), undefined, {
  noIndex: true,
});

/**
 * The one-step username choice after sign-in, for accounts that still have a
 * temporary „clen-…“ name. Calm and skippable: reading works without a
 * username, only posting waits for it (the API enforces that).
 */
export default async function ChooseUsernamePage({
  searchParams,
}: {
  searchParams: Promise<{ redirect?: string }>;
}) {
  const target = safeRedirectTarget((await searchParams).redirect, "/account");
  const self = `/account/username?redirect=${encodeURIComponent(target)}`;
  const token = await getSessionToken();

  if (!token) {
    redirect(`/login?redirect=${encodeURIComponent(self)}`);
  }

  let user;

  try {
    user = await fetchMe();
  } catch (error) {
    if (error instanceof ApiRequestError && error.status === 401) {
      redirect(`/login?redirect=${encodeURIComponent(self)}`);
    }

    throw error;
  }

  if (!user.must_choose_username) {
    redirect(target);
  }

  return (
    <AuthPage
      title={t("usernames.chooserTitle")}
      description={t("usernames.chooserLead")}
    >
      <UsernameChooser temporary={user.username} redirectTo={target} />
    </AuthPage>
  );
}
