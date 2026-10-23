"use client";

import { useEffect, useState } from "react";
import { Button } from "@/components/ui/button";
import { REVIEW_ANCHOR } from "@/components/reviews/review-paths";
import { loginHref } from "@/lib/auth/login-href";
import { readAnyReviewDraft, type ReviewDraft } from "@/lib/review-growth";
import { t, tFormat } from "@/i18n/t";

/**
 * After the e-mail check (often opened in a new tab): when this device holds
 * a review started before registering, offer the way back to it — through
 * sign-in, to the profile's form, where the draft is restored.
 */
export function ContinueReviewLink() {
  const [draft, setDraft] = useState<ReviewDraft | null>(null);

  // A frame after hydration: the draft lives in this browser's storage.
  useEffect(() => {
    const frame = requestAnimationFrame(() => setDraft(readAnyReviewDraft()));

    return () => cancelAnimationFrame(frame);
  }, []);

  if (!draft) {
    return null;
  }

  return (
    <Button
      href={loginHref(`${draft.path}#${REVIEW_ANCHOR}`)}
      variant="secondary"
      size="lg"
      fullWidth
      leadingIcon="star"
    >
      {draft.name
        ? tFormat("reviewGrowth.continueDraft", { name: draft.name })
        : t("reviewGrowth.continueDraftGeneric")}
    </Button>
  );
}
