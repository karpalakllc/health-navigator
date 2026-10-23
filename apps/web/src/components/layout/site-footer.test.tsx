import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import {
  EmergencyLine,
  SiteFooterContent,
} from "@/components/layout/site-footer";
import { publicSettingsDefaults } from "@/lib/api/public-settings";

describe("SiteFooter emergency line", () => {
  it("renders no 194/112 line by default (the setting is empty)", () => {
    render(<SiteFooterContent settings={publicSettingsDefaults} />);

    expect(publicSettingsDefaults.footer_emergency_text).toBe("");
    expect(screen.queryByRole("link", { name: "194" })).toBeNull();
    expect(screen.queryByRole("link", { name: "112" })).toBeNull();
    expect(screen.queryByText(/194|112/)).toBeNull();
  });

  it("renders nothing for a blank or missing admin line", () => {
    const { container } = render(
      <>
        <EmergencyLine text="   " />
        <EmergencyLine text={null} />
      </>,
    );

    expect(container).toBeEmptyDOMElement();
  });

  it("makes 194 and 112 tap-to-call links inside the admin's one quiet sentence", () => {
    const text = "При медицинска итност повикајте 194 или 112 веднаш.";
    render(
      <SiteFooterContent
        settings={{ ...publicSettingsDefaults, footer_emergency_text: text }}
      />,
    );

    const ambulance = screen.getByRole("link", { name: "194" });
    const emergency = screen.getByRole("link", { name: "112" });
    expect(ambulance).toHaveAttribute("href", "tel:194");
    expect(emergency).toHaveAttribute("href", "tel:112");
    // Same paragraph, the sentence intact: no buttons, no extra lines.
    expect(ambulance.parentElement).toBe(emergency.parentElement);
    expect(ambulance.parentElement).toHaveTextContent(text);
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
