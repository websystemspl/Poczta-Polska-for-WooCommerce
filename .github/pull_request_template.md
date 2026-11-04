## Co się zmienia

<!-- Krótko: co i dlaczego. Numer wymagania OPZ albo zgłoszenia, jeśli jest. -->

## Jak sprawdzono

<!-- Testy automatyczne, test na koncie testowym EN (INT), zrzuty ekranu. -->

## Lista kontrolna

- [ ] CI zielone (PHPCS, PHPStan, PHPUnit na PHP 8.2–8.5, audyt zależności, ZIP)
- [ ] Testy E2E (`npm run test:e2e`) przechodzą, jeśli zmiana dotyczy zamówienia albo panelu
- [ ] Nowe teksty mają tłumaczenie pl_PL (`.po`, `.mo`, JSON dla JS)
- [ ] Wpis w `CHANGELOG.md` w sekcji `[Unreleased]`
- [ ] Bezpieczeństwo: nonce i uprawnienia, sanitizacja wejścia, escaping wyjścia, `$wpdb->prepare()`
- [ ] Zamówienia tylko przez CRUD WooCommerce (HPOS i tryb legacy)
- [ ] Plugin Check (PCP) bez błędów przy zmianach w paczce wtyczki
- [ ] Brak danych logowania i danych osobowych w kodzie i plikach testowych
