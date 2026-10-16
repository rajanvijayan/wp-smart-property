#!/usr/bin/env bash
# Install WordPress and the WordPress PHPUnit test library for running tests/phpunit.
#
# Usage: bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-db-create]
#   wp-version: "latest" (default) or a release such as 6.4 or 6.6.2.
#
# Then run: WP_TESTS_DIR=${TMPDIR:-/tmp}/wordpress-tests-lib vendor/bin/phpunit

set -euo pipefail

if [ $# -lt 3 ]; then
	echo "usage: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-db-create]"
	exit 1
fi

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4-localhost}
WP_VERSION=${5-latest}
SKIP_DB_CREATE=${6-false}

TMPDIR=${TMPDIR-/tmp}
TMPDIR=$(echo "$TMPDIR" | sed -e "s/\/$//")
WP_TESTS_DIR=${WP_TESTS_DIR-$TMPDIR/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR-$TMPDIR/wordpress}

if [ "$WP_VERSION" = "latest" ]; then
	WP_VERSION=$(curl -fsSL https://api.wordpress.org/core/version-check/1.7/ | grep -o '"version":"[^"]*' | head -1 | sed 's/"version":"//')
fi
echo "WordPress $WP_VERSION"

# WordPress core.
if [ ! -f "$WP_CORE_DIR/wp-settings.php" ]; then
	mkdir -p "$WP_CORE_DIR"
	curl -fsSL "https://wordpress.org/wordpress-$WP_VERSION.tar.gz" | tar -xz --strip-components=1 -C "$WP_CORE_DIR"
fi

# Test library: tests/phpunit/includes and data from the matching wordpress-develop tag.
if [ ! -f "$WP_TESTS_DIR/includes/functions.php" ]; then
	DEVELOP_DIR=$(mktemp -d)
	git clone --quiet --depth 1 --branch "$WP_VERSION" --filter=blob:none --sparse https://github.com/WordPress/wordpress-develop.git "$DEVELOP_DIR"
	git -C "$DEVELOP_DIR" sparse-checkout set tests/phpunit/includes tests/phpunit/data
	mkdir -p "$WP_TESTS_DIR"
	cp -R "$DEVELOP_DIR/tests/phpunit/includes" "$DEVELOP_DIR/tests/phpunit/data" "$WP_TESTS_DIR/"
	cp "$DEVELOP_DIR/wp-tests-config-sample.php" "$WP_TESTS_DIR/wp-tests-config.php"
	rm -rf "$DEVELOP_DIR"

	WP_CORE_DIR_ESC=$(echo "$WP_CORE_DIR" | sed 's:/\+$::')
	sed -i \
		-e "s:dirname( __FILE__ ) . '/src/':'$WP_CORE_DIR_ESC/':" \
		-e "s:__DIR__ . '/src/':'$WP_CORE_DIR_ESC/':" \
		-e "s/youremptytestdbnamehere/$DB_NAME/" \
		-e "s/yourusernamehere/$DB_USER/" \
		-e "s/yourpasswordhere/$DB_PASS/" \
		-e "s|localhost|$DB_HOST|" \
		"$WP_TESTS_DIR/wp-tests-config.php"
fi

# Test database.
if [ "$SKIP_DB_CREATE" != "true" ]; then
	PARTS=(${DB_HOST//:/ })
	HOST=${PARTS[0]}
	EXTRA=""
	if [ -n "${PARTS[1]-}" ]; then
		if [[ "${PARTS[1]}" =~ ^[0-9]+$ ]]; then
			EXTRA="--port=${PARTS[1]} --protocol=tcp"
		else
			EXTRA="--socket=${PARTS[1]}"
		fi
	elif [ "$HOST" != "localhost" ]; then
		EXTRA="--protocol=tcp"
	fi
	mysqladmin create "$DB_NAME" --user="$DB_USER" --password="$DB_PASS" --host="$HOST" $EXTRA || true
fi
