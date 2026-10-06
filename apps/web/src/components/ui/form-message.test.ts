import { createElement } from "react";
import { renderToStaticMarkup } from "react-dom/server";
import { describe, expect, it } from "vitest";
import { FormSuccess } from "@/components/ui/form-message";

/**
 * A polite live region inserted already holding its text is often not
 * announced. FormSuccess must therefore render its region even when there is
 * nothing to say yet, so callers can keep it mounted and fill it later.
 */
describe("FormSuccess", () => {
  it("renders an empty, visually hidden status region when there is no message", () => {
    const html = renderToStaticMarkup(createElement(FormSuccess, null, null));

    expect(html).toBe(
      '<p role="status" aria-live="polite" class="sr-only"></p>',
    );
  });

  it("fills the same region once there is a message", () => {
    const html = renderToStaticMarkup(
      createElement(FormSuccess, null, "Испратено."),
    );

    expect(html).toContain('role="status"');
    expect(html).toContain("Испратено.");
    expect(html).not.toContain("sr-only");
  });
});
