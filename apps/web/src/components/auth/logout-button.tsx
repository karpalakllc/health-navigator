"use client";

import { usePathname, useRouter } from "next/navigation";
import { useState } from "react";
import { cn } from "@/lib/cn";
import { t } from "@/i18n/t";

const PROTECTED_PREFIXES = ["/account"];

function isProtectedPath(pathname: string): boolean {
  return PROTECTED_PREFIXES.some(
    (prefix) => pathname === prefix || pathname.startsWith(`${prefix}/`),
  );
}

export function LogoutButton({
  className,
  onLoggedOut,
}: {
  className?: string;
  onLoggedOut?: () => void;
}) {
  const router = useRouter();
  const pathname = usePathname();
  const [pending, setPending] = useState(false);

  async function handleLogout() {
    setPending(true);
    try {
      const response = await fetch("/api/session/logout", { method: "POST" });

      if (!response.ok) {
        return;
      }

      onLoggedOut?.();

      if (isProtectedPath(pathname)) {
        router.push("/");
        router.refresh();
      } else {
        router.refresh();
      }
    } finally {
      setPending(false);
    }
  }

  return (
    <button
      type="button"
      onClick={handleLogout}
      disabled={pending}
      className={cn(
        "min-h-[44px] rounded-xl border border-border bg-card px-4 py-2.5 text-sm font-medium text-foreground transition hover:bg-secondary disabled:opacity-60",
        className,
      )}
    >
      {pending ? t("nav.signingOut") : t("nav.logout")}
    </button>
  );
}
