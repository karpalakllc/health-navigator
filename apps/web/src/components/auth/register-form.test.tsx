import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it } from "vitest";
import { RegisterForm } from "@/components/auth/register-form";
import { t } from "@/i18n/t";
import { seriousA11yViolations } from "../../../test/axe";
import {
  mockFetch,
  mockFetchNetworkError,
  requestBody,
} from "../../../test/fetch";

async function fillAndSubmit({
  password = "lozinka12345",
  confirmation,
}: { password?: string; confirmation?: string } = {}) {
  const user = userEvent.setup();
  await user.type(screen.getByLabelText(t("auth.registerName")), "Ана");
  await user.type(screen.getByLabelText(t("auth.email")), "ana@example.mk");
  await user.type(screen.getByLabelText(t("auth.password")), password);
  await user.type(
    screen.getByLabelText(t("auth.registerPasswordConfirm")),
    confirmation ?? password,
  );
  await user.click(screen.getByRole("button", { name: t("auth.register") }));
}

describe("RegisterForm", () => {
  it("suggests a public display name from the name and sends it", async () => {
    const fetch = mockFetch({ status: 202, body: { data: {} } });
    const user = userEvent.setup();
    render(<RegisterForm registrationsEnabled />);

    await user.type(
      screen.getByLabelText(t("auth.registerName")),
      "Марија Костовска",
    );

    const displayName = screen.getByLabelText(t("auth.registerDisplayName"));
    expect(displayName).toHaveValue("Марија К.");
    // The field says, in its description, that it is the public one.
    expect(displayName).toHaveAccessibleDescription(
      t("auth.registerDisplayNameHelp"),
    );

    await user.type(screen.getByLabelText(t("auth.email")), "m@example.mk");
    await user.type(screen.getByLabelText(t("auth.password")), "lozinka12345");
    await user.type(
      screen.getByLabelText(t("auth.registerPasswordConfirm")),
      "lozinka12345",
    );
    await user.click(screen.getByRole("button", { name: t("auth.register") }));

    expect(requestBody(fetch)).toMatchObject({
      name: "Марија Костовска",
      display_name: "Марија К.",
    });
  });

  it("stops following the name once the display name is edited", async () => {
    const user = userEvent.setup();
    render(<RegisterForm registrationsEnabled />);

    const name = screen.getByLabelText(t("auth.registerName"));
    const displayName = screen.getByLabelText(t("auth.registerDisplayName"));

    await user.type(name, "Марија");
    await user.clear(displayName);
    await user.type(displayName, "Мара");
    await user.type(name, " Костовска");

    expect(displayName).toHaveValue("Мара");
  });

  it("shows only the disabled notice when registration is closed", () => {
    render(<RegisterForm registrationsEnabled={false} />);

    expect(screen.getByText(t("auth.registerDisabled"))).toBeInTheDocument();
    expect(screen.queryByRole("textbox")).not.toBeInTheDocument();
  });

  it("labels every field by its visible label alone", () => {
    render(<RegisterForm registrationsEnabled />);

    for (const label of [
      t("auth.registerName"),
      t("auth.registerDisplayName"),
      t("auth.email"),
      t("auth.password"),
      t("auth.registerPasswordConfirm"),
    ]) {
      const field = screen.getByLabelText(label);
      expect(field).toBeRequired();
      // The show/hide button must not leak into the field's name.
      expect(field).toHaveAccessibleName(label);
    }
  });

  it("sends both password fields so the API can check they match", async () => {
    const fetch = mockFetch({ status: 201, body: { data: {} } });
    render(<RegisterForm registrationsEnabled />);

    await fillAndSubmit({
      password: "lozinka12345",
      confirmation: "drugacija999",
    });

    expect(requestBody(fetch)).toEqual({
      name: "Ана",
      display_name: "Ана",
      email: "ana@example.mk",
      password: "lozinka12345",
      password_confirmation: "drugacija999",
    });
  });

  it("surfaces the API's mismatch error as an alert", async () => {
    mockFetch({
      status: 422,
      body: {
        message: "Потврдата на лозинката не се совпаѓа.",
        errors: { password: ["Потврдата на лозинката не се совпаѓа."] },
      },
    });
    render(<RegisterForm registrationsEnabled />);

    await fillAndSubmit({ confirmation: "drugacija999" });

    expect(await screen.findByRole("alert")).toHaveTextContent(
      "Потврдата на лозинката не се совпаѓа.",
    );
  });

  it("falls back to the first password error when there is no message", async () => {
    mockFetch({
      status: 422,
      body: {
        errors: { password: ["Лозинката мора да има најмалку 10 знаци."] },
      },
    });
    render(<RegisterForm registrationsEnabled />);

    await fillAndSubmit({ password: "kratka" });

    expect(await screen.findByRole("alert")).toHaveTextContent(
      "Лозинката мора да има најмалку 10 знаци.",
    );
  });

  it("reports a network failure", async () => {
    mockFetchNetworkError();
    render(<RegisterForm registrationsEnabled />);

    await fillAndSubmit();

    expect(await screen.findByRole("alert")).toHaveTextContent(
      t("auth.registerFailed"),
    );
  });

  it("confirms with the neutral check-your-inbox message", async () => {
    mockFetch({ status: 201, body: { data: { message: "ignored" } } });
    render(<RegisterForm registrationsEnabled />);

    await fillAndSubmit();

    expect(await screen.findByRole("status")).toHaveTextContent(
      t("auth.verifyCheckInboxBody"),
    );
    expect(screen.getByText(t("auth.privacyNote"))).toBeInTheDocument();
    // Nothing that would reveal whether the address was already registered.
    expect(screen.queryByRole("alert")).not.toBeInTheDocument();
    expect(screen.queryByText("ignored")).not.toBeInTheDocument();
  });

  it("marks every field the API rejected, not just the first", async () => {
    mockFetch({
      status: 422,
      body: {
        message: "Е-адресата мора да биде валидна. (и уште 2 грешки)",
        errors: {
          email: ["Е-адресата мора да биде валидна."],
          password: [
            "Лозинката мора да има најмалку 10 знаци.",
            "Потврдата на лозинката не се совпаѓа.",
          ],
          name: ["Името е задолжително."],
        },
      },
    });
    render(<RegisterForm registrationsEnabled />);

    await fillAndSubmit({ password: "kratka" });

    // The summary stays the single alert.
    expect(await screen.findByRole("alert")).toHaveTextContent(
      "(и уште 2 грешки)",
    );

    const expected: Array<[string, string]> = [
      [t("auth.registerName"), "Името е задолжително."],
      [t("auth.email"), "Е-адресата мора да биде валидна."],
      [t("auth.password"), "Лозинката мора да има најмалку 10 знаци."],
    ];
    for (const [label, message] of expected) {
      const field = screen.getByLabelText(label);
      expect(field).toHaveAttribute("aria-invalid", "true");
      // Contains, not equals: fields with help text (the name) keep it in
      // their description alongside the error.
      expect(field).toHaveAccessibleDescription(
        new RegExp(message.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")),
      );
      // The message is not folded into the field's name.
      expect(field).toHaveAccessibleName(label);
    }

    const confirmation = screen.getByLabelText(
      t("auth.registerPasswordConfirm"),
    );
    expect(confirmation).not.toHaveAttribute("aria-invalid");
  });

  it("moves focus to the check-your-inbox heading after registering", async () => {
    mockFetch({ status: 202, body: { data: {} } });
    render(<RegisterForm registrationsEnabled />);

    await fillAndSubmit();

    expect(
      await screen.findByRole("heading", { name: t("auth.verifyCheckInbox") }),
    ).toHaveFocus();
  });

  it("stretches the password fields to the form width", () => {
    render(<RegisterForm registrationsEnabled />);

    for (const label of [
      t("auth.password"),
      t("auth.registerPasswordConfirm"),
    ]) {
      expect(screen.getByLabelText(label).className.split(/\s+/)).toContain(
        "w-full",
      );
    }
  });

  it("has no serious accessibility violations", async () => {
    const { container } = render(<RegisterForm registrationsEnabled />);

    expect(await seriousA11yViolations(container)).toEqual([]);
  });
});
