# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

**kEnAi MSG — Minimalist Site Generator.** Vanilla PHP (8.0+), **zero third-party
dependencies**. Despite the "Generator" name it is **not** a static site generator:
there is **no build step and no `src/`→`build/` split**. It renders HTML
**dynamically on each request** from a single project folder. This is a deliberate
design choice (the author moved away from Eleventy/Astro precisely to avoid the
two-folder build model). Do not add a static-export/build pipeline.

Requires PHP 8.0+ (`str_starts_with`, `str_ends_with`, `str_contains`).

## Commands

```bash
# Dev server — MUST use router.php (see below), not bare `php -S`
php -S localhost:8084 router.php

# Dev with errors shown + drafts/scheduled posts visible in listings
APP_DEBUG=1 php -S localhost:8084 router.php

# Syntax-check a PHP file (there is no test framework or linter config)
php -l path/to/file.php
```

There are **no automated tests, no build, no composer/npm**. Verification is done by
running the dev server and `curl`-ing routes. When changing security-relevant
behavior (`.htaccess`, directory blocking), verify against real **Apache** —
`php -S` ignores `.htaccess`. The deployment target is Apache (the root `.htaccess`
is the front controller); `router.php` only exists to make the PHP dev server behave
like Apache (serve static files, block `app/`+`content/`, route the rest to
`index.php`).

## Request flow (read these together to understand the whole)

1. **`.htaccess`** (Apache) or **`router.php`** (dev) → everything that isn't a real
   static file goes to **`index.php`** (front controller). `app/` and `content/` are
   blocked from direct HTTP access.
2. **`index.php`**: sets `$debug` from `APP_DEBUG`, sends security headers (CSP etc.),
   normalizes `$uri`, then dispatches:
   - **Special dynamic routes** (generate output + `exit`, no layout): `/sitemap.xml`,
     `/feed.xml`, `/robots.txt`.
   - **Friendly routes**: `/categorias/<tag>` → `content/categorias.php` (sets
     `$_GET['tag']`); `/blog/page/N` → `content/blog.php` (sets `$_GET['page']`).
   - **Default**: resolves `content/<uri>.{md,html,php}`, **confined to `content/`
     via `realpath()`** (path-traversal guard).
3. **`app/core/ContentFile.php`** splits YAML frontmatter from the body
   (`splitFrontMatter()` is the single source of frontmatter parsing). `{{ var }}`
   interpolation runs **only for `.md`/`.html`** (never `.php`).
4. **Render by extension**: `.md` → **MarKenai** (`setSafeMode(false)`); `.php` →
   `renderTemplate()` (cached `include` from `app/cache/`, **never `eval`**); `.html`
   → raw.
5. **Layout** (`app/layouts/page.php` or `post.php`, chosen by frontmatter `layout`,
   name-validated) wraps the content and pulls in `app/partials/` (`metatags`,
   `header`, `footer`, `search`).

## Key pieces

- **`app/core/MarKenai.php`** — the custom Markdown parser (replaced Parsedown,
  which was deleted). GFM-ish: headings with unique IDs, fenced code (`language-*`
  for Prism), Mermaid (`<pre class="mermaid">`), tables, task lists, footnote-free
  GFM, emoji, safe mode, URL sanitization (always blocks `javascript:`/`data:`).
- **`app/core/helpers.php`** — content queries + generators. Items returned by
  `getCollection()`/`getAllSiteContent()`/`getAllPosts()` have shape
  `['url', 'data' => <frontmatter>, 'filepath']`. Also: `isPublished()`
  (draft/future-date gating), `paginate()`, `loadSiteConfig()`,
  `renderSitemap()`/`renderFeed()`/`renderRobots()`. Memoized per-request via a
  `static` cache in `getAllSiteContent()`.
- **`app/config/site.yml`** — `url` is **optional**: if unset it's auto-derived from
  the request (set it explicitly in production for stable canonical URLs). Also
  `ogImage`, `twitter`, `perPage`.

## Content authoring model

- Inside a **`.php` content template**, available scope is `$page` (keys: `meta`,
  `filepath`, `uri`, `layout`, `base`, `site` — note: **no `content`**, that only
  exists in layouts), `$site`, and all helper functions. Always escape output with
  `htmlspecialchars`; filter/compare with the **raw** value, not the escaped one.
- **Frontmatter optional fields**: `image` (social card), `draft: true` (hide from
  listings/search/sitemap), `sitemap: false` (exclude from sitemap). Drafts and
  future-`date` posts are hidden in production but shown when `APP_DEBUG` is on;
  direct URL access is never blocked.
- `$page['base']` (path prefix, host-agnostic) is for assets/internal links;
  `$site['url']` is the absolute base for canonical/OG.

## Conventions / gotchas

- **`?>` inside a `//` line comment closes the PHP block** (it does NOT inside
  `/* */`). This has bitten this codebase — never write `?>` in a single-line
  comment.
- **`README.md` and `content/acerca.md` are kept in sync** — acerca.md is the
  on-site docs, rendered live by MarKenai. When editing it, avoid nested triple
  backticks (use a 4-backtick fence to show literal ```` ```mermaid ```` syntax).
- **Mermaid is self-hosted**: the layout only emits the loader script when the page
  rendered a real `<pre class="mermaid">`, and `assets/js/mermaid-init.js` only
  fetches the heavy `assets/js/mermaid.min.js` (user-supplied) when diagrams exist.
  Keep the CSP strict (`script-src 'self'`); do not add CDNs. Prefer self-hosting
  over CDN generally (Prism is also vendored locally).
- **`content/` stays at the top level** (not under `app/`) by intention: the engine
  is self-contained in `app/`; `content/` is the author's data.
- Errors: production hides them (logged via `error_log`); `APP_DEBUG=1` shows them.
  The top-level `catch` is `\Throwable` and scrubs output buffers.
