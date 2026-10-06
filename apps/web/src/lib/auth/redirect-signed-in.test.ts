import { beforeEach, describe, expect, it, vi } from "vitest";

const redirect = vi.fn();
const getShellSession = vi.fn();

vi.mock("next/navigation", () => ({ redirect }));
vi.mock("@/lib/auth/header-session", () => ({ getShellSession }));

const { redirectSignedInToAccount } =
  await import("@/lib/auth/redirect-signed-in");

describe("redirectSignedInToAccount", () => {
  beforeEach(() => {
    redirect.mockReset();
    getShellSession.mockReset();
  });

  it("sends a signed-in member to their account", async () => {
    getShellSession.mockResolvedValue({
      user: { name: "Ана" },
      isLoggedIn: true,
      hasStaleSession: false,
    });

    await redirectSignedInToAccount();

    expect(redirect).toHaveBeenCalledWith("/account");
  });

  it("leaves a signed-out visitor on the page", async () => {
    getShellSession.mockResolvedValue({
      user: null,
      isLoggedIn: false,
      hasStaleSession: false,
    });

    await redirectSignedInToAccount();

    expect(redirect).not.toHaveBeenCalled();
  });

  it("does not redirect on a session the API could not confirm", async () => {
    // A /me hiccup keeps the cookie but gives no user: no redirect loop to an
    // account page that cannot load either.
    getShellSession.mockResolvedValue({
      user: null,
      isLoggedIn: true,
      hasStaleSession: false,
    });

    await redirectSignedInToAccount();

    expect(redirect).not.toHaveBeenCalled();
  });
});
