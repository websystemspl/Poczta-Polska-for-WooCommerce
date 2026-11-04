# 6. Budowanie i wdrażanie

## 6.1. Repozytorium i zasady pracy

Kod jest w repozytorium `https://github.com/websystemspl/Poczta-Polska-for-WooCommerce`. Katalog główny repozytorium to katalog wtyczki. Gałąź `main` jest chroniona: zmiany wchodzą tylko przez pull request z zielonym CI. Zasady (gałęzie, review, numeracja wersji, changelog) opisuje `CONTRIBUTING.md`.

## 6.2. Narzędzia

| Narzędzie | Wersja | Do czego |
|---|---|---|
| PHP | 8.2+ z rozszerzeniami `soap`, `sodium`, `mbstring` | testy, analiza |
| Composer | 2 | PHPCS, PHPStan, PHPUnit (tylko narzędzia, wtyczka nie ma zależności w czasie działania) |
| Node.js, npm | 20+ | testy E2E (Playwright) |
| k6 | 1.0+ | testy wydajnościowe (`tests/k6/README.md`) |
| WP-CLI | 2.x | tłumaczenia, Plugin Check |
| WordPress z WooCommerce | aktualne | testy E2E, Plugin Check |

```bash
git clone https://github.com/websystemspl/Poczta-Polska-for-WooCommerce.git poczta-polska-for-woocommerce
cd poczta-polska-for-woocommerce
composer install
npm install && npx playwright install chromium
```

Do testów w sklepie katalog umieszcza się w `wp-content/plugins/` instalacji WordPressa (albo tworzy tam dowiązanie).

## 6.3. Sprawdzenie kodu

```bash
composer lint       # PHPCS: WordPress-Extra, WordPress-Docs, PHPCompatibilityWP 8.2+
composer analyse    # PHPStan, poziom 8, stuby WordPress i WooCommerce
composer test       # PHPUnit (bez WordPressa: Brain Monkey i nagrane odpowiedzi EN)
npm run test:e2e    # E2E w przeglądarce, instrukcja w tests/e2e/README.md
npm run test:k6     # wydajność zakupów (k6), instrukcja w tests/k6/README.md
```

Plugin Check (wytyczne katalogu WordPress.org) uruchamia się w instalacji WordPressa z wtyczką i włączoną wtyczką Plugin Check:

```bash
wp plugin check poczta-polska-for-woocommerce \
  --exclude-directories=vendor,tests,bin,dist,node_modules,.github \
  --exclude-files=composer.json,composer.lock,package.json,package-lock.json,playwright.config.ts,phpcs.xml.dist,phpstan.neon.dist,phpunit.xml.dist,.gitignore,.distignore,README.md,CHANGELOG.md,CONTRIBUTING.md,LICENSE
```

Pominięte pliki nie trafiają do paczki ZIP. Przed uruchomieniem usuń ukryty plik `.phpcs-cache`, jeśli istnieje.

### CI

GitHub Actions (`.github/workflows/ci.yml`) przy każdym pull requeście i każdej zmianie na `main` uruchamia: PHPCS i PHPStan, PHPUnit na PHP 8.2, 8.3, 8.4 i 8.5, `composer audit` i `npm audit`, budowę paczki ZIP (artefakt przebiegu). Dependabot co tydzień proponuje aktualizacje zależności Composer, npm i akcji GitHub.

CI nie uruchamia testów E2E ani Plugin Check, bo wymagają działającego sklepu; uruchamia się je lokalnie przed pull requestem i przed wydaniem.

## 6.4. Tłumaczenia

Teksty w kodzie są po angielsku, tłumaczenie polskie jest w `languages/`. Po zmianie tekstów:

```bash
wp i18n make-pot . languages/poczta-polska-for-woocommerce.pot --exclude=vendor,tests,node_modules,dist,bin --skip-audit
wp i18n update-po languages/poczta-polska-for-woocommerce.pot languages/poczta-polska-for-woocommerce-pl_PL.po
# uzupełnij puste msgstr w languages/poczta-polska-for-woocommerce-pl_PL.po (liczba mnoga: trzy formy)
wp i18n make-mo languages/
rm languages/*.json && wp i18n make-json languages/ --no-purge
```

Plik `.po` nie może mieć pustych tłumaczeń ani wpisów `fuzzy`. Plik JSON zawiera tłumaczenia skryptu zamówienia na blokach.

## 6.5. Wydanie nowej wersji

1. Gałąź `release/X.Y.Z` od aktualnego `main`.
2. Numer wersji w trzech miejscach: `Version:` i stała `PPWC_VERSION` w `poczta-polska-for-woocommerce.php`, `Stable tag:` w `readme.txt`.
3. W `CHANGELOG.md` przenieś wpisy z `[Unreleased]` do `[X.Y.Z] - RRRR-MM-DD` i uzupełnij linki porównania na końcu pliku. W `readme.txt` dodaj skrócony wpis w sekcji `== Changelog ==`.
4. Po sprawdzeniu z nową wersją WordPressa lub WooCommerce zaktualizuj `Tested up to` w `readme.txt` oraz `WC tested up to` w nagłówku wtyczki (w razie potrzeby także `Requires at least` w obu plikach i `WC requires at least` w nagłówku).
5. Uruchom sprawdzenia z rozdziału 6.3, testy E2E i testy regresji na środowisku testowym EN (przesyłka z zamówienia, etykieta, wysłanie bufora, punkty odbioru).
6. Pull request do `main`, review, zielone CI, scalenie.
7. Tag i wydanie na GitHubie z paczką ZIP:

   ```bash
   git checkout main && git pull
   composer build
   git tag -a vX.Y.Z -m "Poczta Polska for WooCommerce X.Y.Z"
   git push origin vX.Y.Z
   gh release create vX.Y.Z dist/poczta-polska-for-woocommerce-X.Y.Z.zip --title "X.Y.Z" --notes "…"
   ```

8. Publikacja w katalogu WordPress.org (6.7).

## 6.6. Paczka ZIP

```bash
composer build
```

Skrypt `bin/build-zip.sh` sprawdza, czy `Version` w nagłówku zgadza się ze `Stable tag` w `readme.txt`, kopiuje pliki z pominięciem ścieżek z `.distignore` (narzędzia, testy, `vendor`, `node_modules`, pliki konfiguracji, dokumentacja deweloperska) i tworzy `dist/poczta-polska-for-woocommerce-X.Y.Z.zip` z katalogiem `poczta-polska-for-woocommerce/` w środku. Taki plik instaluje się w WordPressie przez **Wtyczki → Dodaj nową → Wyślij wtyczkę na serwer**.

## 6.7. Katalog WordPress.org

Wtyczka jest publikowana na koncie Poczty Polskiej w katalogu WordPress.org. Katalog używa repozytorium SVN `https://plugins.svn.wordpress.org/poczta-polska-for-woocommerce/` z katalogami:

| Katalog | Zawartość |
|---|---|
| `trunk/` | bieżąca wersja (zawartość paczki ZIP) |
| `tags/X.Y.Z/` | kopia każdej wydanej wersji; WordPress instaluje wersję wskazaną przez `Stable tag` |
| `assets/` | ikona (`icon-128x128.png`, `icon-256x256.png`), baner (`banner-772x250.png`, `banner-1544x500.png`), zrzuty ekranu (`screenshot-1.png`…) opisane w sekcji `== Screenshots ==` w `readme.txt` |

Publikacja wersji (konto z dostępem do SVN wtyczki):

```bash
svn checkout https://plugins.svn.wordpress.org/poczta-polska-for-woocommerce/ svn-ppwc
cd svn-ppwc
rm -rf trunk/* && unzip -q ../dist/poczta-polska-for-woocommerce-X.Y.Z.zip -d /tmp/ppwc && cp -R /tmp/ppwc/poczta-polska-for-woocommerce/. trunk/
svn add --force trunk && svn status | grep '^!' | awk '{print $2}' | xargs -r svn delete
svn cp trunk tags/X.Y.Z
svn commit -m "Release X.Y.Z"
```

Po kilku minutach nowa wersja jest widoczna w katalogu i w powiadomieniach o aktualizacjach. Przed pierwszym zgłoszeniem i przed każdym wydaniem wtyczka musi przejść Plugin Check bez błędów.

## 6.8. Wdrożenie w sklepie

Instalacja i aktualizacja w sklepie: rozdziały [1](01-instalacja-i-konfiguracja.md) i [4](04-wordpress-org-i-aktualizacje.md). Konfiguracja serwera:

- PHP 8.2+ z `soap` i `sodium`, połączenie wychodzące HTTPS do adresów WebAPI EN,
- działający WP-Cron albo cron systemowy uruchamiający `wp-cron.php` (zadania Action Scheduler),
- opcjonalnie stała `PPWC_ENCRYPTION_KEY` w `wp-config.php` (długi losowy ciąg) jako osobny klucz szyfrowania haseł EN; ustaw ją przed wpisaniem haseł.
