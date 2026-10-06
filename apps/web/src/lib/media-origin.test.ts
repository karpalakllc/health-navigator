import { describe, expect, it } from "vitest";
import {
  mediaImgSrc,
  mediaRemotePatterns,
  resolveMediaUrl,
} from "./media-origin";

describe("resolveMediaUrl", () => {
  it("treats an unset or blank value as media on the API host", () => {
    expect(resolveMediaUrl(undefined, true)).toEqual({ url: null });
    expect(resolveMediaUrl("  ", true)).toEqual({ url: null });
  });

  it("rejects an http media host in production", () => {
    const result = resolveMediaUrl("http://media.example.com", true);

    expect(result.url).toBeNull();
    expect(result.error).toMatch(/https/);
  });

  it("allows an http media host (local MinIO) outside production", () => {
    expect(
      resolveMediaUrl("http://127.0.0.1:9000/zdravje-media", false).url?.href,
    ).toBe("http://127.0.0.1:9000/zdravje-media");
  });

  it("rejects values that are not http(s) URLs", () => {
    expect(resolveMediaUrl("media.example.com", false).error).toBeDefined();
    expect(resolveMediaUrl("ftp://media.example.com", false).error).toBe(
      "NEXT_PUBLIC_MEDIA_URL must be an http(s) URL.",
    );
  });
});

describe("mediaImgSrc", () => {
  it("is the origin of the media base, without its path", () => {
    expect(mediaImgSrc("https://cdn.example.com/media/", true)).toBe(
      "https://cdn.example.com",
    );
  });

  it("is empty when there is no usable media host", () => {
    expect(mediaImgSrc(undefined, true)).toBe("");
    expect(mediaImgSrc("http://cdn.example.com", true)).toBe("");
  });
});

describe("mediaRemotePatterns", () => {
  it("allows only the media base path on the media host", () => {
    expect(mediaRemotePatterns("https://cdn.example.com/media/", true)).toEqual(
      [
        {
          protocol: "https",
          hostname: "cdn.example.com",
          pathname: "/media/**",
        },
      ],
    );
  });

  it("allows the whole host when the base has no path", () => {
    expect(mediaRemotePatterns("https://media.example.com", true)).toEqual([
      { protocol: "https", hostname: "media.example.com", pathname: "/**" },
    ]);
  });

  it("keeps a non-default port for a local MinIO bucket", () => {
    expect(
      mediaRemotePatterns("http://localhost:9000/zdravje-media", false),
    ).toEqual([
      {
        protocol: "http",
        hostname: "localhost",
        port: "9000",
        pathname: "/zdravje-media/**",
      },
    ]);
  });

  it("adds nothing for http in production or when unset", () => {
    expect(mediaRemotePatterns("http://cdn.example.com", true)).toEqual([]);
    expect(mediaRemotePatterns(undefined, false)).toEqual([]);
  });
});
