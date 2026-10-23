"use client";

import { Button } from "@/components/ui/button";
import { quoteText } from "@/lib/forum/post-body";
import { t, tFormat } from "@/i18n/t";

/** Fired on window; the reply form adds the quote and takes the focus. */
export const FORUM_QUOTE_EVENT = "z360:forum-quote";

export type ForumQuoteDetail = { text: string };

/** „Цитирај“: puts the post, as „> “ lines, into the reply form. */
export function ForumQuoteButton({
  authorName,
  body,
}: {
  authorName: string;
  body: string;
}) {
  return (
    <Button
      type="button"
      variant="ghost"
      size="sm"
      leadingIcon="message-circle"
      className="text-ink-2"
      aria-label={tFormat("forum.quoteAria", { name: authorName })}
      onClick={() => {
        window.dispatchEvent(
          new CustomEvent<ForumQuoteDetail>(FORUM_QUOTE_EVENT, {
            detail: {
              text: quoteText(
                tFormat("forum.quoteIntro", { name: authorName }),
                body,
              ),
            },
          }),
        );
      }}
    >
      {t("forum.quote")}
    </Button>
  );
}
