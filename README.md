# WP Smart Property

Real estate property listings for WordPress: a `property` post type with type, category, city, bedroom, bathroom and country taxonomies, and property grids through the `[post_grid id]` shortcode.

**[WordPress.org listing](https://wordpress.org/plugins/wp-smart-property/)**

> 2.0.x receives security fixes. Version 3.0 is a rewrite, released as **EstateCraft**; see `docs/ROADMAP-3.0.md` on the `plan/3.0` branch.

## Requirements

| | |
|---|---|
| WordPress | 4.7 or later (tested up to 7.1) |
| PHP | 5.6 or later (tested on 8.3) |

## Development

```bash
composer install                     # test tools
bin/install-wp-tests.sh wordpress_test root '' localhost latest
WP_TESTS_DIR=/tmp/wordpress-tests-lib vendor/bin/phpunit --group ajax
```

Releases are deployed to WordPress.org by GitHub Actions when a version tag is pushed. See [RELEASING.md](RELEASING.md).

## License

GPLv2 or later. The property grid is based on the [Post Grid](https://wordpress.org/plugins/post-grid/) plugin by PickPlugins (GPL).
