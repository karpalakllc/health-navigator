import { NextResponse } from "next/server";
import { apiUrl } from "@/lib/config";

type ResetPayload = {
  email?: string;
  token?: string;
  password?: string;
  password_confirmation?: string;
};

export async function POST(request: Request) {
  const body = (await request.json()) as ResetPayload;

  const response = await fetch(apiUrl("/auth/reset-password"), {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body: JSON.stringify({
      email: body.email,
      token: body.token,
      password: body.password,
      password_confirmation: body.password_confirmation,
    }),
  });

  const payload = await response.json();

  return NextResponse.json(payload, { status: response.status });
}
