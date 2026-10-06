"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { formatCharCounter } from "@/components/forum/char-counter";
import { ForumRulesCard } from "@/components/forum/forum-rules-band";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Checkbox, Input, Select, Textarea } from "@/components/ui/field";
import { FormError } from "@/components/ui/form-message";
import { Icon } from "@/components/ui/icons";
import { Tag } from "@/components/ui/tag";
import type { ForumCategory } from "@/lib/api/forum";
import type { PublicSettings } from "@/lib/api/settings";
import { focusField, lengthError } from "@/lib/form-validation";
import { t } from "@/i18n/t";

const CONSENT_ID = "forum-topic-consent";
const PREVIEW_ID = "forum-topic-preview";
const TITLE_ID = "forum-topic-title";
const BODY_ID = "forum-topic-body";
/** API limits (StoreForumTopicRequest). */
const TITLE_MIN_LENGTH = 5;
const TITLE_MAX_LENGTH = 255;
const BODY_MIN_LENGTH = 20;
const BODY_MAX_LENGTH = 10000;

type ForumNewTopicComposerProps = {
  categories: ForumCategory[];
  defaultCategorySlug?: string;
  settings: Pick<
    PublicSettings,
    "forum_rules_enabled" | "forum_rules_title" | "forum_rules_body"
  >;
};

/**
 * The new-topic composer. On mobile it fills the screen (no card) and keeps
 * „Преглед“ and „Испрати на проверка“ in a bar above the tab bar; on desktop
 * it is a card beside the guidelines and the rules.
 */
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
  const [titleError, setTitleError] = useState<string | null>(null);
  const [bodyError, setBodyError] = useState<string | null>(null);

  const canSubmit = accepted;

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault();

    // Our own checks (the form is noValidate): the API's min lengths, in
    // Macedonian, under each field.
    const problems = {
      title: lengthError(title, TITLE_MIN_LENGTH),
      body: lengthError(body, BODY_MIN_LENGTH),
    };
    setTitleError(problems.title);
    setBodyError(problems.body);

    if (problems.title || problems.body) {
      setConsentMissing(!canSubmit);
      setError(null);
      focusField(problems.title ? TITLE_ID : BODY_ID);
      return;
    }

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
    <div className="grid grid-cols-[minmax(0,1fr)] items-start gap-8 lg:grid-cols-[minmax(0,1fr)_22rem]">
      <Card
        padding="none"
        className="max-lg:bg-transparent max-lg:shadow-none lg:p-8"
      >
        <form noValidate onSubmit={handleSubmit} className="flex flex-col gap-6">
          <Select
            label={t("forum.category")}
            value={categorySlug}
            onChange={(event) => setCategorySlug(event.target.value)}
            required
          >
            {categories.map((category) => (
              <option key={category.slug} value={category.slug}>
                {category.name}
              </option>
            ))}
          </Select>
          <Input
            id={TITLE_ID}
            label={t("common.title")}
            error={titleError ?? undefined}
            value={title}
            onChange={(event) => {
              setTitle(event.target.value);
              if (titleError) {
                setTitleError(
                  lengthError(event.target.value, TITLE_MIN_LENGTH),
                );
              }
            }}
            required
            minLength={TITLE_MIN_LENGTH}
            maxLength={TITLE_MAX_LENGTH}
          />
          <Textarea
            id={BODY_ID}
            label={t("common.message")}
            hint={t("forum.replyHint")}
            error={bodyError ?? undefined}
            value={body}
            onChange={(event) => {
              setBody(event.target.value);
              if (bodyError) {
                setBodyError(lengthError(event.target.value, BODY_MIN_LENGTH));
              }
            }}
            required
            minLength={BODY_MIN_LENGTH}
            maxLength={BODY_MAX_LENGTH}
            rows={8}
            counter={formatCharCounter(body.length, BODY_MAX_LENGTH)}
          />

          <div className="rounded-card bg-sand px-4 py-1">
            <Checkbox
              id={CONSENT_ID}
              label={t("forum.consent")}
              checked={accepted}
              onChange={(event) => {
                setAccepted(event.target.checked);
                if (event.target.checked) {
                  setConsentMissing(false);
                }
              }}
              required
              error={consentMissing ? t("forum.consentRequired") : undefined}
            />
          </div>

          {preview ? (
            <Card
              id={PREVIEW_ID}
              tone="sand"
              padding="md"
              className="flex flex-col gap-3"
            >
              <Tag tone="white" icon="eye" className="self-start">
                {t("forum.previewLabel")}
              </Tag>
              <h2 className="type-h2 text-ink">
                {title || t("forum.previewTitlePlaceholder")}
              </h2>
              <p className="whitespace-pre-wrap break-words type-reading text-ink">
                {body || t("forum.previewBodyPlaceholder")}
              </p>
            </Card>
          ) : null}

          {error ? (
            <FormError>
              {consentMissing ? (
                <a
                  href={`#${CONSENT_ID}`}
                  className="underline decoration-2 underline-offset-4"
                >
                  {error}
                </a>
              ) : (
                error
              )}
            </FormError>
          ) : null}

          <p className="flex items-start gap-2 type-meta text-ink-2">
            <Icon name="clock" size={20} className="mt-0.5" />
            <span>{t("forum.topicModerationNote")}</span>
          </p>

          <div className="sticky bottom-[var(--tabbar-space)] z-10 -mx-5 flex gap-3 rounded-t-sheet bg-white px-5 py-3 shadow-sheet lg:static lg:mx-0 lg:justify-end lg:rounded-none lg:bg-transparent lg:p-0 lg:shadow-none">
            <Button
              variant="secondary"
              size="lg"
              leadingIcon={preview ? "eye-off" : "eye"}
              aria-expanded={preview}
              aria-controls={preview ? PREVIEW_ID : undefined}
              onClick={() => setPreview((current) => !current)}
              className="max-lg:px-4"
            >
              {/* Icon-only in the narrow mobile bar; the name stays. */}
              <span className="max-lg:sr-only">
                {preview ? t("forum.hidePreview") : t("forum.showPreview")}
              </span>
            </Button>
            <Button
              type="submit"
              size="lg"
              leadingIcon="send"
              disabled={pending || !canSubmit}
              loading={pending}
              className="flex-1 max-lg:px-4 lg:flex-none"
            >
              {pending
                ? t("common.submitting")
                : t("forum.topicSubmitModeration")}
            </Button>
          </div>
        </form>
      </Card>

      <aside className="flex flex-col gap-5">
        <Card tone="apricot" padding="md" className="flex flex-col gap-2">
          <h2 className="type-h3 text-ink">
            {t("forum.newTopicGuidelinesTitle")}
          </h2>
          <p className="type-body text-ink">
            {t("forum.newTopicGuidelinesBody")}
          </p>
        </Card>
        <ForumRulesCard settings={settings} headingId="forum-composer-rules" />
      </aside>
    </div>
  );
}
