import { describe, expect, it } from "vitest";
import { t } from "@/i18n/t";
import type { MemberNotification } from "@/lib/notification-types";
import {
  monthLabel,
  notificationLine,
  safePath,
} from "@/lib/notification-text";

function item(type: string, data: Record<string, unknown>): MemberNotification {
  return { id: 1, type, data, read: false, created_at: null };
}

const doctor = {
  kind: "doctor",
  slug: "ana",
  name: "д-р Ана Петровска",
  path: "/doctors/ana",
};

describe("notificationLine", () => {
  it("words moderation decisions per content kind", () => {
    expect(
      notificationLine(
        item("moderation", {
          event: "published",
          content: "review",
          title: "д-р Ана Петровска",
          path: "/doctors/ana",
        }),
      ),
    ).toEqual({
      text: "Вашата рецензија за д-р Ана Петровска е објавена.",
      href: "/doctors/ana",
    });
    expect(
      notificationLine(
        item("moderation", {
          event: "removed",
          content: "post",
          title: "Болка во грло",
          path: "/account/forum",
        }),
      ).text,
    ).toBe("Вашиот одговор во темата „Болка во грло“ е отстранет по пријава.");
    expect(
      notificationLine(
        item("moderation", {
          event: "report_resolved",
          content: "review",
          title: "д-р Ана",
          removed: false,
        }),
      ),
    ).toEqual({
      text: "Пријавата е прегледана: содржината („д-р Ана“) останува објавена.",
      href: null,
    });
  });

  it("names who replied and links to the reviews", () => {
    expect(
      notificationLine(
        item("review_reply", { profile: doctor, from_doctor: true }),
      ),
    ).toEqual({
      text: "Лекарот одговори на вашата рецензија за д-р Ана Петровска.",
      href: "/doctors/ana#reviews",
    });
  });

  it("counts helpful votes without anyone's name", () => {
    expect(
      notificationLine(
        item("review_helpful", { profile: doctor, new_votes: 3, total: 5 }),
      ).text,
    ).toBe("Вашата рецензија за д-р Ана Петровска доби уште 3 „Корисно“.");
  });

  it("summarises a digest month", () => {
    expect(
      notificationLine(
        item("impact_digest", {
          month: "2026-09",
          stats: { review_views: 40, helpful_votes: 2 },
        }),
      ).text,
    ).toBe("Месечен преглед за септември 2026: прикажувања 40, „Корисно“ 2.");
  });

  it("falls back instead of rendering unexpected data", () => {
    const fallback = t("notifications.item.fallback");

    expect(notificationLine(item("something_new", {})).text).toBe(fallback);
    expect(notificationLine(item("review_reply", {})).text).toBe(fallback);
    expect(
      notificationLine(
        item("moderation", { event: "published", content: "x", title: "a" }),
      ).text,
    ).toBe(fallback);
  });

  it("never links off-site", () => {
    expect(
      notificationLine(
        item("review_reply", {
          profile: { ...doctor, path: "//evil.example" },
        }),
      ).href,
    ).toBeNull();
    expect(safePath("https://evil.example")).toBeNull();
    expect(safePath("/account/reviews")).toBe("/account/reviews");
  });

  it("formats months", () => {
    expect(monthLabel("2026-01")).toBe("јануари 2026");
    expect(monthLabel("2026-13")).toBeNull();
    expect(monthLabel(null)).toBeNull();
  });
});
