import { act, render, screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";
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
  acceptTerms = true,
}: { password?: string; confirmation?: string; acceptTerms?: boolean } = {}) {
  const user = userEvent.setup();
  await user.type(screen.getByLabelText(t("auth.registerName")), "Ана");
  await user.type(screen.getByLabelText(t("auth.email")), "ana@example.mk");
  await user.type(screen.getByLabelText(t("auth.password")), password);
  await user.type(
    screen.getByLabelText(t("auth.registerPasswordConfirm")),
    confirmation ?? password,
  );
  if (acceptTerms) {
    await user.click(screen.getByRole("checkbox"));
  }
  // Last, so the submit (not the debounced availability check) is the
  // first request the tests inspect.
  await user.type(screen.getByLabelText(t("usernames.label")), "ana_bt");
  await user.click(screen.getByRole("button", { name: t("auth.register") }));
}

describe("RegisterForm", () => {
  it("asks for a public username, separately from the private name", () => {
    render(<RegisterForm registrationsEnabled />);

    const username = screen.getByLabelText(t("usernames.label"));
    expect(username).toHaveValue("");
    expect(username).toHaveAccessibleDescription(
      new RegExp(t("usernames.registerHelp").slice(0, 40)),
    );
    expect(
      screen.getByLabelText(t("auth.registerName")),
    ).toHaveAccessibleDescription(t("auth.registerNameHelp"));
  });

  it("checks the username's format before sending", async () => {
    const fetch = mockFetch({ status: 202, body: { data: {} } });
    const user = userEvent.setup();
    render(<RegisterForm registrationsEnabled />);

    await user.type(screen.getByLabelText(t("usernames.label")), "Аdmin");
    await user.click(screen.getByRole("button", { name: t("auth.register") }));

    expect(fetch).not.toHaveBeenCalled();
    expect(
      screen.getByLabelText(t("usernames.label")),
    ).toHaveAccessibleDescription(new RegExp(t("usernames.errorMixedScript")));
  });

  it("says quietly whether the username is free once typing pauses", async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
    const fetch = mockFetch({
      status: 200,
      body: {
        data: {
          available: false,
          message: "Ова корисничко име веќе се користи.",
        },
      },
    });
    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime });
    render(<RegisterForm registrationsEnabled />);

    await user.type(screen.getByLabelText(t("usernames.label")), "Marija.Bt");
    expect(fetch).not.toHaveBeenCalled();

    await act(async () => {
      await vi.advanceTimersByTimeAsync(500);
    });

    expect(fetch).toHaveBeenCalledTimes(1);
    expect(String(fetch.mock.calls[0][0])).toBe(
      "/api/usernames/availability?username=Marija.Bt",
    );
    const status = await screen.findByText(
      "Ова корисничко име веќе се користи.",
    );
    expect(status.closest('[role="status"]')).toBeInTheDocument();

    // Submitting a name already known to be taken stops at the field.
    await user.click(screen.getByRole("button", { name: t("auth.register") }));
    expect(fetch).toHaveBeenCalledTimes(1);
    expect(screen.getByLabelText(t("usernames.label"))).toHaveAttribute(
      "aria-invalid",
      "true",
    );
    vi.useRealTimers();
  });

  it("requires the 14+ and terms checkbox, with links to both texts", async () => {
    const fetch = mockFetch({ status: 202, body: { data: {} } });
    render(<RegisterForm registrationsEnabled />);

    const consent = screen.getByRole("checkbox");
    expect(consent).toBeRequired();
    expect(consent).toHaveAccessibleName(
      new RegExp(t("usernames.termsBefore").trim()),
    );
    expect(
      screen.getByRole("link", { name: new RegExp(t("usernames.termsLink")) }),
    ).toHaveAttribute("href", "/terms");
    expect(
      screen.getByRole("link", {
        name: new RegExp(t("usernames.privacyLink")),
      }),
    ).toHaveAttribute("href", "/privacy");

    await fillAndSubmit({ acceptTerms: false });

    expect(fetch).not.toHaveBeenCalled();
    expect(consent).toHaveAttribute("aria-invalid", "true");
    expect(
      within(screen.getByRole("alert")).getByRole("link", {
        name: t("usernames.termsSummaryLabel"),
      }),
    ).toHaveAttribute("href", `#${consent.id}`);
  });

  it("shows the API's username refusal under the field", async () => {
    mockFetch({
      status: 422,
      body: {
        message: "Ова корисничко име не е дозволено.",
        errors: { username: ["Ова корисничко име не е дозволено."] },
      },
    });
    render(<RegisterForm registrationsEnabled />);

    await fillAndSubmit();

    expect(await screen.findByRole("alert")).toHaveTextContent(
      "Ова корисничко име не е дозволено.",
    );
    expect(
      screen.getByLabelText(t("usernames.label")),
    ).toHaveAccessibleDescription(/Ова корисничко име не е дозволено\./);
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
      t("usernames.label"),
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

  it("sends both password fields so the API can check them too", async () => {
    const fetch = mockFetch({ status: 201, body: { data: {} } });
    render(<RegisterForm registrationsEnabled />);

    await fillAndSubmit({ password: "lozinka12345" });

    expect(requestBody(fetch)).toEqual({
      name: "Ана",
      username: "ana_bt",
      email: "ana@example.mk",
      password: "lozinka12345",
      password_confirmation: "lozinka12345",
      accept_terms: true,
      // The solved ALTCHA challenge (test/altcha.ts) goes along.
      altcha: "test-altcha-1",
    });
  });

  it("catches a mismatched confirmation before sending, under that field", async () => {
    const fetch = mockFetch({ status: 202, body: { data: {} } });
    render(<RegisterForm registrationsEnabled />);

    await fillAndSubmit({ confirmation: "drugacija999" });

    expect(fetch).not.toHaveBeenCalled();
    const confirmation = screen.getByLabelText(
      t("auth.registerPasswordConfirm"),
    );
    expect(confirmation).toHaveAttribute("aria-invalid", "true");
    expect(confirmation).toHaveAccessibleDescription(
      `${t("ui.errorPrefix")} ${t("auth.passwordMismatch")}`,
    );
    expect(screen.getByLabelText(t("auth.password"))).not.toHaveAttribute(
      "aria-invalid",
    );
    expect(screen.getByRole("alert")).toHaveFocus();
  });

  it("checks required fields and the e-mail shape in Macedonian, not with browser bubbles", async () => {
    const fetch = mockFetch({ status: 202, body: { data: {} } });
    const user = userEvent.setup();
    const { container } = render(<RegisterForm registrationsEnabled />);

    expect(container.querySelector("form")).toHaveAttribute("novalidate");

    await user.type(screen.getByLabelText(t("auth.email")), "ana@");
    await user.click(screen.getByRole("button", { name: t("auth.register") }));

    expect(fetch).not.toHaveBeenCalled();
    expect(
      screen.getByLabelText(t("auth.registerName")),
    ).toHaveAccessibleDescription(new RegExp(t("ui.fieldRequired")));
    expect(screen.getByLabelText(t("auth.email"))).toHaveAccessibleDescription(
      `${t("ui.errorPrefix")} ${t("ui.emailInvalid")}`,
    );
    // The summary names all four rejected fields.
    const summary = screen.getByRole("alert");
    for (const label of [
      t("auth.registerName"),
      t("auth.email"),
      t("auth.password"),
      t("auth.registerPasswordConfirm"),
    ]) {
      expect(
        within(summary).getByRole("link", { name: label }),
      ).toBeInTheDocument();
    }
  });

  it("files the API's „confirmed“ password error under the confirmation field", async () => {
    mockFetch({
      status: 422,
      body: {
        message: "Потврдата на полето лозинка не се совпаѓа.",
        errors: { password: ["Потврдата на полето лозинка не се совпаѓа."] },
      },
    });
    render(<RegisterForm registrationsEnabled />);

    await fillAndSubmit();

    expect(await screen.findByRole("alert")).toHaveTextContent(
      t("auth.passwordMismatch"),
    );
    expect(
      screen.getByLabelText(t("auth.registerPasswordConfirm")),
    ).toHaveAttribute("aria-invalid", "true");
    expect(screen.getByLabelText(t("auth.password"))).not.toHaveAttribute(
      "aria-invalid",
    );
  });

  it("says the password rules in its own words when the API rejects the password", async () => {
    mockFetch({
      status: 422,
      body: {
        errors: {
          password: ["Полето лозинка мора да има најмалку 10 знаци."],
        },
      },
    });
    render(<RegisterForm registrationsEnabled />);

    await fillAndSubmit({ password: "kratka" });

    expect(await screen.findByRole("alert")).toHaveTextContent(
      t("auth.passwordTooWeak"),
    );
  });

  it("keeps the API's message when the rejection names no field", async () => {
    mockFetch({
      status: 429,
      body: { message: "Премногу обиди. Обидете се подоцна." },
    });
    render(<RegisterForm registrationsEnabled />);

    await fillAndSubmit();

    expect(await screen.findByRole("alert")).toHaveTextContent(
      "Премногу обиди. Обидете се подоцна.",
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

    // The summary is the single alert, in our words, not Laravel's
    // "… (и уште 2 грешки)".
    const summary = await screen.findByRole("alert");
    expect(summary).toHaveTextContent(t("ui.errorSummaryTitle"));
    expect(summary).not.toHaveTextContent("(и уште 2 грешки)");

    // The „confirmed“ error filed under `password` lands on the
    // confirmation field; the length error stays on the password.
    const expected: Array<[string, string]> = [
      [t("auth.registerName"), t("ui.fieldRequired")],
      [t("auth.email"), t("ui.emailInvalid")],
      [t("auth.password"), t("auth.passwordTooWeak")],
      [t("auth.registerPasswordConfirm"), t("auth.passwordMismatch")],
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
      // And the summary repeats it, linked to the field.
      expect(
        within(summary).getByRole("link", { name: label }),
      ).toHaveAttribute("href", `#${field.id}`);
    }
    expect(screen.getByLabelText(t("usernames.label"))).not.toHaveAttribute(
      "aria-invalid",
    );
  });

  it("links every rejected field from the error summary", async () => {
    mockFetch({
      status: 422,
      body: {
        message: "Е-адресата мора да биде валидна. (и уште 1 грешка)",
        errors: {
          email: ["Е-адресата мора да биде валидна."],
          password: ["Лозинката мора да има најмалку 10 знаци."],
        },
      },
    });
    const user = userEvent.setup();
    render(<RegisterForm registrationsEnabled />);

    await fillAndSubmit({ password: "kratka" });
    await screen.findByRole("alert");

    // In form order, named by the field's label, pointing at the field.
    const email = screen.getByRole("link", { name: t("auth.email") });
    const password = screen.getByRole("link", { name: t("auth.password") });
    expect(email).toHaveAttribute(
      "href",
      `#${screen.getByLabelText(t("auth.email")).id}`,
    );
    expect(
      email.compareDocumentPosition(password) &
        Node.DOCUMENT_POSITION_FOLLOWING,
    ).toBeTruthy();

    await user.click(password);
    expect(screen.getByLabelText(t("auth.password"))).toHaveFocus();
  });

  it("lists only the fields that failed, and takes focus", async () => {
    mockFetch({
      status: 422,
      body: {
        message: "Е-адресата мора да биде валидна.",
        errors: { email: ["Е-адресата мора да биде валидна."] },
      },
    });
    render(<RegisterForm registrationsEnabled />);

    await fillAndSubmit();
    const summary = await screen.findByRole("alert");

    expect(within(summary).getAllByRole("link")).toHaveLength(1);
    expect(
      within(summary).getByRole("link", { name: t("auth.email") }),
    ).toBeInTheDocument();
    expect(summary).toHaveFocus();
  });

  it("states the password rules up front", () => {
    render(<RegisterForm registrationsEnabled />);

    expect(
      screen.getByLabelText(t("auth.password")),
    ).toHaveAccessibleDescription(t("auth.passwordRules"));
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
