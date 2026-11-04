# Zasady pracy nad kodem

## Gałęzie i pull requesty

- Gałąź `main` zawiera zawsze kod gotowy do wydania. Nie wypychamy do niej bezpośrednio.
- Każda zmiana powstaje na osobnej gałęzi (`feature/…`, `fix/…`, `release/…`) i trafia do `main` przez pull request.
- Pull request wymaga zielonego CI i code review. Uwagi z review rozwiązujemy przed scaleniem.
- Opis pull requesta wypełniamy według szablonu (`.github/pull_request_template.md`).

## Wersje i changelog

- Numerujemy według [SemVer](https://semver.org/lang/pl/): `MAJOR.MINOR.PATCH`.
  - PATCH: poprawki błędów bez zmiany zachowania dla sprzedawcy.
  - MINOR: nowe funkcje zgodne wstecz.
  - MAJOR: zmiany niezgodne wstecz (np. usunięcie ustawienia, zmiana wymagań).
- Każda zmiana ma wpis w `CHANGELOG.md` w sekcji `[Unreleased]` (format [Keep a Changelog](https://keepachangelog.com/pl/1.1.0/)).
- Wydanie: gałąź `release/X.Y.Z`, numer wersji w nagłówku wtyczki, w stałej `PPWC_VERSION` i w `Stable tag` w `readme.txt` (skrypt `composer build` sprawdza zgodność), przeniesienie wpisów z `[Unreleased]` do `[X.Y.Z] - RRRR-MM-DD`, wpis w sekcji Changelog w `readme.txt`. Po scaleniu tag `vX.Y.Z` na `main`.
- Zmiana schematu bazy: podnieść `Schema::VERSION`.
- Zmiana szablonu w `templates/`: podnieść jego `@version`.

## Testy przed pull requestem

```bash
composer lint       # PHPCS: WordPress-Extra, WordPress-Docs, PHPCompatibilityWP 8.2+
composer analyse    # PHPStan, poziom 8
composer test       # PHPUnit
npm run test:e2e    # E2E, gdy zmiana dotyczy zamówienia albo panelu (tests/e2e/README.md)
```

Przed wydaniem dodatkowo Plugin Check (PCP) w WordPressie z wtyczką oraz testy regresji na koncie testowym EN (INT): przesyłka z zamówienia, etykieta, wysłanie bufora, punkty odbioru.

Testy na koncie testowym EN wykonujemy tylko na karcie testowej. Nie tworzymy buforów ani przesyłek na kartach używanych produkcyjnie.

## Kod

- Standardy kodowania WordPress (PHPCS) i bezpieczeństwo: nonce i uprawnienia przy każdej akcji, sanitizacja wejścia, escaping wyjścia, `$wpdb->prepare()`.
- Zamówienia wyłącznie przez CRUD WooCommerce (HPOS i tryb legacy).
- Teksty źródłowe po angielsku, domena `poczta-polska-for-woocommerce`, tłumaczenie pl_PL w `languages/` aktualizowane razem ze zmianą.
- HTML w szablonach (`templates/` dla sklepu i maili, `views/admin/` dla panelu), nie w klasach PHP.
- Bez danych logowania i danych osobowych w kodzie, testach i nagranych odpowiedziach EN.

## Podatności w zależnościach

Dependabot i `composer audit` / `npm audit` w CI zgłaszają podatności. Krytyczne naprawiamy w 72 godziny, wysokie w 7 dni.

## Konta

Konta indywidualne z logowaniem dwuskładnikowym (MFA). Bez kont współdzielonych.
