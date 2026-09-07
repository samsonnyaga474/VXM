# VXM Design System

**Version:** 2.2 — Final Experience Pass  
**Purpose:** Single source of truth for visual language, components, motion, and accessibility.

## Brand Philosophy
Serious digital earning platform. Trustworthy, technically competent, calm confidence. Cinematic only on homepage. Functional and clear everywhere else. Core message: Tasks → Earnings → Wallet → Network → Withdrawal.

## Anti-AI-slop rules
No random neon, excessive glass, meaningless blobs, invented stats, decorative animation without purpose, inconsistent buttons, weak hierarchy.

## Color Tokens
--bg-deep: #030712; --bg-base: #060b18; --bg-elevated: #0b1224; --bg-card-solid: #0f172a;
--accent: #22d3ee; --accent-soft: #67e8f9; --accent-dim: rgba(34,211,238,0.15); --accent-glow: rgba(34,211,238,0.28);
--success: #34d399; --danger: #f87171; --warning: #fbbf24;
--text-primary: #f1f5f9; --text-secondary: #94a3b8; --text-muted: #64748b;
--border: rgba(148,163,184,0.12); --border-strong: rgba(148,163,184,0.22); --border-accent: rgba(34,211,238,0.35);

## Typography
Display: Space Grotesk. Body: Inter. Large editorial on homepage only.

## Motion Principles
Homepage: smooth scroll where justified, mouse local influence, scroll narrative state, ambient particles.
App/Admin: subtle entrances only. No scroll hijacking.
Always respect prefers-reduced-motion. Mobile disables mouse tracking and reduces particles.

## Homepage Motion Architecture
1. Mouse → local parallax / tilt on Energy Core and layers
2. Scroll → major narrative (core rotation, scale, position, depth)
3. Time → ambient particles only
Systems blend via interpolation. No fighting.

## Energy Core
Proprietary CSS + canvas visual. Responds to mouse and scroll progress. Original to VXM.

## Components
Standardized buttons, cards, forms, tables, badges, nav, footer, empty/loading states.

## Performance
Homepage-only heavy motion. Conditional loading. Lightweight product pages.
