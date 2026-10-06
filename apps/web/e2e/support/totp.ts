import { createHmac } from "node:crypto";

/**
 * RFC 6238 TOTP (HMAC-SHA1, 30-second step, 6 digits) — what Filament's app
 * authentication expects. Lets the admin spec pass a staff 2FA challenge with
 * the secret E2ESeeder stores, without an authenticator package.
 */
export function totp(base32Secret: string, now = Date.now()): string {
  const key = base32Decode(base32Secret);
  const counter = Math.floor(now / 1000 / 30);
  const message = Buffer.alloc(8);
  message.writeBigUInt64BE(BigInt(counter));

  const hmac = createHmac("sha1", key).update(message).digest();
  const offset = hmac[hmac.length - 1] & 0x0f;
  const code = (hmac.readUInt32BE(offset) & 0x7fffffff) % 1_000_000;

  return code.toString().padStart(6, "0");
}

/** Seconds left in the current 30-second window. */
export function totpSecondsRemaining(now = Date.now()): number {
  return 30 - (Math.floor(now / 1000) % 30);
}

function base32Decode(input: string): Buffer {
  const alphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567";
  const clean = input.toUpperCase().replace(/=+$/, "").replace(/\s+/g, "");
  let bits = 0;
  let value = 0;
  const bytes: number[] = [];

  for (const char of clean) {
    const index = alphabet.indexOf(char);
    if (index === -1) {
      throw new Error(`Invalid base32 character: ${char}`);
    }
    value = (value << 5) | index;
    bits += 5;
    if (bits >= 8) {
      bytes.push((value >>> (bits - 8)) & 0xff);
      bits -= 8;
    }
  }

  return Buffer.from(bytes);
}
