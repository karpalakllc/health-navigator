import { cookies } from "next/headers";

/** httpOnly cookie set by `/api/session/login` (see docs/architecture.md). */
export const SESSION_COOKIE = "zdravje_api_token";

export async function getSessionToken(): Promise<string | null> {
  const store = await cookies();
  return store.get(SESSION_COOKIE)?.value ?? null;
}

export function sessionCookieOptions(maxAgeSeconds: number) {
  return {
    httpOnly: true,
    secure: process.env.NODE_ENV === "production",
    sameSite: "lax" as const,
    path: "/",
    maxAge: maxAgeSeconds,
  };
}
