"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { filterInputClassName } from "@/components/directory/filter-form";
import { t } from "@/i18n/t";

export function TopicForm({ categorySlug }: { categorySlug: string }) {
  const router = useRouter();
  const [title, setTitle] = useState("");
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
      const response = await fetch("/api/forum/topics", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ categorySlug, title, body }),
      });

      const payload = await response.json();

      if (!response.ok) {
        setError(payload.message ?? t("forum.topicError"));
        return;
      }

      setSuccess(true);
      setTitle("");
      setBody("");
      router.refresh();
    } catch {
      setError(t("forum.topicErrorRetry"));
    } finally {
      setPending(false);
    }
  }

  return (
    <form
      onSubmit={handleSubmit}
      className="grid gap-3 rounded-xl border border-border bg-card p-4"
    >
      <p className="text-sm font-semibold text-foreground">{t("forum.startTopic")}</p>
      <label className="grid gap-1.5 text-sm">
        <span className="font-medium text-foreground">{t("common.title")}</span>
        <input
          value={title}
          onChange={(e) => setTitle(e.target.value)}
          required
          minLength={5}
          className={filterInputClassName}
        />
      </label>
      <label className="grid gap-1.5 text-sm">
        <span className="font-medium text-foreground">{t("common.message")}</span>
        <textarea
          value={body}
          onChange={(e) => setBody(e.target.value)}
          required
          minLength={20}
          rows={5}
          className={filterInputClassName}
        />
      </label>
      {error ? <p className="text-sm text-destructive">{error}</p> : null}
      {success ? (
        <p className="text-sm font-medium text-emerald-700 dark:text-emerald-400">{t("forum.topicSuccess")}</p>
      ) : null}
      <button
        type="submit"
        disabled={pending}
        className="min-h-[44px] w-full rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground hover:bg-primary/90 disabled:opacity-60 sm:w-auto"
      >
        {pending ? t("common.submitting") : t("forum.topicSubmit")}
      </button>
    </form>
  );
}
