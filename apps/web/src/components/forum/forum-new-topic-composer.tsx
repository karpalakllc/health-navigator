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
    defaultCategorySlug && categories.some((c) => c.slug === defaultCategorySlug)
      ? defaultCategorySlug
      : categories[0]?.slug ?? "",
  );
  const [title, setTitle] = useState("");
  const [body, setBody] = useState("");
  const [acceptedRules, setAcceptedRules] = useState(false);
  const [acceptedNoDiagnosis, setAcceptedNoDiagnosis] = useState(false);
  const [acceptedEmergency, setAcceptedEmergency] = useState(false);
  const [preview, setPreview] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [pending, setPending] = useState(false);

  const canSubmit = acceptedRules && acceptedNoDiagnosis && acceptedEmergency;

  const previewBody = useMemo(
    () => (
      <article className="prose prose-sm max-w-none text-foreground">
        <h3 className="text-xl font-bold">{title || t("forum.previewTitlePlaceholder")}</h3>
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
      setError(t("forum.consentRequired"));
      return;
    }

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
            <span className="font-semibold text-foreground">{t("forum.categories")}</span>
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
            <span className="font-semibold text-foreground">{t("common.title")}</span>
            <input
              value={title}
              onChange={(event) => setTitle(event.target.value)}
              required
              minLength={5}
              className={filterInputClassName}
            />
          </label>
          <label className="grid gap-1.5 text-sm">
            <span className="font-semibold text-foreground">{t("common.message")}</span>
            <textarea
              value={body}
              onChange={(event) => setBody(event.target.value)}
              required
              minLength={20}
              rows={8}
              className={filterInputClassName}
            />
          </label>

          <fieldset className="grid gap-2 rounded-xl border border-border bg-muted/20 p-4">
            <legend className="px-1 text-sm font-semibold text-foreground">{t("forum.consentLegend")}</legend>
            <ConsentCheckbox
              checked={acceptedRules}
              onChange={setAcceptedRules}
              label={t("forum.consentRules")}
            />
            <ConsentCheckbox
              checked={acceptedNoDiagnosis}
              onChange={setAcceptedNoDiagnosis}
              label={t("forum.consentNoDiagnosis")}
            />
            <ConsentCheckbox
              checked={acceptedEmergency}
              onChange={setAcceptedEmergency}
              label={t("forum.consentEmergency")}
            />
          </fieldset>

          {preview ? previewBody : null}

          {error ? <p className="text-sm text-destructive">{error}</p> : null}

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
              className="inline-flex min-h-[44px] items-center justify-center rounded-xl bg-primary px-5 text-sm font-extrabold text-primary-foreground hover:bg-primary/90 disabled:opacity-60"
            >
              {pending ? t("common.submitting") : t("forum.topicSubmitModeration")}
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
          <p className="text-sm leading-relaxed text-muted-foreground">{t("forum.newTopicGuidelinesBody")}</p>
        </ProfileContentCard>
        <ForumRulesBand settings={settings} />
      </aside>
    </div>
  );
}

function ConsentCheckbox({
  checked,
  onChange,
  label,
}: {
  checked: boolean;
  onChange: (value: boolean) => void;
  label: string;
}) {
  return (
    <label className="flex cursor-pointer items-start gap-2.5 text-sm text-muted-foreground">
      <input
        type="checkbox"
        checked={checked}
        onChange={(event) => onChange(event.target.checked)}
        className="mt-1 h-4 w-4 rounded border-border text-primary"
      />
      <span>{label}</span>
    </label>
  );
}
