import Link from "next/link";
import { t } from "@/i18n/t";

type ForumHubActionsProps = {
  isLoggedIn: boolean;
};

export function ForumHubActions({ isLoggedIn }: ForumHubActionsProps) {
  return (
    <div className="flex flex-wrap gap-3">
      {isLoggedIn ? (
        <>
          <Link
            href="/forum/new"
            className="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-full bg-primary px-5 text-sm font-extrabold text-primary-foreground shadow-[0_14px_40px_rgb(16_30_36_/_0.12)] hover:bg-primary/90"
          >
            <PlusIcon />
            {t("forum.newTopic")}
          </Link>
          <Link
            href="/account/forum"
            className="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-full border border-white/90 bg-white/[0.86] px-5 text-sm font-extrabold text-[#4f5b67] shadow-[0_14px_40px_rgb(16_30_36_/_0.07)] hover:bg-white"
          >
            {t("nav.myForum")}
          </Link>
        </>
      ) : (
        <>
          <Link
            href="/login?redirect=/forum/new"
            className="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-full bg-primary px-5 text-sm font-extrabold text-primary-foreground shadow-[0_14px_40px_rgb(16_30_36_/_0.12)] hover:bg-primary/90"
          >
            <PlusIcon />
            {t("forum.newTopic")}
          </Link>
          <Link
            href="/login?redirect=/account/forum"
            className="inline-flex min-h-[44px] items-center justify-center rounded-full border border-white/90 bg-white/[0.86] px-5 text-sm font-extrabold text-[#4f5b67] shadow-[0_14px_40px_rgb(16_30_36_/_0.07)] hover:bg-white"
          >
            {t("auth.signIn")}
          </Link>
        </>
      )}
    </div>
  );
}

function PlusIcon() {
  return (
    <svg
      className="h-4 w-4"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2.5"
      aria-hidden
    >
      <path d="M12 5v14M5 12h14" strokeLinecap="round" />
    </svg>
  );
}
