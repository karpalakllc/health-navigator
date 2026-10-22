import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it } from "vitest";
import {
  ProfileHeader,
  ProfileMetaItem,
} from "@/components/directory/profile-header";
import { VerificationBadge } from "@/components/directory/verification-badge";
import { Tag } from "@/components/ui/tag";
import type { Verification } from "@/lib/api/types";
import { t } from "@/i18n/t";
import { seriousA11yViolations } from "../../../test/axe";

const verified: Verification = {
  status: "verified",
  basis: "official_registers",
  basis_label: "Регистар на ФЗОМ и Лекарска комора",
};

const LONG_NAME = "д-р Александра Петковска-Трајковска";

function doctorHeader(
  props: Partial<React.ComponentProps<typeof ProfileHeader>> = {},
) {
  return render(
    <ProfileHeader
      kind="doctor"
      avatarUrl={null}
      name={LONG_NAME}
      subtitle="Интерна медицина · Кардиологија"
      meta={
        <>
          <ProfileMetaItem icon="map-pin">Скопје</ProfileMetaItem>
          <ProfileMetaItem icon="building">
            {t("doctors.noFacilities")}
          </ProfileMetaItem>
        </>
      }
      summary={{ count: 0, average_rating: null }}
      verification={<VerificationBadge verification={verified} kind="doctor" />}
      reportAction={
        <button type="button" aria-label={t("profileReports.action")} />
      }
      tags={
        <>
          <Tag tone="care" icon="check">
            {t("doctors.acceptingPatients")}
          </Tag>
          <Tag icon="shield-check">{t("doctors.licenceValid")}</Tag>
        </>
      }
      {...props}
    />,
  );
}

describe("ProfileHeader (compact mobile hero)", () => {
  it("puts the name beside the avatar, with the specialty and city under it", () => {
    const { container } = doctorHeader();
    const title = container.querySelector(
      '[data-slot="profile-title"]',
    ) as HTMLElement;

    const h1 = screen.getByRole("heading", { level: 1, name: LONG_NAME });
    expect(screen.getAllByRole("heading", { level: 1 })).toHaveLength(1);
    expect(title).toContainElement(h1);
    expect(title).toHaveTextContent(
      `${LONG_NAME}Интерна медицина · КардиологијаСкопје${t("doctors.noFacilities")}`,
    );
    // Avatar first, then the title block: its sibling in the same grid row.
    expect(title.previousElementSibling).toHaveTextContent("АП");
    // Long Cyrillic names wrap instead of overflowing.
    expect(h1.className).toContain("break-words");
    expect(title.className).toContain("min-w-0");
  });

  it("gathers verification, patients and licence into one wrapping chip row", () => {
    const { container } = doctorHeader();
    const chips = container.querySelector(
      '[data-slot="profile-chips"]',
    ) as HTMLElement;

    expect(chips.className).toContain("profile-chips");
    expect(chips.className).toContain("flex-wrap");
    expect(chips).toHaveTextContent(t("verification.verified"));
    expect(chips).toHaveTextContent(t("doctors.acceptingPatients"));
    expect(chips).toHaveTextContent(t("doctors.licenceValid"));
    // The badge's toggletip stays a real button inside the row.
    expect(
      screen.getByRole("button", { name: t("verification.whyVerified") }),
    ).toBeInTheDocument();
  });

  it("keeps the report flag in the card's top-right corner, after the title in reading order", () => {
    const { container } = doctorHeader();
    const slot = container.querySelector(
      '[data-slot="profile-actions"]',
    ) as HTMLElement;
    const title = container.querySelector('[data-slot="profile-title"]')!;

    expect(slot.className).toMatch(/absolute right-1 top-1/);
    expect(slot).toContainElement(
      screen.getByRole("button", { name: t("profileReports.action") }),
    );
    expect(
      title.compareDocumentPosition(slot) & Node.DOCUMENT_POSITION_FOLLOWING,
    ).toBeTruthy();
    // Room for the 44 px flag beside the name.
    expect(title.className).toContain("pr-9");
  });

  it("draws the coral edge without clipping the card, so the toggletip can open past it", () => {
    const { container } = doctorHeader();
    const card = container.firstElementChild as HTMLElement;

    expect(card.className).not.toContain("card-edge");
    expect(
      card.querySelector('[data-slot="profile-edge"]'),
    ).toBeInTheDocument();
  });

  it("spans the desktop avatar over only the rows that exist", () => {
    const { container } = doctorHeader();
    const grid = container.querySelector('[data-slot="profile-title"]')!
      .parentElement as HTMLElement;

    // Title + chips; no rating, no details.
    expect(grid.style.getPropertyValue("--hero-rows")).toBe("2");
  });

  it("puts a facility's logo beside its name under the cover", () => {
    const { container } = render(
      <ProfileHeader
        kind="facility"
        avatarUrl={null}
        cover={{ url: null }}
        name="Естетик Дентамед"
        subtitle="Приватна клиника · Скопје"
        summary={{ count: 0, average_rating: null }}
      />,
    );
    const title = container.querySelector('[data-slot="profile-title"]')!;

    expect(container.querySelector("[data-cover]")).toBeInTheDocument();
    expect(title.previousElementSibling).toHaveAttribute("data-cover-logo");
    expect(
      container.querySelector('[data-slot="profile-chips"]'),
    ).not.toBeInTheDocument();
  });

  it("has no serious axe violations, with the toggletip closed or open", async () => {
    const user = userEvent.setup();
    const { container } = doctorHeader();

    expect(await seriousA11yViolations(container)).toEqual([]);
    await user.click(
      screen.getByRole("button", { name: t("verification.whyVerified") }),
    );
    expect(await seriousA11yViolations(container)).toEqual([]);
  });
});
