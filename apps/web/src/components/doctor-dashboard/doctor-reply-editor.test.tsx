import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it } from "vitest";
import { DoctorReplyEditor } from "@/components/doctor-dashboard/doctor-reply-editor";
import type { DashboardReview } from "@/lib/api/doctor-dashboard-types";
import { t } from "@/i18n/t";
import { seriousA11yViolations } from "../../../test/axe";
import { mockFetch, requestBody } from "../../../test/fetch";
import { router } from "../../../test/next-navigation";

const review: DashboardReview = {
  id: 7,
  rating: 4,
  body: "Внимателен лекар.",
  author_name: "Марија К.",
  published_at: "2026-10-01T10:00:00+02:00",
  helpful_count: 0,
  reply: null,
  can_reply: true,
};

describe("DoctorReplyEditor", () => {
  it("ties the patient-data hint to the field and has no a11y violations", async () => {
    const { container } = render(<DoctorReplyEditor review={review} />);

    const field = screen.getByLabelText(t("doctorDashboard.replyLabel"));
    expect(field).toHaveAccessibleDescription(
      expect.stringContaining(t("doctorDashboard.replyHint")),
    );
    expect(await seriousA11yViolations(container)).toEqual([]);
  });

  it("sends the reply and shows that it waits for staff", async () => {
    const fetch = mockFetch({
      status: 200,
      body: {
        data: {
          review: {
            ...review,
            reply: {
              body: "Благодарам.",
              source: "doctor",
              status: "pending",
              responded_at: "2026-10-06T10:00:00+02:00",
              rejection_note: null,
            },
          },
        },
      },
    });
    const user = userEvent.setup();
    render(<DoctorReplyEditor review={review} />);

    await user.type(
      screen.getByLabelText(t("doctorDashboard.replyLabel")),
      "Благодарам.",
    );
    await user.click(
      screen.getByRole("button", { name: t("doctorDashboard.replySave") }),
    );

    expect(fetch.mock.calls[0]?.[0]).toBe(
      "/api/doctor-dashboard/reviews/7/reply",
    );
    expect(fetch.mock.calls[0]?.[1]?.method).toBe("PUT");
    expect(requestBody(fetch)).toEqual({ body: "Благодарам." });
    expect(
      await screen.findByText(t("doctorDashboard.replyPendingSaved")),
    ).toBeInTheDocument();
    expect(screen.getByText(t("doctorDashboard.replyPending"))).toBeVisible();
    expect(router.refresh).toHaveBeenCalled();
  });

  it("does not call the API for an empty reply", async () => {
    const fetch = mockFetch({ status: 200, body: {} });
    const user = userEvent.setup();
    render(<DoctorReplyEditor review={review} />);

    await user.click(
      screen.getByRole("button", { name: t("doctorDashboard.replySave") }),
    );

    expect(screen.getByRole("alert")).toHaveTextContent(
      t("doctorDashboard.replyTooShort"),
    );
    expect(fetch).not.toHaveBeenCalled();
  });

  it("shows staff's reason on a rejected reply and lets the doctor rewrite it", async () => {
    const user = userEvent.setup();
    render(
      <DoctorReplyEditor
        review={{
          ...review,
          reply: {
            body: "Се сеќавам на вас.",
            source: "doctor",
            status: "rejected",
            responded_at: null,
            rejection_note: "Потврдува дека авторот е пациент.",
          },
        }}
      />,
    );

    expect(screen.getByText(t("doctorDashboard.replyRejected"))).toBeVisible();
    expect(
      screen.getByText(/Потврдува дека авторот е пациент\./),
    ).toBeVisible();

    await user.click(
      screen.getByRole("button", { name: t("doctorDashboard.replyEdit") }),
    );
    expect(screen.getByLabelText(t("doctorDashboard.replyLabel"))).toHaveValue(
      "Се сеќавам на вас.",
    );
  });

  it("deletes the doctor's own reply", async () => {
    const fetch = mockFetch({
      status: 200,
      body: { data: { review: { ...review, reply: null } } },
    });
    const user = userEvent.setup();
    render(
      <DoctorReplyEditor
        review={{
          ...review,
          reply: {
            body: "Благодарам.",
            source: "doctor",
            status: "approved",
            responded_at: null,
            rejection_note: null,
          },
        }}
      />,
    );

    await user.click(
      screen.getByRole("button", { name: t("doctorDashboard.replyDelete") }),
    );

    expect(fetch.mock.calls[0]?.[1]?.method).toBe("DELETE");
    expect(
      await screen.findByText(t("doctorDashboard.replyDeleted")),
    ).toBeInTheDocument();
    expect(screen.getByLabelText(t("doctorDashboard.replyLabel"))).toHaveValue(
      "",
    );
  });

  it("shows a staff-entered response without any editing control", () => {
    render(
      <DoctorReplyEditor
        review={{
          ...review,
          can_reply: false,
          reply: {
            body: "Одговор од тимот.",
            source: "staff",
            status: "approved",
            responded_at: null,
            rejection_note: null,
          },
        }}
      />,
    );

    expect(screen.getByText("Одговор од тимот.")).toBeVisible();
    expect(screen.getByText(t("doctorDashboard.replyStaff"))).toBeVisible();
    expect(screen.queryByRole("button")).toBeNull();
    expect(screen.queryByRole("textbox")).toBeNull();
  });
});
