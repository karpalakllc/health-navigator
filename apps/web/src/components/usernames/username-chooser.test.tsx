import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it } from "vitest";
import { UsernameChooser } from "@/components/usernames/username-chooser";
import { t, tFormat } from "@/i18n/t";
import { seriousA11yViolations } from "../../../test/axe";
import { mockFetch, requestBody } from "../../../test/fetch";
import { router } from "../../../test/next-navigation";

describe("UsernameChooser", () => {
  it("shows the temporary name, saves the chosen one and continues", async () => {
    const fetch = mockFetch({
      status: 200,
      body: {
        data: {
          user: {
            username: "ana_od_ohrid",
            username_change_available_at: null,
          },
        },
      },
    });
    const user = userEvent.setup();
    render(<UsernameChooser temporary="clen-k3x9p2" redirectTo="/forum" />);

    expect(
      screen.getByText(
        tFormat("usernames.chooserTemporary", { username: "clen-k3x9p2" }),
      ),
    ).toBeInTheDocument();

    await user.type(
      screen.getByLabelText(t("usernames.label")),
      "ana_od_ohrid",
    );
    await user.click(
      screen.getByRole("button", { name: t("usernames.chooserSubmit") }),
    );

    expect(requestBody(fetch)).toEqual({ username: "ana_od_ohrid" });
    await screen.findByText(t("usernames.saved"));
    expect(router.push).toHaveBeenCalledWith("/forum");
  });

  it("can be skipped: reading does not need a username", async () => {
    const { container } = render(
      <UsernameChooser temporary="clen-k3x9p2" redirectTo="/forum" />,
    );

    expect(
      screen.getByRole("link", { name: t("usernames.chooserLater") }),
    ).toHaveAttribute("href", "/forum");
    expect(
      screen.getByText(t("usernames.chooserLaterHint")),
    ).toBeInTheDocument();
    expect(await seriousA11yViolations(container)).toEqual([]);
  });
});
