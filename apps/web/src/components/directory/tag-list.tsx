export function TagList({ items }: { items: string[] }) {
  if (items.length === 0) {
    return null;
  }

  return (
    <div className="flex flex-wrap gap-2.5">
      {items.map((item) => (
        <span key={item} className="directory-tag">
          {item}
        </span>
      ))}
    </div>
  );
}
