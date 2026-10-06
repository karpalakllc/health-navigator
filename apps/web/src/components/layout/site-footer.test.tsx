import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import {
  EmergencyLine,
  SiteFooterContent,
} from "@/components/layout/site-footer";
import { publicSettingsDefaults } from "@/lib/api/public-settings";

describe("SiteFooter emergency line", () => {
  it("makes 194 and 112 tap-to-call links inside the one quiet sentence", () => {
    render(<SiteFooterContent settings={publicSettingsDefaults} />);

    const ambulance = screen.getByRole("link", { name: "194" });
    const emergency = screen.getByRole("link", { name: "112" });
    expect(ambulance).toHaveAttribute("href", "tel:194");
    expect(emergency).toHaveAttribute("href", "tel:112");
    // Same paragraph, the sentence intact: no buttons, no extra lines.
    expect(ambulance.parentElement).toBe(emergency.parentElement);
    expect(ambulance.parentElement).toHaveTextContent(
      publicSettingsDefaults.footer_emergency_text,
    );
    expect(ambulance.className).not.toMatch(/\bbtn\b/);
  });

  it("leaves other numbers and admin text as written", () => {
    render(<EmergencyLine text="Повикајте 1940 или 194, не 2112." />);

    expect(screen.getAllByRole("link").map((a) => a.textContent)).toEqual([
      "194",
    ]);
    expect(screen.getByText((_, el) => el?.tagName === "P")).toHaveTextContent(
      "Повикајте 1940 или 194, не 2112.",
    );
  });
});
