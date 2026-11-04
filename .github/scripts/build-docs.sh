#!/usr/bin/env bash
# Builds the documentation from docs/*.md as DOCX and, from that DOCX, as PDF (LibreOffice),
# so both files have the same layout and the DOCX can be edited and exported again.
#
#   .github/scripts/build-docs.sh [output directory]   (default: dist/)
#
# Needs: python3 with the "markdown" package, libreoffice.
# The work directory must be readable by LibreOffice (a snap cannot read /tmp or dot folders).
set -euo pipefail

cd "$(dirname "$0")/../.."

OUT="${1:-dist}"
VERSION="$(grep -oP '^ \* Version:\s+\K\S+' poczta-polska-for-woocommerce.php)"
NAME="Poczta-Polska-for-WooCommerce-dokumentacja-${VERSION}"
WORK="${PPWC_DOCS_WORK:-$HOME/ppwc-docs-build}"

mkdir -p "$OUT" "$WORK"
OUT="$(cd "$OUT" && pwd)"

command -v libreoffice >/dev/null || { echo "LibreOffice is required." >&2; exit 1; }

python3 .github/scripts/build-docs.py "$WORK/$NAME.html" --version "$VERSION" --docx

libreoffice --headless --infilter="HTML (StarWriter)" --convert-to docx:"MS Word 2007 XML" "$WORK/$NAME.html" --outdir "$WORK" 2>/dev/null | grep -q "using filter"
cp "$WORK/$NAME.docx" "$OUT/$NAME.docx"
echo "DOCX: $OUT/$NAME.docx"

libreoffice --headless --convert-to pdf "$WORK/$NAME.docx" --outdir "$WORK" 2>/dev/null | grep -q "using filter"
cp "$WORK/$NAME.pdf" "$OUT/$NAME.pdf"
echo "PDF:  $OUT/$NAME.pdf"
