import { redirect } from "next/navigation";
import { getShellSession } from "@/lib/auth/header-session";

/**
 * Pages for signed-out visitors only (/register, /forgot-password): someone
 * already signed in goes to their account instead of a form that makes no
 * sense for them. Keyed off a confirmed /me (the shell's memoised call, so it
 * costs no extra request); a dead or unreadable session leaves the page as is.
 */
export async function redirectSignedInToAccount(): Promise<void> {
  const session = await getShellSession();

  if (session.user) {
    redirect("/account");
  }
}
