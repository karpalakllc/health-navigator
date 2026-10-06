/**
 * How many of `total` cards to show in a grid of `columns` so the last row
 * is full: the largest multiple of `columns` up to `max`. When there are
 * fewer cards than columns, all of them (one short row reads as intended,
 * a single orphan under a full row does not).
 */
export function fitToColumns(
  total: number,
  columns: number,
  max: number,
): number {
  const available = Math.max(0, Math.min(total, max));

  if (available < columns) {
    return available;
  }

  return available - (available % columns);
}
