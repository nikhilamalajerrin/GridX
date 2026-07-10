/**
 * Small, console-owned copy of the marker-styling helpers used across
 * Fleet-Ops maps (packages/fleetops/addon/utils/route-colors.js), so the
 * route-planning map matches the house style without a cross-package import.
 */
export const ROUTE_COLOR_PALETTE = ['#0EA5E9', '#8B5CF6', '#F59E0B', '#10B981', '#F97316', '#EC4899', '#06B6D4', '#EF4444', '#84CC16', '#6366F1'];

export function colorForId(id = '') {
    let hash = 0;
    for (let i = 0; i < id.length; i++) {
        hash = (hash << 5) - hash + id.charCodeAt(i);
        hash |= 0;
    }
    return ROUTE_COLOR_PALETTE[Math.abs(hash) % ROUTE_COLOR_PALETTE.length];
}

/**
 * Green -> amber -> orange -> red -> purple progression so a leg's color
 * reads as "how far into the route" — first leg green (go), later legs
 * warmer, so a driver/dispatcher can tell visiting order from color alone,
 * not just the numbered stop markers.
 */
export const LEG_PROGRESSION_PALETTE = ['#16A34A', '#CA8A04', '#EA580C', '#DC2626', '#9333EA', '#0891B2', '#DB2777'];

export function colorForLegIndex(index = 0) {
    return LEG_PROGRESSION_PALETTE[index % LEG_PROGRESSION_PALETTE.length];
}

export function waypointIconHtml(label, bgColor) {
    return `<div style="
        width:32px;height:32px;
        background:${bgColor};
        border:2.5px solid #fff;
        border-radius:50%;
        display:flex;align-items:center;justify-content:center;
        color:#fff;font-weight:700;font-size:13px;
        box-shadow:0 2px 8px rgba(0,0,0,0.45);
        font-family:ui-sans-serif,system-ui,sans-serif;
    ">${label}</div>`;
}
