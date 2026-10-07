import { render, screen } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";

const viewerCanPreviewFirstAid = vi.hoisted(() => vi.fn());
const notFound = vi.hoisted(() =>
  vi.fn(() => {
    throw new Error("NEXT_NOT_FOUND");
  }),
);

vi.mock("@/components/first-aid/access", () => ({ viewerCanPreviewFirstAid }));
vi.mock("next/navigation", async (importOriginal) => ({
  ...(await importOriginal<typeof import("next/navigation")>()),
  notFound,
}));

import FirstAidGuidePage, {
  generateMetadata as guideMetadata,
} from "@/app/prva-pomos/[slug]/page";
import FirstAidIndexPage, {
  generateMetadata as indexMetadata,
} from "@/app/prva-pomos/page";
import { FIRST_AID_COPY } from "@/content/first-aid/copy";

function params(slug: string) {
  return { params: Promise.resolve({ slug }) };
}

beforeEach(() => {
  vi.clearAllMocks();
  viewerCanPreviewFirstAid.mockResolvedValue(false);
});

describe("/prva-pomos/[slug]", () => {
  it("is a 404 for the public while the guide is a draft", async () => {
    await expect(FirstAidGuidePage(params("kpr-vozrasni"))).rejects.toThrow(
      "NEXT_NOT_FOUND",
    );
  });

  it("is a 404 for an unknown slug, even for staff", async () => {
    viewerCanPreviewFirstAid.mockResolvedValue(true);

    await expect(FirstAidGuidePage(params("ne-postoi"))).rejects.toThrow(
      "NEXT_NOT_FOUND",
    );
  });

  it("shows staff the draft with the preview banner and no structured data", async () => {
    viewerCanPreviewFirstAid.mockResolvedValue(true);

    const { container } = render(
      await FirstAidGuidePage(params("kpr-vozrasni")),
    );

    expect(
      screen.getByRole("heading", { level: 1, name: /Оживување/ }),
    ).toBeInTheDocument();
    expect(screen.getByRole("status")).toHaveTextContent(
      FIRST_AID_COPY.previewBanner,
    );
    expect(
      container.querySelector('script[type="application/ld+json"]'),
    ).toBeNull();
  });

  it("keeps a draft out of search results", async () => {
    const meta = await guideMetadata(params("kpr-vozrasni"));

    expect(meta.robots).toEqual({ index: false, follow: false });
    expect(meta.alternates?.canonical).toBe("/prva-pomos/kpr-vozrasni");
  });
});

describe("/prva-pomos", () => {
  it("shows the public only the holding note while every guide is a draft", async () => {
    render(await FirstAidIndexPage());

    expect(screen.getByText(FIRST_AID_COPY.emptyIndex)).toBeInTheDocument();
    expect(screen.queryByRole("link", { name: /Оживување/ })).toBeNull();
  });

  it("shows staff every guide", async () => {
    viewerCanPreviewFirstAid.mockResolvedValue(true);
    render(await FirstAidIndexPage());

    expect(
      screen.getByRole("link", { name: /Оживување \(КПР\) кај возрасен/ }),
    ).toHaveAttribute("href", "/prva-pomos/kpr-vozrasni");
  });

  it("is noindex until a guide is published", () => {
    expect(indexMetadata().robots).toEqual({ index: false, follow: false });
  });
});

describe("canPreviewFirstAid", () => {
  it("lets admins and moderators preview drafts, nobody else", async () => {
    const { canPreviewFirstAid } = await vi.importActual<
      typeof import("@/components/first-aid/access")
    >("@/components/first-aid/access");

    expect(canPreviewFirstAid({ role: "admin" })).toBe(true);
    expect(canPreviewFirstAid({ role: "moderator" })).toBe(true);
    expect(canPreviewFirstAid({ role: "member" })).toBe(false);
    expect(canPreviewFirstAid(null)).toBe(false);
  });
});
