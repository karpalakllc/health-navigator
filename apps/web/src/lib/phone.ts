/** A dialable tel: URL for a phone number as shown („02 312 4567“). */
export function telHref(phone: string | null | undefined): string | null {
  const digits = phone?.replace(/[^\d+]/g, "") ?? "";

  return digits.length >= 3 ? `tel:${digits}` : null;
}
