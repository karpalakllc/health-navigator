import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it } from "vitest";
import { ProfileCorrectionForm } from "@/components/corrections/profile-correction-form";
import { ProfileCorrectionLinks } from "@/components/corrections/profile-correction-links";
import { t } from "@/i18n/t";
import { seriousA11yViolations } from "../../../test/axe";
import { mockFetch, requestBody } from "../../../test/fetch";

describe("ProfileCorrectionForm (correction)", () => {
  it("is accessible, lists the doctor's fields and sends the report", async () => {
    const fetch = mockFetch({
      status: 201,
      body: { data: { status: "received", message: "Ви благодариме." } },
    });
    const user = userEvent.setup();
    const { container } = render(
      <ProfileCorrectionForm
        subject="doctor"
        slug="ana-petrovska"
        type="correction"
      />,
    );

    expect(await seriousA11yViolations(container)).toEqual([]);

    const select = screen.getByLabelText(t("corrections.fieldLabel"), {
      exact: false,
    });
    expect(
      screen.getByRole("option", {
        name: t("corrections.fields.no_longer_practising"),
      }),
    ).toBeInTheDocument();
    expect(
      screen.queryByRole("option", {
        name: t("corrections.fields.departments"),
      }),
    ).toBeNull();

    await user.selectOptions(select, "office_hours");
    await user.type(
      screen.getByLabelText(t("corrections.messageLabel"), { exact: false }),
      "Во петок работи до 14 часот.",
    );
    await user.type(
      screen.getByLabelText(t("corrections.contactLabel"), { exact: false }),
      "pacient@example.test",
    );
    await user.click(
      screen.getByRole("button", { name: t("corrections.submit") }),
    );

    expect(await screen.findByText("Ви благодариме.")).toBeInTheDocument();
    expect(fetch).toHaveBeenCalledWith("/api/corrections", expect.anything());
    expect(requestBody(fetch)).toEqual({
      subject: "doctor",
      slug: "ana-petrovska",
      type: "correction",
      field: "office_hours",
      message: "Во петок работи до 14 часот.",
      contact: "pacient@example.test",
      website: "",
    });
    expect(screen.queryByRole("button")).toBeNull();
  });

  it("checks the choice, the message and the optional e-mail before sending", async () => {
    const fetch = mockFetch({ status: 201, body: { data: {} } });
    const user = userEvent.setup();
    const { container } = render(
      <ProfileCorrectionForm
        subject="facility"
        slug="klinika"
        type="correction"
      />,
    );

    await user.type(
      screen.getByLabelText(t("corrections.contactLabel"), { exact: false }),
      "ne-e-adresa",
    );
    await user.click(
      screen.getByRole("button", { name: t("corrections.submit") }),
    );

    expect(fetch).not.toHaveBeenCalled();
    expect(
      screen.getByText(t("corrections.fieldRequired"), { exact: false }),
    ).toBeInTheDocument();
    expect(
      screen.getByText(t("corrections.messageShort"), { exact: false }),
    ).toBeInTheDocument();
    expect(
      screen.getByText(t("corrections.contactInvalid"), { exact: false }),
    ).toBeInTheDocument();
    expect(
      screen.getByLabelText(t("corrections.fieldLabel"), { exact: false }),
    ).toHaveAttribute("aria-invalid", "true");
    expect(await seriousA11yViolations(container)).toEqual([]);
  });

  it("keeps the honeypot out of reach of people and assistive tech", () => {
    const { container } = render(
      <ProfileCorrectionForm
        subject="doctor"
        slug="ana-petrovska"
        type="correction"
      />,
    );

    const trap = container.querySelector<HTMLInputElement>(
      'input[name="website"]',
    );
    expect(trap).not.toBeNull();
    expect(trap).toHaveAttribute("tabindex", "-1");
    expect(trap?.closest('[aria-hidden="true"]')).not.toBeNull();
  });

  it("says so when the address sent too many", async () => {
    mockFetch({ status: 429, body: { message: "Too Many Attempts." } });
    const user = userEvent.setup();
    render(
      <ProfileCorrectionForm
        subject="doctor"
        slug="ana-petrovska"
        type="correction"
      />,
    );

    await user.selectOptions(
      screen.getByLabelText(t("corrections.fieldLabel"), { exact: false }),
      "name",
    );
    await user.type(
      screen.getByLabelText(t("corrections.messageLabel"), { exact: false }),
      "Презимето е погрешно напишано.",
    );
    await user.click(
      screen.getByRole("button", { name: t("corrections.submit") }),
    );

    expect(await screen.findByRole("alert")).toHaveTextContent(
      t("corrections.throttled"),
    );
  });
});

describe("ProfileCorrectionForm (objection)", () => {
  it("is accessible, asks for a contact and sends no field", async () => {
    const fetch = mockFetch({ status: 201, body: { data: {} } });
    const user = userEvent.setup();
    const { container } = render(
      <ProfileCorrectionForm
        subject="doctor"
        slug="ana-petrovska"
        type="objection"
      />,
    );

    expect(await seriousA11yViolations(container)).toEqual([]);
    expect(
      screen.queryByLabelText(t("corrections.fieldLabel"), { exact: false }),
    ).toBeNull();

    await user.type(
      screen.getByLabelText(t("corrections.objectionMessageLabel"), {
        exact: false,
      }),
      "Јас сум овој лекар и барам отстранување.",
    );
    await user.click(
      screen.getByRole("button", { name: t("corrections.objectionSubmit") }),
    );
    expect(fetch).not.toHaveBeenCalled();
    expect(
      screen.getByText(t("corrections.objectionContactShort"), {
        exact: false,
      }),
    ).toBeInTheDocument();

    await user.type(
      screen.getByLabelText(t("corrections.objectionContactLabel"), {
        exact: false,
      }),
      "070 123 456",
    );
    await user.click(
      screen.getByRole("button", { name: t("corrections.objectionSubmit") }),
    );

    expect(
      await screen.findByText(t("corrections.objectionSent")),
    ).toBeInTheDocument();
    expect(requestBody(fetch)).toMatchObject({
      type: "objection",
      field: null,
      contact: "070 123 456",
    });
  });
});

describe("ProfileCorrectionLinks", () => {
  it("offers a doctor's profile both requests as plain links", () => {
    render(
      <ProfileCorrectionLinks subject="doctor" slug="ana-petrovska">
        <p>между</p>
      </ProfileCorrectionLinks>,
    );

    expect(
      screen.getByRole("link", { name: t("corrections.reportLink") }),
    ).toHaveAttribute("href", "/doctors/ana-petrovska/correction");
    expect(
      screen.getByRole("link", { name: t("corrections.objectionLink") }),
    ).toHaveAttribute("href", "/doctors/ana-petrovska/objection");
    expect(screen.getByText("между")).toBeInTheDocument();
  });

  it("offers a facility only the correction", () => {
    render(<ProfileCorrectionLinks subject="facility" slug="klinika" />);

    expect(
      screen.getByRole("link", { name: t("corrections.reportLink") }),
    ).toHaveAttribute("href", "/facilities/klinika/correction");
    expect(
      screen.queryByRole("link", { name: t("corrections.objectionLink") }),
    ).toBeNull();
  });
});
