#!/usr/bin/env node
/**
 * GridX monochrome pass — hue-based desaturation of all blue-family colors
 * in compiled CSS/JS assets. Any color with hue 180–270° and meaningful
 * saturation is replaced by a grey of equal lightness, preserving contrast.
 * Greens/reds/ambers (status colors) are untouched. Visual-only transform.
 *
 * Usage: node monochrome.js <dist-dir>
 */
const fs = require('fs');
const path = require('path');

const HUE_MIN = 180;
const HUE_MAX = 270;
const SAT_MIN = 0.18;

function rgbToHsl(r, g, b) {
    (r /= 255), (g /= 255), (b /= 255);
    const max = Math.max(r, g, b),
        min = Math.min(r, g, b);
    let h = 0,
        s = 0;
    const l = (max + min) / 2;
    if (max !== min) {
        const d = max - min;
        s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
        if (max === r) h = ((g - b) / d + (g < b ? 6 : 0)) * 60;
        else if (max === g) h = ((b - r) / d + 2) * 60;
        else h = ((r - g) / d + 4) * 60;
    }
    return [h, s, l];
}

function isBlue(r, g, b) {
    const [h, s] = rgbToHsl(r, g, b);
    return h >= HUE_MIN && h <= HUE_MAX && s >= SAT_MIN;
}

function greyFor(r, g, b) {
    const [, , l] = rgbToHsl(r, g, b);
    return Math.round(l * 255);
}

function recolorContent(src) {
    let changed = 0;

    // #rrggbb and #rrggbbaa
    src = src.replace(/#([0-9a-fA-F]{6})([0-9a-fA-F]{2})?\b/g, (m, hex, alpha) => {
        const r = parseInt(hex.slice(0, 2), 16),
            g = parseInt(hex.slice(2, 4), 16),
            b = parseInt(hex.slice(4, 6), 16);
        if (!isBlue(r, g, b)) return m;
        changed++;
        const v = greyFor(r, g, b).toString(16).padStart(2, '0');
        return `#${v}${v}${v}${alpha || ''}`;
    });

    // #rgb shorthand
    src = src.replace(/#([0-9a-fA-F]{3})\b(?![0-9a-fA-F])/g, (m, hex) => {
        const r = parseInt(hex[0] + hex[0], 16),
            g = parseInt(hex[1] + hex[1], 16),
            b = parseInt(hex[2] + hex[2], 16);
        if (!isBlue(r, g, b)) return m;
        changed++;
        const v = Math.round(greyFor(r, g, b) / 17)
            .toString(16)
            .slice(0, 1);
        return `#${v}${v}${v}`;
    });

    // rgb()/rgba() — comma or space separated, optional alpha
    src = src.replace(/rgba?\(\s*(\d{1,3})[,\s]+(\d{1,3})[,\s]+(\d{1,3})([^)]*)\)/g, (m, r, g, b, rest) => {
        r = +r; g = +g; b = +b;
        if (r > 255 || g > 255 || b > 255 || !isBlue(r, g, b)) return m;
        changed++;
        const v = greyFor(r, g, b);
        const sep = m.includes(',') ? ', ' : ' ';
        return `${m.startsWith('rgba') ? 'rgba' : 'rgb'}(${v}${sep}${v}${sep}${v}${rest})`;
    });

    // hsl()/hsla() in the blue hue band
    src = src.replace(/hsla?\(\s*(\d{1,3})(?:deg)?[,\s]+([\d.]+)%[,\s]+([\d.]+)%([^)]*)\)/g, (m, h, s, l, rest) => {
        h = +h; s = +s;
        if (h < HUE_MIN || h > HUE_MAX || s < SAT_MIN * 100) return m;
        changed++;
        const sep = m.includes(',') ? ', ' : ' ';
        return `${m.startsWith('hsla') ? 'hsla' : 'hsl'}(0${sep}0%${sep}${l}%${rest})`;
    });

    return [src, changed];
}

function walk(dir, out = []) {
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        const p = path.join(dir, entry.name);
        if (entry.isDirectory()) walk(p, out);
        else if (/\.(css|js)$/.test(entry.name)) out.push(p);
    }
    return out;
}

const dist = process.argv[2] || 'dist';
let total = 0;
for (const file of walk(dist)) {
    const src = fs.readFileSync(file, 'utf8');
    const [next, changed] = recolorContent(src);
    if (changed > 0) {
        fs.writeFileSync(file, next);
        total += changed;
    }
}
console.log(`[GridX] monochrome: desaturated ${total} blue color literals`);
