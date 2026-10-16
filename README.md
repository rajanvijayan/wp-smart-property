# EstateCraft – Real Estate Listings & Portal

[![WordPress plugin version](https://img.shields.io/wordpress/plugin/v/wp-smart-property.svg)](https://wordpress.org/plugins/wp-smart-property/)
[![CI](https://github.com/rajanvijayan/wp-smart-property/actions/workflows/ci.yml/badge.svg)](https://github.com/rajanvijayan/wp-smart-property/actions/workflows/ci.yml)
[![License: GPL v2+](https://img.shields.io/badge/license-GPLv2%2B-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

Property listings for WordPress, with search, maps, agents and inquiries, and a front-end portal where agents submit and manage their own listings. EstateCraft is the 3.0 rewrite of **WP Smart Property**; the plugin slug is still `wp-smart-property`, and existing properties move over automatically. Everything is free.

**[WordPress.org listing](https://wordpress.org/plugins/wp-smart-property/)** · [Roadmap](https://github.com/rajanvijayan/wp-smart-property/blob/plan/3.0/docs/ROADMAP-3.0.md)

> **Status:** 3.0 is in development on the `release/3.0` branch. The version on WordPress.org is 2.0.x, which gets security fixes (2.0.1 on `release/2.0.1`).

## Features

**Ready in 3.0 so far**
- **Properties** keep their 2.0 URLs, and have their own permissions: agents manage only their own listings, while editors and estate managers manage everything.
- **Taxonomies:** property type, status (for sale, for rent, sold, rented), category, location (country, region, city) and features.
- **Property details** as typed, validated fields: price and rental period, price on request, living and land area, bedrooms, bathrooms, garages, year built, address and coordinates (with an option to hide the exact address), gallery, floor plans, video and virtual tour links, energy class, reference number, featured flag, open house times, agents and private notes. Private fields never appear in the REST API.
- **Agents and agencies** with public profiles, and **inquiries** stored as private records.
- **Roles:** Estate agent and Estate manager. Authors and contributors keep what they could do in 2.0.
- **Fast search index:** one indexed row per property (price, rooms, area, coordinates, status), kept in sync on every change and rebuildable at any time.
- **Property details editor** in wp-admin, in both the block and classic editors: tabs for the listing, rooms and size, location (OpenStreetMap pin picker with address lookup), photos and floor plans, open houses, agents, private notes and custom fields.
- **Settings app:** currency and area units, pages, map tiles and starting view, emails, agent portal, privacy, a **custom fields builder** (text, number, choice list, yes/no, date), and tools to rebuild the search index and review the 2.0 upgrade.
- **Admin list** with photo, price, rooms and agent columns, price sorting and status/type filters, plus a dashboard widget.
- **Property pages** for block and classic themes: photo gallery with a full-screen viewer, price and status, key facts, features, details (including custom fields), floor plans, video and virtual tour, an OpenStreetMap map (only the area when the exact address is hidden), open house times, agent contact cards, share and print, and similar properties.
- **Archives and agent pages:** property archives (also by type, status, location and feature) with featured properties first, agent and agency profiles with their listings, and an agent directory. Templates are editable in the Site Editor; classic themes can override them in `yourtheme/estatecraft/`.
- **16 blocks** (Property Listings, Featured Property, Agents, Gallery, Map, Details and more) and patterns for home pages.
- **schema.org data** (`RealEstateListing`, `RealEstateAgent`, breadcrumbs), Open Graph tags when no SEO plugin is active, and clean sitemaps.

**Coming in 3.0**
- Search with filters, shareable URLs and a list/map view on OpenStreetMap, with marker clustering and "search as I move the map".
- Inquiry and viewing forms, favourites, saved searches with email alerts, a mortgage calculator, privacy tools and spam protection.
- **Agent portal:** registration, a dashboard, multi-step listing submission with photo uploads and a map pin, moderation, expiry and renewals.
- Listing packages, with paid packages through WooCommerce when it is installed.
- Import and export, demo content, and the WordPress.org release.

## Upgrading from WP Smart Property 2.0

The upgrade runs by itself when the plugin is updated. It works in small batches, can resume, and never changes or deletes the 2.0 data.

| 2.0 | 3.0 |
|---|---|
| Property Type terms | Property types, same slugs, so `/property-type/…` URLs keep working |
| Property Category terms | Categories, same slugs, so `/property-category/…` URLs keep working |
| Country and City terms | Locations; each city goes under its country when all its properties are in one country |
| Bed Room and Bath Room terms | Bedroom and bathroom numbers ("3", "2 Bedrooms", "Three", "1.5"); names that are not numbers become features |
| Property address | Street address, queued for geocoding |
| Admin notes | Private notes |
| `/city/…`, `/country/…` | 301 redirect to the location page |
| `/bed-room/3/`, `/bath-room/2/` | 301 redirect to the property archive filtered by that number |
| `[post_grid id]` grids | Keep working (left to the Post Grid plugin when it is active) |

## Requirements

| | 3.0 (in development) | 2.0.x (current release) |
|---|---|---|
| WordPress | 6.5 or later | 4.7 or later, tested up to 7.1 |
| PHP | 7.4 or later, including 8.4 | 5.6 or later |

## Development

```bash
composer install          # autoloader, PHPCS and PHPUnit
composer lint             # PHPCS (WordPress Coding Standards, PHP 7.4+ compatibility)

npm ci && npm run build   # admin apps into build/ (npm start to watch)
npm run lint:js && npm run lint:css

bin/install-wp-tests.sh wordpress_test root '' localhost latest
WP_TESTS_DIR=/tmp/wordpress-tests-lib vendor/bin/phpunit               # core tests
WP_TESTS_DIR=/tmp/wordpress-tests-lib vendor/bin/phpunit --group ajax  # grid AJAX tests
```

`vendor/` and `build/` are not committed; the release workflow installs and builds them. Releases are deployed to WordPress.org by GitHub Actions when a version tag is pushed. See [RELEASING.md](RELEASING.md).

## License

GPLv2 or later. The `[post_grid]` grid is based on the [Post Grid](https://wordpress.org/plugins/post-grid/) plugin by PickPlugins (GPL).
