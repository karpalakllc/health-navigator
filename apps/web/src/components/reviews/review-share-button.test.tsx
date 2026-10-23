import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, describe, expect, it, vi } from "vitest";
import { ReviewShareButton } from "@/components/reviews/review-share-button";
import { t, tFormat } from "@/i18n/t";
import { seriousA11yViolations } from "../../../test/axe";

const NAME = "д-р Ана Петровска";
const URL_ =
  "https://zdravje360.mk/doctors/ana?utm_source=share&utm_medium=referral&utm_campaign=napishi-recenzija#napishi-recenzija";

function shareButton() {
  return screen.getByRole("button", { name: t("reviewGrowth.shareButton") });
}

function setNavigator(values: Partial<Navigator>) {
  for (const [key, value] of Object.entries(values)) {
    Object.defineProperty(navigator, key, { value, configurable: true });
  }
}

afterEach(() => {
  for (const key of ["share", "canShare", "clipboard"]) {
    delete (navigator as unknown as Record<string, unknown>)[key];
  }
});

describe("ReviewShareButton", () => {
  it("opens the phone's share sheet with the same link for everyone", async () => {
    const share = vi.fn().mockResolvedValue(undefined);
    setNavigator({ share, canShare: () => true });
    // userEvent installs its own clipboard; install ours after it.
    const user = userEvent.setup();
    const writeText = vi.fn();
    setNavigator({ clipboard: { writeText } as unknown as Clipboard });
    render(<ReviewShareButton name={NAME} url={URL_} />);

    await user.click(shareButton());

    expect(share).toHaveBeenCalledWith({
      title: tFormat("reviewGrowth.shareTitle", { name: NAME }),
      text: tFormat("reviewGrowth.shareText", { name: NAME }),
      url: URL_,
    });
    expect(writeText).not.toHaveBeenCalled();
  });

  it("treats closing the share sheet as a choice, not an error", async () => {
    setNavigator({
      share: vi.fn().mockRejectedValue(new DOMException("x", "AbortError")),
    });
    const user = userEvent.setup();
    const writeText = vi.fn();
    setNavigator({ clipboard: { writeText } as unknown as Clipboard });
    render(<ReviewShareButton name={NAME} url={URL_} />);

    await user.click(shareButton());

    expect(writeText).not.toHaveBeenCalled();
    expect(screen.getByRole("status")).toBeEmptyDOMElement();
  });

  it("copies the link where there is no share sheet, and says so", async () => {
    const user = userEvent.setup();
    const writeText = vi.fn().mockResolvedValue(undefined);
    setNavigator({ clipboard: { writeText } as unknown as Clipboard });
    render(<ReviewShareButton name={NAME} url={URL_} />);

    await user.click(shareButton());

    expect(writeText).toHaveBeenCalledWith(URL_);
    expect(screen.getByRole("status")).toHaveTextContent(
      tFormat("reviewGrowth.shareCopied", { name: NAME }),
    );
  });

  it("shows the link to copy by hand when copying is refused", async () => {
    const user = userEvent.setup();
    setNavigator({
      clipboard: {
        writeText: vi.fn().mockRejectedValue(new Error("denied")),
      } as unknown as Clipboard,
    });
    const { container } = render(<ReviewShareButton name={NAME} url={URL_} />);

    await user.click(shareButton());

    const field = screen.getByLabelText(t("reviewGrowth.shareCopyLabel"));
    expect(field).toHaveValue(URL_);
    expect(field).toHaveAttribute("readonly");
    expect(await seriousA11yViolations(container)).toEqual([]);
  });

  it("has no serious axe violations", async () => {
    const { container } = render(<ReviewShareButton name={NAME} url={URL_} />);

    expect(await seriousA11yViolations(container)).toEqual([]);
  });
});
