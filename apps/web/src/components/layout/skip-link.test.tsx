import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { MAIN_CONTENT_ID, SkipLink } from "@/components/layout/skip-link";

describe("SkipLink", () => {
  it("links past the header to the main landmark", () => {
    render(<SkipLink />);

    expect(
      screen.getByRole("link", { name: "Прескокни до содржината" }),
    ).toHaveAttribute("href", `#${MAIN_CONTENT_ID}`);
  });
});
