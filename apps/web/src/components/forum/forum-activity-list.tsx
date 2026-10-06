import Link from "next/link";
import type { ReactNode } from "react";
import { ForumStatusTag } from "@/components/forum/forum-status-tag";
import { Card } from "@/components/ui/card";
import { Notice } from "@/components/ui/notice";
import { t } from "@/i18n/t";

/** „Мој форум“: a titled white card holding one list of the member's posts. */
export function ForumActivitySection({
  id,
  title,
  pagination,
  children,
}: {
  id: string;
  title: ReactNode;
  pagination?: ReactNode;
  children: ReactNode;
}) {
  return (
    <section aria-labelledby={`${id}-heading`} className="flex flex-col gap-3">
      <h2 id={`${id}-heading`} className="type-h2 text-ink">
        {title}
      </h2>
      <Card padding="none" className="overflow-hidden">
        <ul>{children}</ul>
      </Card>
      {pagination}
    </section>
  );
}

/**
 * One topic or reply with its moderation status. Only approved items link to
 * the thread: a pending or rejected one is not public.
 */
export function ForumActivityItem({
  title,
  href,
  status,
  meta,
  body,
  rejectionNote,
}: {
  title: string;
  href?: string;
  status: string;
  meta?: ReactNode;
  body?: string | null;
  rejectionNote?: string | null;
}) {
  return (
    <li className="flex flex-col gap-2 border-t border-line px-5 py-4 first:border-t-0">
      <div className="flex flex-wrap items-start justify-between gap-x-3 gap-y-2">
        <p className="min-w-0 flex-1 font-ui text-[1.0625rem] leading-6 font-semibold text-ink">
          {href ? (
            <Link
              href={href}
              className="link-underline decoration-coral hover:text-black"
            >
              {title}
            </Link>
          ) : (
            title
          )}
        </p>
        <ForumStatusTag status={status} />
      </div>
      {meta ? <p className="type-meta text-ink-2">{meta}</p> : null}
      {body ? (
        <p className="line-clamp-3 whitespace-pre-wrap break-words type-reading text-ink">
          {body}
        </p>
      ) : null}
      {rejectionNote ? (
        <Notice tone="info" title={t("account.rejectionNote")}>
          {rejectionNote}
        </Notice>
      ) : null}
    </li>
  );
}
