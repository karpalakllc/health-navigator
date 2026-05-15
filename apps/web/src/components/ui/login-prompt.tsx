import Link from "next/link";
import { t } from "@/i18n/t";

type LoginPromptProps = {
  suffix: string;
};

/** Server-safe “log in to …” line with link to /login. */
export function LoginPrompt({ suffix }: LoginPromptProps) {
  return (
    <p className="rounded-xl border border-border bg-muted/30 px-4 py-3 text-sm text-muted-foreground">
      <Link href="/login" className="font-semibold text-primary underline-offset-2 hover:underline">
        {t("nav.login")}
      </Link>{" "}
      {suffix}
    </p>
  );
}
