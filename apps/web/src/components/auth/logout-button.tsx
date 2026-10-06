"use client";

import { usePathname, useRouter } from "next/navigation";
import { useState } from "react";
import { buttonClassName } from "@/components/ui/button";
import { Icon } from "@/components/ui/icons";
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
  withIcon = false,
}: {
  className?: string;
  onLoggedOut?: () => void;
  /** Leading log-out icon (the account page's sign-out row). */
  withIcon?: boolean;
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
      className={buttonClassName({ variant: "secondary", className })}
    >
      {withIcon ? <Icon name="log-out" size={20} /> : null}
      <span>{pending ? t("nav.signingOut") : t("nav.logout")}</span>
    </button>
  );
}
