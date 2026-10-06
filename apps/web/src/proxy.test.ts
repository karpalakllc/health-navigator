import { afterEach, describe, expect, it, vi } from "vitest";
import { NextRequest } from "next/server";
import { proxy } from "./proxy";

function imgSrc(): string[] {
  const csp =
    proxy(new NextRequest("https://www.example.com/")).headers.get(
      "Content-Security-Policy",
    ) ?? "";
  const directive = csp.split("; ").find((part) => part.startsWith("img-src "));

  return directive?.split(" ").slice(1) ?? [];
}

describe("proxy CSP img-src", () => {
  afterEach(() => {
    vi.unstubAllEnvs();
  });

  it("allows the object-storage media host in production", () => {
    vi.stubEnv("NODE_ENV", "production");
    vi.stubEnv("NEXT_PUBLIC_API_URL", "https://api.example.com");
    vi.stubEnv("NEXT_PUBLIC_MEDIA_URL", "https://cdn.example.com/media");

    expect(imgSrc()).toEqual([
      "'self'",
      "data:",
      "https://api.dicebear.com",
      "https://api.example.com",
      "https://cdn.example.com",
    ]);
  });

  it("does not allow an http media host in production", () => {
    vi.stubEnv("NODE_ENV", "production");
    vi.stubEnv("NEXT_PUBLIC_API_URL", "https://api.example.com");
    vi.stubEnv("NEXT_PUBLIC_MEDIA_URL", "http://cdn.example.com");

    expect(imgSrc()).not.toContain("http://cdn.example.com");
  });

  it("allows a local MinIO bucket in development", () => {
    vi.stubEnv("NODE_ENV", "development");
    vi.stubEnv("NEXT_PUBLIC_API_URL", "http://127.0.0.1:8000");
    vi.stubEnv("NEXT_PUBLIC_MEDIA_URL", "http://127.0.0.1:9000/zdravje-media");

    expect(imgSrc()).toContain("http://127.0.0.1:9000");
  });

  it("falls back to the API origin when media is on the API host", () => {
    vi.stubEnv("NODE_ENV", "production");
    vi.stubEnv("NEXT_PUBLIC_API_URL", "https://api.example.com");
    vi.stubEnv("NEXT_PUBLIC_MEDIA_URL", "");

    expect(imgSrc()).toEqual([
      "'self'",
      "data:",
      "https://api.dicebear.com",
      "https://api.example.com",
    ]);
  });
});

describe("proxy request ID", () => {
  const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/;

  function run(headers: Record<string, string> = {}) {
    const response = proxy(
      new NextRequest("https://www.example.com/", { headers }),
    );

    return {
      returned: response.headers.get("X-Request-Id"),
      // NextResponse.next({ request: { headers } }) relays overridden request
      // headers to the render under this prefix.
      forwarded: response.headers.get("x-middleware-request-x-request-id"),
    };
  }

  it("mints one ID, hands it to the render and returns it", () => {
    const { returned, forwarded } = run();

    expect(returned).toMatch(UUID);
    expect(forwarded).toBe(returned);
  });

  it("keeps a well-formed ID an edge already assigned", () => {
    expect(run({ "x-request-id": "edge-12345678" })).toEqual({
      returned: "edge-12345678",
      forwarded: "edge-12345678",
    });
  });

  it("replaces a malformed one", () => {
    expect(run({ "x-request-id": "not ok" }).returned).toMatch(UUID);
  });
});
