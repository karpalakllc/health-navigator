import { readFileSync } from "node:fs";
import { expect } from "@playwright/test";
import { API_URL, MAIL_LOG, WEB_URL } from "./env";

/**
 * Reads links back out of the API's mail log (MAIL_MAILER=log writing to the
 * `e2e-mail` channel, storage/logs/e2e-mail.log). Each message is one log entry
 * starting with "[date] local.DEBUG: " followed by the raw MIME message, whose
 * text part Laravel's log transport has already decoded.
 */
const ENTRY_START = /^\[\d{4}-\d{2}-\d{2} [\d:]+\] \w+\.\w+: /m;

export type MailLink = "verify" | "reset";

const LINK_PATTERNS: Record<MailLink, RegExp> = {
  // Signed API route; the API redirects into the web app after verifying.
  verify: new RegExp(
    `${escape(API_URL)}/api/v1/auth/email/verify/[^\\s\\])"<]+`,
    "g",
  ),
  reset: new RegExp(`${escape(WEB_URL)}/reset-password\\?[^\\s\\])"<]+`, "g"),
};

function escape(value: string): string {
  return value.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
}

/** Every message addressed to `email`, oldest first. */
export function messagesTo(email: string): string[] {
  let log = "";

  try {
    log = readFileSync(MAIL_LOG, "utf8");
  } catch {
    return [];
  }

  const wanted = email.toLowerCase();

  return log
    .split(ENTRY_START)
    .filter((entry) => recipients(entry).includes(wanted));
}

/** Addresses on the To: header, e.g. `Name <a@b.test>, c@d.test`. */
function recipients(entry: string): string[] {
  const headers = entry.split(/\r?\n\r?\n/)[0] ?? "";
  const to = headers.match(/^To: (.*)$/im)?.[1] ?? "";

  return to
    .split(",")
    .map((part) => (part.match(/<([^>]+)>/)?.[1] ?? part).trim().toLowerCase());
}

/** Links of one kind in every message to `email`, oldest first. */
export function linksTo(email: string, kind: MailLink): string[] {
  return messagesTo(email).flatMap((message) => {
    // The HTML part carries the same link with `&` escaped as `&amp;`; once
    // unescaped, every copy (text part, button, fallback) is one link.
    const found = (message.match(LINK_PATTERNS[kind]) ?? []).map((link) =>
      link.replaceAll("&amp;", "&"),
    );
    return [...new Set(found)];
  });
}

/**
 * Waits for the newest `kind` link addressed to `email`. With
 * QUEUE_CONNECTION=sync the mail is written before the request that sent it
 * returns, so this normally resolves on the first read; the poll only absorbs
 * file-system latency.
 *
 * `after` is the number of such links already seen, so a spec can wait for a
 * *new* link rather than picking up an older one.
 */
export async function latestLink(
  email: string,
  kind: MailLink,
  { after = 0 }: { after?: number } = {},
): Promise<string> {
  let links: string[] = [];

  await expect
    .poll(
      () => {
        links = linksTo(email, kind);
        return links.length;
      },
      {
        message: `a ${kind} link mailed to ${email}`,
        timeout: 10_000,
      },
    )
    .toBeGreaterThan(after);

  return links[links.length - 1];
}
