=== EstateCraft – Real Estate Listings & Portal ===

Contributors: rajanit2000
Tags: real estate, property listings, property search, real estate agents, map
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 3.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Property listings with search, OpenStreetMap maps, agents and agencies, for block and classic themes. Formerly WP Smart Property. Free.

== Description ==

EstateCraft turns WordPress into a real estate website: list properties with all their details, let visitors search them by price, rooms and location on a list or a map, and show your agents and agencies. It works with block themes and classic themes, and everything is free.

EstateCraft is the new version of **WP Smart Property**. Existing properties, their pages and their addresses move over automatically when you update.

= Property details =

* Price with rental period ("£1,200 / month") or "price on request", in your currency and number format.
* Bedrooms, bathrooms (halves allowed), parking, living and land area in square feet or square metres, year built, energy class and your own reference number.
* Address with a map pin: drag it on an OpenStreetMap map, or let EstateCraft find it from the address. You can hide the exact address; visitors then only see the area.
* Photo gallery, floor plans, video and virtual tour links, open house times and private notes.
* **Custom fields:** add your own (text, number, choice list, yes/no, date), show them on property pages and use them as search filters.
* Property types, statuses (for sale, for rent, sold, rented), categories, locations (country, region, city) and features.

= Property pages =

* A photo gallery with a full-screen viewer, price and status, key facts, features, a details table, floor plans, video, map, open houses and the agents' contact details.
* Share and print buttons, and similar properties.
* Structured data for search engines (schema.org `RealEstateListing`, breadcrumbs), and share previews when no SEO plugin is active.

= Search and map =

* Filters for keyword (including the reference number), location, type, sale or rent, price, bedrooms, bathrooms, area, features and custom fields.
* Results as a list, a map, or both side by side, with price pins, clustering and "search as I move the map".
* Results update without reloading the page, the address bar always holds a link to the current search, and Back and Forward work. Everything also works without JavaScript.

= Agents and agencies =

* Agent and agency profiles with photo, phone, email, website, licence and languages, each with their listings.
* An Estate agent role (manages only their own listings) and an Estate manager role (manages everything, without access to site settings).

= Blocks and templates =

* 18 blocks, including Property Search, Search Results, Property Listings, Featured Property, Agents, Gallery, Map and Details, and patterns for home and search pages.
* Templates for property, archive, agent and agency pages that you can edit in the Site Editor. Classic themes get matching templates, which a theme can override in `yourtheme/estatecraft/`.

= Coming next =

Inquiry and viewing forms, favourites and saved searches with email alerts, a mortgage calculator, and a front-end portal where agents submit and manage their own listings.

== External services ==

EstateCraft uses two services from the OpenStreetMap project. Both can be changed or turned off in **Properties → Settings → Maps**.

* **Map tiles.** Maps on your site load their images from the OpenStreetMap tile servers (`tile.openstreetmap.org`), or from the tile provider you enter in the settings. When a visitor views a map, their browser requests the tiles from that server, which receives the visitor's IP address and the map area shown. Only pages with a map do this. [OpenStreetMap tile usage policy](https://operations.osmfoundation.org/policies/tiles/), [privacy policy](https://osmfoundation.org/wiki/Privacy_Policy).
* **Address lookup.** To place map pins from addresses, your server sends a property's street address, postcode and location to Nominatim (`nominatim.openstreetmap.org`): when an editor clicks "Find address on the map", and in the background for properties that have an address but no pin. No visitor data is sent. Results are cached for 30 days and requests are limited to one per second. [Nominatim usage policy](https://operations.osmfoundation.org/policies/nominatim/), [privacy policy](https://osmfoundation.org/wiki/Privacy_Policy).

== Installation ==

1. Install and activate EstateCraft from **Plugins → Add New**.
2. Go to **Properties → Settings** to set your currency, area unit and map.
3. Add agents under **Properties → Agents**, then add properties.

Your property archive is at `/property/`. To use a page of your own for searching, add the **Property Search** and **Property Search Results** blocks to it (or the "Property search page" pattern) and choose it in **Settings → Pages**.

== Frequently Asked Questions ==

= I use WP Smart Property 2.0. What happens when I update? =

The update moves your data to EstateCraft by itself, in small batches, and never deletes the 2.0 data:

* Property types and categories keep their slugs, so their links keep working.
* Countries and cities become locations, with each city under its country.
* "Bed Room" and "Bath Room" terms become bedroom and bathroom numbers; names that are not numbers become features.
* The property address becomes the street address and is placed on the map; admin notes become private notes.
* Old city, country, bedroom and bathroom links redirect to the new pages.
* `[post_grid]` grids keep working.

A notice in wp-admin links to a report of what moved.

= Does it work with my theme? =

Yes. Block themes get templates you can edit in the Site Editor; classic themes get templates that use the theme's header and footer. The styles are neutral and take your theme's fonts and colours.

= Can I hide the exact address of a property? =

Yes, with "Hide the exact address" in the property's Location tab. Visitors then see only the area, on the page, on maps and in the REST API, and searches by map area use the approximate position too.

= Do I need an API key for the maps? =

No. The maps use OpenStreetMap. For busy sites, enter the tile address of a tile provider in **Settings → Maps**.

= How do I change a template in a classic theme? =

Copy a file from the plugin's `templates` folder to `yourtheme/estatecraft/` and change it there.

== Screenshots ==

1. A property page: gallery, price, key facts, details, floor plans and the agent's contact details.
2. Search with filters, results and the map side by side.
3. The property details editor in wp-admin.
4. Settings, including the custom fields builder.
5. An agency page with its agents and listings.

== Changelog ==

= 3.0.0 =

WP Smart Property is now EstateCraft, rebuilt from the ground up. Your properties move over automatically.

* New: property details with typed fields, map pins, gallery, floor plans, video and tour, open houses and custom fields, in an editor that works in the block and classic editors.
* New: search with filters, list and map views, clustering and "search as I move the map".
* New: property, archive, agent and agency pages for block and classic themes, 18 blocks and patterns.
* New: agents and agencies, and Estate agent and Estate manager roles.
* New: settings for currency, units, maps and custom fields, and tools to rebuild the search index and find map pins.
* New: schema.org structured data.
* Changed: requires WordPress 6.5 and PHP 7.4.

= 2.0.1 =

* Security: deleting an exported layout file now only accepts an export file name, and every grid admin action checks a security token. Please update.
* Security: the property information box saves only its address and notes fields, sanitized.
* Security: grid search and "load more" only use published grids, and list unpublished posts only for people allowed to read them.
* Fix: no conflict when the Post Grid plugin is active as well.
* Fix: PHP warnings in the grid editor and settings screens on PHP 8.

= 2.0 =

* Update: custom taxonomy code.
* Add: grid options.
* Remove: property search widget.

= 1.0 =

* New: property search widget.

== Upgrade Notice ==

= 3.0.0 =
WP Smart Property becomes EstateCraft: new property details, search and maps. Your data moves over automatically. Requires WordPress 6.5 and PHP 7.4.
