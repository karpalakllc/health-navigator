"use client";

import { useRouter } from "next/navigation";
import { useId, useState } from "react";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Textarea } from "@/components/ui/field";
import { FormError, FormSuccess } from "@/components/ui/form-message";
import { Icon } from "@/components/ui/icons";
import { Monogram } from "@/components/ui/user-avatar";
import { formatCharCounter } from "@/components/forum/char-counter";
import { t } from "@/i18n/t";

/** API limit (StoreForumPostRequest: max 10000). */
export const REPLY_MAX_LENGTH = 10000;

/** Anchor the thread's „Одговори“ actions jump to. */
export const REPLY_FORM_ID = "forum-reply";

export function ReplyForm({
  categorySlug,
  topicSlug,
  viewer,
}: {
  categorySlug: string;
  topicSlug: string;
  /** The signed-in member's public name („Марија К.“) for „Одговарате како“. */
  viewer?: { name: string; initials?: string } | null;
}) {
  const router = useRouter();
  const previewId = useId();
  const [body, setBody] = useState("");
  const [preview, setPreview] = useState(false);
  const [error, setError] = useState<string | null>(null);
  // Which confirmation to show: the API publishes at once for moderators and
  // when post moderation is off, and holds the reply for review otherwise.
  const [success, setSuccess] = useState<"pending" | "approved" | null>(null);
  const [pending, setPending] = useState(false);

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault();
    setError(null);
    setSuccess(null);
    setPending(true);

    try {
      const response = await fetch("/api/forum/posts", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ categorySlug, topicSlug, body }),
      });

      const payload = await response.json();

      if (!response.ok) {
        setError(
          payload.message ??
            payload.errors?.topic?.[0] ??
            t("forum.replyError"),
        );
        return;
      }

      setSuccess(payload.data?.status === "approved" ? "approved" : "pending");
      setBody("");
      setPreview(false);
      router.refresh();
    } catch {
      setError(t("forum.replyErrorRetry"));
    } finally {
      setPending(false);
    }
  }

  return (
    <Card
      as="section"
      id={REPLY_FORM_ID}
      padding="none"
      aria-labelledby="forum-reply-heading"
      className="flex flex-col gap-4 p-5 lg:p-8"
    >
      {viewer ? (
        <p className="flex items-center gap-2 type-meta text-ink-2">
          <Monogram name={viewer.name} initials={viewer.initials} size={32} />
          <span>
            {t("forum.replyingAs")}{" "}
            <strong className="font-semibold text-ink">{viewer.name}</strong>
          </span>
        </p>
      ) : null}
      <h2 id="forum-reply-heading" className="type-h3 text-ink">
        {t("forum.yourReply")}
      </h2>
      <form onSubmit={handleSubmit} className="flex flex-col gap-4">
        <Textarea
          label={t("common.message")}
          hint={t("forum.replyHint")}
          value={body}
          onChange={(e) => setBody(e.target.value)}
          required
          minLength={10}
          maxLength={REPLY_MAX_LENGTH}
          counter={formatCharCounter(body.length, REPLY_MAX_LENGTH)}
        />
        {preview ? (
          <div id={previewId} className="rounded-card bg-sand p-4 lg:p-5">
            <p className="type-label text-ink">{t("forum.showPreview")}</p>
            <p className="mt-2 whitespace-pre-wrap break-words type-reading text-ink">
              {body || t("forum.previewBodyPlaceholder")}
            </p>
          </div>
        ) : null}
        {error ? <FormError>{error}</FormError> : null}
        <FormSuccess>
          {success === "approved"
            ? t("forum.replyPublished")
            : success === "pending"
              ? t("forum.replySuccess")
              : null}
        </FormSuccess>
        <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
          <Button
            variant="secondary"
            size="lg"
            leadingIcon={preview ? "eye-off" : "eye"}
            aria-expanded={preview}
            aria-controls={preview ? previewId : undefined}
            onClick={() => setPreview((current) => !current)}
          >
            {preview ? t("forum.hidePreview") : t("forum.showPreview")}
          </Button>
          <Button type="submit" size="lg" leadingIcon="send" loading={pending}>
            {pending ? t("common.submitting") : t("forum.replySubmit")}
          </Button>
        </div>
        <p className="flex items-start gap-2 type-meta text-ink-2">
          <Icon name="clock" size={20} className="mt-0.5" />
          <span>{t("forum.replyModerationNote")}</span>
        </p>
      </form>
    </Card>
  );
}
