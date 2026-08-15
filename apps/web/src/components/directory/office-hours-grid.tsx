export function OfficeHoursGrid({ hours }: { hours: Record<string, string> }) {
  const entries = Object.entries(hours);

  if (entries.length === 0) {
    return null;
  }

  return (
    <div className="grid gap-3.5 sm:grid-cols-2 lg:grid-cols-3">
      {entries.map(([day, time]) => (
        <div
          key={day}
          className="flex min-h-[92px] flex-col justify-center rounded-[1.375rem] border border-border bg-gradient-to-b from-[#fbfcfc] to-[#f8fafb] px-4 py-4"
        >
          <p className="text-base font-extrabold text-foreground">{day}</p>
          <p className="mt-2 text-base text-muted-foreground">{time}</p>
        </div>
      ))}
    </div>
  );
}
