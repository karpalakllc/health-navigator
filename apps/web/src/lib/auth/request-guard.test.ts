import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import {
  avatarFromForm,
  guardJson,
  guardMultipart,
  MAX_AVATAR_BYTES,
  MAX_JSON_BODY_BYTES,
  rejectCrossSite,
} from "@/lib/auth/request-guard";

/**
 * Login CSRF: a hidden form on another site could POST `text/plain` credentials
 * to /api/session/login and sign the victim into the attacker's account, because
 * the handlers checked neither Origin nor Content-Type. These are the requests a
 * cross-site page can actually send without a CORS preflight.
 */
const SITE = "https://zdravje.test";

function post(
  headers: Record<string, string>,
  body?: BodyInit,
  url = `${SITE}/api/session/login`,
): Request {
  return new Request(url, { method: "POST", headers, body });
}

function jsonPost(body: string, extra: Record<string, string> = {}): Request {
  return post(
    { origin: SITE, "content-type": "application/json", ...extra },
    body,
  );
}

beforeEach(() => {
  vi.stubEnv("NEXT_PUBLIC_SITE_URL", SITE);
  vi.stubEnv("NODE_ENV", "production");
});

afterEach(() => {
  vi.unstubAllEnvs();
});

describe("rejectCrossSite", () => {
  it("lets a request from our own origin through", () => {
    expect(rejectCrossSite(post({ origin: SITE }))).toBeNull();
  });

  it("refuses another origin", () => {
    expect(
      rejectCrossSite(post({ origin: "https://evil.example" }))?.status,
    ).toBe(403);
  });

  it("refuses a sibling subdomain and a scheme downgrade", () => {
    expect(
      rejectCrossSite(post({ origin: "https://evil.zdravje.test" }))?.status,
    ).toBe(403);
    expect(
      rejectCrossSite(post({ origin: "http://zdravje.test" }))?.status,
    ).toBe(403);
  });

  it("refuses the opaque `null` origin", () => {
    expect(rejectCrossSite(post({ origin: "null" }))?.status).toBe(403);
  });

  it("falls back to Fetch Metadata when Origin is missing", () => {
    expect(
      rejectCrossSite(post({ "sec-fetch-site": "same-origin" })),
    ).toBeNull();
    expect(
      rejectCrossSite(post({ "sec-fetch-site": "cross-site" }))?.status,
    ).toBe(403);
    expect(
      rejectCrossSite(post({ "sec-fetch-site": "same-site" }))?.status,
    ).toBe(403);
  });

  it("accepts the runtime ALLOWED_ORIGINS list, read per request", () => {
    const www = "https://www.zdravje.test";
    const staging = "https://staging.zdravje.test";

    expect(rejectCrossSite(post({ origin: www }))?.status).toBe(403);

    vi.stubEnv("ALLOWED_ORIGINS", ` ${www}/ , ${staging},not a url,`);

    expect(rejectCrossSite(post({ origin: www }))).toBeNull();
    expect(rejectCrossSite(post({ origin: staging }))).toBeNull();
    expect(rejectCrossSite(post({ origin: SITE }))).toBeNull();
    expect(
      rejectCrossSite(post({ origin: "https://evil.example" }))?.status,
    ).toBe(403);
  });

  it("accepts Vercel's deployment and branch hosts over https", () => {
    vi.stubEnv("VERCEL_URL", "zdravje-abc123.vercel.app");
    vi.stubEnv("VERCEL_BRANCH_URL", "zdravje-git-feature.vercel.app");

    expect(
      rejectCrossSite(post({ origin: "https://zdravje-abc123.vercel.app" })),
    ).toBeNull();
    expect(
      rejectCrossSite(
        post({ origin: "https://zdravje-git-feature.vercel.app" }),
      ),
    ).toBeNull();
    expect(
      rejectCrossSite(post({ origin: "http://zdravje-abc123.vercel.app" }))
        ?.status,
    ).toBe(403);
  });

  it("refuses a request with no provenance at all", () => {
    expect(rejectCrossSite(post({}))?.status).toBe(403);
  });

  it("in production, does not trust the Host the request was addressed to", () => {
    const request = post(
      { origin: "https://evil.example" },
      undefined,
      "https://evil.example/api/session/login",
    );
    expect(rejectCrossSite(request)?.status).toBe(403);
  });

  it("outside production, also accepts the origin being served", () => {
    vi.stubEnv("NODE_ENV", "development");
    const request = post(
      { origin: "http://localhost:3000" },
      undefined,
      "http://localhost:3000/api/session/login",
    );
    expect(rejectCrossSite(request)).toBeNull();
  });
});

describe("guardJson", () => {
  it("returns the parsed object for a same-origin JSON request", async () => {
    const result = await guardJson<{ email: string }>(
      jsonPost(JSON.stringify({ email: "a@b.mk" })),
    );
    expect(result).toEqual({ ok: true, value: { email: "a@b.mk" } });
  });

  it("accepts a charset parameter on the content type", async () => {
    const result = await guardJson(
      jsonPost("{}", { "content-type": "application/json; charset=utf-8" }),
    );
    expect(result.ok).toBe(true);
  });

  it("refuses the text/plain body a cross-site form can send", async () => {
    const result = await guardJson(
      post(
        { origin: SITE, "content-type": "text/plain" },
        JSON.stringify({ email: "attacker@evil.example", password: "x" }),
      ),
    );
    expect(result.ok ? 200 : result.response.status).toBe(415);
  });

  it("refuses urlencoded and multipart bodies", async () => {
    for (const type of [
      "application/x-www-form-urlencoded",
      "multipart/form-data; boundary=x",
    ]) {
      const result = await guardJson(
        post({ origin: SITE, "content-type": type }, "email=a"),
      );
      expect(result.ok ? 200 : result.response.status).toBe(415);
    }
  });

  it("refuses a cross-site JSON request before reading it", async () => {
    const result = await guardJson(
      post(
        { origin: "https://evil.example", "content-type": "application/json" },
        "{}",
      ),
    );
    expect(result.ok ? 200 : result.response.status).toBe(403);
  });

  it("answers malformed JSON with 400, not a thrown 500", async () => {
    for (const body of ["{", "", "not json", "￿"]) {
      const result = await guardJson(jsonPost(body));
      expect(result.ok ? 200 : result.response.status).toBe(400);
    }
  });

  it("refuses JSON that is not an object", async () => {
    for (const body of ["null", "[]", "42", '"x"']) {
      const result = await guardJson(jsonPost(body));
      expect(result.ok ? 200 : result.response.status).toBe(400);
    }
  });

  it("refuses an oversized body by declared length", async () => {
    const result = await guardJson(
      jsonPost("{}", { "content-length": String(MAX_JSON_BODY_BYTES + 1) }),
    );
    expect(result.ok ? 200 : result.response.status).toBe(413);
  });

  it("refuses an oversized body that does not declare its length", async () => {
    const big = JSON.stringify({ body: "x".repeat(MAX_JSON_BODY_BYTES) });
    const stream = new ReadableStream<Uint8Array>({
      start(controller) {
        controller.enqueue(new TextEncoder().encode(big));
        controller.close();
      },
    });
    const request = new Request(`${SITE}/api/forum/posts`, {
      method: "POST",
      headers: { origin: SITE, "content-type": "application/json" },
      body: stream,
      // @ts-expect-error -- required by undici for a streamed body; not in lib.dom yet.
      duplex: "half",
    });

    const result = await guardJson(request);
    expect(result.ok ? 200 : result.response.status).toBe(413);
  });
});

function pngFile(size = 64): File {
  const bytes = new Uint8Array(size);
  bytes.set([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]);
  return new File([bytes], "me.png", { type: "image/png" });
}

describe("guardMultipart", () => {
  it("parses a same-origin multipart upload", async () => {
    const form = new FormData();
    form.append("avatar", pngFile());
    const encoded = new Response(form);
    const request = post(
      {
        origin: SITE,
        "content-type": encoded.headers.get("content-type") ?? "",
      },
      await encoded.arrayBuffer(),
      `${SITE}/api/session/avatar`,
    );

    const result = await guardMultipart(request);
    expect(result.ok).toBe(true);
    expect(result.ok && result.value.get("avatar")).toBeInstanceOf(File);
  });

  it("refuses non-multipart and cross-site uploads", async () => {
    const plain = await guardMultipart(
      post({ origin: SITE, "content-type": "text/plain" }, "x"),
    );
    expect(plain.ok ? 200 : plain.response.status).toBe(415);

    const crossSite = await guardMultipart(
      post(
        {
          origin: "https://evil.example",
          "content-type": "multipart/form-data",
        },
        "x",
      ),
    );
    expect(crossSite.ok ? 200 : crossSite.response.status).toBe(403);
  });

  it("refuses an upload far beyond the avatar cap", async () => {
    const result = await guardMultipart(
      post(
        {
          origin: SITE,
          "content-type": "multipart/form-data; boundary=x",
          "content-length": String(MAX_AVATAR_BYTES * 2),
        },
        "x",
      ),
    );
    expect(result.ok ? 200 : result.response.status).toBe(413);
  });
});

describe("avatarFromForm", () => {
  it("accepts a real image within the cap", async () => {
    const form = new FormData();
    form.append("avatar", pngFile());
    expect(await avatarFromForm(form)).toBeInstanceOf(File);
  });

  it("rejects a missing part, a plain string and an empty file", async () => {
    const missing = new FormData();
    expect(await avatarFromForm(missing)).toBeNull();

    const text = new FormData();
    text.append("avatar", "not a file");
    expect(await avatarFromForm(text)).toBeNull();

    const empty = new FormData();
    empty.append("avatar", new File([], "x.png", { type: "image/png" }));
    expect(await avatarFromForm(empty)).toBeNull();
  });

  it("rejects a file over the API's 5 MB limit", async () => {
    const form = new FormData();
    form.append("avatar", pngFile(MAX_AVATAR_BYTES + 1));
    expect(await avatarFromForm(form)).toBeNull();
  });

  it("rejects a non-image renamed to .png", async () => {
    const form = new FormData();
    form.append(
      "avatar",
      new File(["<html>hello</html>"], "me.png", { type: "image/png" }),
    );
    expect(await avatarFromForm(form)).toBeNull();
  });
});
