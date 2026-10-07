/** Header the overlay sends its token in (never a query string or cookie). */
export const UX_OVERLAY_TOKEN_HEADER = "x-ux-overlay-token";

export const UX_HEATMAP_PATH = "/api/ux/heatmap";

export type UxHeatCell = {
  x: number;
  y: number;
  clicks: number;
  dead: number;
  rage: number;
};

export type UxHeatmapData = {
  route: string;
  viewport_class: string;
  from: string;
  to: string;
  x_buckets: number;
  y_step: number;
  cells: UxHeatCell[];
  page: {
    views: number;
    scroll: Record<string, number>;
    tfi: Record<string, number>;
  };
};
