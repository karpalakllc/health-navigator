import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

const router = vi.hoisted(() => ({ push: vi.fn(), refresh: vi.fn() }));

vi.mock("next/navigation", () => ({
  useRouter: () => router,
  usePathname: () => "/doctors/ana",
}));

import { LogoutButton } from "@/components/auth/logout-button";
import {
  DRAFT_KEY,
  readAnyReviewDraft,
  saveReviewDraft,
} from "@/lib/review-growth";
import { t } from "@/i18n/t";

function saveDraft() {
  saveReviewDraft({
    kind: "doctor",
    slug: "ana",
    name: "д-р Ана",
    path: "/doctors/ana",
    rating: 4,
    body: "Љубезна и темелна.",
    aspects: {},
  });
}

beforeEach(() => {
  window.localStorage.clear();
  vi.clearAllMocks();
});

afterEach(() => {
  vi.unstubAllGlobals();
});

describe("LogoutButton", () => {
  it("clears the review draft kept across sign-in once signed out", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn(async () => new Response(null, { status: 204 })),
    );
    saveDraft();
    expect(readAnyReviewDraft()).not.toBeNull();
    const user = userEvent.setup();
    render(<LogoutButton />);

    await user.click(screen.getByRole("button", { name: t("nav.logout") }));

    expect(window.localStorage.getItem(DRAFT_KEY)).toBeNull();
    expect(router.refresh).toHaveBeenCalled();
  });

  it("clears unsent forum drafts and reading marks once signed out", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn(async () => new Response(null, { status: 204 })),
    );
    window.localStorage.setItem("z360:forum-drafts:v1", "{}");
    window.localStorage.setItem("z360:forum-seen:v1", "{}");
    const user = userEvent.setup();
    render(<LogoutButton />);

    await user.click(screen.getByRole("button", { name: t("nav.logout") }));

    expect(window.localStorage.getItem("z360:forum-drafts:v1")).toBeNull();
    expect(window.localStorage.getItem("z360:forum-seen:v1")).toBeNull();
  });

  it("keeps the draft when the sign-out fails (still signed in)", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn(async () => new Response(null, { status: 500 })),
    );
    saveDraft();
    const user = userEvent.setup();
    render(<LogoutButton />);

    await user.click(screen.getByRole("button", { name: t("nav.logout") }));

    expect(readAnyReviewDraft()).not.toBeNull();
    expect(router.refresh).not.toHaveBeenCalled();
  });
});
