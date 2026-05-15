"use client";

import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { useState } from "react";
import { filterInputClassName } from "@/components/directory/filter-form";
import { Button } from "@/components/ui/button";
import { t } from "@/i18n/t";

export function LoginForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [pending, setPending] = useState(false);

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault();
    setError(null);
    setPending(true);

    try {
      const response = await fetch("/api/session/login", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email, password }),
      });

      const payload = await response.json();

      if (!response.ok) {
        setError(
          payload.message ?? payload.errors?.email?.[0] ?? t("auth.loginFailed"),
        );
        return;
      }

      const redirect = searchParams.get("redirect") ?? "/account";
      router.push(redirect);
      router.refresh();
    } catch {
      setError(t("auth.loginFailed"));
    } finally {
      setPending(false);
    }
  }

  return (
    <div className="rounded-2xl border border-border bg-card p-5 sm:p-6">
      <form onSubmit={handleSubmit} className="grid gap-4">
        <label className="grid gap-1.5 text-sm">
          <span className="font-medium text-foreground">{t("auth.email")}</span>
          <input
            type="email"
            name="email"
            autoComplete="email"
            required
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            className={filterInputClassName}
          />
        </label>
        <label className="grid gap-1.5 text-sm">
          <span className="font-medium text-foreground">{t("auth.password")}</span>
          <input
            type="password"
            name="password"
            autoComplete="current-password"
            required
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            className={filterInputClassName}
          />
        </label>
        {error ? <p className="text-sm text-destructive">{error}</p> : null}
        <Button type="submit" disabled={pending} className="min-h-[44px] w-full sm:w-auto">
          {pending ? t("auth.signingIn") : t("auth.signIn")}
        </Button>
        <p className="text-xs text-muted-foreground">{t("auth.inviteOnly")}</p>
        <p className="text-xs text-muted-foreground">
          {t("auth.termsNotice")}{" "}
          <Link href="/terms" className="font-medium text-primary underline-offset-2 hover:underline">
            {t("auth.termsLink")}
          </Link>{" "}
          {t("auth.and")}{" "}
          <Link
            href="/disclaimer"
            className="font-medium text-primary underline-offset-2 hover:underline"
          >
            {t("auth.disclaimerLink")}
          </Link>
          .
        </p>
      </form>
    </div>
  );
}
