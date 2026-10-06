import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";
import { EmergencyCallLinks } from "@/components/guidance/emergency-call-links";
import { Button, IconButton, TextLink } from "@/components/ui/button";
import { FilterChip, RemovableChip } from "@/components/ui/chip";
import { EmergencyPill } from "@/components/ui/emergency-pill";
import { Notice, NoticeTelLink } from "@/components/ui/notice";
import { SectionHeader } from "@/components/ui/section-header";
import { StarRating } from "@/components/ui/star-rating";
import { FeaturedTag, VerifiedTag } from "@/components/ui/tag";
import { Monogram } from "@/components/ui/user-avatar";
import { t, tFormat } from "@/i18n/t";
import { seriousA11yViolations } from "../../../test/axe";

describe("Button", () => {
  it("renders site paths as links and tel: as plain anchors", () => {
    render(
      <>
        <Button href="/doctors">Види профил</Button>
        <Button href="tel:023124567" leadingIcon="phone" variant="soft">
          Јави се
        </Button>
      </>,
    );

    expect(screen.getByRole("link", { name: "Види профил" })).toHaveAttribute(
      "href",
      "/doctors",
    );
    expect(screen.getByRole("link", { name: "Јави се" })).toHaveAttribute(
      "href",
      "tel:023124567",
    );
  });

  it("defaults to type=button and blocks clicks while loading", async () => {
    const onClick = vi.fn();
    const user = userEvent.setup();
    const { rerender } = render(<Button onClick={onClick}>Испрати</Button>);

    const button = screen.getByRole("button", { name: "Испрати" });
    expect(button).toHaveAttribute("type", "button");
    await user.click(button);
    expect(onClick).toHaveBeenCalledTimes(1);

    rerender(
      <Button onClick={onClick} loading>
        Испрати
      </Button>,
    );
    expect(button).toHaveAttribute("aria-busy", "true");
    await user.click(button);
    expect(onClick).toHaveBeenCalledTimes(1);
  });

  it("disables natively", () => {
    render(<Button disabled>Испрати</Button>);

    expect(screen.getByRole("button", { name: "Испрати" })).toBeDisabled();
  });

  it("lets className utilities override the base classes", () => {
    render(<Button className="w-full">Пребарај</Button>);

    expect(screen.getByRole("button").className).toMatch(
      /^btn btn-primary btn-md w-full$/,
    );
  });
});

describe("IconButton / TextLink", () => {
  it("takes its accessible name from the required label", () => {
    render(
      <>
        <IconButton icon="heart" label="Зачувај" />
        <IconButton icon="search" label="Пребарај" href="/search" />
      </>,
    );

    expect(screen.getByRole("button", { name: "Зачувај" })).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Пребарај" })).toHaveAttribute(
      "href",
      "/search",
    );
  });

  it("renders a coral-underlined link", () => {
    render(<TextLink href="/forum">Сите теми</TextLink>);

    expect(screen.getByRole("link", { name: "Сите теми" })).toHaveClass(
      "link-underline",
    );
  });
});

describe("Chips", () => {
  it("FilterChip exposes its state with aria-pressed", async () => {
    const onClick = vi.fn();
    const user = userEvent.setup();
    const { rerender } = render(
      <FilterChip selected={false} onClick={onClick}>
        Отворено сега
      </FilterChip>,
    );

    const chip = screen.getByRole("button", { name: "Отворено сега" });
    expect(chip).toHaveAttribute("aria-pressed", "false");
    await user.click(chip);
    expect(onClick).toHaveBeenCalled();

    rerender(
      <FilterChip selected onClick={onClick}>
        Отворено сега
      </FilterChip>,
    );
    expect(chip).toHaveAttribute("aria-pressed", "true");
  });

  it("RemovableChip names its remove control after the filter", async () => {
    const onRemove = vi.fn();
    const user = userEvent.setup();
    render(
      <>
        <RemovableChip label="Кардиологија" onRemove={onRemove} />
        <RemovableChip label="Скопје" removeHref="/doctors" />
      </>,
    );

    await user.click(
      screen.getByRole("button", {
        name: tFormat("ui.removeFilter", { label: "Кардиологија" }),
      }),
    );
    expect(onRemove).toHaveBeenCalled();
    expect(
      screen.getByRole("link", {
        name: tFormat("ui.removeFilter", { label: "Скопје" }),
      }),
    ).toHaveAttribute("href", "/doctors");
  });
});

describe("Emergency", () => {
  it("the pill dials 194 and its name starts with the visible text", () => {
    render(<EmergencyPill />);

    const pill = screen.getByRole("link", {
      name: new RegExp(`^${t("ui.emergencyPill")}`),
    });
    expect(pill).toHaveAttribute("href", "tel:194");
    expect(pill).toHaveClass("bg-emergency", "border-ink", "border-2");
  });

  it("the guidance call links keep their names and tel: targets", () => {
    render(<EmergencyCallLinks />);

    expect(
      screen.getByRole("link", { name: t("guidance.call194") }),
    ).toHaveAttribute("href", "tel:194");
    expect(
      screen.getByRole("link", { name: t("guidance.call112") }),
    ).toHaveAttribute("href", "tel:112");
  });

  it("the safety notice is not red and its numbers are tel: links", () => {
    render(
      <Notice tone="safety">
        Итно? <NoticeTelLink number="194" /> или <NoticeTelLink number="112" />
      </Notice>,
    );

    expect(screen.getByRole("link", { name: "194" })).toHaveAttribute(
      "href",
      "tel:194",
    );
    expect(screen.getByRole("link", { name: "112" })).toHaveAttribute(
      "href",
      "tel:112",
    );
    expect(document.body.innerHTML).not.toMatch(/emergency|destructive/);
  });
});

describe("StarRating, Monogram, tags, SectionHeader", () => {
  it("labels a fractional rating with one decimal", () => {
    render(<StarRating value={4.75} size="md" />);

    expect(screen.getByRole("img")).toHaveAccessibleName("4,8 / 5");
  });

  it("takes a doctor's initials from the name, not the title", () => {
    const { container } = render(
      <Monogram name="д-р Марија Петровска" kind="doctor" />,
    );

    expect(container).toHaveTextContent("МП");
    expect(container.firstElementChild).toHaveAttribute("aria-hidden", "true");
  });

  it("renders the neutral featured and the care verified tags", () => {
    render(
      <>
        <FeaturedTag />
        <VerifiedTag />
      </>,
    );

    expect(screen.getByText(t("ui.featured")).parentElement).toHaveClass(
      "text-ink-2",
    );
    expect(screen.getByText(t("ui.verified")).parentElement).toHaveClass(
      "text-care",
    );
  });

  it("gives the section heading an id for aria-labelledby", () => {
    render(
      <section aria-labelledby="s1">
        <SectionHeader
          id="s1"
          title="Од заедницата"
          action={{ href: "/forum", label: "Сите теми" }}
        />
      </section>,
    );

    expect(
      screen.getByRole("region", { name: "Од заедницата" }),
    ).toBeInTheDocument();
    expect(screen.getByRole("heading", { level: 2 })).toHaveAttribute(
      "id",
      "s1",
    );
    expect(screen.getByRole("link", { name: "Сите теми" })).toHaveAttribute(
      "href",
      "/forum",
    );
  });

  it("has no serious accessibility violations", async () => {
    const { container } = render(
      <div>
        <Button>Види профил</Button>
        <IconButton icon="flag" label="Пријави" />
        <FilterChip selected>Прима нови пациенти</FilterChip>
        <RemovableChip label="Скопје" onRemove={() => {}} />
        <EmergencyPill />
        <StarRating value={4.5} />
        <FeaturedTag />
      </div>,
    );

    expect(await seriousA11yViolations(container)).toEqual([]);
  });
});
