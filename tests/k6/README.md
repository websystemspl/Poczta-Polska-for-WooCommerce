# Testy wydajnościowe (k6)

Skrypty [k6](https://k6.io/) symulują ruch w sklepie i w panelu sprzedawcy, zgodnie z OPZ II.7.4.e (testy wydajnościowe prowadzi Wykonawca pod nadzorem Zamawiającego narzędziem automatycznie symulującym obciążenie). k6 to program uruchamiany z linii poleceń: wysyła do sklepu żądania HTTP według scenariusza i mierzy czasy odpowiedzi i liczbę błędów.

## Scenariusze

| Plik | Co robi | Elektroniczny Nadawca |
|---|---|---|
| `checkout.js` | Klienci składają zamówienia: nowy koszyk, produkt, adres w Polsce, metoda **Pocztex PUNKT** z punktem odbioru (dwa na trzy zamówienia) albo **Pocztex Kurier**, płatność przelewem. To te same żądania Store API, które wysyła blokowy formularz zamówienia. Punkt pochodzi z zapasowej wyszukiwarki wtyczki (`GET /wp-json/ppwc/v1/points`), bo mapa Poczty Polskiej działa tylko w przeglądarce. | **Nie łączy się z EN.** Bezpieczny na każdym sklepie z wtyczką. |
| `bulk-shipments.js` | Składa `PPWC_K6_ORDERS` zamówień z Pocztex Kurier, po czym `PPWC_K6_MANAGERS` sprzedawców **w tym samym momencie** uruchamia akcję zbiorczą „Poczta Polska: utwórz przesyłki” na tych samych zamówieniach. Mierzy czas akcji i sprawdza, że powstała dokładnie jedna przesyłka na zamówienie (blokady przy równoległych żądaniach). Na koniec usuwa przesyłki i puste bufory w EN i przenosi zamówienia do kosza. | **Tworzy przesyłki w EN.** Domyślnie wyłączony (`PPWC_K6_EN=1`). Zatrzymuje się, jeśli wtyczka nie pracuje na środowisku testowym EN z dozwoloną kartą (`PPWC_K6_EN_CARD`, domyślnie 458350). |

Produkty, adres, kod pocztowy do wyszukiwania punktu i dane sprzedawcy są w `lib/config.js` i można je zmienić zmiennymi środowiskowymi (tabela niżej).

## Wymagania

- k6 (Linux: pakiet `k6` z repozytorium Grafany, macOS: `brew install k6`, Windows: `winget install k6`).
- Sklep z wtyczką: strefa wysyłki „Polska” z metodami Poczty Polskiej, płatność „Przelew” (`bacs`), zakupy bez konta, kopia punktów odbioru pobrana z EN (Ustawienia wtyczki → Logi i dane → Odśwież teraz), produkt z wagą (domyślnie ID 13).
- Dla `bulk-shipments.js`: konto sprzedawcy z uprawnieniem `manage_woocommerce`, wtyczka na środowisku testowym EN, karta 458350.

## Uruchomienie

W katalogu wtyczki:

```bash
# Zakupy: 5 klientów przez minutę (domyślnie)
k6 run tests/k6/checkout.js

# Zakupy: 50 klientów przez 5 minut na stagingu
PPWC_K6_BASE_URL=https://staging.example PPWC_K6_INSECURE=0 PPWC_K6_VUS=50 PPWC_K6_DURATION=5m k6 run tests/k6/checkout.js

# Przesyłki zbiorcze: 50 zamówień, 2 sprzedawców naraz (tworzy przesyłki w EN!)
PPWC_K6_EN=1 PPWC_K6_ADMIN_PASSWORD='hasło' PPWC_K6_ORDERS=50 k6 run tests/k6/bulk-shipments.js
```

Raport w pliku: `k6 run --summary-export=wynik.json tests/k6/checkout.js` (JSON) albo `--out csv=wynik.csv` (każde żądanie).

## Ustawienia (zmienne środowiskowe)

| Zmienna | Domyślnie | Znaczenie |
|---|---|---|
| `PPWC_K6_BASE_URL` | `https://poczta.localhost` | Adres sklepu |
| `PPWC_K6_INSECURE` | `1` | `1` akceptuje certyfikat lokalny (self-signed); na stagingu ustaw `0` |
| `PPWC_K6_PRODUCT` | `13` | ID produktu z wagą |
| `PPWC_K6_POINT_SEARCH` | `94-238` | Kod pocztowy, miasto albo ulica do wyszukania punktu odbioru |
| `PPWC_K6_ADDRESS`, `PPWC_K6_POSTCODE`, `PPWC_K6_CITY` | Pługowa 39, 94-238, Łódź | Adres dostawy w Polsce |
| `PPWC_K6_EMAIL` | `k6@example.com` | E-mail klienta (po tym adresie łatwo znaleźć zamówienia testowe) |
| `PPWC_K6_VUS` | `5` | Liczba równoczesnych klientów (`checkout.js`) |
| `PPWC_K6_DURATION` | `1m` | Czas pełnego obciążenia (plus 15 s rozpędzania i 10 s wygaszania) |
| `PPWC_K6_ADMIN_USER` | `admin` | Login sprzedawcy (`bulk-shipments.js`) |
| `PPWC_K6_ADMIN_PASSWORD` | (brak) | Hasło sprzedawcy, wymagane dla `bulk-shipments.js` |
| `PPWC_K6_EN` | `0` | `1` pozwala tworzyć przesyłki w EN |
| `PPWC_K6_EN_CARD` | `458350` | Jedyna dozwolona karta umowy |
| `PPWC_K6_ORDERS` | `20` | Liczba zamówień do akcji zbiorczej (najwyżej 100, tyle obsługuje jedna akcja) |
| `PPWC_K6_MANAGERS` | `2` | Liczba sprzedawców uruchamiających akcję naraz |
| `PPWC_K6_KEEP` | `0` | `1` zostawia zamówienia, przesyłki i bufory po teście |

## Progi powodzenia

Wynik jest **zielony**, gdy wszystkie progi zapisane w `options.thresholds` są spełnione; k6 kończy wtedy z kodem 0, a przy przekroczeniu z kodem 99.

`checkout.js`:

- błędy HTTP poniżej 1%, sprawdzenia (`checks`) powyżej 99%,
- 95% żądań: koszyk poniżej 1,5 s, adres i wybór punktu poniżej 2 s, wyszukiwarka punktów poniżej 0,8 s, złożenie zamówienia poniżej 3 s.

`bulk-shipments.js`:

- błędy HTTP poniżej 1%, wszystkie sprawdzenia zaliczone,
- akcja zbiorcza poniżej 120 s (95%); w logu `shipments in the buffers of the plugin: N for N orders` liczby muszą być równe (brak dubli przy równoległych sprzedawcach).

Progi są wartościami startowymi do uzgodnienia z Zamawiającym przed testami akceptacyjnymi. Zmienia się je w pliku scenariusza.

## Jak czytać wynik

Najważniejsze wiersze podsumowania:

- `http_req_duration{name:...}`: czasy odpowiedzi według kroku (`cart`, `cart_update_customer`, `points_search`, `cart_set_point`, `checkout`, `bulk_create`); patrz na `p(95)`,
- `http_req_failed`: odsetek żądań z błędem HTTP,
- `checks`: odsetek zaliczonych sprawdzeń (np. „order placed”); nazwa niezaliczonego sprawdzenia wskazuje krok, który zawiódł,
- `ppwc_orders_placed`, `ppwc_shipments_created`, `ppwc_bulk_create_seconds`: liczniki wtyczki.

## Co jeszcze sprawdzić w trakcie testu

- **Brak wywołań EN podczas zakupów:** przy poziomie logowania „wszystko (diagnostyka)” w ustawieniach wtyczki każde wywołanie EN trafia do logu `poczta-polska` (WooCommerce → Stan → Logi). Liczba wpisów nie może wzrosnąć w czasie `checkout.js`. Sprawdzone lokalnie 4.11.2026: 30 zamówień, 0 nowych wpisów.
- **Brak dubli przesyłek:** po `bulk-shipments.js` z `PPWC_K6_KEEP=1` ekran Poczta Polska → Bufory pokazuje tyle przesyłek, ile było zamówień.
- Obciążenie serwera (CPU, baza) obserwuje administrator stagingu; k6 mierzy tylko czasy odpowiedzi.

## Uwagi

- Wyniki z komputera deweloperskiego są tylko orientacyjne. Właściwy test wykonuje się na stagingu Zamawiającego, z komputera w tej samej sieci lub o znanej przepustowości, w terminie uzgodnionym z Zamawiającym.
- `checkout.js` zostawia w sklepie zamówienia testowe (klient `k6@example.com`, status „oczekuje na płatność”). Po teście można je usunąć w WooCommerce → Zamówienia (filtr po kliencie) albo WP-CLI.
- Scenariusze nie uruchamiają JavaScriptu ani mapy Poczty Polskiej; obciążenie mapy po stronie PP nie jest przedmiotem testu.
