/**
 * A coarse "browser · OS" label for the API token a sign-in creates, so the
 * account's device list can tell sessions apart („Chrome · macOS“). Only the
 * label is sent to the API — never the raw user agent, which is more
 * identifying than the member needs to recognise their own devices.
 *
 * Order matters: Edge and Opera include "Chrome", and Chrome includes
 * "Safari", so the more specific tokens are tested first.
 */

const BROWSERS: [RegExp, string][] = [
  [/\bEdg(?:e|A|iOS)?\//, "Edge"],
  [/\b(?:OPR|Opera)\//, "Opera"],
  [/\bSamsungBrowser\//, "Samsung Internet"],
  [/\b(?:Firefox|FxiOS)\//, "Firefox"],
  [/\b(?:Chrome|CriOS|Chromium)\//, "Chrome"],
  [/\bVersion\/[\d.]+.*\bSafari\//, "Safari"],
];

const SYSTEMS: [RegExp, string][] = [
  [/\b(?:iPhone|iPad|iPod)\b/, "iOS"],
  [/\bAndroid\b/, "Android"],
  [/\bWindows\b/, "Windows"],
  [/\bCrOS\b/, "ChromeOS"],
  [/\bMac OS X\b|\bMacintosh\b/, "macOS"],
  [/\bLinux\b/, "Linux"],
];

/** Stored when nothing is recognised; the device list shows its own wording. */
export const UNKNOWN_DEVICE_LABEL = "web";

function match(table: [RegExp, string][], userAgent: string): string | null {
  for (const [pattern, name] of table) {
    if (pattern.test(userAgent)) {
      return name;
    }
  }

  return null;
}

export function deviceLabel(userAgent: string | null | undefined): string {
  const ua = (userAgent ?? "").slice(0, 512);
  const browser = match(BROWSERS, ua);
  const system = match(SYSTEMS, ua);

  if (browser && system) return `${browser} · ${system}`;

  return browser ?? system ?? UNKNOWN_DEVICE_LABEL;
}
