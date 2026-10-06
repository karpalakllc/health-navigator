import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { FilterField } from "@/components/directory/filter-form";

describe("Directory filter fields", () => {
  it("name their control by the visible label", () => {
    render(
      <FilterField label="Град" hint="Пр. Скопје">
        <input name="city" />
      </FilterField>,
    );

    // The hint is not part of the name.
    expect(screen.getByRole("textbox", { name: "Град" })).toBeInTheDocument();
  });
});
