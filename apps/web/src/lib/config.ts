const API_BASE_URL_ENV = "NEXT_PUBLIC_API_URL";

export function getApiBaseUrl(): string {
  const url = process.env[API_BASE_URL_ENV];

  if (!url) {
    throw new Error(`${API_BASE_URL_ENV} is not set`);
  }

  return url.replace(/\/$/, "");
}

/** Build a full URL for a versioned API path, e.g. `/health` or `/v1/health`. */
export function apiUrl(path: string): string {
  const normalized = path.startsWith("/") ? path : `/${path}`;
  const versioned = normalized.startsWith("/v1/")
    ? normalized
    : `/v1${normalized}`;
  return `${getApiBaseUrl()}/api${versioned}`;
}
