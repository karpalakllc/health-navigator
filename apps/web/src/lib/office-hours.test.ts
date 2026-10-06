import { describe, expect, it } from "vitest";
import {
  officeHoursRows,
  openStatus,
  parseDays,
  parseRanges,
  skopjeClock,
} from "@/lib/office-hours";

// 2026-10-06 is a Tuesday. Skopje is UTC+2 in October (CEST).
const tuesdayAt = (hhmm: string) => new Date(`2026-10-06T${hhmm}:00+02:00`);

const DOCTOR = {
  Пон: "08:00–14:00",
  Вто: "08:00–14:00",
  Сре: "12:00–18:00",
  Чет: "08:00–14:00",
  Пет: "08:00–12:00",
};

describe("parseDays", () => {
  it.each([
    ["Пон", [0]],
    ["Понеделник", [0]],
    ["Пон–Пет", [0, 1, 2, 3, 4]],
    ["Сабота - Недела", [5, 6]],
    ["Саб, Нед", [5, 6]],
    ["Сабота и Недела", [5, 6]],
    ["Пет–Пон", [0, 4, 5, 6]],
    ["Работни денови", [0, 1, 2, 3, 4]],
    ["работни дена", [0, 1, 2, 3, 4]],
    ["Викенд", [5, 6]],
    ["Секој ден", [0, 1, 2, 3, 4, 5, 6]],
    ["Секој ден во неделата", [0, 1, 2, 3, 4, 5, 6]],
    ["Работни денови и Сабота", [0, 1, 2, 3, 4, 5]],
    ["Понеделник до Петок", [0, 1, 2, 3, 4]],
    ["Пон до Пет", [0, 1, 2, 3, 4]],
    ["од Понеделник до Петок", [0, 1, 2, 3, 4]],
    ["Пон. – Пет.", [0, 1, 2, 3, 4]],
    ["Понеделник — Сабота", [0, 1, 2, 3, 4, 5]],
    ["Сабота–Недела", [5, 6]],
    ["Pon–Pet", [0, 1, 2, 3, 4]],
    ["ponedelnik do petok", [0, 1, 2, 3, 4]],
    ["Sab, Ned", [5, 6]],
    ["Čet", [3]],
  ])("reads %s", (label, days) => {
    expect(parseDays(label)).toEqual(days);
  });

  it("gives up on labels it does not know rather than guessing", () => {
    expect(parseDays("Празници")).toEqual([]);
    expect(parseDays("Пон–Празник")).toEqual([]);
  });

  it("matches whole day words, not any word that starts like one", () => {
    expect(parseDays("Неделно")).toEqual([]);
    expect(parseDays("Средно")).toEqual([]);
    expect(parseDays("Петочно попладне")).toEqual([]);
    expect(parseDays("Понеделник попладне")).toEqual([]);
    expect(parseDays("Пон до")).toEqual([]);
  });
});

describe("parseRanges", () => {
  it("reads one or several ranges with any dash", () => {
    expect(parseRanges("08:00–14:00")).toEqual([{ from: 480, to: 840 }]);
    expect(parseRanges("8.00 - 12.00, 16:00—20:00")).toEqual([
      { from: 480, to: 720 },
      { from: 960, to: 1200 },
    ]);
    expect(parseRanges("Затворено")).toEqual([]);
  });
});

describe("skopjeClock", () => {
  it("uses Skopje time, not the server's", () => {
    // 23:30 UTC on Monday is already 01:30 on Tuesday in Skopje.
    expect(skopjeClock(new Date("2026-10-05T23:30:00Z"))).toEqual({
      day: 1,
      minutes: 90,
    });
  });
});

describe("officeHoursRows", () => {
  it("marks the row that covers today", () => {
    const rows = officeHoursRows(DOCTOR, tuesdayAt("10:00"));

    expect(rows.map((r) => r.isToday)).toEqual([
      false,
      true,
      false,
      false,
      false,
    ]);
  });

  it("marks a range row on a day inside the range", () => {
    const rows = officeHoursRows(
      { "Пон–Пет": "07:30–20:00", Саб: "08:00–14:00" },
      tuesdayAt("10:00"),
    );

    expect(rows[0].isToday).toBe(true);
    expect(rows[1].isToday).toBe(false);
  });

  it("treats the API's empty array as no hours", () => {
    expect(officeHoursRows([])).toEqual([]);
    expect(officeHoursRows(null)).toEqual([]);
  });
});

describe("openStatus", () => {
  it("is open until the end of today's range", () => {
    const now = tuesdayAt("10:00");
    expect(openStatus(officeHoursRows(DOCTOR, now), now)).toEqual({
      state: "open",
      until: "14:00",
    });
  });

  it("is closed after hours and still says today's hours", () => {
    const now = tuesdayAt("15:00");
    expect(openStatus(officeHoursRows(DOCTOR, now), now)).toEqual({
      state: "closed",
      todayHours: "08:00–14:00",
    });
  });

  it("is closed on a day the schedule does not list", () => {
    const sunday = new Date("2026-10-11T10:00:00+02:00");
    expect(openStatus(officeHoursRows(DOCTOR, sunday), sunday)).toEqual({
      state: "closed",
      todayHours: null,
    });
  });

  it("is closed on a day marked „Затворено“", () => {
    const now = tuesdayAt("10:00");
    const rows = officeHoursRows({ "Пон–Пет": "Затворено" }, now);
    expect(openStatus(rows, now)).toEqual({
      state: "closed",
      todayHours: null,
    });
  });

  it("knows a 24-hour schedule", () => {
    const now = tuesdayAt("03:00");
    const rows = officeHoursRows({ "Пон–Нед": "00:00–24:00" }, now);
    expect(openStatus(rows, now)).toEqual({ state: "open24" });
  });

  it("does not guess when nothing parses", () => {
    const now = tuesdayAt("10:00");
    expect(
      openStatus(officeHoursRows({ Секогаш: "по договор" }, now), now),
    ).toBeNull();
    expect(
      openStatus(officeHoursRows({ Вто: "по договор" }, now), now),
    ).toBeNull();
  });

  it("reads the common phrase labels", () => {
    const now = tuesdayAt("10:00");
    const rows = officeHoursRows(
      { "Работни денови": "08:00–16:00", Викенд: "Затворено" },
      now,
    );
    expect(openStatus(rows, now)).toEqual({ state: "open", until: "16:00" });

    const sunday = new Date("2026-10-11T10:00:00+02:00");
    expect(
      openStatus(
        officeHoursRows(
          { "Работни денови": "08:00–16:00", Викенд: "Затворено" },
          sunday,
        ),
        sunday,
      ),
    ).toEqual({ state: "closed", todayHours: null });

    expect(
      openStatus(officeHoursRows({ "Секој ден": "07:00–22:00" }, now), now),
    ).toEqual({ state: "open", until: "22:00" });
  });

  it("does not say closed when a row it could not read may cover today", () => {
    const now = tuesdayAt("10:00");
    const rows = officeHoursRows(
      { Саб: "08:00–14:00", "Секој втор ден": "08:00–14:00" },
      now,
    );
    expect(openStatus(rows, now)).toBeNull();
  });

  it("still answers when a parsed row covers today despite an unread row", () => {
    const now = tuesdayAt("10:00");
    const rows = officeHoursRows(
      { "Пон–Пет": "08:00–14:00", Празници: "по договор" },
      now,
    );
    expect(openStatus(rows, now)).toEqual({ state: "open", until: "14:00" });
  });

  it("keeps an overnight range open after midnight on the next day", () => {
    // Monday 22:00–02:00; Tuesday is not listed.
    const hours = { Пон: "22:00–02:00" };
    const lateMonday = new Date("2026-10-05T23:00:00+02:00");
    expect(openStatus(officeHoursRows(hours, lateMonday), lateMonday)).toEqual({
      state: "open",
      until: "02:00",
    });

    const now = tuesdayAt("01:00");
    expect(openStatus(officeHoursRows(hours, now), now)).toEqual({
      state: "open",
      until: "02:00",
    });

    const later = tuesdayAt("03:00");
    expect(openStatus(officeHoursRows(hours, later), later)).toEqual({
      state: "closed",
      todayHours: null,
    });
  });

  it("does not open early in the morning from today's own overnight range", () => {
    // Tuesday 22:00–02:00 only: at 01:00 on Tuesday it is not open yet.
    const now = tuesdayAt("01:00");
    expect(
      openStatus(officeHoursRows({ Вто: "22:00–02:00" }, now), now),
    ).toEqual({ state: "closed", todayHours: "22:00–02:00" });
  });

  it("reads „Понеделник до Петок“ as the whole working week", () => {
    const now = tuesdayAt("10:00");
    const rows = officeHoursRows({ "Понеделник до Петок": "08:00–14:00" }, now);
    expect(rows[0].isToday).toBe(true);
    expect(openStatus(rows, now)).toEqual({ state: "open", until: "14:00" });
  });

  it("does not take „Неделно“ for Sunday", () => {
    const sunday = new Date("2026-10-11T10:00:00+02:00");
    const rows = officeHoursRows(
      { "Пон–Пет": "08:00–14:00", Неделно: "08:00–14:00" },
      sunday,
    );
    expect(rows[1].days).toEqual([]);
    expect(openStatus(rows, sunday)).toBeNull();
  });

  it("combines a split shift typed over two rows", () => {
    const hours = { "Пон–Пет": "08:00–12:00", "Вто, Чет": "16:00–20:00" };

    const morning = tuesdayAt("10:00");
    expect(openStatus(officeHoursRows(hours, morning), morning)).toEqual({
      state: "open",
      until: "12:00",
    });

    const evening = tuesdayAt("17:00");
    expect(openStatus(officeHoursRows(hours, evening), evening)).toEqual({
      state: "open",
      until: "20:00",
    });

    const lunch = tuesdayAt("13:00");
    expect(openStatus(officeHoursRows(hours, lunch), lunch)).toEqual({
      state: "closed",
      todayHours: "08:00–12:00, 16:00–20:00",
    });
  });

  it("lists today's rows in time order when closed", () => {
    const now = tuesdayAt("21:00");
    const rows = officeHoursRows(
      { "Вто, Чет": "16:00–20:00", "Пон–Пет": "08:00–12:00" },
      now,
    );
    expect(openStatus(rows, now)).toEqual({
      state: "closed",
      todayHours: "08:00–12:00, 16:00–20:00",
    });
  });

  it("is open until the last of touching ranges ends", () => {
    const now = tuesdayAt("10:00");
    const rows = officeHoursRows(
      { "Пон–Пет": "08:00–12:00", Вто: "12:00–16:00" },
      now,
    );
    expect(openStatus(rows, now)).toEqual({ state: "open", until: "16:00" });
  });

  it("does not guess when today's rows disagree", () => {
    const now = tuesdayAt("10:00");
    const rows = officeHoursRows(
      { "Пон–Пет": "08:00–14:00", Вто: "Затворено" },
      now,
    );
    expect(openStatus(rows, now)).toBeNull();
  });
});
