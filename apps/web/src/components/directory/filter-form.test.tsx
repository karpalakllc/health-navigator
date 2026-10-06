import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { FilterField } from "@/components/directory/filter-form";
import { FilterInputWrap } from "@/components/directory/filter-input-wrap";

describe("Directory filter fields", () => {
  it("name their control by the visible label", () => {
    render(
      <FilterField label="Град" hint="Пр. Скопје">
        <FilterInputWrap>
          <input name="city" />
        </FilterInputWrap>
      </FilterField>,
    );

    // The hint is not part of the name.
    expect(screen.getByRole("textbox", { name: "Град" })).toBeInTheDocument();
  });

  it("show a focus ring on the wrap, since the control drops its outline", () => {
    render(
      <FilterInputWrap>
        <select name="sort" aria-label="Подредување" />
      </FilterInputWrap>,
    );

    const wrap = screen
      .getByRole("combobox")
      .closest("div.rounded-\\[1\\.125rem\\]");
    expect(wrap?.className).toMatch(/focus-within:ring-2/);
  });
});
