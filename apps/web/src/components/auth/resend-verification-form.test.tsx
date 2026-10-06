import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it } from "vitest";
import { ResendVerificationForm } from "@/components/auth/resend-verification-form";
import { t } from "@/i18n/t";
import { seriousA11yViolations } from "../../../test/axe";
import {
  mockFetch,
  mockFetchNetworkError,
  requestBody,
} from "../../../test/fetch";

async function resend() {
  await userEvent
    .setup()
    .click(screen.getByRole("button", { name: t("auth.verifyResend") }));
}

describe("ResendVerificationForm", () => {
  it("prefills the address and keeps one empty status region", () => {
    render(<ResendVerificationForm defaultEmail="ana@example.mk" />);

    expect(screen.getByLabelText(t("auth.email"))).toHaveValue(
      "ana@example.mk",
    );
    expect(screen.getAllByRole("status")).toHaveLength(1);
    expect(screen.getByRole("status")).toBeEmptyDOMElement();
  });

  it("announces 'sent' in the same region on a 2xx", async () => {
    const fetch = mockFetch({ status: 202, body: { data: {} } });
    render(<ResendVerificationForm defaultEmail="ana@example.mk" />);
    const region = screen.getByRole("status");

    await resend();

    expect(await screen.findByText(t("auth.verifyResendSent"))).toBe(region);
    expect(region).toBeInTheDocument();
    expect(screen.getAllByRole("status")).toHaveLength(1);
    expect(requestBody(fetch)).toEqual({ email: "ana@example.mk" });
    expect(
      screen.queryByRole("button", { name: t("auth.verifyResend") }),
    ).toBeNull();
  });

  it.each([
    [
      429,
      { message: "Премногу барања. Почекајте." },
      "Премногу барања. Почекајте.",
    ],
    [500, undefined, t("auth.verifyResendFailed")],
  ])("never reports 'sent' on a %i", async (status, body, text) => {
    mockFetch({ status, body });
    render(<ResendVerificationForm defaultEmail="ana@example.mk" />);
    const region = screen.getByRole("status");

    await resend();

    expect(await screen.findByRole("alert")).toHaveTextContent(text);
    expect(region).toBeEmptyDOMElement();
    expect(screen.queryByText(t("auth.verifyResendSent"))).toBeNull();
    // The form stays for another try.
    expect(
      screen.getByRole("button", { name: t("auth.verifyResend") }),
    ).toBeEnabled();
  });

  it("never reports 'sent' when the request does not reach the server", async () => {
    mockFetchNetworkError();
    render(<ResendVerificationForm defaultEmail="ana@example.mk" />);

    await resend();

    expect(await screen.findByRole("alert")).toHaveTextContent(
      t("auth.verifyResendFailed"),
    );
    expect(screen.getByRole("status")).toBeEmptyDOMElement();
  });

  it("has no serious accessibility violations", async () => {
    const { container } = render(<ResendVerificationForm />);

    expect(await seriousA11yViolations(container)).toEqual([]);
  });
});
