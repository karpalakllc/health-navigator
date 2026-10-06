import type { Metadata } from "next";
import { notFound } from "next/navigation";
import type { ReactNode } from "react";
import { EmergencyCallLinks } from "@/components/guidance/emergency-call-links";
import { Button, IconButton, TextLink } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { ChipLink } from "@/components/ui/chip";
import {
  Checkbox,
  Fieldset,
  Input,
  Radio,
  Select,
} from "@/components/ui/field";
import { FormError, FormSuccess } from "@/components/ui/form-message";
import { ICON_NAMES, Icon } from "@/components/ui/icons";
import { Notice, NoticeTelLink } from "@/components/ui/notice";
import { SectionHeader } from "@/components/ui/section-header";
import { Skeleton } from "@/components/ui/skeleton";
import { SponsoredBadge } from "@/components/ui/sponsored-badge";
import { StarRating } from "@/components/ui/star-rating";
import { FeaturedTag, Tag, VerifiedTag } from "@/components/ui/tag";
import { Monogram } from "@/components/ui/user-avatar";
import {
  FilterChipDemo,
  LoadingButtonDemo,
  RemovableChipDemo,
  StarRatingInputDemo,
  TextareaCounterDemo,
} from "./gallery-demos";

/*
 * Living gallery of the D2a primitives (src/components/ui) for page agents
 * and reviewers. Development only: in production it is a 404 unless
 * NEXT_PUBLIC_SHOW_DESIGN_SYSTEM=1. Never indexed. Sample content is
 * fictional and hard-coded on purpose (this is not a product page).
 */
export const metadata: Metadata = {
  title: "Design system — Zdravje360",
  robots: { index: false, follow: false },
};

const COLOURS: Array<[string, string, string]> = [
  ["ink", "#2a2220", "text, every action"],
  ["ink-2", "#5e514b", "meta (7.1:1 on cream)"],
  ["cream", "#fbf6f1", "page background"],
  ["white", "#ffffff", "cards / surface"],
  ["sand", "#f6ebe2", "soft pills, bands, avatars"],
  ["line", "#eaded5", "dividers (decorative)"],
  ["line-strong", "#857369", "input / chip borders (4.5:1)"],
  ["apricot", "#fcdfcc", "hero band, feature tiles"],
  ["chip-tint", "#fde9dd", "„Без одговор“, safety note"],
  ["coral", "#ff5757", "logo, 4px edge, tab tick, underline"],
  ["care", "#2f6b4f", "„Прима нови пациенти“, verified"],
  ["care-tint", "#e4f1ea", "care chip surface"],
  ["emergency", "#b42318", "ONLY the guidance 194/112 calls"],
  ["star", "#a14806", "rating stars"],
  ["emergency-hover", "#9a1d13", "pressed 194/112 buttons"],
];

/** Static copies of the global focus styles (globals.css) for the gallery. */
const FOCUS_PREVIEW = {
  outline: "2px solid var(--color-focus-ring)",
  outlineOffset: "2px",
  boxShadow: "0 0 0 6px var(--color-focus-halo)",
} as const;
const INPUT_FOCUS_PREVIEW = {
  borderColor: "var(--color-focus-ring)",
  boxShadow:
    "0 0 0 1px var(--color-focus-ring), 0 0 0 5px var(--color-focus-halo)",
} as const;

const TYPE: Array<[string, string, string]> = [
  ["type-display", "34/40 → 56/62 · 700", "Како можеме да ви помогнеме?"],
  ["type-h1", "28/34 → 40/46 · 700", "д-р Марија Петровска"],
  ["type-h2", "22/28 → 30/36 · 600", "Искуства на пациенти"],
  ["type-h3", "19/26 → 22/28 · 600", "Работно време"],
  ["type-body", "17/24 → 18/26 · 400", "ПЗУ „Кардиомед“, Карпош, Скопје"],
  [
    "type-reading",
    "Source Sans 3 18/28 → 19/30",
    "Докторката одвои доволно време да ми ги објасни резултатите од холтерот и што значат за мене. Ѓ ќ ѕ љ њ џ ј.",
  ],
  ["type-button", "17/22 · 600", "Види профил"],
  ["type-label", "16/22 · 600", "Град"],
  ["type-meta", "15/22 → 16/24 · ink-2", "пред 3 дена · 23 рецензии"],
  ["type-chip", "16/20 · 500", "Отворено сега"],
  ["type-tag", "15/20 · 500", "Проверена посета"],
  ["type-tab", "14/16 · 500 (tabs only)", "Почетна"],
];

function Section({
  id,
  title,
  note,
  children,
}: {
  id: string;
  title: string;
  note?: ReactNode;
  children: ReactNode;
}) {
  return (
    <section aria-labelledby={id} className="flex flex-col gap-4">
      <SectionHeader id={id} title={title} description={note} />
      {children}
    </section>
  );
}

function Row({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div className="flex flex-col gap-2">
      <p className="type-meta text-ink-2">{label}</p>
      <div className="flex flex-wrap items-center gap-3">{children}</div>
    </div>
  );
}

export default function DesignSystemPage() {
  if (
    process.env.NODE_ENV === "production" &&
    process.env.NEXT_PUBLIC_SHOW_DESIGN_SYSTEM !== "1"
  ) {
    notFound();
  }

  return (
    <div className="mx-auto flex w-full max-w-[1240px] flex-col gap-14 px-5 py-8 lg:gap-20 lg:px-6 lg:py-14">
      <header className="flex flex-col gap-3">
        <p className="type-meta text-ink-2">D2a „Праска“ · foundation</p>
        <h1 className="type-display text-ink">Design system</h1>
        <p className="measure type-reading text-ink">
          Every primitive in <code>src/components/ui</code> and its states.
          Light only. Resize to 390px to see the mobile sizes; tab through to
          see the focus ring.
        </p>
      </header>

      <Section
        id="ds-colour"
        title="Colour"
        note="bg-* / text-* / border-* utilities"
      >
        <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
          {COLOURS.map(([name, hex, use]) => (
            <li key={name} className="card overflow-hidden">
              <div
                className="h-16 border-b border-line"
                style={{ background: hex }}
              />
              <div className="p-3">
                <p className="font-semibold text-ink">{name}</p>
                <p className="type-meta text-ink-2">{hex}</p>
                <p className="type-meta text-ink-2">{use}</p>
              </div>
            </li>
          ))}
        </ul>
      </Section>

      <Section
        id="ds-type"
        title="Type"
        note="type-* utilities (mobile → desktop at lg)"
      >
        <div className="flex flex-col gap-5">
          {TYPE.map(([cls, spec, sample]) => (
            <div
              key={cls}
              className="flex flex-col gap-1 border-b border-line pb-4 lg:flex-row lg:items-baseline lg:gap-8"
            >
              <p className="type-meta w-64 shrink-0 text-ink-2">
                <code>{cls}</code> · {spec}
              </p>
              <p className={`${cls} text-ink`}>{sample}</p>
            </div>
          ))}
        </div>
      </Section>

      <Section id="ds-shape" title="Radii, shadows, spacing">
        <div className="flex flex-wrap gap-4">
          {[
            ["rounded-sm · 8", "rounded-sm"],
            ["rounded-input · 14", "rounded-input"],
            ["rounded-card · 20", "rounded-card"],
            ["rounded-sheet · 28", "rounded-sheet"],
            ["rounded-pill · 999", "rounded-pill"],
          ].map(([label, cls]) => (
            <div
              key={label}
              className={`flex h-24 w-36 items-end bg-white p-3 shadow-card ${cls}`}
            >
              <span className="type-meta text-ink-2">{label}</span>
            </div>
          ))}
        </div>
        <div className="flex flex-wrap gap-6">
          <div className="flex h-24 w-56 items-center justify-center rounded-card bg-white shadow-card type-meta">
            shadow-card
          </div>
          <div className="flex h-24 w-56 items-center justify-center rounded-t-sheet bg-white shadow-sheet type-meta">
            shadow-sheet
          </div>
        </div>
        <div className="flex flex-wrap items-end gap-3">
          {[1, 2, 3, 4, 5, 6, 8, 10, 14, 20].map((n) => (
            <div key={n} className="flex flex-col items-center gap-1">
              <div
                className="w-4 rounded-sm bg-apricot"
                style={{ height: `${n * 4}px` }}
              />
              <span className="type-meta text-ink-2">{n * 4}</span>
            </div>
          ))}
        </div>
      </Section>

      <Section
        id="ds-buttons"
        title="Button"
        note="<Button variant size leadingIcon trailingIcon loading fullWidth href>"
      >
        <Row label="Variants (md 48)">
          <Button>Види профил</Button>
          <Button variant="secondary">Прикажи уште</Button>
          <Button variant="soft" leadingIcon="phone">
            Јави се
          </Button>
          <Button variant="ghost">Откажи</Button>
          <div className="rounded-card bg-apricot p-4">
            <Button variant="white" leadingIcon="search">
              Пребарај лекар или аптека
            </Button>
          </div>
        </Row>
        <Row label="Sizes: sm 44 · md 48 · lg 56 (52 desktop)">
          <Button size="sm">Најава</Button>
          <Button size="md">Види профил</Button>
          <Button size="lg" trailingIcon="arrow-right">
            Започни
          </Button>
        </Row>
        <Row label="States: disabled (sand + ink-2, 6.5:1) · loading">
          <Button disabled>Испрати на проверка</Button>
          <Button variant="secondary" disabled>
            Преглед
          </Button>
          <LoadingButtonDemo />
        </Row>
        <Row label="Full width (mobile primary)">
          <Button size="lg" fullWidth leadingIcon="search">
            Пребарај
          </Button>
        </Row>
        <Row label="IconButton (label → aria-label, required)">
          <IconButton icon="heart" label="Зачувај" variant="secondary" />
          <IconButton icon="sliders" label="Филтри" variant="soft" />
          <IconButton icon="flag" label="Пријави" />
          <IconButton
            icon="search"
            label="Пребарај"
            variant="primary"
            size={44}
          />
          <IconButton icon="x" label="Затвори" />
        </Row>
        <Row label="TextLink (ink + 2px coral underline)">
          <TextLink href="#ds-buttons" trailingIcon="arrow-right">
            Сите дежурни аптеки
          </TextLink>
        </Row>
      </Section>

      <Section
        id="ds-focus"
        title="Focus"
        note="Keyboard (:focus-visible): 2px ink ring, 2px offset, 6px apricot halo — the ring is ≥12:1 on every surface. Text inputs (any focus): ink border +1px and the halo. No yellow. Static previews below; tab through the page to see the real thing."
      >
        <Row label="Ring on ink, white and apricot · input focus">
          <Button style={FOCUS_PREVIEW}>Види профил</Button>
          <Button variant="secondary" style={FOCUS_PREVIEW}>
            Прикажи уште
          </Button>
          <span className="rounded-card bg-apricot p-4">
            <Button variant="white" style={FOCUS_PREVIEW}>
              Пребарај
            </Button>
          </span>
          <input
            aria-label="Пример: фокусирано поле"
            className="field-control max-w-[260px]"
            defaultValue="Скопје"
            style={INPUT_FOCUS_PREVIEW}
          />
        </Row>
        <Row label="Skip link (first Tab on every page)">
          <span className="inline-flex min-h-11 items-center rounded-pill bg-ink px-4 type-label text-white">
            Прескокни до содржината
          </span>
        </Row>
      </Section>

      <Section
        id="ds-forms"
        title="Form controls"
        note="Label above · hint · error (ink + icon + „Грешка:“) · aria-describedby wired"
      >
        <div className="grid gap-8 lg:grid-cols-2">
          <Input label="Град" placeholder="Скопје" />
          <Input
            label="Е-адреса"
            type="email"
            hint="Ќе ја користиме само за најава."
            defaultValue="marija@"
            error="Внесете важечка е-адреса."
          />
          <Input label="Јавно име" disabled defaultValue="Марија К." />
          <Select label="Подреди" defaultValue="rel">
            <option value="rel">Најрелевантни</option>
            <option value="top">Најдобро оценети</option>
            <option value="count">Најмногу рецензии</option>
          </Select>
          <TextareaCounterDemo />
          <div className="flex flex-col gap-6">
            <Fieldset legend="Оценка" hint="Изберете една опција.">
              <Radio name="ds-rating" label="Сите" defaultChecked />
              <Radio name="ds-rating" label="4+" />
              <Radio name="ds-rating" label="4,5+" />
            </Fieldset>
            <Checkbox
              label="Ги прифаќам правилата на заедницата"
              error="Потребно е да ги прифатите правилата."
            />
            <Checkbox label="Прима нови пациенти" defaultChecked />
            <Checkbox label="Оневозможено" disabled />
          </div>
        </div>
        <div className="flex flex-col gap-3">
          <FormError>
            Најавата не успеа. Проверете ја е-адресата и лозинката.
          </FormError>
          <FormSuccess>Рецензијата е испратена на проверка.</FormSuccess>
        </div>
      </Section>

      <Section
        id="ds-chips"
        title="Chips"
        note="44px pills in rows with 8px gaps"
      >
        <Row label="FilterChip (aria-pressed)">
          <FilterChipDemo />
        </Row>
        <Row label="ChipLink (current = aria-current)">
          <ChipLink href="#ds-chips" icon="clock">
            Дежурни аптеки
          </ChipLink>
          <ChipLink href="#ds-chips" current>
            Кардиолог
          </ChipLink>
          <ChipLink href="#ds-chips">Педијатар</ChipLink>
        </Row>
        <Row label="RemovableChip (× named „Отстрани филтер: …“)">
          <RemovableChipDemo />
        </Row>
      </Section>

      <Section
        id="ds-tags"
        title="Tags & badges"
        note="Non-interactive, 32px, 15/500"
      >
        <Row label="Tones">
          <Tag>Автор</Tag>
          <Tag tone="care" icon="check">
            Прима нови пациенти
          </Tag>
          <Tag tone="tint">Без одговор</Tag>
          <Tag tone="outline">Истакнат</Tag>
          <Tag tone="ink">Денес</Tag>
          <span className="rounded-card bg-apricot p-3">
            <Tag tone="white">На праска</Tag>
          </span>
        </Row>
        <Row label="FeaturedTag · VerifiedTag · SponsoredBadge (toggletip)">
          <FeaturedTag />
          <VerifiedTag />
          <VerifiedTag>Здравствен работник</VerifiedTag>
          <SponsoredBadge />
        </Row>
      </Section>

      <Section
        id="ds-cards"
        title="Card"
        note="white · radius 20 · card shadow · no border"
      >
        <div className="grid gap-5 lg:grid-cols-4">
          <Card>
            <p className="type-h3">Обична картичка</p>
            <p className="type-meta mt-1 text-ink-2">padding lg (24)</p>
          </Card>
          <Card edge>
            <p className="type-h3">Со корален раб</p>
            <p className="type-meta mt-1 text-ink-2">edge — 4px coral top</p>
          </Card>
          <Card tone="sand">
            <p className="type-h3">Песок</p>
            <p className="type-meta mt-1 text-ink-2">tone=&quot;sand&quot;</p>
          </Card>
          <Card tone="apricot">
            <p className="type-h3">Праска</p>
            <p className="type-meta mt-1 text-ink-2">
              tone=&quot;apricot&quot;
            </p>
          </Card>
        </div>
      </Section>

      <Section
        id="ds-stars"
        title="StarRating"
        note="Amber #a14806, half-star drawing, one-decimal label"
      >
        <Row label="sm 16 · md 20 · lg 24">
          <StarRating value={4.8} />
          <StarRating value={4.5} size="md" />
          <StarRating value={3.3} size="lg" />
          <StarRating value={0} size="md" />
        </Row>
        <Row label="In a meta row">
          <span className="inline-flex items-center gap-2 type-body">
            <StarRating value={4.8} size="md" />
            <span className="font-bold">4,8</span>
            <span className="text-ink-2">· 23 рецензии</span>
          </span>
        </Row>
        <Row label="StarRatingInput (radiogroup, arrows/Home/End, 48px stars)">
          <StarRatingInputDemo />
        </Row>
      </Section>

      <Section
        id="ds-avatars"
        title="Avatar / Monogram"
        note="Initials from the name, never the title"
      >
        <Row label="Monogram sizes 28 · 40 · 56 · 80 (doctor: „д-р Марија Петровска“ → МП)">
          <Monogram name="Ана Митревска" size={28} />
          <Monogram name="Горан Стојанов" size={40} />
          <Monogram name="д-р Марија Петровска" kind="doctor" size={56} />
          <Monogram name="д-р Ѓорѓи Ристовски" kind="doctor" size={80} />
          <span className="rounded-card bg-apricot p-3">
            <Monogram name="Весна Т." size={40} tone="white" />
          </span>
        </Row>
      </Section>

      <Section
        id="ds-emergency"
        title="Emergency"
        note="The only red: tel: links with a 2px ink frame"
      >
        <Row label="EmergencyCallLinks (guidance outcome)">
          <EmergencyCallLinks />
        </Row>
      </Section>

      <Section id="ds-notice" title="Notice & SectionHeader">
        <div className="grid gap-4 lg:grid-cols-3">
          <Notice>Цените се информативни; проверете со установата.</Notice>
          <Notice tone="safety">
            Форумот е за искуства и поддршка, не за медицински совет. Итно?
            Јавете се на <NoticeTelLink number="194" /> или{" "}
            <NoticeTelLink number="112" />.
          </Notice>
          <Notice tone="success" title="Испратено">
            Одговорите ги проверува модератор пред да станат видливи.
          </Notice>
        </div>
        <Card>
          <SectionHeader
            title="Од заедницата"
            action={{ href: "#ds-notice", label: "Сите теми" }}
          />
        </Card>
      </Section>

      <Section id="ds-skeleton" title="Skeleton">
        <Card className="flex items-center gap-4">
          <Skeleton className="size-14 rounded-full" />
          <div className="flex flex-1 flex-col gap-2">
            <Skeleton className="h-5 w-1/2" />
            <Skeleton className="h-4 w-1/3" />
          </div>
        </Card>
      </Section>

      <Section
        id="ds-icons"
        title="Icons"
        note="<Icon name size> · 24px grid · 1.75 stroke · currentColor"
      >
        <ul className="grid grid-cols-3 gap-3 sm:grid-cols-5 lg:grid-cols-8">
          {ICON_NAMES.map((name) => (
            <li
              key={name}
              className="flex flex-col items-center gap-2 rounded-card bg-white p-3 text-center shadow-card"
            >
              <Icon name={name} />
              <span className="type-meta break-all text-ink-2">{name}</span>
            </li>
          ))}
        </ul>
      </Section>
    </div>
  );
}
