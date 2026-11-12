#!/usr/bin/env python3
"""Builds the documentation (docs/*.md) as one HTML file with a title page, a table of contents
and embedded screenshots. .github/scripts/build-docs.sh turns it into DOCX and PDF (LibreOffice).

Usage: build-docs.py OUTPUT.html [--docx] [--version 0.2.0] [--date 2026-11-04]

--docx writes a variant for the LibreOffice Writer HTML import (inline styles and table
attributes instead of a stylesheet, page breaks on headings).
"""
import argparse
import base64
import html
import re
from pathlib import Path

import markdown
from markdown.extensions.toc import slugify

ROOT = Path(__file__).resolve().parent.parent.parent
DOCS = ROOT / 'docs'

args = argparse.ArgumentParser()
args.add_argument('output')
args.add_argument('--docx', action='store_true')
args.add_argument('--version', default=re.search(r'^ \* Version:\s+(\S+)', (ROOT / 'poczta-polska-for-woocommerce.php').read_text(encoding='utf-8'), re.M).group(1))
args.add_argument('--date', default=__import__('datetime').date.today().isoformat())
options = args.parse_args()

CHAPTERS = ['README.md'] + sorted(p.name for p in DOCS.glob('0*.md'))


def slug(text: str) -> str:
    """Anchor of a heading, exactly like the markdown toc extension makes it."""
    return slugify(text, '-')


def chapter_anchor(name: str) -> str:
    first = next(line for line in (DOCS / name).read_text(encoding='utf-8').splitlines() if line.startswith('# '))
    return slug(first[2:])


def prepare(name: str) -> str:
    text = (DOCS / name).read_text(encoding='utf-8')

    # Links between chapters become links inside the document.
    def link(match: re.Match) -> str:
        target, anchor = match.group(2), match.group(3)
        return '[' + match.group(1) + '](#' + (slug(anchor) if anchor else chapter_anchor(target)) + ')'

    text = re.sub(r'\[([^\]]+)\]\((0\d-[^)#]+\.md|README\.md)(?:#([^)]+))?\)', link, text)

    # Screenshots embedded, so the file is self-contained.
    def image(match: re.Match) -> str:
        data = base64.b64encode((DOCS / match.group(2)).read_bytes()).decode()
        caption = html.escape(match.group(1))
        img = '<img src="data:image/png;base64,' + data + '" alt="' + caption + '"' + (' width="620"' if options.docx else '') + '>'

        if options.docx:
            return '<p align="center">' + img + '</p><p align="center" style="font-size:9pt;color:#555"><i>' + caption + '</i></p>'

        return '<figure>' + img + '<figcaption>' + caption + '</figcaption></figure>'

    text = re.sub(r'!\[([^\]]*)\]\(([^)]+)\)', image, text)

    if name == 'README.md':
        # The index table of the README is replaced by the generated table of contents.
        text = re.sub(r'\| Rozdział \|.*?\n\n', '', text, flags=re.S)
        text = text.replace('# Poczta Polska for WooCommerce - dokumentacja\n', '# Wprowadzenie\n')

    return text


md = markdown.Markdown(extensions=['tables', 'fenced_code', 'toc', 'sane_lists'], extension_configs={'toc': {'toc_depth': '1-2', 'title': 'Spis treści'}})
body = md.convert('\n\n'.join(prepare(name) for name in CHAPTERS))
toc = md.toc

CSS = '''
@page { size: A4; margin: 20mm 18mm; }
body { font-family: "DejaVu Sans", Arial, sans-serif; font-size: 10.5pt; line-height: 1.45; color: #222; max-width: 170mm; margin: 0 auto; }
.title { page-break-after: always; padding-top: 70mm; }
.title h1 { font-size: 26pt; border: 0; margin-bottom: 4mm; page-break-before: auto; }
.title p { font-size: 12pt; color: #555; }
.toc { page-break-after: always; }
.toc ul { list-style: none; padding-left: 0; }
.toc ul ul { padding-left: 6mm; }
.toc a { text-decoration: none; color: #222; }
h1 { font-size: 20pt; page-break-before: always; border-bottom: 2px solid #c00; padding-bottom: 2mm; margin-top: 0; }
h2 { font-size: 14pt; margin-top: 9mm; }
h3 { font-size: 11.5pt; margin-top: 6mm; }
h1, h2, h3 { page-break-after: avoid; }
p, li { orphans: 3; widows: 3; }
code { font-family: "DejaVu Sans Mono", monospace; font-size: 9pt; background: #f3f3f3; padding: 0 2px; }
pre { background: #f3f3f3; border: 1px solid #ddd; padding: 3mm; font-size: 8.5pt; white-space: pre-wrap; word-break: break-word; page-break-inside: avoid; }
pre code { background: none; padding: 0; }
table { border-collapse: collapse; width: 100%; margin: 3mm 0; font-size: 9.5pt; }
th, td { border: 1px solid #bbb; padding: 1.5mm 2mm; vertical-align: top; text-align: left; }
th { background: #eee; }
tr { page-break-inside: avoid; }
figure { margin: 4mm 0; text-align: center; page-break-inside: avoid; }
figure img { max-width: 100%; border: 1px solid #ccc; }
figcaption { font-size: 9pt; color: #555; margin-top: 1.5mm; }
blockquote { border-left: 3px solid #ccc; margin: 3mm 0; padding-left: 4mm; color: #444; }
a { color: #0645ad; }
'''

page = f'''<!DOCTYPE html>
<html lang="pl"><head><meta charset="utf-8"><title>Poczta Polska for WooCommerce - dokumentacja {options.version}</title><style>{CSS}</style></head>
<body>
<div class="title">
<h1>Poczta Polska for WooCommerce</h1>
<p>Dokumentacja wtyczki WordPress/WooCommerce łączącej sklep z Elektronicznym Nadawcą</p>
<p>Wersja wtyczki {options.version} · stan na {options.date}</p>
<p>Instalacja i konfiguracja · instrukcja sprzedawcy · architektura i API · WordPress.org i aktualizacje · logi i błędy · budowanie i wdrażanie</p>
</div>
<div class="toc">{toc}</div>
{body}
</body></html>'''

if options.docx:
    # LibreOffice Writer honours attributes and inline styles better than the stylesheet.
    page = page.replace('<table>', '<table border="1" cellpadding="5" cellspacing="0" width="100%" style="width:100%;border-collapse:collapse;table-layout:fixed;font-size:9pt">')
    page = page.replace('</table>', '</table><p></p>')
    page = re.sub(r'<h1( id="[^"]*")?>', lambda m: '<h1' + (m.group(1) or '') + ' style="page-break-before:always;font-family:DejaVu Sans;font-size:20pt;color:#222">', page)
    page = re.sub(r'<h2( id="[^"]*")?>', lambda m: '<h2' + (m.group(1) or '') + ' style="font-family:DejaVu Sans;font-size:14pt;color:#222">', page)
    page = re.sub(r'<h3( id="[^"]*")?>', lambda m: '<h3' + (m.group(1) or '') + ' style="font-family:DejaVu Sans;font-size:11.5pt;color:#222">', page)
    page = page.replace('<div class="title">\n<h1 style="page-break-before:always;', '<div class="title">\n<h1 style="')
    page = page.replace('<span class="toctitle">Spis treści</span>', '<p style="page-break-before:always;font-family:DejaVu Sans;font-size:14pt"><b>Spis treści</b></p>')
    page = page.replace('<pre>', '<pre style="font-size:8.5pt;background:#f3f3f3">')

Path(options.output).write_text(page, encoding='utf-8')
print(options.output, len(page), 'bytes')
