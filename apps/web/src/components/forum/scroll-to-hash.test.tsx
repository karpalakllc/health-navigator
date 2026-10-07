import { render } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";
import { ScrollToHash } from "@/components/forum/scroll-to-hash";

describe("ScrollToHash", () => {
  afterEach(() => {
    window.history.replaceState(null, "", "/");
    document.body.innerHTML = "";
  });

  function box() {
    const element = document.createElement("section");
    element.id = "forum-reply";
    element.scrollIntoView = vi.fn();
    document.body.appendChild(element);
    return element;
  }

  it("scrolls the reply box into view when the address ends in its id", () => {
    const element = box();
    window.history.replaceState(null, "", "/forum/a/b#forum-reply");

    render(<ScrollToHash id="forum-reply" />);

    expect(element.scrollIntoView).toHaveBeenCalledWith({ block: "start" });
  });

  it("leaves the scroll alone otherwise", () => {
    const element = box();
    window.history.replaceState(null, "", "/forum/a/b#post-3");

    render(<ScrollToHash id="forum-reply" />);

    expect(element.scrollIntoView).not.toHaveBeenCalled();
  });
});
