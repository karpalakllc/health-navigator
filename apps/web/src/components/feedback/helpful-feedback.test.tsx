import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { HelpfulFeedback } from "@/components/feedback/helpful-feedback";
import { seriousA11yViolations } from "../../../test/axe";

const fetchMock = vi.fn();

beforeEach(() => {
  fetchMock.mockResolvedValue(new Response(null, { status: 204 }));
  vi.stubGlobal("fetch", fetchMock);
});

afterEach(() => {
  vi.unstubAllGlobals();
  fetchMock.mockReset();
});

function sent() {
  return fetchMock.mock.calls.map(([url, init]) => [
    url,
    JSON.parse(init.body),
  ]);
}

describe("HelpfulFeedback", () => {
  it("sends the vote at once, then up to three optional reasons", async () => {
    const user = userEvent.setup();
    const { container } = render(<HelpfulFeedback item="guide:kako-do-uput" />);

    expect(await seriousA11yViolations(container)).toEqual([]);

    await user.click(screen.getByRole("button", { name: "Не" }));
    expect(sent()).toEqual([
      [
        "/api/feedback",
        { kind: "vote", item: "guide:kako-do-uput", helpful: false },
      ],
    ]);
    expect(screen.getByText("Ви благодариме.")).toBeInTheDocument();
    // Only the „not helpful“ chips; no text box anywhere.
    expect(
      screen.queryByRole("button", { name: "Јасно објаснето" }),
    ).toBeNull();
    expect(screen.queryByRole("textbox")).toBeNull();

    await user.click(screen.getByRole("button", { name: "Застарено" }));
    await user.click(screen.getByRole("button", { name: "Нејасно" }));
    await user.click(screen.getByRole("button", { name: "Погрешни податоци" }));
    // A fourth is ignored.
    await user.click(
      screen.getByRole("button", { name: "Не најдов што барав" }),
    );
    expect(
      screen.getByRole("button", { name: "Не најдов што барав" }),
    ).toHaveAttribute("aria-pressed", "false");
    expect(await seriousA11yViolations(container)).toEqual([]);

    await user.click(screen.getByRole("button", { name: "Испрати" }));
    expect(sent()[1]).toEqual([
      "/api/feedback",
      {
        kind: "reasons",
        item: "guide:kako-do-uput",
        helpful: false,
        reasons: ["outdated", "unclear", "wrong-info"],
      },
    ]);
    expect(screen.getByText(/ова ни помага/)).toBeInTheDocument();
  });

  it("renders nothing for a key outside the vocabulary", () => {
    const { container } = render(<HelpfulFeedback item="guide:Мој водич" />);

    expect(container).toBeEmptyDOMElement();
  });
});
