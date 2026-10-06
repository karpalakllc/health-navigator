import { describe, expect, it, vi } from "vitest";
import {
  RECENTLY_VIEWED_KEY,
  RECENTLY_VIEWED_LIMIT,
  clearRecentlyViewed,
  getRecentlyViewedServerSnapshot,
  getRecentlyViewedSnapshot,
  readRecentlyViewed,
  recentlyViewedPath,
  recordRecentlyViewed,
  sanitizeEntry,
} from "@/lib/recently-viewed";

function memoryStorage(initial: Record<string, string> = {}) {
  const data = new Map(Object.entries(initial));
  return {
    data,
    getItem: (key: string) => data.get(key) ?? null,
    setItem: (key: string, value: string) => {
      data.set(key, value);
    },
    removeItem: (key: string) => {
      data.delete(key);
    },
  };
}

function throwingStorage() {
  const fail = () => {
    throw new DOMException("blocked", "SecurityError");
  };
  return { getItem: fail, setItem: fail, removeItem: fail };
}

const doctor = {
  kind: "doctor" as const,
  slug: "ana-petrovska",
  name: "д-р Ана Петровска",
  subtitle: "Кардиологија · Скопје",
  avatarUrl: "https://media.example/ana.webp",
};

describe("recently viewed storage", () => {
  it("records newest first under a versioned key, with only the card fields", () => {
    const storage = memoryStorage();

    expect(recordRecentlyViewed({ ...doctor, viewedAt: 1 }, storage)).toBe(
      true,
    );
    recordRecentlyViewed(
      {
        kind: "pharmacy",
        slug: "apteka-ohrid",
        name: "Аптека Охрид",
        subtitle: null,
        avatarUrl: null,
        viewedAt: 2,
      },
      storage,
    );

    expect(RECENTLY_VIEWED_KEY).toMatch(/:v\d+$/);
    const stored = JSON.parse(storage.data.get(RECENTLY_VIEWED_KEY)!);
    expect(stored.map((e: { slug: string }) => e.slug)).toEqual([
      "apteka-ohrid",
      "ana-petrovska",
    ]);
    expect(Object.keys(stored[1]).sort()).toEqual(
      ["avatarUrl", "kind", "name", "slug", "subtitle", "viewedAt"].sort(),
    );
  });

  it("moves a revisited profile to the front instead of duplicating it", () => {
    const storage = memoryStorage();
    recordRecentlyViewed(doctor, storage);
    recordRecentlyViewed({ ...doctor, slug: "other" }, storage);
    recordRecentlyViewed({ ...doctor, name: "д-р Ана П." }, storage);

    const entries = readRecentlyViewed(storage);
    expect(entries.map((e) => e.slug)).toEqual(["ana-petrovska", "other"]);
    expect(entries[0]!.name).toBe("д-р Ана П.");
  });

  it("keeps the same slug apart across kinds", () => {
    const storage = memoryStorage();
    recordRecentlyViewed(doctor, storage);
    recordRecentlyViewed({ ...doctor, kind: "facility" }, storage);

    expect(readRecentlyViewed(storage)).toHaveLength(2);
  });

  it(`keeps at most ${RECENTLY_VIEWED_LIMIT}`, () => {
    const storage = memoryStorage();
    for (let i = 0; i < 12; i++) {
      recordRecentlyViewed({ ...doctor, slug: `d-${i}` }, storage);
    }

    const entries = readRecentlyViewed(storage);
    expect(entries).toHaveLength(RECENTLY_VIEWED_LIMIT);
    expect(entries[0]!.slug).toBe("d-11");
    expect(entries.at(-1)!.slug).toBe("d-4");
  });

  it("clears the list", () => {
    const storage = memoryStorage();
    recordRecentlyViewed(doctor, storage);
    clearRecentlyViewed(storage);

    expect(storage.data.has(RECENTLY_VIEWED_KEY)).toBe(false);
    expect(readRecentlyViewed(storage)).toEqual([]);
  });

  it("survives storage that throws on every access", () => {
    const storage = throwingStorage();

    expect(readRecentlyViewed(storage)).toEqual([]);
    expect(recordRecentlyViewed(doctor, storage)).toBe(false);
    expect(() => clearRecentlyViewed(storage)).not.toThrow();
  });

  it("survives a full quota on write and keeps what was there", () => {
    const storage = memoryStorage();
    recordRecentlyViewed(doctor, storage);
    storage.setItem = () => {
      throw new DOMException("full", "QuotaExceededError");
    };

    expect(recordRecentlyViewed({ ...doctor, slug: "new" }, storage)).toBe(
      false,
    );
    expect(readRecentlyViewed(storage).map((e) => e.slug)).toEqual([
      "ana-petrovska",
    ]);
  });

  it("works without any storage (server, blocked site data)", () => {
    expect(readRecentlyViewed(null)).toEqual([]);
    expect(recordRecentlyViewed(doctor, null)).toBe(false);
    expect(getRecentlyViewedSnapshot()).toEqual([]);
    expect(getRecentlyViewedServerSnapshot()).toEqual([]);
  });

  it("ignores corrupt or foreign data", () => {
    for (const raw of ["not json", "{}", "42", "null", '"x"']) {
      expect(
        readRecentlyViewed(memoryStorage({ [RECENTLY_VIEWED_KEY]: raw })),
      ).toEqual([]);
    }
  });

  it("drops stored items that could build a bad link or image", () => {
    const raw = JSON.stringify([
      { ...doctor, kind: "admin" },
      { ...doctor, slug: "../../account" },
      { ...doctor, slug: "" },
      { ...doctor, name: "   " },
      { ...doctor, slug: "ok", avatarUrl: "javascript:alert(1)" },
      { ...doctor, slug: "ok2", avatarUrl: "//evil.example/x.png" },
      { ...doctor, slug: "ok3", avatarUrl: "/storage/media/a.webp" },
      "string",
      null,
    ]);

    const entries = readRecentlyViewed(
      memoryStorage({ [RECENTLY_VIEWED_KEY]: raw }),
    );

    expect(entries.map((e) => [e.slug, e.avatarUrl])).toEqual([
      ["ok", null],
      ["ok2", null],
      ["ok3", "/storage/media/a.webp"],
    ]);
  });

  it("keeps only https or same-site avatars (plain http only from the API host)", () => {
    vi.stubEnv("NEXT_PUBLIC_API_URL", "http://127.0.0.1:8021");
    const avatarOf = (avatarUrl: string) =>
      sanitizeEntry({ ...doctor, avatarUrl, viewedAt: 0 })!.avatarUrl;

    expect(avatarOf("https://cdn.example/a.webp")).toBe(
      "https://cdn.example/a.webp",
    );
    expect(avatarOf("/storage/media/a.webp")).toBe("/storage/media/a.webp");
    expect(avatarOf("http://127.0.0.1:8021/storage/media/a.webp")).toBe(
      "http://127.0.0.1:8021/storage/media/a.webp",
    );
    expect(avatarOf("http://tracker.example/pixel.gif")).toBeNull();
    expect(avatarOf("data:image/png;base64,AAAA")).toBeNull();
    vi.unstubAllEnvs();
  });

  it("trims and caps text fields", () => {
    const entry = sanitizeEntry({
      ...doctor,
      name: `  ${"А".repeat(500)}  `,
      subtitle: "   ",
      viewedAt: "yesterday",
    });

    expect(entry!.name).toHaveLength(200);
    expect(entry!.subtitle).toBeNull();
    expect(entry!.viewedAt).toBe(0);
  });

  it("links each kind to its public profile", () => {
    expect(recentlyViewedPath({ ...doctor, viewedAt: 0 })).toBe(
      "/doctors/ana-petrovska",
    );
    expect(
      recentlyViewedPath({ ...doctor, kind: "facility", viewedAt: 0 }),
    ).toBe("/facilities/ana-petrovska");
    expect(
      recentlyViewedPath({
        ...doctor,
        kind: "pharmacy",
        slug: "аптека 1",
        viewedAt: 0,
      }),
    ).toBe(`/pharmacies/${encodeURIComponent("аптека 1")}`);
  });
});
