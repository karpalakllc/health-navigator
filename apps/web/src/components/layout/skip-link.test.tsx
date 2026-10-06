import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { MAIN_CONTENT_ID, SkipLink } from "@/components/layout/skip-link";
import { PageShell } from "@/components/ui/page-shell";

describe("SkipLink", () => {
  it("links past the header to the main landmark", () => {
    render(<SkipLink />);

    expect(
      screen.getByRole("link", { name: "Прескокни до содржината" }),
    ).toHaveAttribute("href", `#${MAIN_CONTENT_ID}`);
  });

  it("leaves the single main landmark to the layout", () => {
    // The layout's <main id="main"> wraps PageShell; a second <main> inside it
    // would be an invalid nested landmark.
    render(
      <main id={MAIN_CONTENT_ID}>
        <PageShell>
          <p>Содржина</p>
        </PageShell>
      </main>,
    );

    expect(screen.getAllByRole("main")).toHaveLength(1);
  });
});
