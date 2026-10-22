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
  return safeBasename(parseDisposition(disposition)) ?? fallback;
}

/**
 * The header is server-controlled, but a decoded name may still carry path
 * separators or control characters (filename*=UTF-8''..%2F..%2Fx): keep only
 * the last path segment and drop control characters.
 */
function safeBasename(name: string | null): string | null {
  if (name === null) {
    return null;
  }

  const cleaned = name.replace(/[\u0000-\u001f\u007f]/g, "");
  const base = cleaned.split(/[\\/]/).pop()?.trim() ?? "";

  return base === "" || base === "." || base === ".." ? null : base;
}

function parseDisposition(disposition: string | null | undefined) {
  if (!disposition) {
    return null;
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

  return name ? name : null;
}
