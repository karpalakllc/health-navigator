import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { HoursTable } from "@/components/directory/profile-parts";

describe("HoursTable", () => {
  it("never breaks a time range; the day label is the part that wraps", () => {
    render(
      <HoursTable
        hours={{ "Понеделник–Петок": "07:30–20:00", Сабота: "08:00–14:00" }}
      />,
    );

    for (const range of ["07:30–20:00", "08:00–14:00"]) {
      const cell = screen.getByRole("cell", { name: range });
      expect(cell).toHaveClass("whitespace-nowrap");
    }
    expect(
      screen.getByRole("rowheader", { name: /Понеделник–Петок/ }),
    ).not.toHaveClass("whitespace-nowrap");
  });
});
