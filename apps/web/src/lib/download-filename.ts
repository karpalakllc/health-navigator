const FALLBACK = "zdravje360.json";

/**
 * File name from a Content-Disposition header. Handles the RFC 6266 forms a
 * server may send: filename*=UTF-8''..., filename="...", and the bare token
 * filename=... that Laravel emits for plain ASCII names.
 */
export function filenameFromDisposition(
  disposition: string | null | undefined,
  fallback: string = FALLBACK,
): string {
  if (!disposition) {
    return fallback;
  }

  const extended = /filename\*\s*=\s*[^']*'[^']*'([^;]+)/i.exec(disposition);

  if (extended?.[1]) {
    try {
      const decoded = decodeURIComponent(extended[1].trim());
      if (decoded !== "") {
        return decoded;
      }
    } catch {
      // fall through to the plain parameter
    }
  }

  const plain = /filename\s*=\s*(?:"([^"]+)"|([^;\s]+))/i.exec(disposition);
  const name = plain?.[1] ?? plain?.[2];

  return name ? name : fallback;
}
