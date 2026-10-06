import { render, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it } from "vitest";
import { LoginForm } from "@/components/auth/login-form";
import { t } from "@/i18n/t";
import { seriousA11yViolations } from "../../../test/axe";
import {
  mockFetch,
  mockFetchNetworkError,
  requestBody,
} from "../../../test/fetch";
import { router, setSearchParams } from "../../../test/next-navigation";

async function fillAndSubmit(
  email = "ana@example.mk",
  password = "secret-pass",
) {
  const user = userEvent.setup();
  await user.type(screen.getByLabelText(t("auth.email")), email);
  await user.type(screen.getByLabelText(t("auth.password")), password);
  await user.click(screen.getByRole("button", { name: t("auth.signIn") }));

  return user;
}

describe("LoginForm", () => {
  it("labels both fields and marks them required", () => {
    render(<LoginForm />);

    const email = screen.getByLabelText(t("auth.email"));
    const password = screen.getByLabelText(t("auth.password"));

    expect(email).toHaveAttribute("type", "email");
    expect(email).toBeRequired();
    expect(password).toHaveAttribute("type", "password");
    expect(password).toBeRequired();
    // The forgot-password link sits outside the label, so it does not leak
    // into the password field's accessible name.
    expect(password).toHaveAccessibleName(t("auth.password"));
    expect(
      screen.getByRole("link", { name: t("auth.forgotPassword") }),
    ).toHaveAttribute("href", "/forgot-password");
  });

  it("confirms a password reset when sent back with ?reset=1", () => {
    setSearchParams({ reset: "1" });
    render(<LoginForm />);

    expect(screen.getByText(t("auth.resetPasswordDone"))).toBeVisible();
  });

  it("says nothing about a reset on a plain visit", () => {
    render(<LoginForm />);

    expect(
      screen.queryByText(t("auth.resetPasswordDone")),
    ).not.toBeInTheDocument();
  });

  it("does not submit an empty form", async () => {
    const fetch = mockFetch({ status: 200, body: {} });
    render(<LoginForm />);

    await userEvent
      .setup()
      .click(screen.getByRole("button", { name: t("auth.signIn") }));

    expect(fetch).not.toHaveBeenCalled();
    expect(screen.getByLabelText(t("auth.email"))).toBeInvalid();
  });

  it("toggles password visibility with a named button", async () => {
    render(<LoginForm />);
    const user = userEvent.setup();
    const password = screen.getByLabelText(t("auth.password"));

    await user.click(
      screen.getByRole("button", { name: t("auth.showPassword") }),
    );
    expect(password).toHaveAttribute("type", "text");

    await user.click(
      screen.getByRole("button", { name: t("auth.hidePassword") }),
    );
    expect(password).toHaveAttribute("type", "password");
  });

  it.each([
    [
      401,
      { message: "Невалидни податоци за најава." },
      "Невалидни податоци за најава.",
    ],
    [
      422,
      { errors: { email: ["Полето е-пошта е задолжително."] } },
      "Полето е-пошта е задолжително.",
    ],
    [
      429,
      { message: "Премногу обиди. Почекајте." },
      "Премногу обиди. Почекајте.",
    ],
    [500, {}, t("auth.loginFailed")],
  ])("surfaces a %i response as an alert", async (status, body, expected) => {
    mockFetch({ status, body });
    render(<LoginForm />);

    await fillAndSubmit();

    expect(await screen.findByRole("alert")).toHaveTextContent(expected);
    expect(router.push).not.toHaveBeenCalled();
    // The button is usable again for a retry.
    expect(
      screen.getByRole("button", { name: t("auth.signIn") }),
    ).toBeEnabled();
  });

  it("reports a network failure without crashing", async () => {
    mockFetchNetworkError();
    render(<LoginForm />);

    await fillAndSubmit();

    expect(await screen.findByRole("alert")).toHaveTextContent(
      t("auth.loginFailed"),
    );
  });

  it("posts the credentials to the session route", async () => {
    const fetch = mockFetch({ status: 200, body: { data: {} } });
    render(<LoginForm />);

    await fillAndSubmit("ana@example.mk", "secret-pass");

    expect(fetch).toHaveBeenCalledWith(
      "/api/session/login",
      expect.objectContaining({ method: "POST" }),
    );
    expect(requestBody(fetch)).toEqual({
      email: "ana@example.mk",
      password: "secret-pass",
    });
  });

  it("redirects to a same-origin ?redirect= target after signing in", async () => {
    setSearchParams({ redirect: "/forum/zdravje?page=2" });
    mockFetch({ status: 200, body: { data: {} } });
    render(<LoginForm />);

    await fillAndSubmit();

    await waitFor(() => expect(router.push).toHaveBeenCalled());
    expect(router.push).toHaveBeenCalledWith("/forum/zdravje?page=2");
    expect(router.refresh).toHaveBeenCalled();
  });

  it.each([
    "https://evil.example/phish",
    "//evil.example/phish",
    "/\\evil.example",
    "javascript:alert(1)",
  ])("refuses the off-site redirect %s and goes home", async (target) => {
    setSearchParams({ redirect: target });
    mockFetch({ status: 200, body: { data: {} } });
    render(<LoginForm />);

    await fillAndSubmit();

    await waitFor(() => expect(router.push).toHaveBeenCalled());
    expect(router.push).toHaveBeenCalledWith("/");
  });

  it("offers to resend the link when the address is not verified", async () => {
    mockFetch({
      status: 403,
      body: { message: "Unverified", code: "auth.email_unverified" },
    });
    render(<LoginForm />);

    await fillAndSubmit("nov@example.mk");

    expect(
      await screen.findByRole("heading", { name: t("auth.verifyCheckInbox") }),
    ).toBeInTheDocument();
    expect(screen.queryByRole("alert")).not.toBeInTheDocument();
    // The resend form is prefilled with the address just typed.
    expect(screen.getByLabelText(t("auth.email"))).toHaveValue(
      "nov@example.mk",
    );
    expect(
      screen.getByRole("button", { name: t("auth.verifyResend") }),
    ).toBeInTheDocument();
    expect(router.push).not.toHaveBeenCalled();
  });

  it("has no serious accessibility violations, including with an error", async () => {
    mockFetch({ status: 401, body: { message: "Невалидни податоци." } });
    const { container } = render(<LoginForm />);

    expect(await seriousA11yViolations(container)).toEqual([]);

    await fillAndSubmit();
    await screen.findByRole("alert");

    expect(await seriousA11yViolations(container)).toEqual([]);
    expect(within(container).getByRole("alert")).toBeVisible();
  });
});
