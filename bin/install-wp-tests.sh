#!/usr/bin/env bash

if [ $# -lt 3 ]; then
	echo "usage: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-database-creation]"
	exit 1
fi

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4-localhost}
WP_VERSION=${5-latest}
SKIP_DB_CREATE=${6-false}

TMPDIR=${TMPDIR-/tmp}
TMPDIR=$(echo "$TMPDIR" | sed -e 's/\/$//')
WP_TESTS_DIR=${WP_TESTS_DIR-$TMPDIR/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR-$TMPDIR/wordpress}

download() {
	if which curl >/dev/null; then
		curl -s "$1" >"$2"
	elif which wget >/dev/null; then
		wget -nv -O "$2" "$1"
	else
		echo "Error: curl or wget is required."
		exit 1
	fi
}

if [[ $WP_VERSION =~ ^[0-9]+\.[0-9]+(\.[0-9]+)?$ ]]; then
	WP_TESTS_TAG="tags/$WP_VERSION"
elif [[ $WP_VERSION == 'nightly' || $WP_VERSION == 'trunk' ]]; then
	WP_TESTS_TAG="trunk"
else
	WP_TESTS_TAG="tags/$WP_VERSION"
fi

set -ex

install_wp() {
	if [ -d "$WP_CORE_DIR" ]; then
		return
	fi

	mkdir -p "$WP_CORE_DIR"

	if [[ $WP_VERSION == 'nightly' || $WP_VERSION == 'latest' ]]; then
		local ARCHIVE_NAME='latest'
	elif [[ $WP_VERSION == 'trunk' ]]; then
		svn co --quiet https://develop.svn.wordpress.org/trunk "$WP_CORE_DIR"
		return
	else
		local ARCHIVE_NAME="wordpress-$WP_VERSION"
	fi

	download "https://wordpress.org/${ARCHIVE_NAME}.tar.gz" /tmp/wordpress.tar.gz
	tar --strip-components=1 -zxmf /tmp/wordpress.tar.gz -C "$WP_CORE_DIR"
}

install_test_suite() {
	if [ -d "$WP_TESTS_DIR" ]; then
		return
	fi

	mkdir -p "$WP_TESTS_DIR"
	svn co --quiet https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/includes/ "$WP_TESTS_DIR/includes"
	svn co --quiet https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/data/ "$WP_TESTS_DIR/data"
}

install_db() {
	if [ "$SKIP_DB_CREATE" = "true" ]; then
		return
	fi

	local EXTRA=""
	if ! [ -z "$DB_HOST" ]; then
		EXTRA=" --host=$DB_HOST"
	fi

	mysqladmin create "$DB_NAME" --user="$DB_USER" --password="$DB_PASS"$EXTRA 2>/dev/null || true
}

install_wp
install_test_suite
install_db

WP_CONFIG_PATH="$WP_TESTS_DIR/wp-tests-config.php"
download "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/wp-tests-config-sample.php" "$WP_CONFIG_PATH"

sed -i.bak "s:dirname( __FILE__ ) . '/src/':'$WP_CORE_DIR/':" "$WP_CONFIG_PATH"
sed -i.bak "s/youremptytestdbnamehere/$DB_NAME/" "$WP_CONFIG_PATH"
sed -i.bak "s/yourusernamehere/$DB_USER/" "$WP_CONFIG_PATH"
sed -i.bak "s/yourpasswordhere/$DB_PASS/" "$WP_CONFIG_PATH"
sed -i.bak "s|localhost|${DB_HOST}|" "$WP_CONFIG_PATH"
rm -f "$WP_CONFIG_PATH.bak"
