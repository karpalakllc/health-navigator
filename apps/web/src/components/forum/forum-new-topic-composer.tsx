"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useMemo, useState } from "react";
import { AuthFormCard } from "@/components/auth/auth-form-card";
import { filterInputClassName } from "@/components/directory/filter-form";
import { ForumRulesBand } from "@/components/forum/forum-rules-band";
import { ProfileContentCard } from "@/components/design/profile-content-card";
import type { ForumCategory } from "@/lib/api/forum";
import type { PublicSettings } from "@/lib/api/settings";
import { t } from "@/i18n/t";
import { FormError } from "@/components/ui/form-message";

const CONSENT_ERROR_ID = "forum-topic-consent-error";

type ForumNewTopicComposerProps = {
  categories: ForumCategory[];
  defaultCategorySlug?: string;
  settings: Pick<
    PublicSettings,
    "forum_rules_enabled" | "forum_rules_title" | "forum_rules_body"
  >;
};

export function ForumNewTopicComposer({
  categories,
  defaultCategorySlug,
  settings,
}: ForumNewTopicComposerProps) {
  const router = useRouter();
  const [categorySlug, setCategorySlug] = useState(
    defaultCategorySlug &&
      categories.some((c) => c.slug === defaultCategorySlug)
      ? defaultCategorySlug
      : (categories[0]?.slug ?? ""),
  );
  const [title, setTitle] = useState("");
  const [body, setBody] = useState("");
  // One consent covering the rules, "no diagnosis" and "call 194/112";
  // the API records when it was given (forum_topics.community_rules_accepted_at).
  const [accepted, setAccepted] = useState(false);
  const [consentMissing, setConsentMissing] = useState(false);
  const [preview, setPreview] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [pending, setPending] = useState(false);

  const canSubmit = accepted;

  const previewBody = useMemo(
    () => (
      <article className="prose prose-sm max-w-none text-foreground">
        <h3 className="text-xl font-bold">
          {title || t("forum.previewTitlePlaceholder")}
        </h3>
        <p className="whitespace-pre-wrap text-muted-foreground">
          {body || t("forum.previewBodyPlaceholder")}
        </p>
      </article>
    ),
    [title, body],
  );

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault();
    if (!canSubmit || !categorySlug) {
      setConsentMissing(!canSubmit);
      setError(t("forum.consentRequired"));
      return;
    }

    setConsentMissing(false);
    setError(null);
    setPending(true);

    try {
      const response = await fetch("/api/forum/topics", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          categorySlug,
          title,
          body,
          accepted_community_rules: true,
        }),
      });

      const payload = await response.json();

      if (!response.ok) {
        setError(payload.message ?? t("forum.topicError"));
        return;
      }

      router.push("/account/forum");
      router.refresh();
    } catch {
      setError(t("forum.topicErrorRetry"));
    } finally {
      setPending(false);
    }
  }

  return (
    <div className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_300px]">
      <AuthFormCard>
        <form onSubmit={handleSubmit} className="grid gap-4">
          <label className="grid gap-1.5 text-sm">
            <span className="font-semibold text-foreground">
              {t("forum.categories")}
            </span>
            <select
              value={categorySlug}
              onChange={(event) => setCategorySlug(event.target.value)}
              className={filterInputClassName}
              required
            >
              {categories.map((category) => (
                <option key={category.slug} value={category.slug}>
                  {category.name}
                </option>
              ))}
            </select>
          </label>
          <label className="grid gap-1.5 text-sm">
            <span className="font-semibold text-foreground">
              {t("common.title")}
            </span>
            <input
              value={title}
              onChange={(event) => setTitle(event.target.value)}
              required
              minLength={5}
              className={filterInputClassName}
            />
          </label>
          <label className="grid gap-1.5 text-sm">
            <span className="font-semibold text-foreground">
              {t("common.message")}
            </span>
            <textarea
              value={body}
              onChange={(event) => setBody(event.target.value)}
              required
              minLength={20}
              rows={8}
              className={filterInputClassName}
            />
          </label>

          <div className="rounded-xl border border-border bg-muted/20 p-4">
            <label className="flex cursor-pointer items-start gap-2.5 text-sm text-muted-foreground">
              <input
                type="checkbox"
                checked={accepted}
                onChange={(event) => {
                  setAccepted(event.target.checked);
                  if (event.target.checked) {
                    setConsentMissing(false);
                  }
                }}
                required
                aria-invalid={consentMissing || undefined}
                aria-describedby={consentMissing ? CONSENT_ERROR_ID : undefined}
                className="mt-1 h-4 w-4 shrink-0 rounded border-border text-primary"
              />
              <span>{t("forum.consent")}</span>
            </label>
          </div>

          {preview ? previewBody : null}

          {error ? (
            <FormError id={consentMissing ? CONSENT_ERROR_ID : undefined}>
              {error}
            </FormError>
          ) : null}

          <div className="flex flex-wrap gap-3">
            <button
              type="button"
              onClick={() => setPreview((current) => !current)}
              className="inline-flex min-h-[44px] items-center justify-center rounded-xl border border-border bg-card px-4 text-sm font-semibold text-foreground hover:bg-muted/50"
            >
              {preview ? t("forum.hidePreview") : t("forum.showPreview")}
            </button>
            <button
              type="submit"
              disabled={pending || !canSubmit}
              // Disabled was white on 60%-opacity coral (about 2:1, unreadable);
              // grey with dark text reads as unavailable and stays legible.
              className="inline-flex min-h-[44px] items-center justify-center rounded-xl bg-primary px-5 text-sm font-extrabold text-primary-foreground hover:bg-primary/90 disabled:cursor-not-allowed disabled:bg-muted disabled:text-secondary-foreground"
            >
              {pending
                ? t("common.submitting")
                : t("forum.topicSubmitModeration")}
            </button>
            <Link
              href={categorySlug ? `/forum/${categorySlug}` : "/forum"}
              className="inline-flex min-h-[44px] items-center justify-center text-sm font-semibold text-muted-foreground hover:text-foreground"
            >
              {t("common.cancel")}
            </Link>
          </div>
        </form>
      </AuthFormCard>

      <aside className="space-y-6">
        <ProfileContentCard title={t("forum.newTopicGuidelinesTitle")}>
          <p className="text-sm leading-relaxed text-muted-foreground">
            {t("forum.newTopicGuidelinesBody")}
          </p>
        </ProfileContentCard>
        <ForumRulesBand settings={settings} />
      </aside>
    </div>
  );
}
