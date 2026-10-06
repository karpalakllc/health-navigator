import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { LicenceStatusTag } from "@/components/directory/licence-status";

describe("LicenceStatusTag", () => {
  it("shows the calm valid-licence line when the API says so", () => {
    render(<LicenceStatusTag valid />);

    expect(screen.getByText("Лиценца: важечка")).toBeInTheDocument();
  });

  it.each([false, null, undefined])("shows nothing for %s", (valid) => {
    const { container } = render(<LicenceStatusTag valid={valid} />);

    expect(container).toBeEmptyDOMElement();
  });
});
