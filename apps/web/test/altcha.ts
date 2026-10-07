import { vi } from "vitest";

/**
 * Component tests run without Web Workers or the real ALTCHA widget. This
 * stands in for src/lib/altcha/load.ts (mocked in setup-dom.ts): it defines
 * an `altcha-widget` element whose verify() hands out numbered payloads
 * („test-altcha-1“, …) at once, so forms submit as in a browser and tests
 * can see which payload each request carried. `altchaControl` lets a test
 * make solving fail or inspect calls.
 */

export const altchaControl = {
  fail: false,
  issued: 0,
  configure: vi.fn(),
  reset() {
    this.fail = false;
    this.issued = 0;
    this.configure.mockClear();
  },
};

/**
 * Like the real (Svelte) element, its methods exist only once it has
 * mounted, a microtask after it is connected.
 */
class FakeAltchaWidget extends HTMLElement {
  configure?: (config: unknown) => Promise<void>;
  getState?: () => string;
  verify?: () => Promise<{ payload: string } | null>;
  reset?: () => void;

  async connectedCallback() {
    await Promise.resolve();
    this.configure = async (config) => {
      altchaControl.configure(config);
    };
    this.getState = () => "unverified";
    this.verify = async () => {
      if (altchaControl.fail) {
        return null;
      }

      altchaControl.issued += 1;

      return { payload: `test-altcha-${altchaControl.issued}` };
    };
    this.reset = () => {};
  }
}

export const altchaLoadMock = {
  ALTCHA_ELEMENT: "altcha-widget",
  ALTCHA_MIN_FILL_MS: 0,
  loadAltcha: async () => {
    if (!customElements.get("altcha-widget")) {
      customElements.define("altcha-widget", FakeAltchaWidget);
    }
  },
};
