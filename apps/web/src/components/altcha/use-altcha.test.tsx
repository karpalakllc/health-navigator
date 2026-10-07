import { act, render, waitFor } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { useAltcha } from "@/components/altcha/use-altcha";
import { altchaControl } from "../../../test/altcha";

/**
 * useAltcha against the fake widget (test/altcha.ts): solving starts on
 * mount, each payload is handed out once, renew() prepares the next one,
 * and a failure is null rather than an exception.
 */
function harness() {
  const api: { current: ReturnType<typeof useAltcha> | null } = {
    current: null,
  };

  function Form() {
    const altcha = useAltcha();
    api.current = altcha;

    return <form>{altcha.widget}</form>;
  }

  const view = render(<Form />);

  return { api: () => api.current!, view };
}

describe("useAltcha", () => {
  it("starts solving on mount with an invisible, hidden widget", async () => {
    const { view } = harness();

    // The element mounts a microtask after it is connected; the hook waits
    // for that before configuring it.
    await waitFor(() => expect(altchaControl.issued).toBe(1));

    const host = view.container.querySelector("[data-altcha]");
    expect(host).toHaveAttribute("hidden");
    expect(host?.querySelector("altcha-widget")).not.toBeNull();
    expect(altchaControl.configure).toHaveBeenCalledTimes(1);
  });

  it("hands each payload out once and solves a new one on renew()", async () => {
    const { api } = harness();

    expect(await api().solve()).toBe("test-altcha-1");

    act(() => api().renew());
    // renew() when one is already waiting does not throw it away.
    act(() => api().renew());
    expect(await api().solve()).toBe("test-altcha-2");

    // Without renew() the next solve starts afresh.
    expect(await api().solve()).toBe("test-altcha-3");
    expect(altchaControl.issued).toBe(3);
  });

  it("resolves to null when the widget cannot solve", async () => {
    altchaControl.fail = true;
    const { api } = harness();

    expect(await api().solve()).toBeNull();
  });

  it("removes the widget on unmount", async () => {
    const { view } = harness();
    await waitFor(() => expect(altchaControl.issued).toBe(1));

    view.unmount();

    expect(document.querySelector("altcha-widget")).toBeNull();
  });
});
