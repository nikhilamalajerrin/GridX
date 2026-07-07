#!/bin/sh
# GridX monochrome pass — rewrites Tailwind blue/sky/indigo palettes to
# neutral greys in the compiled CSS. Runs after `pnpm build`; visual only.
set -e
DIST="${1:-dist}"

recolor() {  # $1 = from-hex, $2 = from-rgb-triplet, $3 = to-hex, $4 = to-rgb-triplet
  cs_from="$(echo $2 | sed 's/ /, /g')"
  cs_to="$(echo $4 | sed 's/ /, /g')"
  find "$DIST" \( -name '*.css' -o -name '*.js' \) -exec sed -i \
    -e "s/$1/$3/gI" \
    -e "s/rgb($2/rgb($4/g" \
    -e "s/$cs_from/$cs_to/g" {} +
}

# shade   blue-hex  blue-rgb        -> neutral-hex neutral-rgb
recolor '#eff6ff' '239 246 255' '#fafafa' '250 250 250'
recolor '#dbeafe' '219 234 254' '#f5f5f5' '245 245 245'
recolor '#bfdbfe' '191 219 254' '#e5e5e5' '229 229 229'
recolor '#93c5fd' '147 197 253' '#d4d4d4' '212 212 212'
recolor '#60a5fa' '96 165 250'  '#a3a3a3' '163 163 163'
recolor '#3b82f6' '59 130 246'  '#737373' '115 115 115'
recolor '#2563eb' '37 99 235'   '#525252' '82 82 82'
recolor '#1d4ed8' '29 78 216'   '#404040' '64 64 64'
recolor '#1e40af' '30 64 175'   '#262626' '38 38 38'
recolor '#1e3a8a' '30 58 138'   '#171717' '23 23 23'
recolor '#172554' '23 37 84'    '#0a0a0a' '10 10 10'
# sky
recolor '#f0f9ff' '240 249 255' '#fafafa' '250 250 250'
recolor '#e0f2fe' '224 242 254' '#f5f5f5' '245 245 245'
recolor '#bae6fd' '186 230 253' '#e5e5e5' '229 229 229'
recolor '#7dd3fc' '125 211 252' '#d4d4d4' '212 212 212'
recolor '#38bdf8' '56 189 248'  '#a3a3a3' '163 163 163'
recolor '#0ea5e9' '14 165 233'  '#737373' '115 115 115'
recolor '#0284c7' '2 132 199'   '#525252' '82 82 82'
recolor '#0369a1' '3 105 161'   '#404040' '64 64 64'
recolor '#075985' '7 89 133'    '#262626' '38 38 38'
recolor '#0c4a6e' '12 74 110'   '#171717' '23 23 23'
# indigo
recolor '#eef2ff' '238 242 255' '#fafafa' '250 250 250'
recolor '#e0e7ff' '224 231 255' '#f5f5f5' '245 245 245'
recolor '#c7d2fe' '199 210 254' '#e5e5e5' '229 229 229'
recolor '#a5b4fc' '165 180 252' '#d4d4d4' '212 212 212'
recolor '#818cf8' '129 140 248' '#a3a3a3' '163 163 163'
recolor '#6366f1' '99 102 241'  '#737373' '115 115 115'
recolor '#4f46e5' '79 70 229'   '#525252' '82 82 82'
recolor '#4338ca' '67 56 202'   '#404040' '64 64 64'
recolor '#3730a3' '55 48 163'   '#262626' '38 38 38'
recolor '#312e81' '49 46 129'   '#171717' '23 23 23'

echo "[GridX] monochrome pass complete"
