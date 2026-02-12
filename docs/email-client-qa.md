# Email Client QA

Use this checklist before shipping email template changes.

## 1) Render local fixtures

Generate a stable fixture set with both HTML and plain-text variants:

```bash
php artisan email:qa:render
```

Fixtures are written to `storage/app/email-qa` by default.

## 2) Validate required coverage

Confirm each transactional email has:

- HTML file (`*.html`)
- Plain-text file (`*.txt`)
- Valid links for CTA actions
- Expected branded colors for primary/secondary usage

## 3) Cross-client visual checks

Run the generated HTML fixtures through at least:

- Gmail (web + mobile)
- Outlook desktop
- Apple Mail

Recommended external tooling:

- Litmus
- Email on Acid

## 4) Accessibility checks

When changing branding colors:

- Keep strong contrast for body links and button text
- Avoid very light colors for text on white backgrounds
- Verify rendered CTA text remains readable

## 5) Regression checks

Run the email-focused tests:

```bash
php artisan test tests/Feature/Settings/BrandingServiceTest.php tests/Feature/Emails/EmailBrandingTemplateTest.php
```
