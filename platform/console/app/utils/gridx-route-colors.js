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

// Great-circle distance in km — used to pace the truck animation and to
// pick which segment of a route a given point along it falls on.
export function haversineKm([lat1, lng1], [lat2, lng2]) {
    const R = 6371;
    const dLat = ((lat2 - lat1) * Math.PI) / 180;
    const dLng = ((lng2 - lng1) * Math.PI) / 180;
    const a = Math.sin(dLat / 2) ** 2 + Math.cos((lat1 * Math.PI) / 180) * Math.cos((lat2 * Math.PI) / 180) * Math.sin(dLng / 2) ** 2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

// Compass bearing (degrees, 0 = north) from point A to point B, for
// rotating the truck icon to face its direction of travel.
export function bearingDeg([lat1, lng1], [lat2, lng2]) {
    const toRad = (d) => (d * Math.PI) / 180;
    const toDeg = (r) => (r * 180) / Math.PI;
    const y = Math.sin(toRad(lng2 - lng1)) * Math.cos(toRad(lat2));
    const x = Math.cos(toRad(lat1)) * Math.sin(toRad(lat2)) - Math.sin(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.cos(toRad(lng2 - lng1));
    return (toDeg(Math.atan2(y, x)) + 360) % 360;
}

export function truckIconHtml(bearing, color) {
    return `<div style="
        width:26px;height:26px;
        display:flex;align-items:center;justify-content:center;
        transform:rotate(${bearing}deg);
        filter:drop-shadow(0 2px 3px rgba(0,0,0,0.5));
    ">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="${color}" stroke="#fff" stroke-width="0.75">
            <path d="M12 2 L20 20 L12 16 L4 20 Z" />
        </svg>
    </div>`;
}
