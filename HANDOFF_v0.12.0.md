# Handoff: v0.12.0 + Companion Theme 0.3.0 (ten more menu styles)

John: "Give me 10 more templates. Some of them need to have a centered logo
with the menu underneath. Give some variety." (After seeing the five menu
styles.) Now 15 in `MenuController::STYLES`:

| Value | Name | How |
|---|---|---|
| centered-band | Centered, menu in a color band | full-width accent band (100vw trick), white links |
| masthead | Newspaper masthead | big name, menu between a double rule and a rule |
| centered-dividers | Centered, with dividers | vertical lines between links |
| centered-caps | Centered, small capitals | uppercase, letter-spaced, small |
| split | Logo in the middle | partial gets `$brand`; two lists around it in a grid; one list on phones |
| right | Logo left, menu right | nav pushed right |
| pills | Rounded buttons | pill links, current one filled |
| tabs | Tabs | row under the logo, underline marks current; search top-right |
| two-tier | Two rows | `.pb-topbar` (accent strip: name + search) above logo and menu |
| button | Menu button only | toggle on all screens opens a panel listing every page in columns; Escape / outside click closes |

All except mega/sidebar/bar show 2 levels with the shared drop-down rules.
Theme setting options, menu builder previews (sketch CSS, split/button/
two-tier get their own preview markup), and the Site menu block's options
(read from STYLES) all include them.

Tests: theme test now renders all 15 (depth, one logo, phone toggle target,
split halves, two-row search), 70 checks; menu builder 21. Full suite passes.
Not done: the mockup's Public site artboard still offers only the first five.
