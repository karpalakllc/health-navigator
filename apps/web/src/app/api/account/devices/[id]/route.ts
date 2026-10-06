import { NextResponse } from "next/server";
import { t } from "@/i18n/t";
import { revokeTokens } from "../revoke";

/** Signs out one device. Another member's id is a 404 from the API. */
export async function DELETE(
  request: Request,
  { params }: { params: Promise<{ id: string }> },
) {
  const { id } = await params;

  if (!/^\d{1,18}$/.test(id)) {
    return NextResponse.json(
      { message: t("account.devices.notFound") },
      { status: 404 },
    );
  }

  return revokeTokens(request, `/me/tokens/${id}`);
}
