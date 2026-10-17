#!/bin/sh
set -e

# The uploads volume is the only place PHP may write to.
UPLOAD_DIR=/var/www/html/public/uploads
mkdir -p "$UPLOAD_DIR/pins" "$UPLOAD_DIR/avatars"
chown -R www-data:www-data "$UPLOAD_DIR"
chmod 0755 "$UPLOAD_DIR" "$UPLOAD_DIR/pins" "$UPLOAD_DIR/avatars"

exec "$@"
