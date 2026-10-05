import { NextResponse, type NextRequest } from "next/server";
import {
  encodeResetCookie,
  RESET_COOKIE,
  RESET_FORM_PATH,
  resetCookieOptions,
  resetCredentialsFromQuery,
} from "@/lib/auth/reset-token";

/**
 * Landing point of the emailed reset link (/reset-password?token=…&email=…).
 *
 * This used to be the form page itself, which meant analytics and error
 * reporting saw the live token in `location.href`. Now nothing renders here: the
 * values go into an httpOnly cookie and the browser is sent on to the form at a
 * clean URL. A redirect also keeps the token out of the tab's history.
 */
export function GET(request: NextRequest) {
  const credentials = resetCredentialsFromQuery(request.nextUrl.searchParams);

  // Relative Location: correct behind any proxy, whatever Host it forwards.
  const res = new NextResponse(null, {
    status: 303,
    headers: {
      Location: RESET_FORM_PATH,
      "Cache-Control": "no-store",
      "Referrer-Policy": "no-referrer",
    },
  });

  // Without both values there is nothing to carry; the form page 404s on its own
  // unless an earlier, still-valid link already left a cookie.
  if (credentials) {
    res.cookies.set(
      RESET_COOKIE,
      encodeResetCookie(credentials),
      resetCookieOptions(),
    );
  }

  return res;
}
