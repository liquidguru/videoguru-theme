#!/bin/sh
# WP-CLI against the VideoGuru test site (_test/wp; DB videoguru_test on the Local MySQL at 127.0.0.1:10023, so start any Local site first).
# MSYS_NO_PATHCONV stops Git Bash rewriting "/path" arguments, so all paths here are Windows-style.
export MSYS_NO_PATHCONV=1
PHP="C:/Users/liqui/AppData/Roaming/Local/lightning-services/php-8.2.29+0/bin/win64/php.exe"
EXT="C:/Users/liqui/AppData/Roaming/Local/lightning-services/php-8.2.29+0/bin/win64/ext"
WP="C:/Users/liqui/AppData/Local/Programs/local/resources/extraResources/bin/wp-cli/wp-cli.phar"
cd "C:/Users/liqui/dev/videoguru-theme/_test/wp" && exec "$PHP" -d extension_dir="$EXT" -d extension=mysqli -d extension=mbstring -d extension=openssl -d extension=curl -d extension=gd -d extension=exif -d extension=zip -d mysqli.default_port=10023 -d memory_limit=512M "$WP" "$@"
