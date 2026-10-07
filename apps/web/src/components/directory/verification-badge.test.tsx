import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it } from "vitest";
import { DoctorCard } from "@/components/directory/doctor-card";
import { FacilityCard } from "@/components/directory/facility-card";
import { PharmacyCard } from "@/components/directory/pharmacy-card";
import { ProfileHeader } from "@/components/directory/profile-header";
import {
  VERIFICATION_MORE_HREF,
  VerificationBadge,
} from "@/components/directory/verification-badge";
import type {
  DoctorListItem,
  FacilityListItem,
  PharmacyListItem,
  Verification,
} from "@/lib/api/types";
import { t } from "@/i18n/t";
import { seriousA11yViolations } from "../../../test/axe";

const verified: Verification = {
  status: "verified",
  basis: "official_registers",
  basis_label: "Регистар на ФЗОМ и Лекарска комора",
};
const unverified: Verification = {
  status: "unverified",
  basis: null,
  basis_label: null,
};

describe("VerificationBadge", () => {
  it.each([
    ["doctor", verified, "verification.verified", "verification.whyVerified"],
    [
      "doctor",
      unverified,
      "verification.unverified",
      "verification.whyUnverified",
    ],
    [
      "facility",
      verified,
      "verification.verifiedFeminine",
      "verification.whyVerifiedFeminine",
    ],
    [
      "facility",
      unverified,
      "verification.unverifiedFeminine",
      "verification.whyUnverifiedFeminine",
    ],
    [
      "pharmacy",
      verified,
      "verification.verifiedFeminine",
      "verification.whyVerifiedFeminine",
    ],
    [
      "pharmacy",
      unverified,
      "verification.unverifiedFeminine",
      "verification.whyUnverifiedFeminine",
    ],
  ] as const)(
    "%s, %o: the right gender form and a named toggletip button",
    (kind, verification, label, why) => {
      render(<VerificationBadge verification={verification} kind={kind} />);

      expect(screen.getByText(t(label))).toBeInTheDocument();
      expect(screen.getByRole("button", { name: t(why) })).toHaveAttribute(
        "aria-expanded",
        "false",
      );
    },
  );

  it("explains a verified profile with its public basis and links to the transparency section", async () => {
    const user = userEvent.setup();
    render(<VerificationBadge verification={verified} kind="doctor" />);

    await user.click(
      screen.getByRole("button", { name: t("verification.whyVerified") }),
    );

    const panel = screen.getByRole("status");
    // The basis names the source once; no second sentence about sources.
    expect(panel).toHaveTextContent(
      "Податоците се потврдени. Основа: Регистар на ФЗОМ и Лекарска комора.",
    );
    expect(panel).not.toHaveTextContent(t("verification.verifiedInfo"));
    expect(
      screen.getByRole("link", { name: t("integrity.disclosureMore") }),
    ).toHaveAttribute("href", VERIFICATION_MORE_HREF);
  });

  it.each([
    ["doctor", "verification.verifiedInfo"],
    ["facility", "verification.verifiedInfoFacility"],
    ["pharmacy", "verification.verifiedInfoPharmacy"],
  ] as const)(
    "without a public basis, a %s names its own sources",
    async (kind, info) => {
      const user = userEvent.setup();
      render(
        <VerificationBadge
          verification={{ ...verified, basis_label: null }}
          kind={kind}
        />,
      );

      await user.click(screen.getByRole("button"));

      expect(screen.getByRole("status")).toHaveTextContent(t(info));
    },
  );

  it("uses the short feminine forms for places, with no noun", () => {
    expect(t("verification.verifiedFeminine")).toBe("Верификувана");
    expect(t("verification.unverifiedFeminine")).toBe("Неверификувана");
    expect(t("verification.verifiedInfoPharmacy")).not.toMatch(
      /ФЗОМ|Министерство/,
    );
  });

  it("explains an unverified profile calmly, from the keyboard", async () => {
    const user = userEvent.setup();
    render(<VerificationBadge verification={unverified} kind="facility" />);

    await user.tab();
    await user.keyboard("{Enter}");

    expect(screen.getByRole("status")).toHaveTextContent(
      t("verification.unverifiedInfo"),
    );
    expect(screen.getByRole("status")).not.toHaveTextContent("Основа");
  });

  it("renders nothing when the payload has no status", () => {
    const { container } = render(
      <VerificationBadge verification={undefined} kind="doctor" />,
    );

    expect(container).toBeEmptyDOMElement();
  });

  it("has no serious axe violations, open or closed", async () => {
    const user = userEvent.setup();
    const { container } = render(
      <>
        <VerificationBadge verification={verified} kind="doctor" />
        <VerificationBadge verification={unverified} kind="pharmacy" />
      </>,
    );

    expect(await seriousA11yViolations(container)).toEqual([]);
    await user.click(
      screen.getByRole("button", { name: t("verification.whyVerified") }),
    );
    expect(await seriousA11yViolations(container)).toEqual([]);
  });
});

describe("verification on cards and the profile header", () => {
  const doctor: DoctorListItem = {
    slug: "ana",
    full_name: "д-р Ана Петрова",
    title: null,
    subspecialty: null,
    city: "Скопје",
    avatar_url: null,
    years_experience: null,
    accepts_new_patients: false,
    is_featured: false,
    is_sponsored: false,
    primary_specialty: null,
    specialties: [],
    primary_facility: null,
    review_summary: { count: 0, average_rating: null },
    verification: verified,
  };

  it("shows the badge on doctor, facility and pharmacy cards", () => {
    render(
      <>
        <DoctorCard doctor={doctor} />
        <FacilityCard
          facility={
            {
              slug: "klinika",
              name: "Клиника Здравје",
              type: "clinic",
              city: "Битола",
              avatar_url: null,
              cover_url: null,
              phone: null,
              has_emergency_services: false,
              is_featured: false,
              departments_count: 0,
              review_summary: { count: 0, average_rating: null },
              verification: unverified,
            } as FacilityListItem
          }
        />
        <PharmacyCard
          pharmacy={
            {
              slug: "apteka",
              name: "Аптека Липа",
              city: "Охрид",
              avatar_url: null,
              cover_url: null,
              phone: null,
              review_summary: { count: 0, average_rating: null },
              verification: verified,
            } as PharmacyListItem
          }
        />
      </>,
    );

    expect(screen.getByText(t("verification.verified"))).toBeInTheDocument();
    expect(
      screen.getByText(t("verification.unverifiedFeminine")),
    ).toBeInTheDocument();
    expect(
      screen.getByText(t("verification.verifiedFeminine")),
    ).toBeInTheDocument();
  });

  it("puts the badge in the profile title row, with a slot for the profile's actions", () => {
    render(
      <ProfileHeader
        kind="doctor"
        avatarUrl={null}
        name="д-р Ана Петрова"
        summary={{ count: 0, average_rating: null }}
        verification={
          <VerificationBadge verification={verified} kind="doctor" />
        }
        reportAction={<button type="button">Пријави профил</button>}
      />,
    );

    const heading = screen.getByRole("heading", {
      level: 1,
      name: "д-р Ана Петрова",
    });
    const row = heading.parentElement as HTMLElement;
    expect(row).toContainElement(screen.getByText(t("verification.verified")));
    const slot = row.querySelector('[data-slot="profile-actions"]');
    expect(slot).toContainElement(
      screen.getByRole("button", { name: "Пријави профил" }),
    );
  });
});
