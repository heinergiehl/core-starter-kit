# Theming and Branding

This kit uses **templates + CSS variables + admin-managed branding settings** to keep theming flexible without rewriting your Tailwind setup.

## 1) Mental model (how styling is decided)
Customer-facing pages (marketing, auth, dashboard, and `/{locale}/docs`) follow this precedence:

1) **Template defaults** (palette + vibe)
2) **Branding overrides** saved in the database

The stable token API is **CSS variables** (RGB triplets):
- `--color-primary`
- `--color-secondary`
- `--color-accent`

Example:
```css
:root { --color-primary: 99 102 241; }
.dark { --color-primary: 129 140 248; }
```

## 2) Quickstart: change branding without touching CSS
1) Login as an admin (`admin@example.com` / `password`)
2) Visit the Admin Panel: `/admin`
3) Go to **Settings → Branding**
4) Set:
   - Application name
   - Logo + favicon
   - Template (for customer-facing pages)
   - Optional interface colors (primary/secondary/accent)
   - Optional email colors (emails only)
5) Click **Save changes**

## 3) Where things live (code + data)

### 3.1 Templates
- Config: `config/template.php`
- CSS: `resources/css/templates/_*.css`
- Default selector env var: `SAAS_TEMPLATE=default|void|aurora|prism|velvet|frost|ember|ocean`

### 3.2 Branding settings (Admin overrides)
- Admin UI: `app/Filament/Admin/Pages/ManageBranding.php`
- Model/table: `app/Domain/Settings/Models/BrandSetting.php` → `brand_settings`
- Runtime resolution: `app/Domain/Settings/Services/BrandingService.php`

## 4) Assets (logo/favicon) and hosting notes
- Uploads are stored on the `public` disk under `storage/app/public/branding/...`.
- Ensure you run `php artisan storage:link` in each environment.
- If your host restricts `/storage`, the app includes a fallback route:
  - `GET /branding/{path}`

## 5) Email branding workflow
1) Update email colors in **Settings → Branding**
2) Render fixtures:
```bash
php artisan email:qa:render
```
3) Run the checklist in [Email Client QA](email-client-qa.md)

## 6) Accessibility guardrails
- Prefer darker primary colors when using white button text (contrast matters).
- Verify focus states after palette changes.
- Check a few key pages in both light and dark modes.
