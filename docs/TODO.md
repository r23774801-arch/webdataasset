# TODO - Fix Filter Popup Positioning Bug (aset-ga.html)

## ✅ 1. CSS Improvements
- [x] Increased `.filter-popup` width from 260px → 300px for more breathing room
- [x] Removed `max-height: 320px` — no more forced scrollbar
- [x] Removed `overflow-y: auto` from base CSS (handled dynamically by JS)
- [x] Removed `.filter-popup::-webkit-scrollbar` custom styles
- [x] Added `appearance: none` to `<select>` with custom SVG dropdown arrow
- [x] Added `padding-right: 30px` for custom arrow icon space

## ✅ 2. JavaScript - Robust `toggleFilterPopup` rewrite
- [x] Close all other popups first
- [x] Use `getBoundingClientRect()` on the icon element
- [x] Position below icon with 6px gap, centered horizontally
- [x] **Left boundary**: Ensure popup doesn't overflow left edge (min 15px offset)
- [x] **Right boundary**: Ensure popup doesn't overflow right edge
- [x] **Bottom boundary**: If popup overflows bottom viewport, position it ABOVE the icon
- [x] Dynamic `overflow-y: auto` set via JS when needed

## ✅ 3. JavaScript - Add missing Filter Functions
- [x] Added `applyPopupFilter(col)` function
- [x] Added `clearPopupFilter(col)` function
- [x] Added document-level outside-click event listener
- [x] Initialize `window.popupFilters = {}` state

## ⬜ 4. Testing Checklist (manual)
- [ ] Click filter icon - popup appears centered under the icon
- [ ] Scroll table horizontally - popup stays anchored to viewport position
- [ ] Click near right edge - popup adjusts to stay within viewport
- [ ] Click near bottom of page - popup flips above the icon
- [ ] Click outside popup - popup closes
- [ ] Apply filter - data filters correctly
- [ ] Clear filter - data resets

