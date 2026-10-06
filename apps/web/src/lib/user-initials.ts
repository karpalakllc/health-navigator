export function initialsFromName(name: string): string {
  const parts = name.trim().split(/\s+/).filter(Boolean);

  if (parts.length === 0) {
    return "?";
  }

  if (parts.length === 1) {
    return parts[0].slice(0, 2).toUpperCase();
  }

  return `${parts[0].charAt(0)}${parts[parts.length - 1].charAt(0)}`.toUpperCase();
}

/**
 * Titles that lead a doctor's stored name ("д-р Ана Петровска", "проф. д-р …").
 * Taking the first letter of the full name gave every doctor the same "Д".
 */
const HONORIFIC =
  /^(?:д-р|др\.?|dr\.?|d-r|проф\.?|prof\.?|доц\.?|doc\.?|м-р|mr\.?|прим\.?|prim\.?)$/iu;

export function stripHonorifics(name: string): string {
  const parts = name.trim().split(/\s+/).filter(Boolean);
  let start = 0;

  while (start < parts.length - 1 && HONORIFIC.test(parts[start])) {
    start++;
  }

  return parts.slice(start).join(" ");
}

/** Given name + surname initials of a doctor, ignoring leading titles. */
export function doctorInitials(fullName: string): string {
  return initialsFromName(stripHonorifics(fullName));
}
