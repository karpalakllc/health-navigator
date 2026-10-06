import { describe, expect, it } from "vitest";
import {
  doctorInitials,
  initialsFromName,
  stripHonorifics,
} from "@/lib/user-initials";

describe("doctorInitials", () => {
  it.each([
    ["д-р Ана Петровска", "АП"],
    ["Д-р Марко Стојанов", "МС"],
    ["проф. д-р Елена Илиевска", "ЕИ"],
    ["доц. д-р Јован Ѕивковски", "ЈЅ"],
    ["прим. д-р Влатко Николов", "ВН"],
    ["м-р Ива Трајковска", "ИТ"],
    ["Dr. Ana Petrovska", "AP"],
    ["dr Marko Stojanov", "MS"],
  ])("skips the titles in %s", (name, expected) => {
    // Every doctor's monogram was "Д", from "д-р".
    expect(doctorInitials(name)).toBe(expected);
  });

  it("keeps a name that is only a title rather than returning nothing", () => {
    expect(stripHonorifics("д-р")).toBe("д-р");
  });
});

describe("initialsFromName", () => {
  it("keeps Macedonian Cyrillic letters (Ј and Ѕ only look like Latin J and S)", () => {
    const initials = initialsFromName("Јован Ѕивковски");

    expect(initials).toBe("ЈЅ");
    expect([...initials].map((c) => c.codePointAt(0))).toEqual([
      0x0408, 0x0405,
    ]);
  });
});
