"use client";

import { useEffect, useRef } from "react";
import { useRouter } from "next/navigation";

/**
 * Clears a session cookie whose token the API has rejected.
 *
 * Server Components can read cookies but cannot write them, so the cookie is
 * cleared by calling the existing logout route handler, which already expires it.
 * Rendered only when a token is present but `/me` returned 401.
 */
export function StaleSessionCleanup() {
  const router = useRouter();
  const done = useRef(false);

  useEffect(() => {
    if (done.current) {
      return;
    }

    done.current = true;

    // The upstream logout will 401 on a dead token; the cookie is cleared regardless.
    void fetch("/api/session/logout", { method: "POST" })
      .catch(() => undefined)
      .then(() => router.refresh());
  }, [router]);

  return null;
}
