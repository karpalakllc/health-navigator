import { describe, expect, it } from "vitest";
import {
  clearDraft,
  DRAFTS_KEY,
  loadDraft,
  MAX_DRAFTS,
  RETENTION_DAYS,
  saveDraft,
} from "@/lib/forum/drafts";
import { parsePostBody, quoteText } from "@/lib/forum/post-body";
import {
  hasNewActivity,
  lastSeenActivity,
  markTopicSeen,
  SEEN_TOPICS_KEY,
  topicHash,
} from "@/lib/forum/seen-topics";

function memoryStorage(initial: Record<string, string> = {}) {
  const data = new Map(Object.entries(initial));

  return {
    data,
    getItem: (key: string) => data.get(key) ?? null,
    setItem: (key: string, value: string) => void data.set(key, value),
    removeItem: (key: string) => void data.delete(key),
  };
}

const DAY = 24 * 60 * 60 * 1000;
const NOW = Date.parse("2026-10-23T10:00:00Z");

describe("forum drafts", () => {
  it("keeps, restores and clears a draft per place", () => {
    const storage = memoryStorage();

    saveDraft("reply:/forum/a/b", { body: "Здраво" }, storage, NOW);
    saveDraft("topic:new", { title: "Наслов", body: "" }, storage, NOW);

    expect(loadDraft("reply:/forum/a/b", storage, NOW)).toEqual({
      body: "Здраво",
    });
    expect(loadDraft("topic:new", storage, NOW)?.title).toBe("Наслов");

    clearDraft("reply:/forum/a/b", storage, NOW);
    expect(loadDraft("reply:/forum/a/b", storage, NOW)).toBeNull();
  });

  it("drops empty and expired drafts and caps how many are kept", () => {
    const storage = memoryStorage();

    saveDraft("old", { body: "текст" }, storage, NOW);
    expect(
      loadDraft("old", storage, NOW + (RETENTION_DAYS + 1) * DAY),
    ).toBeNull();

    saveDraft("blank", { body: "   " }, storage, NOW);
    expect(loadDraft("blank", storage, NOW)).toBeNull();

    for (let i = 0; i < MAX_DRAFTS + 5; i++) {
      saveDraft(`scope-${i}`, { body: "x" }, storage, NOW + i);
    }
    const kept = Object.keys(JSON.parse(storage.data.get(DRAFTS_KEY)!));
    expect(kept).toHaveLength(MAX_DRAFTS);
    expect(kept).not.toContain("scope-0");
  });

  it("survives broken or blocked storage", () => {
    const broken = memoryStorage({ [DRAFTS_KEY]: "{not json" });
    expect(loadDraft("x", broken, NOW)).toBeNull();

    const throwing = {
      getItem: () => {
        throw new Error("blocked");
      },
      setItem: () => {
        throw new Error("blocked");
      },
      removeItem: () => {
        throw new Error("blocked");
      },
    };
    expect(() => saveDraft("x", { body: "a" }, throwing, NOW)).not.toThrow();
    expect(loadDraft("x", throwing, NOW)).toBeNull();
    expect(loadDraft("x", null, NOW)).toBeNull();
  });
});

describe("seen topics", () => {
  const path = "/forum/opsto/voda";

  it("marks a topic new only when it was opened before and is active since", () => {
    const storage = memoryStorage();

    expect(hasNewActivity(path, "2026-10-20T10:00:00Z", storage, NOW)).toBe(
      false,
    );

    markTopicSeen(path, "2026-10-20T10:00:00Z", storage, NOW);
    expect(hasNewActivity(path, "2026-10-20T10:00:00Z", storage, NOW)).toBe(
      false,
    );
    expect(hasNewActivity(path, "2026-10-21T10:00:00Z", storage, NOW)).toBe(
      true,
    );
    expect(lastSeenActivity(path, storage, NOW)).toBe(
      Date.parse("2026-10-20T10:00:00Z"),
    );
  });

  it("stores a hash of the path, never the path itself", () => {
    const storage = memoryStorage();
    markTopicSeen(path, "2026-10-20T10:00:00Z", storage, NOW);

    const raw = storage.data.get(SEEN_TOPICS_KEY)!;
    expect(raw).not.toContain("voda");
    expect(Object.keys(JSON.parse(raw))).toEqual([topicHash(path)]);
  });
});

describe("post bodies and quotes", () => {
  it("splits „>“ lines into quote blocks", () => {
    expect(
      parsePostBody("> Ана напиша:\n> Пијте вода.\n\nСе согласувам."),
    ).toEqual([
      { kind: "quote", text: "Ана напиша:\nПијте вода." },
      { kind: "text", text: "Се согласувам." },
    ]);
    expect(parsePostBody("Само текст\nво два реда")).toEqual([
      { kind: "text", text: "Само текст\nво два реда" },
    ]);
  });

  it("quotes a post without its own quotes and cuts long ones", () => {
    expect(quoteText("Ана напиша:", "> старо\nНово мислење")).toBe(
      "> Ана напиша:\n> Ново мислење\n\n",
    );

    const long = quoteText("Ана напиша:", "збор ".repeat(400));
    expect(long.length).toBeLessThan(700);
    expect(long.trimEnd().endsWith("…")).toBe(true);
  });
});
