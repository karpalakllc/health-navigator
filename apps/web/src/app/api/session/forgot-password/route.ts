import { NextResponse } from "next/server";
import { apiUrl } from "@/lib/config";

export async function POST(request: Request) {
  const body = (await request.json()) as { email?: string };

  const response = await fetch(apiUrl("/auth/forgot-password"), {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body: JSON.stringify({ email: body.email }),
  });

  const payload = await response.json();

  return NextResponse.json(payload, { status: response.status });
}
