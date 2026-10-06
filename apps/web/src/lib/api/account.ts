import { apiGetServer } from "@/lib/api/server";

/** One signed-in device: a Sanctum token named at login („Chrome · macOS“). */
export type AccountDevice = {
  id: number;
  name: string;
  created_at: string | null;
  last_used_at: string | null;
  expires_at: string | null;
  /** The device making this request. */
  is_current: boolean;
};

export async function fetchMyDevices(): Promise<AccountDevice[]> {
  const data = await apiGetServer<{ tokens: AccountDevice[] }>("/me/tokens");
  return data.tokens;
}
