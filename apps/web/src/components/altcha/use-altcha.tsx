"use client";

import { useCallback, useEffect, useRef, type ReactNode } from "react";
import {
  ALTCHA_ELEMENT,
  ALTCHA_MIN_FILL_MS,
  loadAltcha,
  type AltchaWidgetElement,
} from "@/lib/altcha/load";

/** Same-origin relay to GET /v1/altcha/challenge (app/api/altcha/challenge). */
export const ALTCHA_CHALLENGE_URL = "/api/altcha/challenge";

type Attempt = {
  /** Resolves to the widget's base64 payload, or null when solving failed. */
  payload: Promise<string | null>;
  startedAt: number;
};

const sleep = (ms: number) =>
  new Promise<void>((resolve) => setTimeout(resolve, ms));

/**
 * ALTCHA for a form: an invisible widget that starts solving a proof-of-work
 * challenge in a background worker as soon as the form mounts, and `solve()`
 * for the submit handler.
 *
 * - Nothing to click and nothing on screen: the work is done while the
 *   person fills in the form, so submitting rarely waits.
 * - Each payload is handed out once: the API spends it on first use, even
 *   when the request is then refused for another reason. When the API
 *   refuses a submit, call `renew()` so the next challenge is solved while
 *   the person corrects the form (`solve()` without one starts afresh).
 * - `solve()` resolves to null when the widget cannot load or solve (no
 *   network, an old browser): the form shows `altcha.failed` and the person
 *   can simply try again.
 *
 * Render `widget` once inside the form.
 */
export function useAltcha(): {
  widget: ReactNode;
  solve: () => Promise<string | null>;
  renew: () => void;
} {
  const hostRef = useRef<HTMLDivElement>(null);
  const elementRef = useRef<AltchaWidgetElement | null>(null);
  const attemptRef = useRef<Attempt | null>(null);
  const readyRef = useRef<Promise<AltchaWidgetElement | null> | null>(null);
  // Bumped on unmount, so a load that finishes afterwards adds nothing.
  const generationRef = useRef(0);

  const ready = useCallback((): Promise<AltchaWidgetElement | null> => {
    const generation = generationRef.current;

    readyRef.current ??= loadAltcha()
      .then(async () => {
        const host = hostRef.current;

        if (!host || generation !== generationRef.current) {
          return null;
        }

        const element = document.createElement(
          ALTCHA_ELEMENT,
        ) as AltchaWidgetElement;
        // Invisible from its first render; configure() below adds the rest.
        element.setAttribute("display", "invisible");
        element.setAttribute("auto", "off");
        host.appendChild(element);
        // The element mounts its component a microtask after it is
        // connected; its methods exist only from then on.
        for (
          let tries = 0;
          typeof element.configure !== "function" && tries < 50;
          tries += 1
        ) {
          await new Promise((resolve) => setTimeout(resolve, 10));
        }

        if (generation !== generationRef.current || !element.isConnected) {
          // Unmounted meanwhile: do no work for a form that is gone.
          element.remove();

          return null;
        }

        await element.configure({
          challenge: ALTCHA_CHALLENGE_URL,
          display: "invisible",
          auto: "off",
          hideFooter: true,
          hideLogo: true,
          // No pointer or scroll tracking: the proof of work is enough.
          humanInteractionSignature: false,
          language: "mk",
          name: "altcha",
          minDuration: 0,
          workers: Math.max(
            1,
            Math.min(4, globalThis.navigator?.hardwareConcurrency ?? 2),
          ),
        });
        elementRef.current = element;

        return element;
      })
      .catch((error: unknown) => {
        // The form then says altcha.failed; this tells a developer why.
        console.warn("ALTCHA could not start", error);
        readyRef.current = null;

        return null;
      });

    return readyRef.current;
  }, []);

  const start = useCallback((): Attempt => {
    const attempt: Attempt = {
      startedAt: Date.now(),
      payload: ready().then(async (element) => {
        if (!element) {
          return null;
        }

        // The challenge is fetched (and its issue time set) from here on.
        attempt.startedAt = Date.now();

        try {
          return (await element.verify())?.payload ?? null;
        } catch {
          return null;
        }
      }),
    };
    attemptRef.current = attempt;

    return attempt;
  }, [ready]);

  useEffect(() => {
    start();
    // An unused solution ran out (a form left open for a long time): start
    // a new one so the eventual submit carries a live one.
    const onExpired = () => {
      if (attemptRef.current !== null) {
        start();
      }
    };

    void ready().then((element) =>
      element?.addEventListener("expired", onExpired),
    );

    return () => {
      generationRef.current += 1;
      elementRef.current?.removeEventListener("expired", onExpired);
      elementRef.current?.reset();
      elementRef.current?.remove();
      elementRef.current = null;
      attemptRef.current = null;
      readyRef.current = null;
    };
  }, [ready, start]);

  const solve = useCallback(async (): Promise<string | null> => {
    const attempt = attemptRef.current ?? start();
    // Hand this one out once; whatever happens next needs a new one.
    attemptRef.current = null;

    const payload = await attempt.payload;
    const wait = attempt.startedAt + ALTCHA_MIN_FILL_MS - Date.now();

    if (payload !== null && wait > 0) {
      await sleep(wait);
    }

    return payload;
  }, [start]);

  const renew = useCallback(() => {
    if (attemptRef.current === null) {
      start();
    }
  }, [start]);

  return {
    widget: <div ref={hostRef} hidden data-altcha="" />,
    solve,
    renew,
  };
}
