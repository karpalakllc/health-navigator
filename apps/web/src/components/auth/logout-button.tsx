"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { cn } from "@/lib/cn";
import { t } from "@/i18n/t";

export function LogoutButton({
  className,
  onLoggedOut,
}: {
  className?: string;
  onLoggedOut?: () => void;
}) {
  const router = useRouter();
  const [pending, setPending] = useState(false);

  async function handleLogout() {
    setPending(true);
    try {
      await fetch("/api/session/logout", { method: "POST" });
      onLoggedOut?.();
      router.push("/");
      router.refresh();
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
