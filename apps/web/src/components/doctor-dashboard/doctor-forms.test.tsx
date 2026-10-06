import { render, screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it } from "vitest";
import { ChangeList } from "@/components/doctor-dashboard/change-list";
import { DoctorChangeRequestForm } from "@/components/doctor-dashboard/doctor-change-request-form";
import { DoctorClaimForm } from "@/components/doctor-dashboard/doctor-claim-form";
import { DoctorPracticeForm } from "@/components/doctor-dashboard/doctor-practice-form";
import { OfficialResponse } from "@/components/reviews/review-list";
import type {
  DoctorDashboard,
  ManagedDoctor,
} from "@/lib/api/doctor-dashboard-types";
import { t, tFormat } from "@/i18n/t";
import { seriousA11yViolations } from "../../../test/axe";
import { mockFetch, requestBody } from "../../../test/fetch";
import { router } from "../../../test/next-navigation";

const doctor: ManagedDoctor = {
  slug: "ana-petrovska",
  is_published: true,
  full_name: "д-р Ана Петровска",
  title: "д-р",
  subspecialty: null,
  education: null,
  years_experience: 12,
  city: "Скопје",
  bio: "Кардиолог.",
  phone: "02 111 111",
  email: null,
  consultation_fee_note: null,
  accepts_new_patients: true,
  office_hours: { Пон: "08:00–14:00" },
  avatar_url: null,
  specialties: [{ id: 1, name: "Кардиологија", is_primary: true }],
  facilities: [],
  language_ids: [1],
  clinical_interest_ids: [],
  procedure_ids: [],
  linked_at: null,
};

const options: DoctorDashboard["options"] = {
  specialties: [
    { id: 1, name: "Кардиологија" },
    { id: 2, name: "Интерна медицина" },
  ],
  languages: [
    { id: 1, name: "Македонски" },
    { id: 2, name: "Англиски" },
  ],
  clinical_interests: [],
  procedures: [{ id: 5, name: "ЕКГ" }],
  facilities: [{ id: 9, name: "Клиника Центар", city: "Скопје" }],
  days: ["Пон", "Вто", "Сре", "Чет", "Пет", "Саб", "Нед"],
};

describe("DoctorPracticeForm", () => {
  it("saves the practice details at once and nothing staff-controlled", async () => {
    const fetch = mockFetch({ status: 200, body: { data: {} } });
    const user = userEvent.setup();
    const { container } = render(
      <DoctorPracticeForm doctor={doctor} options={options} />,
    );

    expect(await seriousA11yViolations(container)).toEqual([]);

    const phone = screen.getByLabelText(t("doctorDashboard.phone"));
    await user.clear(phone);
    await user.type(phone, "070 123 456");
    await user.type(
      screen.getByLabelText(
        tFormat("doctorDashboard.dayHours", { day: "Пет" }),
      ),
      "12:00–18:00",
    );
    await user.click(screen.getByLabelText("Англиски"));
    await user.click(
      screen.getByRole("button", { name: t("doctorDashboard.savePractice") }),
    );

    expect(fetch.mock.calls[0]?.[0]).toBe("/api/doctor-dashboard/profile");
    expect(fetch.mock.calls[0]?.[1]?.method).toBe("PATCH");
    const body = requestBody(fetch);
    expect(body).toMatchObject({
      phone: "070 123 456",
      office_hours: { Пон: "08:00–14:00", Пет: "12:00–18:00" },
      language_ids: [1, 2],
      accepts_new_patients: true,
    });
    for (const key of [
      "full_name",
      "slug",
      "is_featured",
      "is_sponsored",
      "is_published",
    ]) {
      expect(body).not.toHaveProperty(key);
    }
    expect(
      await screen.findByText(t("doctorDashboard.practiceSaved")),
    ).toBeInTheDocument();
    expect(router.refresh).toHaveBeenCalled();
  });

  it("shows the API's field error next to the field", async () => {
    mockFetch({
      status: 422,
      body: {
        message: "Телефонот не е валиден.",
        errors: { phone: ["Телефонот не е валиден."] },
      },
    });
    const user = userEvent.setup();
    render(<DoctorPracticeForm doctor={doctor} options={options} />);

    await user.click(
      screen.getByRole("button", { name: t("doctorDashboard.savePractice") }),
    );

    expect(
      await screen.findByLabelText(t("doctorDashboard.phone")),
    ).toHaveAccessibleDescription(/Телефонот не е валиден\./);
  });
});

describe("DoctorChangeRequestForm", () => {
  it("sends the sensitive fields for review", async () => {
    const fetch = mockFetch({ status: 201, body: { data: {} } });
    const user = userEvent.setup();
    const { container } = render(
      <DoctorChangeRequestForm doctor={doctor} options={options} />,
    );

    expect(await seriousA11yViolations(container)).toEqual([]);

    const name = screen.getByLabelText(t("doctorDashboard.fullName"), {
      exact: false,
    });
    await user.clear(name);
    await user.type(name, "д-р Ана Петровска-Ристовска");
    await user.click(screen.getByLabelText("Интерна медицина"));
    await user.selectOptions(
      screen.getByLabelText(t("doctorDashboard.primarySpecialty")),
      "2",
    );
    await user.click(
      screen.getByRole("button", { name: t("doctorDashboard.submitRequest") }),
    );

    expect(fetch.mock.calls[0]?.[0]).toBe(
      "/api/doctor-dashboard/change-requests",
    );
    expect(requestBody(fetch)).toMatchObject({
      full_name: "д-р Ана Петровска-Ристовска",
      specialty_ids: [1, 2],
      primary_specialty_id: 2,
      years_experience: 12,
    });
    expect(
      await screen.findByText(t("doctorDashboard.requestSent")),
    ).toBeInTheDocument();
  });

  it("says so when nothing differs from the profile", async () => {
    mockFetch({
      status: 422,
      body: { message: "Нема промени.", code: "doctor_account.no_changes" },
    });
    const user = userEvent.setup();
    render(<DoctorChangeRequestForm doctor={doctor} options={options} />);

    await user.click(
      screen.getByRole("button", { name: t("doctorDashboard.submitRequest") }),
    );

    expect(await screen.findByRole("alert")).toHaveTextContent(
      t("doctorDashboard.noChanges"),
    );
  });
});

describe("ChangeList", () => {
  it("names each field with its current and requested value", () => {
    render(
      <ChangeList
        changes={{
          full_name: { old: "д-р Ана", new: "д-р Ана Р." },
          specialties: {
            old: [{ id: 1, name: "Кардиологија", is_primary: true }],
            new: [],
          },
        }}
      />,
    );

    const name = screen
      .getByText(t("doctorDashboard.fullName"))
      .closest("div") as HTMLElement;
    expect(within(name).getByText(/д-р Ана Р\./)).toBeVisible();
    expect(
      screen.getByText(`Кардиологија (${t("doctorDashboard.primaryMark")})`, {
        exact: false,
      }),
    ).toBeVisible();
  });
});

describe("DoctorClaimForm", () => {
  it("checks the fields before sending and announces the request", async () => {
    const fetch = mockFetch({ status: 201, body: { data: {} } });
    const user = userEvent.setup();
    const { container } = render(<DoctorClaimForm slug="ana-petrovska" />);

    expect(await seriousA11yViolations(container)).toEqual([]);

    await user.click(
      screen.getByRole("button", { name: t("doctorDashboard.claimSubmit") }),
    );
    expect(fetch).not.toHaveBeenCalled();
    expect(
      screen.getByLabelText(t("doctorDashboard.claimContact"), {
        exact: false,
      }),
    ).toHaveAccessibleDescription(/Внесете телефон или е-адреса\./);

    await user.type(
      screen.getByLabelText(t("doctorDashboard.claimMessage"), {
        exact: false,
      }),
      "Јас сум д-р Ана Петровска.",
    );
    await user.type(
      screen.getByLabelText(t("doctorDashboard.claimContact"), {
        exact: false,
      }),
      "070 123 456",
    );
    await user.click(
      screen.getByRole("button", { name: t("doctorDashboard.claimSubmit") }),
    );

    expect(requestBody(fetch)).toEqual({
      slug: "ana-petrovska",
      message: "Јас сум д-р Ана Петровска.",
      contact: "070 123 456",
    });
    expect(await screen.findByRole("status")).toHaveTextContent(
      t("doctorDashboard.claimSent"),
    );
  });
});

describe("OfficialResponse", () => {
  it("labels the doctor's own reply apart from a staff-entered one", () => {
    const { rerender } = render(
      <OfficialResponse
        response={{
          body: "Благодарам.",
          responder_name: "д-р Ана Петровска",
          responded_at: null,
          source: "doctor",
        }}
      />,
    );

    expect(screen.getByText(t("doctorDashboard.publicLabel"))).toBeVisible();
    expect(
      screen.getByRole("heading", { name: "д-р Ана Петровска" }),
    ).toBeVisible();

    rerender(
      <OfficialResponse
        response={{
          body: "Благодариме.",
          responder_name: "д-р Ана Петровска",
          responded_at: null,
          source: "staff",
        }}
      />,
    );

    expect(screen.queryByText(t("doctorDashboard.publicLabel"))).toBeNull();
    expect(
      screen.getByRole("heading", {
        name: tFormat("reviewResponse.title", { name: "д-р Ана Петровска" }),
      }),
    ).toBeVisible();
  });
});
