import { mk, type MkMessages } from "@/i18n/mk";

type NestedKeyOf<T, Prefix extends string = ""> = T extends object
  ? {
      [K in keyof T & string]: T[K] extends object
        ? NestedKeyOf<T[K], `${Prefix}${K}.`>
        : `${Prefix}${K}`;
    }[keyof T & string]
  : never;

export type MessageKey = NestedKeyOf<MkMessages>;

function resolvePath(
  obj: Record<string, unknown>,
  path: string,
): string | undefined {
  const parts = path.split(".");
  let current: unknown = obj;

  for (const part of parts) {
    if (current === null || typeof current !== "object" || !(part in current)) {
      return undefined;
    }
    current = (current as Record<string, unknown>)[part];
  }

  return typeof current === "string" ? current : undefined;
}

/** Macedonian UI string (R1 — single locale, no next-intl). */
export function t(key: MessageKey): string {
  const value = resolvePath(mk as unknown as Record<string, unknown>, key);

  if (value === undefined) {
    return key;
  }

  return value;
}

/** Replace `{name}` placeholders in a translated string. */
export function tFormat(
  key: MessageKey,
  vars: Record<string, string | number>,
): string {
  let value = t(key);

  for (const [name, replacement] of Object.entries(vars)) {
    value = value.replaceAll(`{${name}}`, String(replacement));
  }

  return value;
}
