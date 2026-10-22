import { fireEvent, render, screen } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("@/lib/sentry-client", () => ({ reportException: vi.fn() }));

import ErrorBoundary from "@/app/error";
import GlobalError from "@/app/global-error";
import { t } from "@/i18n/t";

describe("error boundaries", () => {
  beforeEach(() => {
    vi.spyOn(console, "error").mockImplementation(() => {});
  });

  it("the page boundary's button calls Next's retry", () => {
    const retry = vi.fn();
    render(<ErrorBoundary error={new Error("boom")} retry={retry} />);

    fireEvent.click(screen.getByRole("button", { name: t("errors.retry") }));

    // retry (not reset) refetches the failed server payload.
    expect(retry).toHaveBeenCalledTimes(1);
  });

  it("the global boundary's button calls Next's retry", () => {
    const retry = vi.fn();
    render(<GlobalError error={new Error("boom")} retry={retry} />, {
      container: document.documentElement,
    });

    fireEvent.click(screen.getByRole("button", { name: t("errors.retry") }));

    expect(retry).toHaveBeenCalledTimes(1);
  });
});
