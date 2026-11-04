# 3. Architektura i interfejsy API

## 3.1. Założenia

- Sama wtyczka WordPressa. Sklep łączy się bezpośrednio z WebAPI Elektronicznego Nadawcy (SOAP), bez serwerów pośrednich, kluczy licencyjnych i telemetrii.
- Jedyne usługi zewnętrzne: WebAPI EN (z serwera sklepu) i skrypt mapy punktów Poczty Polskiej (z przeglądarki klienta).
- Zamówienia tylko przez CRUD WooCommerce (`wc_get_order()`, `$order->get_meta()`), więc wtyczka działa z HPOS i z tradycyjnym przechowywaniem zamówień. Deklaruje zgodność `custom_order_tables` i `cart_checkout_blocks`.
- Wymagane PHP 8.2+. Plik główny ma składnię zgodną ze starszym PHP i na zbyt starej wersji wyłącza wtyczkę z komunikatem.
- Kod w przestrzeni nazw `PocztaPolska\WooCommerce\` (PSR-4, własny autoloader, bez zależności Composera w czasie działania). Prefiks globalny `ppwc_` / `PPWC_`, domena tłumaczeń `poczta-polska-for-woocommerce`.

## 3.2. Struktura kodu

| Katalog / klasa | Zadanie |
|---|---|
| `poczta-polska-for-woocommerce.php` | nagłówek, stałe `PPWC_*`, kontrola wersji PHP, start |
| `src/Plugin.php` | rejestracja modułów, deklaracje zgodności z WooCommerce, akcja `ppwc_init` |
| `src/Lifecycle.php`, `src/Database/Schema.php` | włączenie i wyłączenie wtyczki, tabele, aktualizacja schematu |
| `src/Settings.php`, `src/SettingsSchema.php` | opcja `ppwc_settings`, definicje pól, sanitizacja, wartości per środowisko |
| `src/Security/Crypto.php` | szyfrowanie haseł EN (libsodium) |
| `src/En/` | klient WebAPI: `Client` (operacje), `SoapTransport` (HTTP przez API WordPressa), `ClientFactory`, `Dictionary` (karty, urzędy, profile z pamięcią podręczną), DTO i wyjątki |
| `src/Shipping/` | rejestr usług (`ServiceRegistry`, `Service`), masa koszyka, dostępność metod, metody dostawy WooCommerce (`Methods/`) |
| `src/Points/` | kopia punktów (`PointsRepository`, `PointsSync`), pobieranie punktu (`PointResolver`), walidacja (`PointValidator`), punkt w sesji i w zamówieniu, wyświetlanie |
| `src/Checkout/` | wybór punktu w zamówieniu klasycznym i na blokach, integracja z blokami, skrypty |
| `src/Shipments/` | przesyłki: budowa danych z zamówienia (`DraftFactory`, `ShipmentBuilder`), bufory (`BufferService`), operacje (`ShipmentService`), tabela przesyłek, stany zbiorów |
| `src/Admin/` | ekrany panelu: ustawienia, test połączenia, panel przesyłki w zamówieniu, kolumna i akcje zbiorcze na liście zamówień, ekran Bufory, ostrzeżenia |
| `src/Rest/PointsController.php` | publiczne REST API punktów |
| `src/Privacy.php`, `src/AuditLog.php`, `src/Logger.php` | prywatność, rejestr operacji, log WooCommerce z maskowaniem |
| `templates/` | szablony dla klienta i e-maili (do nadpisania w motywie) |
| `views/admin/` | widoki panelu |
| `assets/` | skrypty, style, logo Poczty Polskiej i Pocztex |
| `languages/` | tłumaczenie pl_PL (PHP i JavaScript) |
| `resources/wsdl/labs.wsdl` | lokalna kopia WSDL WebAPI EN (wersja w97) |

## 3.3. WebAPI Elektronicznego Nadawcy

Klient SOAP używa lokalnego WSDL, a adres usługi ustawia z konfiguracji, bo WSDL wskazuje środowisko produkcyjne:

| Środowisko | Adres |
|---|---|
| produkcyjne | `https://e-nadawca.api.poczta-polska.pl/websrv/` |
| testowe (INT) | `https://int-e-nadawca.api.poczta-polska.pl/websrv/` |

Żądania idą przez `wp_remote_post()` z uwierzytelnieniem HTTP Basic, weryfikacją certyfikatu i wymuszonym TLS 1.2 lub nowszym. Limit czasu: 30 s, dla operacji zbiorczych (przesyłki, etykiety, bufory) 120 s.

| Funkcja wtyczki | Operacje EN |
|---|---|
| Test połączenia | `hello`, `getPasswordExpiredDate` |
| Karty, urzędy, profile | `getKarty`, `getUrzedyNadania`, `getProfilList` |
| Kopia punktów odbioru | `getPlacowkiPocztowe` (16 województw), `getPlacowkaPocztowa` (pojedynczy punkt) |
| Bufory | `createEnvelopeBufor`, `getEnvelopeBuforList`, `updateEnvelopeBufor`, `clearEnvelope` |
| Przesyłki | `addShipment` (Pocztex: `pocztex2021KurierType` z `punktOdbioru` dla PUNKT/AUTOMAT i `subPrzesylka` dla wielu paczek; eCommerce: `przesylkaFirmowaPoleconaType`), `getEnvelopeBufor`, `clearEnvelopeByGuids`, `moveShipments` |
| Etykiety | `getPrintForParcel` (jeden plik PDF) |
| Wysłanie bufora | `sendEnvelope`, `getEnvelopeStatus`, `getEnvelopeContentShort` |
| Kurier | `zamowKuriera` |

Zasady integracji:

- Przesyłka dostaje GUID wygenerowany przez wtyczkę i zapisany w bazie **przed** wywołaniem `addShipment`. Po zerwanym połączeniu wtyczka szuka przesyłki w EN po GUID, zamiast tworzyć ją drugi raz.
- `numerPrzesylkiKlienta` = `PPWC-` + 20 znaków GUID. EN zachowuje go przy zmianie daty bufora, gdy przesyłka z wydrukowaną etykietą dostaje nowy GUID i numer nadania; wtyczka odnajduje ją po tym numerze.
- Karta umowy jest atrybutem bufora (`idKarta`), dlatego wtyczka trzyma osobny bufor na kartę, urząd nadania, profil i dzień.
- Kwota pobrania w `addShipment` jest w groszach; limit pobrania punktu (`maksymalnaKwotaPobrania`) w złotych.
- Operacje na buforach i zamówieniach są blokowane przed równoległym wykonaniem (blokady w opcjach `ppwc_lock_*`, 180 s).
- Wtyczka obsługuje tylko bufory, które sama utworzyła, i porównuje je z listą w EN.

## 3.4. Dane

### Tabele

| Tabela | Zawartość |
|---|---|
| `{prefix}ppwc_points` | kopia punktów odbioru: PNI, nazwa, sieć, typ (PUNKT/AUTOMAT), adres, województwo, współrzędne, limit pobrania, czy wydaje paczki, data odświeżenia |
| `{prefix}ppwc_shipments` | przesyłki: zamówienie, środowisko, GUID, usługa, stan (`pending`, `created`, `failed`, `sent`), numer nadania, bufor, karta, urząd, zbiór, data wydruku etykiety, dane przesyłki (JSON), ostatni błąd |
| `{prefix}ppwc_audit_log` | rejestr operacji: data, użytkownik, operacja, obiekt, szczegóły |

Wersja schematu jest w opcji `ppwc_db_version`; zmiana `Schema::VERSION` aktualizuje tabele przy następnym wejściu do panelu (`dbDelta`).

W danych przesyłki wtyczka nie przechowuje adresu odbiorcy (zostaje w zamówieniu). Zapisuje tylko telefony zmienione dla przesyłki przez sprzedawcę.

### Opcje i transienty

| Klucz | Zawartość |
|---|---|
| `ppwc_settings` | ustawienia (hasła zaszyfrowane, prefiks `ppwc1:`) |
| `ppwc_version`, `ppwc_db_version` | wersja wtyczki i schematu |
| `ppwc_buffers` | bufory utworzone przez wtyczkę, per środowisko |
| `ppwc_envelope_status` | ostatni stan wysłanych zbiorów |
| `ppwc_password_expiry` | data ważności hasła EN per środowisko |
| `ppwc_points_sync` | stan odświeżania punktów per województwo |
| transienty `ppwc_dict_*` | karty, urzędy, profile z EN (12 h) |
| transienty `ppwc_point_*` | punkty spoza kopii pobrane z EN (6 h, brak punktu 1 h) |
| `woocommerce_ppwc_*_settings` | ustawienia instancji metod dostawy (WooCommerce) |

### Zamówienie i sesja

- Meta zamówienia `_ppwc_pickup_point`: PNI, nazwa, typ, sieć, adres, limit pobrania wybranego punktu.
- Sesja WooCommerce `ppwc_pickup_points`: punkty wybrane w trakcie zamówienia (osobno PUNKT i AUTOMAT).
- Pozycje wysyłki zamówienia mają `method_id` równe identyfikatorowi usługi (`ppwc_pocztex_courier`, `ppwc_pocztex_point`, `ppwc_pocztex_locker`, `ppwc_ecommerce`).

## 3.5. Zadania w tle (Action Scheduler, grupa `ppwc`)

| Akcja | Kiedy | Działanie |
|---|---|---|
| `ppwc_sync_points` | codziennie ok. 5:00 czasu sklepu | kolejkuje odświeżenie 16 województw |
| `ppwc_sync_points_voivodeship` | po `ppwc_sync_points` | pobiera punkty województwa, zapisuje partiami po 200, usuwa punkty, których EN już nie zwraca; pusta lub błędna odpowiedź nie kasuje kopii |
| `ppwc_check_password_expiry` | codziennie | sprawdza ważność hasła EN |

## 3.6. Interfejsy

### REST API (publiczne, tylko odczyt)

| Trasa | Parametry | Wynik |
|---|---|---|
| `GET /wp-json/ppwc/v1/points` | `search` (2–60 znaków: kod pocztowy, miejscowość, ulica), `type` (`PUNKT` albo `AUTOMAT`), `cod` (kwota pobrania w zł, opcjonalnie) | do 20 punktów z kopii: `pni`, `name`, `type`, `network`, `address`, `cod_limit`, `pickup` |
| `GET /wp-json/ppwc/v1/points/{pni}` | – | jeden punkt albo 404 |

Wyszukiwanie korzysta tylko z kopii lokalnej. Pojedynczy punkt spoza kopii jest pobierany z EN; dla niezalogowanych najwyżej 30 takich zapytań na 10 minut.

### Store API (zamówienie na blokach)

- Dane koszyka: `extensions.ppwc.points` – wybrane punkty `{ "PUNKT": {…}|null, "AUTOMAT": {…}|null }`.
- Aktualizacja: `POST /wp-json/wc/store/v1/cart/extensions` z `{"namespace":"ppwc","data":{"type":"PUNKT","pni":536973}}`.
- Walidacja przy złożeniu zamówienia (`POST /checkout`): błąd `ppwc_pickup_point` (HTTP 400) z komunikatem dla klienta.

W zamówieniu klasycznym punkt trafia w polu `ppwc_pickup_point[PUNKT|AUTOMAT]`, a walidacja działa w `woocommerce_after_checkout_validation`.

### Akcje w panelu (nonce i uprawnienia wymagane)

| Akcja | Typ | Uprawnienie |
|---|---|---|
| `ppwc_test_connection` | AJAX | `manage_woocommerce` |
| `ppwc_shipment` (create, recreate, delete, check) | AJAX | `edit_shop_order` dla zamówienia |
| `ppwc_change_order_point` | AJAX | `edit_shop_order` dla zamówienia |
| `ppwc_labels` | admin-post (GET) | `edit_shop_orders` i `edit_shop_order` dla każdego zamówienia |
| `ppwc_buffers` (labels, move, delete_shipments, today, send, delete_buffer, refresh_envelopes, courier) | admin-post | `manage_woocommerce` |
| `ppwc_refresh_dictionaries`, `ppwc_refresh_points` | admin-post | `manage_woocommerce` |
| `ppwc_create_shipments`, `ppwc_print_labels`, `ppwc_delete_shipments` | akcje zbiorcze listy zamówień | `edit_shop_order` dla każdego zamówienia |

## 3.7. Hooki wtyczki

| Hook | Typ | Parametry | Zastosowanie |
|---|---|---|---|
| `ppwc_init` | akcja | `Plugin $plugin` | wtyczka gotowa, WooCommerce dostępny; miejsce na moduły rozszerzeń |
| `ppwc_services` | filtr | `array<string, Service> $services` | dodanie usług (np. z Prawa Opcji) albo zmiana parametrów istniejących |
| `ppwc_en_client` | filtr | `ClientInterface $client`, `string $environment` | podmiana klienta EN, np. w testach |
| `ppwc_settings_field_placeholder` | filtr | `string $placeholder`, `string $key` | podpowiedzi w polach ustawień |
| `ppwc_settings_after_form` | akcja | `string $tab` | dodatkowa treść pod formularzem zakładki ustawień |

Klasa `Service` (parametry usługi): `id` (identyfikator metody WooCommerce), `en_type` (typ przesyłki w WSDL), `product_symbol`, `point_type` (`PUNKT`, `AUTOMAT` albo `null`), `supports_cod`, `max_weight` (g), `formats`, `requires_weight`.

Wtyczka korzysta z hooków WooCommerce: `woocommerce_shipping_methods`, `woocommerce_available_payment_gateways` (ukrycie płatności przy odbiorze), `woocommerce_after_shipping_rate`, `woocommerce_checkout_update_order_review`, `woocommerce_after_checkout_validation`, `woocommerce_checkout_create_order`, `woocommerce_store_api_checkout_update_order_from_request`, `woocommerce_blocks_checkout_block_registration`, `woocommerce_email_customer_details`, `woocommerce_order_details_after_customer_details`, `woocommerce_admin_order_data_after_shipping_address`, `woocommerce_privacy_export_order_personal_data`, `woocommerce_privacy_remove_order_personal_data` oraz `bulk_actions-*` i `handle_bulk_actions-*` dla obu ekranów zamówień.

## 3.8. Szablony

Szablony w `templates/` można nadpisać w motywie, kopiując je do `twoj-motyw/poczta-polska-for-woocommerce/`:

| Plik | Gdzie |
|---|---|
| `checkout/pickup-point.php` | wybór punktu w zamówieniu klasycznym |
| `checkout/pickup-point-summary.php` | opis wybranego punktu |
| `order/pickup-point.php` | punkt na stronie podziękowania i w Moim koncie |
| `emails/pickup-point.php`, `emails/plain/pickup-point.php` | punkt w e-mailach HTML i tekstowych |

Każdy szablon ma w nagłówku `@version`. Przy zmianie szablonu we wtyczce numer rośnie; nadpisanie ze starszym numerem trzeba porównać z nową wersją. Klasy CSS, atrybuty `data-*` i ukryte pole z szablonu zamówienia muszą zostać, bo korzysta z nich skrypt.

## 3.9. Bezpieczeństwo

- Hasła EN szyfrowane libsodium (`crypto_secretbox`, losowy nonce). Klucz pochodzi ze stałej `PPWC_ENCRYPTION_KEY` w `wp-config.php`, a gdy jej nie ma, z kluczy i soli WordPressa (`AUTH_KEY`, `AUTH_SALT`, `SECURE_AUTH_KEY`, `SECURE_AUTH_SALT`). Zmiana klucza albo soli wymaga ponownego wpisania haseł.
- Każda akcja w panelu sprawdza nonce i uprawnienia; dane wejściowe są sanitizowane, wyjście escapowane, zapytania SQL przez `$wpdb->prepare()`.
- Publiczne punkty wejścia (REST, Store API) nie zmieniają danych sklepu poza sesją koszyka i mają limit zapytań do EN.
- Logi nie zawierają treści żądań EN; wrażliwe pola są maskowane (rozdział [5](05-logi-i-bledy.md)).
- Bez sekretów w kodzie.

## 3.10. Rozszerzanie

Nową usługę (np. przesyłkę zagraniczną z Prawa Opcji) dodaje się przez filtr `ppwc_services` i własną klasę metody dziedziczącą po `PocztaPolska\WooCommerce\Shipping\Methods\AbstractMethod`, zarejestrowaną w `woocommerce_shipping_methods`. Budowę danych przesyłki dla nowego typu EN rozszerza się w `ShipmentBuilder`. Klient EN (`ClientInterface`) jest niezależny od interfejsu, więc nowe operacje WebAPI (np. śledzenie, reklamacje) dodaje się w `src/En/Client.php` bez zmian w ekranach.
