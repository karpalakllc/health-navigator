"use client";

import { useRouter } from "next/navigation";
import { useId, useState } from "react";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/field";
import { FormError, FormSuccess } from "@/components/ui/form-message";
import { Tag } from "@/components/ui/tag";
import {
  REPLY_MAX_LENGTH,
  type DashboardReview,
  type DoctorReplyState,
} from "@/lib/api/doctor-dashboard-types";
import { t, tFormat } from "@/i18n/t";

type ReplyResponse = {
  message?: string;
  errors?: { body?: string[] };
  data?: { review?: DashboardReview; message?: string };
};

function ReplyStatus({ reply }: { reply: DoctorReplyState }) {
  if (reply.status === "pending") {
    return (
      <Tag tone="tint" icon="clock">
        {t("doctorDashboard.replyPending")}
      </Tag>
    );
  }

  if (reply.status === "rejected") {
    return (
      <Tag tone="outline" icon="x">
        {t("doctorDashboard.replyRejected")}
      </Tag>
    );
  }

  return (
    <Tag tone="care" icon="check">
      {t("doctorDashboard.replyPublished")}
    </Tag>
  );
}

/**
 * The doctor's one reply under a review on „Мој профил“: write it, see where
 * it stands (waiting for staff, published, or not published with staff's
 * reason), change it or take it back. A response staff entered is shown but
 * not editable here. The one-line hint about patient data is the field's
 * description, so it is read with the field.
 */
export function DoctorReplyEditor({
  review: initial,
}: {
  review: DashboardReview;
}) {
  const router = useRouter();
  const [review, setReview] = useState(initial);
  const [editing, setEditing] = useState(initial.reply === null);
  const [body, setBody] = useState(initial.reply?.body ?? "");
  const [pending, setPending] = useState<"save" | "delete" | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const errorId = useId();
  const reply = review.reply;

  async function send(method: "PUT" | "DELETE") {
    setError(null);
    setNotice(null);

    if (method === "PUT" && body.trim().length < 2) {
      setError(t("doctorDashboard.replyTooShort"));

      return;
    }

    setPending(method === "PUT" ? "save" : "delete");

    try {
      const response = await fetch(
        `/api/doctor-dashboard/reviews/${review.id}/reply`,
        {
          method,
          headers:
            method === "PUT"
              ? { "Content-Type": "application/json" }
              : undefined,
          body: method === "PUT" ? JSON.stringify({ body }) : undefined,
        },
      );
      const payload = (await response
        .json()
        .catch(() => null)) as ReplyResponse | null;

      if (!response.ok) {
        setError(
          payload?.errors?.body?.[0] ??
            payload?.message ??
            t("doctorDashboard.replyError"),
        );

        return;
      }

      const next = payload?.data?.review;

      if (next) {
        setReview(next);
        setBody(next.reply?.body ?? "");
      }

      setEditing(method === "DELETE");
      setNotice(
        method === "DELETE"
          ? t("doctorDashboard.replyDeleted")
          : next?.reply?.status === "pending"
            ? t("doctorDashboard.replyPendingSaved")
            : t("doctorDashboard.replyPublishedSaved"),
      );
      router.refresh();
    } catch {
      setError(t("doctorDashboard.replyError"));
    } finally {
      setPending(null);
    }
  }

  // A staff-entered response: shown, not the doctor's to change.
  if (reply && reply.source === "staff") {
    return (
      <div className="flex flex-col gap-2 rounded-2xl bg-sand p-4">
        <p className="whitespace-pre-line break-words type-reading text-ink">
          {reply.body}
        </p>
        <p className="type-meta text-ink-2">
          {t("doctorDashboard.replyStaff")}
        </p>
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-3">
      {reply && !editing ? (
        <section
          aria-label={tFormat("doctorDashboard.replyFor", {
            name: review.author_name,
          })}
          className="flex flex-col gap-2 rounded-2xl border-l-4 border-line-strong bg-sand p-4"
        >
          <div className="flex flex-wrap items-center gap-2">
            <ReplyStatus reply={reply} />
          </div>
          <p className="whitespace-pre-line break-words type-reading text-ink">
            {reply.body}
          </p>
          {reply.status === "rejected" && reply.rejection_note ? (
            <p className="type-body text-ink">
              {tFormat("doctorDashboard.replyRejectedReason", {
                reason: reply.rejection_note,
              })}
            </p>
          ) : null}
          <div className="flex flex-wrap gap-2 pt-1">
            <Button
              variant="secondary"
              size="sm"
              leadingIcon="reply"
              onClick={() => {
                setEditing(true);
                setNotice(null);
              }}
            >
              {t("doctorDashboard.replyEdit")}
            </Button>
            <Button
              variant="ghost"
              size="sm"
              leadingIcon="x"
              loading={pending === "delete"}
              disabled={pending !== null}
              onClick={() => send("DELETE")}
            >
              {pending === "delete"
                ? t("doctorDashboard.replyDeleting")
                : t("doctorDashboard.replyDelete")}
            </Button>
          </div>
        </section>
      ) : (
        <form
          noValidate
          className="flex flex-col gap-3"
          onSubmit={(event) => {
            event.preventDefault();
            void send("PUT");
          }}
        >
          <Textarea
            label={t("doctorDashboard.replyLabel")}
            hint={t("doctorDashboard.replyHint")}
            name="reply"
            rows={4}
            maxLength={REPLY_MAX_LENGTH}
            value={body}
            aria-describedby={error ? errorId : undefined}
            aria-invalid={error ? true : undefined}
            counter={tFormat("doctorDashboard.replyCounter", {
              count: body.length,
              max: REPLY_MAX_LENGTH,
            })}
            onChange={(event) => setBody(event.target.value)}
          />
          {error ? <FormError id={errorId}>{error}</FormError> : null}
          <div className="flex flex-wrap gap-2">
            <Button
              type="submit"
              size="sm"
              loading={pending === "save"}
              disabled={pending !== null}
            >
              {pending === "save"
                ? t("doctorDashboard.replySaving")
                : t("doctorDashboard.replySave")}
            </Button>
            {reply ? (
              <Button
                variant="ghost"
                size="sm"
                onClick={() => {
                  setEditing(false);
                  setBody(reply.body);
                  setError(null);
                }}
              >
                {t("doctorDashboard.replyCancel")}
              </Button>
            ) : null}
          </div>
        </form>
      )}
      <FormSuccess>{notice}</FormSuccess>
    </div>
  );
}
