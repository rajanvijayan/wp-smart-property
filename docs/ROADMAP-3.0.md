# EstateCraft 3.0: real estate listings and portal

The plugin keeps the WordPress.org slug and text domain `wp-smart-property`. It is released as **EstateCraft – Real Estate Listings & Portal**. Everything stays free.

## Where we start

### Live release 2.0 on WordPress.org
- Last updated January 2018, tested up to WordPress 4.7, about 10 active installs.
- There is no SVN tag, so the site is served from `trunk`.
- **Data:**
  - post type `property` (public, archive at `/property/`)
  - six hierarchical taxonomies registered under display names with spaces: `Property Type`, `Property Category`, `City`, `Bed Room`, `Bath Room` and `Country`
  - post meta `property_address` and `admin_notes`
- **Embedded Post Grid:** a renamed copy of the third-party Post Grid plugin (2.0.18), with:
  - post type `post_grid`
  - shortcode `[post_grid id]`
  - option `post_grid_layout_content`
  - meta `post_grid_meta_options`
- **Security problems:**
  1. `wp_ajax_post_grid_ajax_remove_export_content_layout` passes a POSTed path to `unlink()` with no nonce. An attacker can trick a logged-in admin (CSRF) into deleting any file the web server can write, such as `wp-config.php`.
  2. The property meta box saves every key in `property_additional_field[...]`, unsanitized, on posts as well as properties. Any author can write arbitrary meta, including protected keys.
  3. Other Post Grid AJAX handlers (`load_more`, `search`) are public and build queries from POSTed grid settings.
- **Conflict:** if the real Post Grid plugin is active, the `PostGrid` class, the `post_grid` post type and the shortcode collide, which can cause a fatal error.

### GitHub `master`
An unfinished 2024 skeleton (about 220 lines, never released) under an MIT licence. It is replaced. The first commit of `release/3.0` imports 2.0 from WordPress.org as the baseline, as was done for SlideCraft.

## Goals
- **For visitors:** a modern listings site. Fast search with filters and a map, rich property pages, agents, and inquiries that reach a person.
- **For agents:** a front-end portal to submit and manage listings, answer inquiries and renew listings, with packages and limits set by the site owner.
- **Theme support:** works with block themes (templates, patterns, blocks) and classic themes (template loader with theme overrides).
- **Upgrade safety:** 2.0 sites upgrade without losing properties, terms, addresses or `[post_grid]` pages.
- **Engineering:** WordPress coding standards, PHP 7.4 to 8.4, WordPress 6.5+ (Interactivity API), accessible, and Plugin Check clean.

## M0: 2.0.1 security release (first, before any rewrite)
A small patch on top of 2.0 so current sites are safe now:
- **File-delete AJAX handler:** remove it. Exports are no longer written to `uploads/`.
- **Other admin AJAX handlers:** add nonce and capability checks to every one of them.
- **Meta box:**
  - save only `property_address` and `admin_notes`
  - only on `property` posts
  - sanitize with `sanitize_textarea_field`
  - check `edit_post`
- **Public `load_more` and `search` handlers:** accept only published `post_grid` IDs, clamp `per_page` and `paged`, and sanitize terms and keyword.
- **Conflict guard:** load the embedded grid only when `class_exists( 'PostGrid' )` is false. Otherwise show an admin notice.
- **Release:** add the release tooling (CI, deploy and assets workflows, `.distignore`, `RELEASING.md`), bump to 2.0.1 and deploy. The tag is pushed by the owner.

## Architecture
- **PHP:**
  - namespace `EstateCraft`, Composer classmap autoload, one bootstrap `wp-smart-property.php`
  - text domain `wp-smart-property`
  - prefixes `estatecraft_` and `ESTATECRAFT_`
- **JavaScript:** `@wordpress/scripts` with these entries:
  - admin app (settings, inquiries, packages)
  - property editor panel
  - blocks
  - front end (Interactivity API stores for search, map, favourites and forms)
  - portal dashboard
- **REST namespace `estatecraft/v1`:**
  - properties: search, map bounds, similar
  - agents and agencies
  - inquiries
  - favourites and saved searches
  - portal: my listings, media, renew, mark sold
  - packages
  - settings
  - geocode (server-side and cached)
- **Search index:** a custom table `{prefix}estatecraft_index`. Price, beds, baths, areas, status, type, location and latitude/longitude are kept in one indexed row per property, synced on save and on term changes. `meta_query` is too slow for faceted search and map bounds. The table is rebuilt from post data at any time (WP-CLI and a tools button).
- **Other tables:**
  - `{prefix}estatecraft_saved_searches`: user, criteria JSON, frequency, last sent
  - `{prefix}estatecraft_events`: views and inquiry counts, for agent stats
  - `dbDelta` with a schema version option
- **Background work:**
  - geocoding queue, alert emails, listing expiry and index rebuilds
  - batched WP-Cron jobs, plus Action Scheduler when WooCommerce provides it
- **Release engineering:** copied from SlideCraft:
  - project notes, `.distignore` and the CI, deploy and assets workflows
  - the assets workflow guard: it skips until the Stable tag exists in SVN
  - PHPUnit, Jest, Playwright, Plugin Check and the shipped-PHP lint

## Data model
- **Post type `property`** (kept, so URLs stay the same):
  - supports: title, editor, excerpt, thumbnail, author, revisions, custom-fields
  - `capability_type` becomes `estate_property` with `map_meta_cap`, so agents only edit their own listings
- **Registered meta** (`show_in_rest`, typed and sanitized):
  - status details: price, price suffix and period (month or week for rent), price on request
  - sizes: living area and land area with unit (sq ft or m²), bedrooms, bathrooms, garages, year built
  - address: street, city, region, postcode, country code, latitude, longitude, hide exact address
  - media: gallery (attachment IDs), floor plans (title, image, area, beds, baths), video URL, virtual tour URL
  - other: energy class, reference/MLS ID, featured, open houses (date and time ranges), agent IDs, private notes (from `admin_notes`)
  - portal: expiry date, package ID
- **Taxonomies** (clean keys, translatable labels):
  - `property_type` (house, apartment, land and so on, hierarchical)
  - `property_status` (for sale, for rent, sold, rented)
  - `property_location` (country, region, city, hierarchical)
  - `property_feature` (amenities)
  - `property_label` (e.g. "New", "Reduced")
- **Agents:** post type `estate_agent` (public profile: photo, phone, email, languages, social links, license, linked WP user). `estate_agency` groups agents.
- **Inquiries:** post type `estate_inquiry`, private. It stores name, email, phone, message, property, agent, status (new, replied, closed) and consent. Email goes to the agent with a copy to the admin. Personal data export and erase hooks are included.
- **Favourites:** user meta, plus `localStorage` for visitors, merged at login.
- **Custom fields:** the site owner can add extra fields (text, number, select, checkbox, date). They appear in the editor, the portal form, search filters and the details block.

## Front end
- **Single property:**
  - gallery with lightbox and a full-screen viewer
  - key facts bar and description
  - features grid and floor plans
  - map with nearby points, an approximate circle when the address is hidden
  - video and virtual tour
  - mortgage calculator
  - agent card, inquiry form and "schedule a viewing"
  - similar properties, share and print view
- **Search and archive:**
  - filter bar (keyword, location autocomplete, type, status, price range, beds, baths, area, features, custom fields)
  - sorting, and grid, list or split list-and-map view
  - "search as I move the map", marker clustering, URL-synced state with shareable links, AJAX paging with server-rendered first paint
- **Agent and agency pages:** profile and active listings.
- **Blocks:**
  - Property Search, Property Listings (query, filters, layout grid/list/carousel), Property Map, Featured Property
  - Property Details (pick fields), Property Gallery, Floor Plans
  - Agent Card, Inquiry Form, Mortgage Calculator
  - Portal Dashboard and Submit Property
- **Block themes:** templates `single-property`, `archive-property` and `taxonomy-property_*`, plus patterns (hero search, featured grid, agent team, call to action).
- **Classic themes:** a template loader with overrides in `yourtheme/estatecraft/`.
- **Maps:**
  - Leaflet and OpenStreetMap tiles, with attribution
  - optional tile provider URL and key (for example MapTiler or Stadia) for busy sites
  - marker clustering, lazy loaded when the map scrolls into view
  - geocoding through Nominatim from the server: at most one request per second, a proper User-Agent, results cached, never in bulk
  - editors can drag the pin to correct a position
- **SEO:**
  - schema.org `RealEstateListing` with `Offer` and the place's `GeoCoordinates`
  - `RealEstateAgent` on agent pages
  - breadcrumbs, Open Graph images, and properties in core sitemaps
- **Performance and accessibility:**
  - assets only on pages that need them
  - responsive images, LCP image hinting, no layout shift
  - keyboard-accessible map alternatives: list view and listing links
  - WCAG 2.2 AA forms with errors announced
  - `prefers-reduced-motion`

## Portal (front end)
- **Roles:** `estate_agent` (own listings) and `estate_manager` (all listings, inquiries and agents). An administrator can grant either role to existing users.
- **Registration:** core registration, plus "register as an agent" with optional admin approval. Login and lost-password pages use core.
- **Dashboard** (block or `[estatecraft_dashboard]`):
  - **My listings:** status, views, inquiries, expiry, and actions to edit, mark sold or rented, renew and delete.
  - **Submit or edit listing:** a multi-step form (details, location with map pin, media upload through REST with size and type limits, features, review). New listings go to "pending review" unless the site trusts the agent.
  - **Inquiries inbox:** reply by email, set status.
  - **Profile:** agent details and photo.
  - **Favourites and saved searches:** for every logged-in user.
- **Moderation:** an approval queue in wp-admin, email notifications, and rejection reasons.
- **Listing expiry:** per package or site setting. Reminder emails before expiry, then expired listings move to draft.
- **Saved-search alerts:** daily or weekly digest emails with new matches, and one-click unsubscribe links.
- **Spam protection:** honeypot and time check on every form, a per-IP rate limit, and optional Cloudflare Turnstile or hCaptcha (off by default for privacy).

## Packages (free, payments through WooCommerce when present)
- Packages set the number of listings, number of featured listings, listing duration and image limit.
- **Free packages:** work without any shop.
- **Paid packages:** map to WooCommerce products. When the order completes, the agent's package is credited. EstateCraft never handles card data and has no payment gateway.
- Featured upgrades work as one-off products.
- **Admin:** a packages screen and an agent credits overview.

## wp-admin
- **Property editor:**
  - a "Property details" panel built in React
  - works in the block editor sidebar and as a meta box in the classic editor
  - grouped fields, map pin picker with geocoding, gallery and floor-plan managers, agent picker
- **List table:** price, status, type, city, agent and expiry columns, filters and quick edit for price and status.
- **Settings app (React):**
  - general (currency, number format, units)
  - pages (search results, dashboard, submit)
  - search filters and order
  - custom fields builder
  - maps (tiles, default centre, clustering)
  - emails (templates with placeholders)
  - portal (approval, roles, expiry), packages
  - privacy (consent text, retention of inquiries)
- **Inquiries screen:** list, status, CSV export.
- **Tools:** rebuild index, re-geocode missing coordinates, import/export (CSV and JSON, with a column-mapping step), demo content installer, and the 2.0 migration report.
- **Dashboard widget:** new inquiries, pending listings and expiring listings.

## Upgrade from 2.0
Runs automatically on update. It is idempotent and resumable in batches, and never deletes old data.
- **Properties:** keep their IDs, URLs and content.
- **Taxonomies** (terms copied, assignments kept):
  - `Property Type` → `property_type`
  - `Property Category` → `property_label`
  - `Country` and `City` → `property_location` (each City placed under its Country when a property has both)
- **Bedrooms and bathrooms:** `Bed Room` and `Bath Room` term names are parsed to numbers and stored as meta. Names that don't parse are kept as features.
- **Old term URLs:** the old taxonomies stay registered, hidden from the UI, so old term URLs redirect with a 301 to the new archives.
- **Addresses:** `property_address` is copied into the street field and queued for geocoding. `admin_notes` becomes private notes.
- **Grids:**
  - `[post_grid id]` keeps working, rendered by a compatibility layer as a Property Listings grid using the grid's saved post types, taxonomies and paging
  - if the real Post Grid plugin is active, it is left alone
  - `post_grid` posts are kept
- **Notice and report:** an admin notice and a migration report summarize what moved and anything that needs a look.

## Milestones (about 18 weeks for one developer)

| # | Scope | Estimate |
|---|---|---|
| M0 | 2.0.1 security release and release tooling | 2–3 days |
| M1 | Foundations: bootstrap, data model, registered meta, roles and caps, search index table, REST base, 2.0 migration with tests | 2 w |
| M2 | wp-admin: property editor panel, list table, agents and agencies, settings app, custom fields builder | 2.5 w |
| M3 | Front end: templates (block and classic), single property, archives, agent pages, blocks and patterns, schema | 3 w |
| M4 | Search and map: index queries, filters, URL state, Leaflet map with clusters and bounds search, geocoding queue | 2.5 w |
| M5 | Inquiries, favourites, saved searches and alerts, mortgage calculator, privacy tools, spam protection | 1.5 w |
| M6 | Portal: registration, dashboard, submission form with uploads, moderation, expiry and renewals | 3 w |
| M7 | Packages and WooCommerce bridge, featured upgrades, credits | 1.5 w |
| M8 | Import/export, demo content, README and readme.txt, WordPress.org assets, Playwright suite, performance and accessibility audit, upgrade test on a real 2.0 site, release 3.0.0 | 2 w |

**Release process, as for SlideCraft:**
1. Work on `release/3.0`.
2. Before release: CI green (PHPCS, ESLint, Stylelint, PHPUnit on 6.5 and latest, Plugin Check, shipped-PHP lint).
3. The owner pushes the `3.0.0` tag, the deploy finishes, then merge to `master`.

## Risks
- **OpenStreetMap usage policies.** Nominatim allows one request per second and no bulk geocoding. Shared tiles aren't for heavy traffic. Hence server-side caching, manual pins and a tile provider setting.
- **Privacy.** Inquiries hold personal data, and map tiles load from a third party. Covered by privacy policy text, consent, retention settings, and export/erase hooks.
- **Theme variety.** Classic themes need the template loader and neutral styles; block themes need templates and patterns. Tested with Twenty Twenty-Five and a classic theme.
- **Portal security.** Front-end uploads and editing get capability checks on every REST route, file type and size limits, and ownership checks on every listing action.
- **Odd legacy data.** Taxonomy names with spaces and free-text bed/bath terms are handled by tolerant parsing and the migration report.

## Verification
- **PHPUnit:**
  - migration from a seeded 2.0 dataset (terms, meta, `post_grid` grids)
  - index sync on save and term change
  - search queries (filters, bounds, sorting)
  - capabilities (agents can't touch others' listings)
  - REST permissions
  - package credit and expiry logic
  - inquiry spam rules
  - privacy export and erase
- **Jest:** search state and URL sync, form validation, mortgage maths.
- **Playwright:**
  - visitor search with filters and the map
  - property page inquiry
  - agent registration, submit, approve, edit, mark sold
  - favourites and saved-search email (captured locally)
  - WooCommerce package purchase
- **Upgrade test:** install the real 2.0, create data with it, replace the files with the 3.0 build as an update does, then check pages, URLs, redirects and the migration report.
- **Release checks:** Plugin Check clean, a Lighthouse pass on single and search pages, and keyboard and screen reader checks of search, map and forms.
