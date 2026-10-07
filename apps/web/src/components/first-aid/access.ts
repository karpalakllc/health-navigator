import type { AuthUser } from "@/lib/api/me";
import { getShellSession } from "@/lib/auth/header-session";

/**
 * Staff (the API's „admin“ and „moderator“ account roles) may preview draft
 * first-aid guides; everyone else sees only published, clinician-reviewed
 * ones. A display gate only — the drafts are not secret, just not yet safe to
 * rely on.
 */
export function canPreviewFirstAid(
  user: Pick<AuthUser, "role"> | null,
): boolean {
  return user?.role === "admin" || user?.role === "moderator";
}

/** The current visitor may preview drafts (shares the shell's /me call). */
export async function viewerCanPreviewFirstAid(): Promise<boolean> {
  const { user } = await getShellSession();

  return canPreviewFirstAid(user);
}
