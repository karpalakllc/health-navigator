import { describe, expect, it } from "vitest";
import { knownLanguage, type DoctorLanguage } from "@/lib/api/languages";

const languages: DoctorLanguage[] = [
  { slug: "angliski", name: "Англиски", doctors_count: 3 },
  { slug: "albanski", name: "Албански", doctors_count: 1 },
];

describe("knownLanguage", () => {
  it("passes a slug the API listed", () => {
    expect(knownLanguage("albanski", languages)).toBe("albanski");
  });

  it.each([undefined, "", "klingonski", "Англиски"])(
    "drops %j, which the API would answer with 422",
    (raw) => {
      expect(knownLanguage(raw, languages)).toBeUndefined();
    },
  );

  it("drops every value when the list could not be read", () => {
    expect(knownLanguage("angliski", [])).toBeUndefined();
  });
});
