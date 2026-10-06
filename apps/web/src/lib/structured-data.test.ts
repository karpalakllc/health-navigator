import { describe, expect, it } from "vitest";
import { facilitySchemaType, placeJsonLd } from "@/lib/structured-data";

const base = {
  name: "Аптека Центар",
  description: null,
  city: "Скопје",
  address: null,
  latitude: null,
  longitude: null,
  phone: "02 123 456",
  email: null,
  website: null,
  review_summary: { count: 0, average_rating: null },
};

describe("placeJsonLd", () => {
  it("emits only the facts that are present", () => {
    const data = placeJsonLd("Pharmacy", base, "https://x.test/pharmacies/a");

    expect(data).toEqual({
      "@context": "https://schema.org",
      "@type": "Pharmacy",
      name: "Аптека Центар",
      url: "https://x.test/pharmacies/a",
      address: {
        "@type": "PostalAddress",
        addressLocality: "Скопје",
        addressCountry: "MK",
      },
      telephone: "02 123 456",
    });
  });

  it("claims a rating only when published reviews back it", () => {
    expect(placeJsonLd("Pharmacy", base, "u")).not.toHaveProperty(
      "aggregateRating",
    );

    const rated = placeJsonLd(
      "Pharmacy",
      { ...base, review_summary: { count: 3, average_rating: 4.3 } },
      "u",
    );
    expect(rated.aggregateRating).toMatchObject({
      ratingValue: 4.3,
      reviewCount: 3,
    });
  });
});

describe("facilitySchemaType", () => {
  it("maps facility kinds to schema.org types", () => {
    expect(facilitySchemaType("clinic")).toBe("MedicalClinic");
    expect(facilitySchemaType("hospital")).toBe("Hospital");
    expect(facilitySchemaType("laboratory")).toBe("DiagnosticLab");
  });
});
