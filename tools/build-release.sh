#!/usr/bin/env bash
set -euo pipefail
project_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$project_root"
version="$(awk -F': ' '/^ \* Version:/{print $2; exit}' contactpanda-business/contactpanda-business.php)"
requires="$(awk -F': ' '/^ \* Requires at least:/{print $2; exit}' contactpanda-business/contactpanda-business.php)"
php_version="$(awk -F': ' '/^ \* Requires PHP:/{print $2; exit}' contactpanda-business/contactpanda-business.php)"
basic="$(awk -F': ' '/^ \* Requires ContactPanda:/{print $2; exit}' contactpanda-business/contactpanda-business.php)"
mkdir -p dist
zip_path="dist/contactpanda-business-${version}.zip"
git archive --format=zip --prefix=contactpanda-business/ HEAD:contactpanda-business > "$zip_path"
unzip -tq "$zip_path" >/dev/null
sha="$(shasum -a 256 "$zip_path" | awk '{print $1}')"
printf '{\n  "slug": "contactpanda-business",\n  "version": "%s",\n  "requires": "%s",\n  "requires_php": "%s",\n  "tested": "%s",\n  "requires_basic": "%s",\n  "sha256": "%s"\n}\n' "$version" "$requires" "$php_version" "${CONTACTPANDA_TESTED_WP:-$requires}" "$basic" "$sha" > dist/contactpanda-business-update.json
echo "Created $zip_path and update metadata."
