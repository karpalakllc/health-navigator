import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it } from "vitest";
import { LicenceStatusTag } from "@/components/directory/licence-status";
import { formatListDate } from "@/lib/mk-date";
import { t } from "@/i18n/t";

describe("LicenceStatusTag", () => {
  it("shows the calm valid-licence line when the API says so", () => {
    render(<LicenceStatusTag valid />);

    expect(screen.getByText("Лиценца: важечка")).toBeInTheDocument();
  });

  it("names the Комора list and its date in a toggletip", async () => {
    const user = userEvent.setup();
    render(<LicenceStatusTag valid listDate="2026-07-02" />);

    expect(screen.getByText("Лиценца: важечка")).toBeInTheDocument();
    await user.click(
      screen.getByRole("button", { name: t("doctors.licenceSourceWhy") }),
    );
    expect(screen.getByRole("status")).toHaveTextContent(
      "Според листата на Лекарска комора од 02.07.2026.",
    );
  });

  it.each([false, null, undefined])("shows nothing for %s", (valid) => {
    const { container } = render(
      <LicenceStatusTag valid={valid} listDate="2026-07-02" />,
    );

    expect(container).toBeEmptyDOMElement();
  });

  it("formats only plain calendar dates", () => {
    expect(formatListDate("2026-07-02")).toBe("02.07.2026");
    expect(formatListDate("2026-07-02T10:00:00Z")).toBeNull();
    expect(formatListDate(null)).toBeNull();
  });
});
