"use client";

import { useId, useRef, useState } from "react";
import { Button } from "@/components/ui/button";
import { t, tFormat } from "@/i18n/t";

type ReviewShareButtonProps = {
  /** The profile's name, for the lead and the shared text. */
  name: string;
  /** The share link (lib/review-growth reviewShareUrl), the same for everyone. */
  url: string;
};

/**
 * „Препорачајте рецензија“: a patient sends the profile's review link to
 * someone else who was there. The phone's share sheet where there is one
 * (Web Share API), otherwise the link is copied; if even that is refused,
 * the link is shown in a read-only field to copy by hand.
 *
 * Deliberately plain: one link for everyone, no reward, no counter of what
 * was shared, nothing for the doctor's account (docs/reviews-growth.md).
 */
export function ReviewShareButton({ name, url }: ReviewShareButtonProps) {
  const [status, setStatus] = useState<"idle" | "copied" | "manual">("idle");
  const fieldId = useId();
  const field = useRef<HTMLInputElement>(null);

  async function share() {
    const data = {
      title: tFormat("reviewGrowth.shareTitle", { name }),
      text: tFormat("reviewGrowth.shareText", { name }),
      url,
    };

    if (typeof navigator.share === "function") {
      try {
        if (!navigator.canShare || navigator.canShare(data)) {
          await navigator.share(data);
          return;
        }
      } catch (error) {
        // Closing the share sheet is a choice, not a failure.
        if (error instanceof DOMException && error.name === "AbortError") {
          return;
        }
      }
    }

    try {
      await navigator.clipboard.writeText(url);
      setStatus("copied");
    } catch {
      setStatus("manual");
      // Selecting the text is not focus stealing: the visitor just asked
      // for the link and this is where it is.
      requestAnimationFrame(() => field.current?.select());
    }
  }

  return (
    <div className="flex flex-col" data-track="review-share">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p className="type-meta text-ink-2 sm:max-w-[34rem]">
          {tFormat("reviewGrowth.shareLead", { name })}
        </p>
        <Button
          type="button"
          variant="white"
          leadingIcon="share"
          onClick={() => void share()}
          className="self-start sm:shrink-0"
        >
          {t("reviewGrowth.shareButton")}
        </Button>
      </div>
      <p role="status" className="mt-2 type-meta text-ink empty:mt-0">
        {status === "copied"
          ? tFormat("reviewGrowth.shareCopied", { name })
          : null}
      </p>
      {status === "manual" ? (
        <div className="mt-3 flex flex-col gap-1">
          <label htmlFor={fieldId} className="type-meta font-semibold text-ink">
            {t("reviewGrowth.shareCopyLabel")}
          </label>
          <input
            ref={field}
            id={fieldId}
            type="url"
            readOnly
            value={url}
            onFocus={(event) => event.currentTarget.select()}
            aria-describedby={`${fieldId}-hint`}
            className="field-control"
          />
          <p id={`${fieldId}-hint`} className="type-meta text-ink-2">
            {t("reviewGrowth.shareCopyHint")}
          </p>
        </div>
      ) : null}
    </div>
  );
}
