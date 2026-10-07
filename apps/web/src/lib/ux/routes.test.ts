import { describe, expect, it } from "vitest";
import { uxRouteTemplate } from "@/lib/ux/routes";
import { scrollMilestone, tfiBucket, widthBucket } from "@/lib/ux/schema";
import { parseUxBatch } from "@/lib/ux/validate";

describe("uxRouteTemplate", () => {
  it.each([
    ["/", "/"],
    ["/doctors", "/doctors"],
    ["/doctors/", "/doctors"],
    ["/doctors/ivan-petrov", "/doctors/[slug]"],
    ["/facilities/klinika-x", "/facilities/[slug]"],
    ["/pharmacies/apteka", "/pharmacies/[slug]"],
    ["/products/aspirin", "/products/[slug]"],
    ["/forum", "/forum"],
    ["/forum/srce", "/forum/[categorySlug]"],
    ["/forum/srce/bolka-vo-gradite", "/forum/[categorySlug]/[topicSlug]"],
    ["/forum/tags/pritisok", "/forum/tags/[tag]"],
    ["/guidance", "/guidance"],
    ["/search", "/search"],
  ])("%s → %s", (path, template) => {
    expect(uxRouteTemplate(path)).toBe(template);
  });

  it.each([
    "/account",
    "/account/data",
    "/login",
    "/register",
    "/forgot-password",
    "/reset-password/new",
    "/verify-email",
    "/design-system",
    "/doctors/ivan-petrov/claim",
    "/doctors/ivan-petrov/correction",
    "/doctors/ivan-petrov/objection",
    "/facilities/klinika-x/correction",
    "/forum/new",
    "/forum/tags",
    "/admin",
    "/no-such-page",
  ])("%s is never tracked", (path) => {
    expect(uxRouteTemplate(path)).toBeNull();
  });
});

describe("buckets", () => {
  it("rounds widths down to 80 px and caps them", () => {
    expect(widthBucket(390)).toBe(320);
    expect(widthBucket(1440)).toBe(1440);
    expect(widthBucket(9000)).toBe(3840);
  });

  it("maps scroll depth to milestones", () => {
    expect(scrollMilestone(0.1)).toBe(0);
    expect(scrollMilestone(0.3)).toBe(25);
    expect(scrollMilestone(0.9)).toBe(90);
    expect(scrollMilestone(0.995)).toBe(100);
  });

  it("maps time to first click to buckets", () => {
    expect(tfiBucket(400)).toBe(0);
    expect(tfiBucket(2_000)).toBe(1);
    expect(tfiBucket(9_999)).toBe(2);
    expect(tfiBucket(20_000)).toBe(3);
    expect(tfiBucket(120_000)).toBe(4);
  });
});

describe("parseUxBatch", () => {
  const good = {
    r: "/doctors",
    vc: "mobile",
    wb: 320,
    x: 5,
    y: 10,
    k: "doctor-card/heading",
    d: true,
    g: false,
  };

  it("copies only known fields and drops invalid entries", () => {
    const batch = parseUxBatch({
      session: "abc",
      clicks: [
        { ...good, text: "Болка во градите", id: 7 },
        { ...good, k: "main/Болка" },
        { ...good, r: "/account" },
        { ...good, r: "/doctors/ivan-petrov" },
        { ...good, x: 100 },
        { ...good, wb: 390 },
      ],
      views: [
        { r: "/doctors", vc: "mobile", s: 50, t: null, url: "/doctors?q=x" },
        { r: "/doctors", vc: "mobile", s: 60, t: null },
      ],
    });

    expect(batch).toEqual({
      clicks: [good],
      views: [{ r: "/doctors", vc: "mobile", s: 50, t: null }],
    });
    expect(JSON.stringify(batch)).not.toMatch(/Болка|session|ivan|url/);
  });

  it("is null when nothing valid is left", () => {
    expect(parseUxBatch({ clicks: [{ ...good, vc: "watch" }] })).toBeNull();
    expect(parseUxBatch([])).toBeNull();
    expect(parseUxBatch("x")).toBeNull();
  });
});
