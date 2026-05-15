"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { filterInputClassName } from "@/components/directory/filter-form";
import { StarRatingInput } from "@/components/reviews/star-rating-input";
import { t } from "@/i18n/t";

type ReviewFormProps = {
  kind: "doctor" | "facility";
  slug: string;
};

export function ReviewForm({ kind, slug }: ReviewFormProps) {
  const router = useRouter();
  const [rating, setRating] = useState(5);
  const [body, setBody] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState(false);
  const [pending, setPending] = useState(false);

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault();
    setError(null);
    setSuccess(false);
    setPending(true);

    try {
      const response = await fetch("/api/reviews", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          kind,
          slug,
          rating,
          body: body.trim() === "" ? null : body.trim(),
        }),
      });

      const payload = await response.json();

      if (!response.ok) {
        const message =
          payload.message ??
          payload.errors?.review?.[0] ??
          t("reviews.submitError");
        setError(message);
        return;
      }

      setSuccess(true);
      setBody("");
      router.refresh();
    } catch {
      setError(t("reviews.submitErrorRetry"));
    } finally {
      setPending(false);
    }
  }

  return (
    <form
      onSubmit={handleSubmit}
      className="grid gap-3 rounded-lg border border-zinc-200 bg-zinc-50 p-4"
    >
      <p className="text-sm font-medium text-zinc-900">{t("reviews.submitTitle")}</p>
      <StarRatingInput value={rating} onChange={setRating} disabled={pending} />
      <label className="grid gap-1 text-sm">
        <span>{t("reviews.body")}</span>
        <textarea
          value={body}
          onChange={(e) => setBody(e.target.value)}
          rows={4}
          className={filterInputClassName}
        />
      </label>
      {error ? <p className="text-sm text-red-600">{error}</p> : null}
      {success ? (
        <p className="text-sm text-green-700">{t("reviews.submitSuccess")}</p>
      ) : null}
      <button
        type="submit"
        disabled={pending}
        className="rounded bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-60"
      >
        {pending ? t("common.submitting") : t("reviews.submit")}
      </button>
    </form>
  );
}
