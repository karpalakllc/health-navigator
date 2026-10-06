import { render } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { FacilityMapEmbed } from "@/components/directory/facility-map-embed";

describe("FacilityMapEmbed", () => {
  it("sends OpenStreetMap no referrer, so it never learns which profile was open", () => {
    const { container } = render(
      <FacilityMapEmbed latitude={41.99} longitude={21.43} name="Клиника" />,
    );

    const iframe = container.querySelector("iframe");
    expect(iframe).not.toBeNull();
    expect(iframe).toHaveAttribute("referrerpolicy", "no-referrer");
  });
});
