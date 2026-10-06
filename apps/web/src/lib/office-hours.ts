/**
 * Office hours as the admin types them: a map of free-form day labels
 * („Пон“, „Пон–Пет“, „Сабота–Недела“) to free-form hours („08:00–14:00“,
 * „08:00–12:00, 16:00–20:00“, „Затворено“). The profile shows them as a
 * table with today's row marked, and the contact block says whether the
 * place is open right now — both computed in Skopje time, on the server.
 *
 * Anything that does not parse is shown verbatim and simply never matches
 * "today": a wrong „Отворено“ is worse than none.
 */

export const SKOPJE_TIME_ZONE = "Europe/Skopje";

/** Monday = 0 … Sunday = 6. */
export type Weekday = 0 | 1 | 2 | 3 | 4 | 5 | 6;

/*
 * Whole day words only, matched after lower-casing and dropping a trailing
 * dot: full names, the usual abbreviations and their Latin transliterations.
 * A prefix match would read „Неделно“ as Sunday or „Понеделник до Петок“ as
 * Monday alone.
 */
const DAY_WORDS: Record<string, Weekday> = {};
const DAY_SPELLINGS: [Weekday, string[]][] = [
  [0, ["понеделник", "пон", "пн", "ponedelnik", "pon", "pn", "mon", "monday"]],
  [1, ["вторник", "вто", "вт", "vtornik", "vto", "vt", "tue", "tuesday"]],
  [2, ["среда", "сре", "ср", "sreda", "sre", "sr", "wed", "wednesday"]],
  [
    3,
    [
      "четврток",
      "чет",
      "чт",
      "chetvrtok",
      "cetvrtok",
      "četvrtok",
      "chet",
      "cet",
      "čet",
      "thu",
      "thursday",
    ],
  ],
  [4, ["петок", "пет", "пт", "petok", "pet", "pt", "fri", "friday"]],
  [5, ["сабота", "саб", "сб", "sabota", "sab", "sb", "sat", "saturday"]],
  [6, ["недела", "нед", "нд", "nedela", "ned", "nd", "sun", "sunday"]],
];
for (const [day, words] of DAY_SPELLINGS) {
  for (const word of words) DAY_WORDS[word] = day;
}

type Range = { from: number; to: number };

export type HoursRow = {
  /** The admin's label, e.g. „Пон“ or „Пон–Пет“. */
  label: string;
  /** Weekdays the label covers; empty when it did not parse. */
  days: Weekday[];
  /** The admin's hours text. */
  hours: string;
  /** Opening ranges in minutes after midnight; empty when closed/unparsed. */
  ranges: Range[];
  closed: boolean;
  isToday: boolean;
};

export type OpenStatus =
  | { state: "open"; until: string }
  | { state: "open24" }
  | { state: "closed"; todayHours: string | null };

function dayFromWord(word: string): Weekday | null {
  const key = word.trim().toLowerCase().replace(/\.$/, "");
  return key in DAY_WORDS ? DAY_WORDS[key] : null;
}

const WORKDAYS: Weekday[] = [0, 1, 2, 3, 4];
const WEEKEND: Weekday[] = [5, 6];
const EVERY_DAY: Weekday[] = [0, 1, 2, 3, 4, 5, 6];

/** Common whole-phrase labels admins type instead of day names. */
const DAY_PHRASES: [RegExp, Weekday[]][] = [
  [/^работни\s+(денови|дена|ден)$/, WORKDAYS],
  [/^викенд(от)?$/, WEEKEND],
  [/^(секој\s+ден(\s+во\s+неделата)?|секојдневно|цела\s+недела)$/, EVERY_DAY],
];

function daysFromPhrase(part: string): Weekday[] | null {
  const text = part.trim().toLowerCase().replace(/\s+/g, " ");
  for (const [pattern, days] of DAY_PHRASES) {
    if (pattern.test(text)) return days;
  }
  return null;
}

/**
 * „Пон“ → [0]; „Пон–Пет“ / „Пон до Пет“ → [0…4]; „Саб, Нед“ → [5, 6];
 * „Работни денови“ → [0…4]; „Викенд“ → [5, 6]; „Секој ден“ → [0…6].
 */
export function parseDays(label: string): Weekday[] {
  const days = new Set<Weekday>();

  for (const part of label.split(/,|\/|\s+и\s+/)) {
    const phrase = daysFromPhrase(part);
    if (phrase) {
      phrase.forEach((d) => days.add(d));
      continue;
    }
    // „Пон–Пет“, „Пон - Пет“, „Понеделник до Петок“, „од Пон до Пет“.
    const bounds = part
      .trim()
      .replace(/^(од|od)\s+/i, "")
      .split(/\s*[–—-]\s*|\s+(?:до|do)\s+/i)
      .filter(Boolean);
    if (bounds.length === 1) {
      const day = dayFromWord(bounds[0]);
      if (day === null) return [];
      days.add(day);
    } else if (bounds.length === 2) {
      const from = dayFromWord(bounds[0]);
      const to = dayFromWord(bounds[1]);
      if (from === null || to === null) return [];
      for (let d = from; ; d = ((d + 1) % 7) as Weekday) {
        days.add(d);
        if (d === to) break;
      }
    } else {
      return [];
    }
  }

  return [...days].sort((a, b) => a - b);
}

const TIME_RANGE = /(\d{1,2})[:.](\d{2})\s*[–—-]\s*(\d{1,2})[:.](\d{2})/g;

export function parseRanges(hours: string): Range[] {
  const ranges: Range[] = [];

  for (const match of hours.matchAll(TIME_RANGE)) {
    const from = Number(match[1]) * 60 + Number(match[2]);
    const to = Number(match[3]) * 60 + Number(match[4]);
    if (from <= 24 * 60 && to <= 24 * 60 && to !== from) {
      ranges.push({ from, to });
    }
  }

  return ranges;
}

function isClosedText(hours: string): boolean {
  return /затвор|не работи|неработ/i.test(hours);
}

/** Weekday (Mon = 0) and minutes after midnight in Skopje. */
export function skopjeClock(now: Date): { day: Weekday; minutes: number } {
  const parts = new Intl.DateTimeFormat("en-US", {
    timeZone: SKOPJE_TIME_ZONE,
    weekday: "short",
    hour: "2-digit",
    minute: "2-digit",
    hourCycle: "h23",
  }).formatToParts(now);
  const get = (type: string) => parts.find((p) => p.type === type)?.value;
  const weekday = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"].indexOf(
    get("weekday") ?? "",
  );

  return {
    day: Math.max(0, weekday) as Weekday,
    minutes: Number(get("hour")) * 60 + Number(get("minute")),
  };
}

/** The API sends `[]` for "no hours" and an object otherwise. */
export function officeHoursRows(
  hours: Record<string, string> | unknown[] | null | undefined,
  now: Date = new Date(),
): HoursRow[] {
  if (!hours || Array.isArray(hours)) {
    return [];
  }

  const { day: today } = skopjeClock(now);

  return Object.entries(hours).map(([label, text]) => {
    const days = parseDays(label);
    const ranges = parseRanges(text);

    return {
      label,
      days,
      hours: text,
      ranges,
      closed: ranges.length === 0 && isClosedText(text),
      isToday: days.includes(today),
    };
  });
}

function hhmm(minutes: number): string {
  const h = Math.floor(minutes / 60);
  const m = minutes % 60;
  return `${String(h).padStart(2, "0")}:${String(m).padStart(2, "0")}`;
}

function contains(range: Range, minutes: number): boolean {
  return range.to > range.from
    ? minutes >= range.from && minutes < range.to
    : minutes >= range.from;
}

/**
 * Open right now? Only answered when the hours parse: null means "we can't
 * tell", and the UI then says nothing rather than guessing — in particular
 * when today is not covered by any parsed row but some row did not parse
 * (it may well be the one that covers today), or when the rows for today
 * disagree („Пон–Пет: 08:00–14:00“ next to „Сре: Затворено“).
 *
 * Every row that covers today counts, so a split shift typed over two rows
 * („Пон–Пет: 08:00–12:00“, „Пон–Пет: 16:00–20:00“) reads as one day. Ranges
 * that touch („08:00–12:00“, „12:00–16:00“) are open until the last one ends.
 *
 * An overnight range („20:00–02:00“) belongs to the day it starts on: it
 * keeps that day open until midnight and the next day open until it ends.
 */
export function openStatus(
  rows: HoursRow[],
  now: Date = new Date(),
): OpenStatus | null {
  const parsed = rows.filter((row) => row.days.length > 0);
  if (parsed.length === 0) {
    return null;
  }
  const hasUnparsed = parsed.length < rows.length;

  const { day, minutes } = skopjeClock(now);
  const todayRows = parsed.filter((row) => row.days.includes(day));
  const yesterday = ((day + 6) % 7) as Weekday;
  const previousRanges = parsed
    .filter((row) => row.days.includes(yesterday) && !row.closed)
    .flatMap((row) => row.ranges);

  const openRows = todayRows.filter((row) => !row.closed);
  const todayRanges = openRows.flatMap((row) => row.ranges);

  if (todayRanges.some((r) => r.from === 0 && r.to === 24 * 60)) {
    return todayRows.some((row) => row.closed) ? null : { state: "open24" };
  }

  // Still inside last night's overnight range?
  const carriedOver = previousRanges
    .filter((r) => r.to < r.from && minutes < r.to)
    .map((r) => r.to);
  // Today's ranges carry on from wherever the current opening ends.
  const extend = (until: number): number => {
    for (;;) {
      const next = todayRanges.find(
        (r) => r.from <= until && (r.to <= r.from || r.to > until),
      );
      if (!next) return until;
      // An overnight range runs past midnight: its end is tomorrow's time.
      if (next.to <= next.from) return next.to;
      until = next.to;
    }
  };

  if (carriedOver.length > 0) {
    return { state: "open", until: hhmm(extend(Math.max(...carriedOver))) };
  }

  if (todayRows.length === 0) {
    return hasUnparsed ? null : { state: "closed", todayHours: null };
  }
  if (openRows.length === 0) {
    return { state: "closed", todayHours: null };
  }
  if (
    openRows.length < todayRows.length ||
    openRows.some((row) => row.ranges.length === 0)
  ) {
    // A row says closed while another gives hours, or something is written
    // that is not a time („По договор“): do not guess.
    return null;
  }

  const current = todayRanges.filter((r) => contains(r, minutes));
  if (current.length > 0) {
    const overnight = current.find((r) => r.to <= r.from);
    const until = overnight
      ? overnight.to
      : extend(Math.max(...current.map((r) => r.to)));
    return { state: "open", until: hhmm(until) };
  }

  return {
    state: "closed",
    todayHours: [...openRows]
      .sort(
        (a, b) =>
          Math.min(...a.ranges.map((r) => r.from)) -
          Math.min(...b.ranges.map((r) => r.from)),
      )
      .map((row) => row.hours)
      .join(", "),
  };
}
