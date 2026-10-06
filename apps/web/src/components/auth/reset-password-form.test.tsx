import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it } from "vitest";
import { ResetPasswordForm } from "@/components/auth/reset-password-form";
import { t } from "@/i18n/t";
import { seriousA11yViolations } from "../../../test/axe";
import {
  mockFetch,
  mockFetchNetworkError,
  requestBody,
} from "../../../test/fetch";
import { router } from "../../../test/next-navigation";

const TOKEN = "a".repeat(64);

function renderForm() {
  return render(<ResetPasswordForm email="ana@example.mk" token={TOKEN} />);
}

async function submit(password = "novaLozinka123", confirmation = password) {
  const user = userEvent.setup();
  await user.type(screen.getByLabelText(t("auth.password")), password);
  await user.type(
    screen.getByLabelText(t("auth.registerPasswordConfirm")),
    confirmation,
  );
  await user.click(
    screen.getByRole("button", { name: t("auth.resetPasswordSubmit") }),
  );
}

describe("ResetPasswordForm", () => {
  it("shows the address read-only and never renders the token", () => {
    const { container } = renderForm();

    const email = screen.getByLabelText(t("auth.email"));
    expect(email).toHaveValue("ana@example.mk");
    expect(email).toHaveAttribute("readonly");
    expect(container.innerHTML).not.toContain(TOKEN);
    expect(screen.getByLabelText(t("auth.password"))).toBeRequired();
    expect(
      screen.getByLabelText(t("auth.registerPasswordConfirm")),
    ).toBeRequired();
  });

  it("posts the token and address with both passwords", async () => {
    const fetch = mockFetch({ status: 200, body: { data: {} } });
    renderForm();

    await submit("novaLozinka123", "novaLozinka123");

    expect(fetch).toHaveBeenCalledWith(
      "/api/session/reset-password",
      expect.objectContaining({ method: "POST" }),
    );
    expect(requestBody(fetch)).toEqual({
      email: "ana@example.mk",
      token: TOKEN,
      password: "novaLozinka123",
      password_confirmation: "novaLozinka123",
    });
  });

  it("sends the visitor to sign in once the password is changed", async () => {
    mockFetch({ status: 200, body: { data: {} } });
    renderForm();

    await submit();

    await waitFor(() =>
      expect(router.push).toHaveBeenCalledWith("/login?reset=1"),
    );
    expect(router.refresh).toHaveBeenCalled();
  });

  it("shows an expired or invalid token error from the API", async () => {
    mockFetch({
      status: 422,
      body: {
        errors: { email: ["Линкот за промена на лозинката е неважечки."] },
      },
    });
    renderForm();

    await submit();

    expect(await screen.findByRole("alert")).toHaveTextContent(
      "Линкот за промена на лозинката е неважечки.",
    );
    expect(router.push).not.toHaveBeenCalled();
  });

  it("catches a mismatched confirmation before sending, under that field", async () => {
    const fetch = mockFetch({ status: 200, body: { data: {} } });
    const { container } = renderForm();

    expect(container.querySelector("form")).toHaveAttribute("novalidate");
    await submit("novaLozinka123", "drugaLozinka123");

    expect(fetch).not.toHaveBeenCalled();
    const confirmation = screen.getByLabelText(
      t("auth.registerPasswordConfirm"),
    );
    expect(confirmation).toHaveAccessibleDescription(
      `${t("ui.errorPrefix")} ${t("auth.passwordMismatch")}`,
    );
    expect(confirmation).toHaveFocus();
  });

  it("files the API's password errors under the password fields", async () => {
    mockFetch({
      status: 422,
      body: {
        message: "Потврдата на полето лозинка не се совпаѓа.",
        errors: {
          password: [
            "Полето лозинка мора да има најмалку 10 знаци.",
            "Потврдата на полето лозинка не се совпаѓа.",
          ],
        },
      },
    });
    renderForm();

    await submit();

    await waitFor(() =>
      expect(
        screen.getByLabelText(t("auth.registerPasswordConfirm")),
      ).toHaveAttribute("aria-invalid", "true"),
    );
    expect(
      screen.getByLabelText(t("auth.registerPasswordConfirm")),
    ).toHaveAccessibleDescription(
      `${t("ui.errorPrefix")} ${t("auth.passwordMismatch")}`,
    );
    expect(screen.getByLabelText(t("auth.password"))).toHaveAccessibleDescription(
      new RegExp(t("auth.passwordTooWeak")),
    );
    expect(router.push).not.toHaveBeenCalled();
  });

  it("reports a network failure", async () => {
    mockFetchNetworkError();
    renderForm();

    await submit();

    expect(await screen.findByRole("alert")).toHaveTextContent(
      t("auth.resetPasswordFailed"),
    );
    expect(
      screen.getByRole("button", { name: t("auth.resetPasswordSubmit") }),
    ).toBeEnabled();
  });

  it("has no serious accessibility violations", async () => {
    const { container } = renderForm();

    expect(await seriousA11yViolations(container)).toEqual([]);
  });
});
