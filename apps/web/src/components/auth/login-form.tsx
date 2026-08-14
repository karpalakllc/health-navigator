"use client";

import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { useState } from "react";
import { AuthFormCard } from "@/components/auth/auth-form-card";
import { PasswordInput } from "@/components/auth/password-input";
import { ResendVerificationForm } from "@/components/auth/resend-verification-form";
import { filterInputClassName } from "@/components/directory/filter-form";
import { Button } from "@/components/ui/button";
import { safeRedirectTarget } from "@/lib/auth/login-href";
import { t } from "@/i18n/t";

export function LoginForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [unverified, setUnverified] = useState(false);
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
        // The account exists and the password was right — it just is not
        // activated yet, so offer the resend rather than a dead end.
        if (payload.code === "auth.email_unverified") {
          setUnverified(true);
          setError(null);
          return;
        }

        setError(
          payload.message ??
            payload.errors?.email?.[0] ??
            t("auth.loginFailed"),
        );
        return;
      }

      const redirect = safeRedirectTarget(searchParams.get("redirect"), "/");
      router.push(redirect);
      router.refresh();
    } catch {
      setError(t("auth.loginFailed"));
    } finally {
      setPending(false);
    }
  }

  if (unverified) {
    return (
      <AuthFormCard>
        <div className="grid gap-3">
          <h2 className="text-lg font-semibold text-foreground">
            {t("auth.verifyCheckInbox")}
          </h2>
          <p className="text-sm text-muted-foreground">
            {t("auth.verifyUnverified")}
          </p>
          <ResendVerificationForm defaultEmail={email} />
        </div>
      </AuthFormCard>
    );
  }

  return (
    <AuthFormCard>
      <form onSubmit={handleSubmit} className="grid gap-4">
        <label className="grid gap-1.5 text-sm">
          <span className="font-semibold text-foreground">
            {t("auth.email")}
          </span>
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
          <span className="flex items-center justify-between gap-2 font-semibold text-foreground">
            <span>{t("auth.password")}</span>
            <Link
              href="/forgot-password"
              className="text-xs font-semibold text-primary underline-offset-4 hover:underline"
            >
              {t("auth.forgotPassword")}
            </Link>
          </span>
          <PasswordInput
            id="login-password"
            name="password"
            autoComplete="current-password"
            required
            value={password}
            onChange={setPassword}
          />
        </label>
        {error ? <p className="text-sm text-destructive">{error}</p> : null}
        <Button
          type="submit"
          disabled={pending}
          className="min-h-[44px] w-full sm:w-auto"
        >
          {pending ? t("auth.signingIn") : t("auth.signIn")}
        </Button>
        <p className="text-sm text-muted-foreground">
          {t("auth.noAccount")}{" "}
          <Link
            href="/register"
            className="font-semibold text-primary underline-offset-4 hover:underline"
          >
            {t("auth.register")}
          </Link>
        </p>
        <p className="text-xs text-muted-foreground">
          {t("auth.termsNotice")}{" "}
          <Link
            href="/terms"
            className="font-medium text-primary underline-offset-2 hover:underline"
          >
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
    </AuthFormCard>
  );
}
