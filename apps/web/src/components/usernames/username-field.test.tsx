import { render, screen, waitFor } from "@testing-library/react";
import { useState } from "react";
import { describe, expect, it } from "vitest";
import { UsernameField } from "@/components/usernames/username-field";
import { t } from "@/i18n/t";
import { mockFetch } from "../../../test/fetch";

function Harness({ initial }: { initial: string }) {
  const [value, setValue] = useState(initial);

  return (
    <UsernameField
      id="username"
      value={value}
      onChange={setValue}
      hint={t("usernames.registerHelp")}
    />
  );
}

describe("UsernameField", () => {
  it("is not offered to password managers as the login name", () => {
    render(<Harness initial="" />);

    // Login is by e-mail; the handle is a public nickname.
    expect(screen.getByLabelText(t("usernames.label"))).toHaveAttribute(
      "autocomplete",
      "nickname",
    );
  });

  it("marks the field invalid when the name is taken", async () => {
    mockFetch({
      status: 200,
      body: { data: { available: false, message: "Зафатено." } },
    });
    render(<Harness initial="bitolchanka" />);

    const field = screen.getByLabelText(t("usernames.label"));
    expect(field).not.toHaveAttribute("aria-invalid", "true");

    await waitFor(() => expect(screen.getByText("Зафатено.")).toBeVisible(), {
      timeout: 2000,
    });
    expect(field).toHaveAttribute("aria-invalid", "true");
  });
});
