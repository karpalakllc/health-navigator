import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it } from "vitest";
import { ForgotPasswordForm } from "@/components/auth/forgot-password-form";
import { t } from "@/i18n/t";
import { seriousA11yViolations } from "../../../test/axe";
import {
  mockFetch,
  mockFetchNetworkError,
  requestBody,
} from "../../../test/fetch";

async function submit(email = "ana@example.mk") {
  const user = userEvent.setup();
  await user.type(screen.getByLabelText(t("auth.email")), email);
  await user.click(
    screen.getByRole("button", { name: t("auth.forgotPasswordSubmit") }),
  );
}

describe("ForgotPasswordForm", () => {
  it("requires an email address", async () => {
    const fetch = mockFetch({ status: 200, body: {} });
    render(<ForgotPasswordForm />);

    expect(screen.getByLabelText(t("auth.email"))).toBeRequired();
    await userEvent
      .setup()
      .click(
        screen.getByRole("button", { name: t("auth.forgotPasswordSubmit") }),
      );

    expect(fetch).not.toHaveBeenCalled();
    // In Macedonian under the field (noValidate: no browser bubble).
    expect(screen.getByLabelText(t("auth.email"))).toHaveAccessibleDescription(
      `${t("ui.errorPrefix")} ${t("ui.emailRequired")}`,
    );
  });

  it("rejects a malformed address before sending", async () => {
    const fetch = mockFetch({ status: 200, body: {} });
    render(<ForgotPasswordForm />);

    await submit("ana@");

    expect(fetch).not.toHaveBeenCalled();
    expect(screen.getByLabelText(t("auth.email"))).toHaveAttribute(
      "aria-invalid",
      "true",
    );
  });

  it("keeps an empty status region mounted before anything is sent", () => {
    render(<ForgotPasswordForm />);

    // A polite region inserted already holding its text is often not
    // announced; it has to exist before the confirmation fills it.
    expect(screen.getByRole("status")).toBeEmptyDOMElement();
  });

  it("confirms with the same neutral message for any address", async () => {
    const fetch = mockFetch({
      status: 200,
      body: { data: { message: "We have emailed your password reset link." } },
    });
    render(<ForgotPasswordForm />);
    const region = screen.getByRole("status");

    await submit("nepostoecka@example.mk");

    expect(await screen.findByText(t("auth.forgotPasswordSuccess"))).toBe(
      region,
    );
    expect(screen.getByText(t("auth.privacyNote"))).toBeInTheDocument();
    expect(screen.queryByRole("alert")).not.toBeInTheDocument();
    // The API's own wording is not echoed (it could differ per address).
    expect(screen.queryByText(/emailed/)).not.toBeInTheDocument();
    expect(requestBody(fetch)).toEqual({ email: "nepostoecka@example.mk" });
    expect(
      screen.getByRole("link", { name: t("auth.backToLogin") }),
    ).toHaveAttribute("href", "/login");
  });

  it.each([
    [
      429,
      { message: "Премногу барања. Почекајте." },
      "Премногу барања. Почекајте.",
    ],
    [
      422,
      { errors: { email: ["Внесете валидна е-пошта."] } },
      "Внесете валидна е-пошта.",
    ],
    [500, {}, t("auth.forgotPasswordFailed")],
  ])(
    "shows a %i failure as an alert, not as success",
    async (status, body, text) => {
      mockFetch({ status, body });
      render(<ForgotPasswordForm />);

      await submit();

      expect(await screen.findByRole("alert")).toHaveTextContent(text);
      expect(screen.getByRole("status")).toBeEmptyDOMElement();
    },
  );

  it("reports a network failure", async () => {
    mockFetchNetworkError();
    render(<ForgotPasswordForm />);

    await submit();

    expect(await screen.findByRole("alert")).toHaveTextContent(
      t("auth.forgotPasswordFailed"),
    );
  });

  it("has no serious accessibility violations", async () => {
    const { container } = render(<ForgotPasswordForm />);

    expect(await seriousA11yViolations(container)).toEqual([]);
  });
});
