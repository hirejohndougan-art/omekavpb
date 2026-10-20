# Companion Theme for Visual Page Builder

A clean theme made to go with the **Visual Page Builder** module.

- Pages look the way you build them: it uses Omeka's standard page layout
  everywhere, with no page templates that rearrange blocks.
- Special Blocks pick up the theme's accent color.
- The menu is drawn in the style chosen in the module's **Menu builder**,
  one of 19 (five run down the side of the page), in the colors chosen there
  (see the module's README). On
  phones every style becomes a "Menu" button. Sub-menus open with buttons,
  so they work with a keyboard and touch, not only a mouse.
- A header or footer built in the module (Modules › Visual Builder) replaces
  the theme's own simple one on every page.
- No outside fonts or scripts are loaded; the fonts are ones already on
  visitors' computers.

Requires Omeka S 4.1 or later. It works without the module, but then the menu
style setting is the only way to choose the menu style, and Special Blocks and
visit info aren't available.

## Install

1. Unzip the download and find the folder named
   `visual-page-builder-companion`.
2. Upload that folder into Omeka's `themes/` folder, so you have
   `themes/visual-page-builder-companion/config/theme.ini`.
3. In Omeka's admin: Sites › your site › Theme, choose **Companion Theme for Visual Page Builder**.
4. Click the theme's settings (gear) to set your logo, colors, fonts, page
   width, and footer.

## Settings

| Setting | What it does |
|---|---|
| Logo | Replaces the site title in the header |
| Accent color, Header background, Page background | Colors |
| Heading font, Text font | Font styles (system fonts; nothing is downloaded) |
| Page width | Narrow, standard, or wide |
| Menu style | Used only if the menu builder hasn't chosen one |
| Show a search box | In the header |
| Footer text, social links | In the footer |
| Show visit info in the footer | Address and hours from the module's Visit info settings |

## For developers

| File | What it does |
|---|---|
| `config/theme.ini` | Theme settings |
| `view/layout/layout.phtml` | Header, content, footer; turns settings into CSS variables |
| `view/common/vpb-menu.phtml` | The menu, in the chosen style |
| `asset/css/style.css` | All styles; one section per menu style |
| `asset/js/theme.js` | Menu and sub-menu buttons |

Omeka's own templates are used for everything else (item and browse pages),
styled lightly in `style.css`.

## License

Copyright (C) 2026 Dickinson Historical Society

This program is free software; you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation; either version 2 of the License, or (at your option) any later
version. (SPDX: `GPL-2.0-or-later`.)

This program is distributed in the hope that it will be useful, but WITHOUT
ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
The full text is in the `LICENSE` file.
