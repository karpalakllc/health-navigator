import { NextResponse } from "next/server";
import { guardJson } from "@/lib/auth/request-guard";
import { relayToApi } from "@/lib/api/notifications-relay";
import { PREFERENCE_TYPES } from "@/lib/api/notifications";
import { t } from "@/i18n/t";

type PreferencesPayload = { email_enabled?: unknown; types?: unknown };

/** Saves the member's e-mail switches; only known keys and booleans pass. */
export async function PUT(request: Request) {
  const guarded = await guardJson<PreferencesPayload>(request);

  if (!guarded.ok) {
    return guarded.response;
  }

  const body: { email_enabled?: boolean; types?: Record<string, boolean> } = {};
  const { email_enabled, types } = guarded.value;

  if (typeof email_enabled === "boolean") {
    body.email_enabled = email_enabled;
  }

  if (types && typeof types === "object" && !Array.isArray(types)) {
    const picked: Record<string, boolean> = {};

    for (const key of PREFERENCE_TYPES) {
      const value = (types as Record<string, unknown>)[key];

      if (typeof value === "boolean") {
        picked[key] = value;
      }
    }

    body.types = picked;
  }

  if (body.email_enabled === undefined && body.types === undefined) {
    return NextResponse.json(
      { message: t("notifications.saveError") },
      { status: 422 },
    );
  }

  return relayToApi(request, "/me/notification-preferences", {
    method: "PUT",
    body,
    auth: "required",
  });
}
