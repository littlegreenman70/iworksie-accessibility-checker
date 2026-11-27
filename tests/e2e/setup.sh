#!/bin/bash
# Prepare a throwaway WordPress site with broken and clean test pages for tests/e2e/run.js.
# usage: setup.sh <wpdir-name> <port> <dbname>
# Env: WP (wp-cli binary), WP_CORE_DIR (dir holding clean WordPress copies), SITES_DIR, DB_CONTAINER.
# Run `composer build` first so build/iworksie-accessibility-checker.zip exists.
set -e
PLUGIN_DIR="$(cd "$(dirname "$0")/../.." && pwd)"
WP=${WP:-wp}
SITES=${SITES_DIR:-/tmp/sites}
SRC=${WP_CORE_DIR:-/tmp/wpc}/$1; DST=$SITES/$1; PORT=$2; DB=$3
mkdir -p "$SITES"
rm -rf $DST; cp -r $SRC $DST
cd $DST
docker exec ${DB_CONTAINER:-iwdb} mariadb -uroot -e "DROP DATABASE IF EXISTS $DB; CREATE DATABASE $DB;"
$WP --allow-root config create --dbname=$DB --dbuser=root --dbpass= --dbhost=127.0.0.1 --skip-check --quiet --extra-php <<PHP
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', '$SITES/$1-debug.log' );
define( 'WP_DEBUG_DISPLAY', false );
PHP
$WP --allow-root core install --url=http://localhost:$PORT --title="A11y Test Site" --admin_user=admin --admin_password=admin --admin_email=admin@example.org --skip-email --quiet
$WP --allow-root rewrite structure '/%postname%/' --quiet
rm -rf wp-content/plugins/iworksie-accessibility-checker
unzip -q $PLUGIN_DIR/build/iworksie-accessibility-checker.zip -d wp-content/plugins/
cat > $SITES/bad.html <<'HTML'
<!-- wp:heading {"level":4} --><h4>Skipped heading level</h4><!-- /wp:heading -->
<!-- wp:html -->
<img src="/wp-includes/images/w-logo-blue.png" width="80" height="80">
<p style="color:#bbbbbb;background:#ffffff">Low contrast text that is hard to read.</p>
<a href="/somewhere/"><span class="dashicons dashicons-admin-home"></span></a>
<form><input type="text" name="q" placeholder="Search"><button type="submit"></button></form>
<div id="dup" aria-labelledby="missing-id" role="button">Fake button</div>
<!-- /wp:html -->
HTML
$WP --allow-root post create $SITES/bad.html --post_type=page --post_status=publish --post_title="Broken Page" --quiet
$WP --allow-root post create --post_type=page --post_status=publish --post_title="Good Page" --post_content='<!-- wp:paragraph --><p>This page is fine. It has <a href="/">a clear link</a>.</p><!-- /wp:paragraph -->' --quiet
for i in 1 2 3; do $WP --allow-root post create --post_status=publish --post_title="News item $i" --post_content="<!-- wp:paragraph --><p>Story $i.</p><!-- /wp:paragraph -->" --quiet; done
$WP --allow-root plugin activate iworksie-accessibility-checker
rm -f $SITES/$1-debug.log
echo "site $1 ready on $PORT"
