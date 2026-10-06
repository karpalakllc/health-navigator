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

const DAY_STEMS: Record<string, Weekday> = {
  пон: 0,
  вто: 1,
  сре: 2,
  чет: 3,
  пет: 4,
  саб: 5,
  нед: 6,
};

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
  const stem = word.trim().toLowerCase().slice(0, 3);
  return stem in DAY_STEMS ? DAY_STEMS[stem] : null;
}

/** „Пон“ → [0]; „Пон–Пет“ → [0…4]; „Саб, Нед“ → [5, 6]. */
export function parseDays(label: string): Weekday[] {
  const days = new Set<Weekday>();

  for (const part of label.split(/,|\/|\s+и\s+/)) {
    const bounds = part.split(/\s*[–—-]\s*/).filter(Boolean);
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

/**
 * Open right now? Only answered when the hours parse: null means "we can't
 * tell", and the UI then says nothing rather than guessing.
 */
export function openStatus(
  rows: HoursRow[],
  now: Date = new Date(),
): OpenStatus | null {
  const parsed = rows.filter((row) => row.days.length > 0);
  if (parsed.length === 0) {
    return null;
  }

  const { day, minutes } = skopjeClock(now);
  const today = parsed.find((row) => row.days.includes(day));

  if (!today || today.closed) {
    return { state: "closed", todayHours: null };
  }
  if (today.ranges.length === 0) {
    // „По договор“ and the like: something is written, but not a time.
    return null;
  }
  if (today.ranges.some((r) => r.from === 0 && r.to === 24 * 60)) {
    return { state: "open24" };
  }

  const current = today.ranges.find((r) =>
    r.to > r.from
      ? minutes >= r.from && minutes < r.to
      : minutes >= r.from || minutes < r.to,
  );

  return current
    ? { state: "open", until: hhmm(current.to) }
    : { state: "closed", todayHours: today.hours };
}
