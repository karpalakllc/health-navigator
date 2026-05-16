export function OfficeHoursGrid({
  hours,
}: {
  hours: Record<string, string>;
}) {
  const entries = Object.entries(hours);

  if (entries.length === 0) {
    return null;
  }

  return (
    <div className="grid gap-2 sm:grid-cols-2 md:grid-cols-3">
      {entries.map(([day, time]) => (
        <div
          key={day}
          className="rounded-xl border border-border/60 bg-secondary/40 px-3 py-3 text-sm shadow-[inset_0_1px_0_0_color-mix(in_srgb,var(--color-card)_70%,transparent)]"
        >
          <p className="font-semibold">{day}</p>
          <p className="text-muted-foreground">{time}</p>
        </div>
      ))}
    </div>
  );
}
