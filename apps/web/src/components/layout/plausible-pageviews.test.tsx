import { render } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";
import { PlausiblePageviews } from "@/components/layout/plausible-pageviews";
import { setPathname } from "../../../test/next-navigation";

describe("PlausiblePageviews", () => {
  afterEach(() => {
    delete window.plausible;
    window.history.replaceState(null, "", "/");
  });

  it("queues a pageview without the query string before the script loads", () => {
    window.history.replaceState(null, "", "/search?q=bolki-vo-gradite#x");
    setPathname("/search");

    render(<PlausiblePageviews />);

    expect(window.plausible?.q).toEqual([
      ["pageview", { u: `${window.location.origin}/search` }],
    ]);
  });

  it("reports each new path through the loaded script", () => {
    const plausible = vi.fn();
    window.plausible = plausible;

    window.history.replaceState(null, "", "/doctors?city=Skopje");
    setPathname("/doctors");
    const { rerender } = render(<PlausiblePageviews />);

    window.history.replaceState(null, "", "/doctors/dr-ana?tab=reviews");
    setPathname("/doctors/dr-ana");
    rerender(<PlausiblePageviews />);

    expect(plausible.mock.calls).toEqual([
      ["pageview", { u: `${window.location.origin}/doctors` }],
      ["pageview", { u: `${window.location.origin}/doctors/dr-ana` }],
    ]);
  });

  it("never reports the e-mail unsubscribe page", () => {
    const plausible = vi.fn();
    window.plausible = plausible;

    window.history.replaceState(null, "", "/unsubscribe?token=1.moderation.x");
    setPathname("/unsubscribe");
    render(<PlausiblePageviews />);

    expect(plausible).not.toHaveBeenCalled();
  });
});
