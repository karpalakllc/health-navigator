import { describe, expect, it } from "vitest";
import { requestIdFor, validRequestId } from "@/lib/request-id";

const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/;

describe("validRequestId", () => {
  it("accepts a UUID and the IDs edges mint", () => {
    expect(validRequestId("3f2b8c1e-7a4d-4e9b-9c1a-0d5e6f7a8b9c")).toBe(
      "3f2b8c1e-7a4d-4e9b-9c1a-0d5e6f7a8b9c",
    );
    expect(validRequestId("render-ABC123")).toBe("render-ABC123");
  });

  it("drops anything that could forge or flood a log line", () => {
    for (const bad of [
      "short",
      "a".repeat(65),
      "abcd1234\nforged",
      "abc_def.ghi",
      "a b c d e f g h",
      "",
      null,
      undefined,
    ]) {
      expect(validRequestId(bad)).toBeUndefined();
    }
  });
});

describe("requestIdFor", () => {
  it("keeps a well-formed incoming ID", () => {
    expect(requestIdFor(new Headers({ "x-request-id": "edge-12345678" }))).toBe(
      "edge-12345678",
    );
  });

  it("mints a fresh UUID otherwise", () => {
    const minted = requestIdFor(new Headers({ "x-request-id": "bad id!" }));

    expect(minted).toMatch(UUID);
    expect(requestIdFor(new Headers())).not.toBe(minted);
  });
});
