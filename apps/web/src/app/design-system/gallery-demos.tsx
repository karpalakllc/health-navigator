"use client";

import { useState } from "react";
import { StarRatingInput } from "@/components/reviews/star-rating-input";
import { Button } from "@/components/ui/button";
import { FilterChip, RemovableChip } from "@/components/ui/chip";
import { Textarea } from "@/components/ui/field";

/* Interactive pieces of the /design-system gallery (sample content only). */

export function FilterChipDemo() {
  const [on, setOn] = useState<Record<string, boolean>>({
    "Прима нови пациенти": true,
  });
  const chips = [
    "Прима нови пациенти",
    "Има рецензии",
    "Отворено сега",
    "Зборува англиски",
  ];

  return (
    <div className="scroll-row -mx-5 flex gap-2 px-5 py-1 lg:mx-0 lg:flex-wrap lg:px-0">
      {chips.map((label) => (
        <FilterChip
          key={label}
          selected={Boolean(on[label])}
          onClick={() => setOn((s) => ({ ...s, [label]: !s[label] }))}
        >
          {label}
        </FilterChip>
      ))}
    </div>
  );
}

export function RemovableChipDemo() {
  const [items, setItems] = useState(["Кардиологија", "Скопје"]);

  return (
    <div className="flex flex-wrap gap-2">
      {items.map((label) => (
        <RemovableChip
          key={label}
          label={label}
          onRemove={() => setItems((s) => s.filter((x) => x !== label))}
        />
      ))}
      {items.length === 0 ? (
        <Button
          variant="soft"
          size="sm"
          onClick={() => setItems(["Кардиологија", "Скопје"])}
        >
          Врати ги филтрите
        </Button>
      ) : null}
    </div>
  );
}

export function StarRatingInputDemo() {
  const [value, setValue] = useState<number | null>(null);
  return <StarRatingInput value={value} onChange={setValue} />;
}

export function LoadingButtonDemo() {
  const [loading, setLoading] = useState(false);

  return (
    <Button
      loading={loading}
      onClick={() => {
        setLoading(true);
        window.setTimeout(() => setLoading(false), 1500);
      }}
    >
      {loading ? "Се испраќа…" : "Испрати на проверка"}
    </Button>
  );
}

export function TextareaCounterDemo() {
  const [text, setText] = useState("");
  const max = 5000;

  return (
    <Textarea
      label="Вашиот одговор"
      hint="Споделете искуство. Не објавувајте лични податоци (полно име, ЕМБГ, адреса, телефон)."
      value={text}
      maxLength={max}
      onChange={(e) => setText(e.target.value)}
      counter={`${text.length.toLocaleString("mk-MK")} / ${max.toLocaleString("mk-MK")}`}
    />
  );
}
