import { Badge } from "@/components/ui/badge";

export function TagList({ items }: { items: string[] }) {
  if (items.length === 0) {
    return null;
  }

  return (
    <div className="flex flex-wrap gap-1.5">
      {items.map((item) => (
        <Badge key={item} variant="secondary">
          {item}
        </Badge>
      ))}
    </div>
  );
}
