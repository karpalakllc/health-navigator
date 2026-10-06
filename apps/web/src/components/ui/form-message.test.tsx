import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { FormError, FormSuccess } from "@/components/ui/form-message";

describe("FormError", () => {
  it("is an assertive alert carrying its id for aria-describedby", () => {
    render(
      <>
        <input aria-describedby="err" aria-label="Поле" />
        <FormError id="err">Задолжително поле.</FormError>
      </>,
    );

    expect(screen.getByRole("alert")).toHaveTextContent("Задолжително поле.");
    expect(screen.getByLabelText("Поле")).toHaveAccessibleDescription(
      "Задолжително поле.",
    );
  });
});

describe("FormSuccess", () => {
  it("is a polite status region that is present but hidden while empty", () => {
    render(<FormSuccess>{null}</FormSuccess>);

    const region = screen.getByRole("status");
    expect(region).toHaveAttribute("aria-live", "polite");
    expect(region).toBeEmptyDOMElement();
    expect(region).toHaveClass("sr-only");
  });

  it("fills the same element when a message arrives", () => {
    const { rerender } = render(<FormSuccess>{null}</FormSuccess>);
    const region = screen.getByRole("status");

    rerender(<FormSuccess>Испратено.</FormSuccess>);

    expect(screen.getByRole("status")).toBe(region);
    expect(region).toHaveTextContent("Испратено.");
    expect(region).not.toHaveClass("sr-only");
  });

  it("treats false like no message", () => {
    render(<FormSuccess>{false}</FormSuccess>);

    expect(screen.getByRole("status")).toBeEmptyDOMElement();
  });
});
