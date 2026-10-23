import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { FirstAidPhoneLinks, telHref } from "@/components/first-aid/call-links";

describe("telHref", () => {
  it.each([
    ["02 3147 168", "tel:+38923147168"],
    ["070 390 632", "tel:+38970390632"],
    ["+389 72 271 048", "tel:+38972271048"],
    ["15 315", "tel:15315"],
  ])("dials %s as %s", (number, href) => {
    expect(telHref(number)).toBe(href);
  });
});

describe("FirstAidPhoneLinks", () => {
  it("renders each number as a tel: link that shows the number", () => {
    render(
      <FirstAidPhoneLinks
        phones={[{ number: "02 3147 168", label: "Клиника за токсикологија" }]}
      />,
    );

    expect(
      screen.getByRole("link", {
        name: "Клиника за токсикологија — 02 3147 168",
      }),
    ).toHaveAttribute("href", "tel:+38923147168");
  });

  it("renders nothing without numbers", () => {
    const { container } = render(<FirstAidPhoneLinks phones={[]} />);

    expect(container).toBeEmptyDOMElement();
  });
});
