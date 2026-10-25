#!/usr/bin/env bash
set -euo pipefail
project_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$project_root"
status=0
if command -v php >/dev/null 2>&1; then
  while IFS= read -r -d '' file; do php -l "$file" >/dev/null || status=1; done < <(find contactpanda-business tests -type f -name '*.php' -print0)
  php tests/business-foundation.php || status=1
else
  python3 tools/check_php_structure.py contactpanda-business tests || status=1
  echo "SKIPPED: full PHP contract tests require PHP CLI."
fi
header="$(awk -F': ' '/^ \* Version:/{print $2; exit}' contactpanda-business/contactpanda-business.php)"
constant="$(sed -n "s/^define( 'CONTACTPANDA_BUSINESS_VERSION', '\([^']*\)' );/\1/p" contactpanda-business/contactpanda-business.php | head -1)"
stable="$(awk -F': ' '/^Stable tag:/{print $2; exit}' contactpanda-business/readme.txt)"
[[ "$header" == "$constant" && "$header" == "$stable" ]] || { echo "Business versions do not match." >&2; status=1; }
for translation_file in \
  contactpanda-business/languages/contactpanda-business-de_DE.po \
  contactpanda-business/languages/contactpanda-business-de_DE.mo \
  contactpanda-business/languages/contactpanda-business-de_DE_formal.po \
  contactpanda-business/languages/contactpanda-business-de_DE_formal.mo; do
  [[ -f "$translation_file" ]] || { echo "Missing German translation file: $translation_file" >&2; status=1; }
done
if [[ -e contactpanda-business/includes/class-contactpanda-business-updater.php ]]; then
  echo "Business must use the protected updater supplied centrally by ContactPanda Basic." >&2
  status=1
fi
if ! grep -q "contactpanda_customer_portal_tabs" contactpanda-business/includes/class-contactpanda-business-projects.php; then
  echo "Business must extend the shared ContactPanda customer portal." >&2
  status=1
fi
[[ "$status" -eq 0 ]] || exit "$status"
echo "ContactPanda Business checks passed."
