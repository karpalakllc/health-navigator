import type { AltchaGlobal, Configuration, State } from "altcha/types";
import { mk } from "@/i18n/mk";

/**
 * Loads the ALTCHA widget (npm `altcha`, MIT) in the browser, once.
 *
 * - `altcha/external` is the CSP-friendly build: no inline workers and no
 *   injected stylesheet. Its only algorithm here is PBKDF2/SHA-256, the one
 *   our API issues (App\Support\Altcha\AltchaGuard), run in a same-origin
 *   worker (./pbkdf2.worker.ts).
 * - Its strings come from mk.ts (the widget normally stays invisible).
 *
 * Dynamic import, so the ~60 kB widget is fetched only by pages with a
 * protected form, and never during server rendering.
 */

/** The custom element's methods we use (altcha/types WidgetMethods). */
export type AltchaWidgetElement = HTMLElement & {
  configure: (config: Partial<Configuration>) => Promise<void>;
  getState: () => State | `${State}`;
  verify: () => Promise<{ payload: string } | null>;
  reset: () => void;
};

export const ALTCHA_ELEMENT = "altcha-widget";

/**
 * The API refuses a solution returned sooner than ALTCHA_MIN_FILL_SECONDS (2)
 * after its challenge was issued (whole seconds, server clock); the forms
 * wait a second longer than that before sending one, so network jitter never
 * gets a quick but honest person refused. (Zero in the component tests.)
 */
export const ALTCHA_MIN_FILL_MS = 3000;

let loading: Promise<void> | null = null;

export function loadAltcha(): Promise<void> {
  if (typeof window === "undefined") {
    return Promise.reject(new Error("ALTCHA runs in the browser only."));
  }

  loading ??= (async () => {
    await import("altcha/external");

    const altcha = (globalThis as unknown as { $altcha?: AltchaGlobal })
      .$altcha;

    if (!altcha) {
      throw new Error("ALTCHA did not load.");
    }

    altcha.algorithms.set(
      "PBKDF2/SHA-256",
      () =>
        new Worker(new URL("./pbkdf2.worker.ts", import.meta.url), {
          name: "altcha-pbkdf2",
        }),
    );
    altcha.i18n.set("mk", { ...mk.altcha.widget });

    await customElements.whenDefined(ALTCHA_ELEMENT);
  })().catch((error: unknown) => {
    // Let a later form try again (e.g. after a network hiccup).
    loading = null;
    throw error;
  });

  return loading;
}
