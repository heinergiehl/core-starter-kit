# Theming and Branding

This kit provides a theming and branding system designed for SSR + Filament.

## 1) Design goals
- Simple and safe defaults
- Easy to customize without rewriting Tailwind config
- Global branding overrides via Admin Panel

---

## 2) Token model (CSS variables)

Use CSS variables as the stable design token API:
- `--color-primary`
- `--color-secondary`
- `--color-accent`
- `--color-bg`
- `--color-fg`

Light/dark:
- define token sets for `:root` and `.dark`

Example:
```css
:root { --color-primary: 99 102 241; }
.dark { --color-primary: 129 140 248; }
```

Tailwind integration:
- map Tailwind colors to `rgb(var(--color-primary) / <alpha-value>)` patterns

---

## 3) Branding settings

### 3.1 App-level settings
- app name
- logo
- support email
- support discord URL
- invoice name, billing email, tax ID, and footer (for provider invoices)

Store in:
- config for defaults
- DB for overrides (recommended), e.g., `settings` table

---

## 4) Theme editor UI (Admin Panel)
Provide a page for admins:
- upload logo
- select brand colors
- preview in a small live area
- save settings

Requirements:
- validate uploads (type/size)
- sanitize inputs
- audit log optional

---

## 5) Assets and sizing
- Provide light and dark logo variants if possible
- Keep a square icon for favicons and PWA usage
- Enforce max dimensions to keep layout stable

---

## 6) Email branding (optional)
- Use the same logo and primary color in notification emails
- Ensure emails remain readable in light/dark email clients

---

## 7) Accessibility and testing
- Check contrast for primary and accent colors
- Ensure focus states remain visible after theming
- Feature test: admin can update theme settings
