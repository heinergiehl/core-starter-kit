# Blog Content Sync

Use markdown files in `content/blog` to create and update multilingual blog posts in the normal database-backed blog system.

Imported posts:
- become regular `blog_posts` records
- show up in the existing Filament blog admin
- can still be read, filtered, edited, published, archived, and translated in the UI
- keep categories and tags in the normal `blog_categories` / `blog_tags` tables

Important:
- file-managed posts can be edited in Admin, but the next `blog:sync-content` run can overwrite those synced fields
- posts created manually in Admin are not touched by the sync command
- categories and tags are shared across locales, not localized per language

## Folder Structure

Each article family lives in its own folder. Each locale is one markdown file named after the locale code.

```text
content/blog/
  stripe-vs-paddle/
    en.md
    de.md
    fr.md
  billing/launch-checklist/
    en.md
    es.md
```

Rules:
- the folder path is the stable article family key
- the filename without `.md` must be a supported locale from `config/saas.php`
- every folder can contain one file per locale
- nested article folders are allowed
- renaming a folder or moving a file changes the source key/path seen by the importer

If you rename or remove markdown files, re-run the sync with `--archive-missing` so old imported posts are archived safely.

## Markdown File Contract

Every markdown file must start with YAML front matter wrapped in `---`.

Required front matter:
- `title`

Optional front matter:
- `slug`
  If omitted, the importer generates it from `title`.
- `excerpt`
- `author_email`
  Must match an existing user email. If omitted, the command falls back to the first admin user, or the `--author=` option.
- `category`
  Created automatically if it does not exist yet.
- `tags`
  YAML list or comma-separated string. Tags are created automatically if they do not exist yet.
- `status`
  `draft`, `published`, or `archived`. Defaults to `draft`.
- `published_at`
- `meta_title`
- `meta_description`
- `featured_image`
  Store a public disk path such as `blog-images/stripe-vs-paddle.png`.

Example:

```md
---
title: Stripe vs Paddle for SaaS Billing
slug: stripe-vs-paddle-for-saas-billing
excerpt: Practical comparison for SaaS founders.
author_email: admin@example.com
category: Billing
tags:
  - Stripe
  - Paddle
  - SaaS
status: published
published_at: 2026-03-21 09:00:00
meta_title: Stripe vs Paddle for SaaS Billing
meta_description: Compare Stripe and Paddle for subscriptions, tax, and global SaaS sales.
featured_image: blog-images/stripe-vs-paddle.png
---
# Stripe vs Paddle for SaaS Billing

Write the full article in markdown here.
```

## Sync Command

Preview changes without writing to the database:

```bash
php artisan blog:sync-content --dry-run
```

Run the real sync:

```bash
php artisan blog:sync-content
```

Archive imported posts whose source files no longer exist:

```bash
php artisan blog:sync-content --archive-missing
```

Use a custom root path:

```bash
php artisan blog:sync-content --path=content/blog
```

Provide a fallback author when files omit `author_email`:

```bash
php artisan blog:sync-content --author=admin@example.com
```

## What The Sync Does

For each markdown file, the importer:
1. validates the folder structure, locale filename, and front matter
2. groups locale variants from the same folder into one translation family
3. creates or reuses categories and tags from front matter
4. creates or updates the matching `blog_posts` record
5. stores both `body_markdown` and rendered `body_html`
6. marks the post as markdown-managed so Admin can show where it came from

The importer does not modify manually created posts unless they explicitly share the same markdown source tracking fields, which the UI does not set.

## Admin Workflow

After import, the posts appear in the normal Admin blog resource.

That means you can still:
- create manual posts in Admin
- edit imported posts in Admin
- manage categories and tags in Admin
- publish or archive imported posts in Admin

The key constraint is ownership:
- manual posts stay manual
- markdown-managed posts stay tied to their source files
- future sync runs update markdown-managed posts again
