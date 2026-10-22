import { fireEvent, render, screen } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";

const refresh = vi.fn();
vi.mock("next/navigation", () => ({
  useRouter: () => ({ refresh }),
}));
vi.mock("@/lib/sentry-client", () => ({ reportException: vi.fn() }));

import ErrorBoundary from "@/app/error";
import { t } from "@/i18n/t";

describe("app error boundary", () => {
  beforeEach(() => {
    refresh.mockReset();
    vi.spyOn(console, "error").mockImplementation(() => {});
  });

  it("retry refreshes the server payload and resets the boundary", () => {
    const reset = vi.fn();
    render(<ErrorBoundary error={new Error("boom")} reset={reset} />);

    fireEvent.click(screen.getByRole("button", { name: t("errors.retry") }));

    // reset() alone re-renders with the same failed RSC payload.
    expect(refresh).toHaveBeenCalledTimes(1);
    expect(reset).toHaveBeenCalledTimes(1);
  });
});
