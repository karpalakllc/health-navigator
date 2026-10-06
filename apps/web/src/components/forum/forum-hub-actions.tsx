import { Button } from "@/components/ui/button";
import { t } from "@/i18n/t";

type ForumHubActionsProps = {
  isLoggedIn: boolean;
};

/** „Нова тема“ (ink pill) plus „Мој форум“, or sign-in for guests. */
export function ForumHubActions({ isLoggedIn }: ForumHubActionsProps) {
  return (
    <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
      <Button
        href={isLoggedIn ? "/forum/new" : "/login?redirect=/forum/new"}
        size="lg"
        leadingIcon="message-circle"
      >
        {t("forum.newTopic")}
      </Button>
      <Button
        href={isLoggedIn ? "/account/forum" : "/login?redirect=/account/forum"}
        variant="secondary"
        size="lg"
      >
        {isLoggedIn ? t("nav.myForum") : t("auth.signIn")}
      </Button>
    </div>
  );
}
