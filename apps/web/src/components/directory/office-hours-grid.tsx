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
          className="rounded-xl bg-secondary/60 px-3 py-2 text-sm"
        >
          <p className="font-semibold">{day}</p>
          <p className="text-muted-foreground">{time}</p>
        </div>
      ))}
    </div>
  );
}
