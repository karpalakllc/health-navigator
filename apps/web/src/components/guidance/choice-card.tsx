import { Icon } from "@/components/ui/icons";
import { cn } from "@/lib/cn";

/*
 * One answer as a large tappable card (≥56px): the whole card is the label,
 * the 24px control shows the state, and a selected card also gets a 2px ink
 * ring and a heavier label, so the choice never rests on colour alone.
 */
export function ChoiceCard({
  type,
  id,
  name,
  value,
  checked,
  onChange,
  label,
  hint,
  disabled = false,
}: {
  type: "radio" | "checkbox";
  id: string;
  name?: string;
  value: string;
  checked: boolean;
  onChange: () => void;
  label: string;
  /** A short explanation under the label, tied to the control. */
  hint?: string | null;
  disabled?: boolean;
}) {
  const round = type === "radio";

  return (
    <label
      htmlFor={id}
      className={cn(
        "flex min-h-14 cursor-pointer items-center gap-4 rounded-card bg-white px-5 py-4 text-ink shadow-card type-body",
        checked
          ? "font-semibold ring-2 ring-inset ring-ink"
          : "hover:ring-[1.5px] hover:ring-inset hover:ring-line-strong",
        disabled && !checked && "cursor-not-allowed text-ink-2 hover:ring-0",
      )}
    >
      <span className="relative inline-flex size-6 shrink-0">
        <input
          type={type}
          id={id}
          name={name}
          value={value}
          checked={checked}
          onChange={onChange}
          disabled={disabled}
          aria-describedby={hint ? `${id}-hint` : undefined}
          className={cn(
            "peer size-6 cursor-[inherit] appearance-none border-[1.5px] border-line-strong bg-white",
            "checked:border-ink checked:bg-ink",
            round ? "rounded-full" : "rounded-[6px]",
          )}
        />
        {round ? (
          <span
            aria-hidden="true"
            className="pointer-events-none absolute inset-0 m-auto size-2.5 rounded-full bg-white opacity-0 peer-checked:opacity-100"
          />
        ) : (
          <Icon
            name="check"
            size={16}
            className="pointer-events-none absolute inset-0 m-auto text-white opacity-0 peer-checked:opacity-100"
          />
        )}
      </span>
      <span className="flex min-w-0 flex-col gap-1">
        <span>{label}</span>
        {hint ? (
          <span id={`${id}-hint`} className="type-meta font-normal text-ink-2">
            {hint}
          </span>
        ) : null}
      </span>
    </label>
  );
}
