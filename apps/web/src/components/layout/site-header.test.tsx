import { render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { SiteHeader } from "@/components/layout/site-header";
import { ApiRequestError } from "@/lib/api/errors";
import type { AuthUser } from "@/lib/api/me";
import { t } from "@/i18n/t";
import { mockFetch } from "../../../test/fetch";
import { router } from "../../../test/next-navigation";

// SiteHeader is an async Server Component; it is awaited to plain JSX here and
// its server-side data sources are replaced, so the rendered decision — which
// header shell a visitor gets — can be checked in jsdom.
const session = vi.hoisted(() => ({
  getSessionToken: vi.fn<() => Promise<string | null>>(),
  fetchMe: vi.fn<() => Promise<unknown>>(),
  captureException: vi.fn(),
}));

vi.mock("@/lib/auth/session", () => ({
  getSessionToken: session.getSessionToken,
}));
vi.mock("@/lib/api/me", () => ({ fetchMe: session.fetchMe }));
vi.mock("@/lib/api/server", async () => ({
  ApiRequestError: (await import("@/lib/api/errors")).ApiRequestError,
}));
vi.mock("@/lib/api/settings", () => ({
  fetchPublicSettings: async () => ({
    logo_url: null,
    public_guidance: true,
    public_products: true,
    public_pharmacies: true,
    public_forum: true,
  }),
}));
vi.mock("@sentry/nextjs", () => ({
  captureException: session.captureException,
}));

const ana: AuthUser = {
  id: 5,
  name: "Ана Петровска",
  display_name: "Ана П.",
  email: "ana@example.mk",
  role: "member",
  community_roles: [],
  avatar_url: null,
  avatar_initials: "АП",
  profile_avatar: { min_messages: 10, message_count: 0, can_change: false },
};

async function renderHeader() {
  return render(await SiteHeader());
}

function accountButton() {
  return screen.queryByRole("button", { name: t("nav.account") });
}

function loginLink() {
  return screen.queryByRole("link", { name: t("nav.login") });
}

describe("SiteHeader", () => {
  beforeEach(() => {
    session.getSessionToken.mockReset();
    session.fetchMe.mockReset();
    session.captureException.mockReset();
  });

  it("shows the sign-in link to a visitor without a session", async () => {
    session.getSessionToken.mockResolvedValue(null);
    await renderHeader();

    expect(loginLink()).toBeInTheDocument();
    expect(accountButton()).toBeNull();
    expect(session.fetchMe).not.toHaveBeenCalled();
  });

  it("shows the account menu to a signed-in member", async () => {
    session.getSessionToken.mockResolvedValue("tok");
    session.fetchMe.mockResolvedValue(ana);
    await renderHeader();

    expect(accountButton()).toBeInTheDocument();
    expect(loginLink()).toBeNull();
  });

  it.each([
    ["a 5xx", new ApiRequestError("Server error", 503)],
    ["a 429", new ApiRequestError("Too many requests", 429)],
    ["a network failure", new TypeError("fetch failed")],
  ])("keeps the signed-in shell when /me fails with %s", async (_, error) => {
    const fetch = mockFetch({ status: 204 });
    session.getSessionToken.mockResolvedValue("tok");
    session.fetchMe.mockRejectedValue(error);

    await renderHeader();

    // Degraded, not signed out: a placeholder account menu, no login link,
    // the error reported, and the session cookie left alone.
    expect(accountButton()).toBeInTheDocument();
    expect(loginLink()).toBeNull();
    expect(session.captureException).toHaveBeenCalledWith(error);
    expect(fetch).not.toHaveBeenCalled();
  });

  it("treats a 401 from /me as a dead session and clears it", async () => {
    const fetch = mockFetch({ status: 204 });
    session.getSessionToken.mockResolvedValue("stale");
    session.fetchMe.mockRejectedValue(
      new ApiRequestError("Unauthenticated", 401),
    );

    await renderHeader();

    expect(loginLink()).toBeInTheDocument();
    expect(accountButton()).toBeNull();
    expect(session.captureException).not.toHaveBeenCalled();
    await waitFor(() =>
      expect(fetch).toHaveBeenCalledWith(
        "/api/session/logout",
        expect.objectContaining({ method: "POST" }),
      ),
    );
    await waitFor(() => expect(router.refresh).toHaveBeenCalled());
  });
});
