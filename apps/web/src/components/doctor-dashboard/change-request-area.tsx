"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useRef,
  useState,
  type ReactNode,
} from "react";

const AnnounceContext = createContext<(message: string) => void>(() => {});

/** Lets the change-request form and the pending request report a result. */
export function useChangeRequestAnnounce() {
  return useContext(AnnounceContext);
}

/**
 * Wraps „Податоци што ги проверува тимот“, whose content the server swaps
 * after router.refresh(): sending a request replaces the form with the
 * pending request, withdrawing brings the form back. Either way the button
 * that was used disappears, and a confirmation inside the old content would
 * vanish with it. This wrapper stays mounted across the swap, so it holds the
 * confirmation in a persistent live region and moves focus to it.
 */
export function ChangeRequestArea({ children }: { children: ReactNode }) {
  const [message, setMessage] = useState<{ text: string; key: number } | null>(
    null,
  );
  const messageRef = useRef<HTMLParagraphElement>(null);

  const announce = useCallback((text: string) => {
    setMessage((previous) => ({ text, key: (previous?.key ?? 0) + 1 }));
  }, []);

  useEffect(() => {
    if (message) {
      messageRef.current?.focus();
    }
  }, [message]);

  return (
    <AnnounceContext.Provider value={announce}>
      <div role="status" aria-live="polite" className="empty:hidden">
        {message ? (
          <p
            ref={messageRef}
            tabIndex={-1}
            className="type-body font-semibold text-care"
          >
            {message.text}
          </p>
        ) : null}
      </div>
      {children}
    </AnnounceContext.Provider>
  );
}
