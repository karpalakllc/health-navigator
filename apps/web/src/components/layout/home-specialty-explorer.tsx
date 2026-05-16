import Link from "next/link";
import { Card } from "@/components/ui/card";
import type { Specialty } from "@/lib/api/types";
import { tFormat } from "@/i18n/t";

export function HomeSpecialtyExplorer({ specialties }: { specialties: Specialty[] }) {
  const items = [...specialties]
    .filter((s) => s.doctors_count > 0)
    .sort((a, b) => b.doctors_count - a.doctors_count)
    .slice(0, 8);

  if (items.length === 0) {
    return null;
  }

  return (
    <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      {items.map((s) => (
        <li key={s.slug}>
          <Link href={`/doctors?specialty=${encodeURIComponent(s.slug)}`} className="block h-full">
            <Card className="card-hover flex h-full flex-col justify-between p-4">
              <p className="font-semibold text-foreground">{s.name}</p>
              <p className="mt-2 text-sm text-muted-foreground">
                {tFormat("home.specialtyDoctorCount", { count: String(s.doctors_count) })}
              </p>
            </Card>
          </Link>
        </li>
      ))}
    </ul>
  );
}
