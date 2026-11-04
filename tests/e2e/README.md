# Testy automatyczne E2E (Playwright)

Testy przechodzą w przeglądarce przez zamówienie w sklepie i przez panel sprzedawcy, tak jak robi to człowiek. Sprawdzają wymagania OPZ II.1.2–II.1.4 (metody dostawy, punkty odbioru, pobranie) oraz II.2.1–II.2.2 (przesyłki i etykiety w Elektronicznym Nadawcy).

## Co jest sprawdzane

| Plik | Scenariusze |
|---|---|
| `specs/checkout-blocks.spec.ts` | Zamówienie na blokach (Cart/Checkout Blocks): cztery metody PP dla adresu w Polsce; brak listu eCommerce dla produktu bez wagi; brak metod krajowych dla adresu za granicą; brak zamówienia bez wybranego punktu; zamówienie z punktem i punkt na stronie podziękowania; automat z pobraniem; blokada pobrania w punkcie bez pobrania i ponad limit punktu; brak pobrania przy liście eCommerce; otwarcie mapy Poczty Polskiej. |
| `specs/checkout-classic.spec.ts` | To samo w klasycznym zamówieniu (`[woocommerce_checkout]`). |
| `specs/admin-shipments.spec.ts` | Panel sprzedawcy: przesyłka z zamówienia w EN, numer nadania na liście zamówień, etykieta PDF i blokada edycji po jej pobraniu, usunięcie przesyłki albo wysłanie bufora. **Domyślnie wyłączone**, patrz niżej. |

Scenariusze oznaczone `@mobile` przechodzą też w widoku telefonu (Pixel 7).

Punkt odbioru testy wybierają w zapasowej wyszukiwarce wtyczki (lokalna kopia punktów z EN), bo mapy Poczty Polskiej, usługi zewnętrznej, nie da się wiarygodnie klikać automatem. Osobny test sprawdza, że mapa się otwiera.

## Wymagania

- Node.js 20 lub nowszy.
- Sklep testowy z wtyczką: WooCommerce, strona z zamówieniem na blokach i strona z `[woocommerce_checkout]`, strefa wysyłki „Polska” z czterema metodami Poczty Polskiej, płatności „Przelew” (`bacs`) i „Płatność przy odbiorze” (`cod`), zakupy bez konta, kopia punktów odbioru (Ustawienia wtyczki → Logi i dane → Odśwież teraz).
- Trzy produkty: lekki z wagą, bez wagi i droższy niż limit pobrania punktu (domyślnie 4000 zł).

## Uruchomienie

W katalogu wtyczki:

```bash
npm install
npx playwright install chromium
PPWC_E2E_BASE_URL=https://sklep-testowy.example PPWC_E2E_ADMIN_PASSWORD='hasło' npm run test:e2e
```

Raport HTML: `npm run test:e2e:report` (pliki w `tests/e2e/report/`). Przy błędzie w `tests/e2e/results/` zostają zrzut ekranu i ślad (`npx playwright show-trace …`).

## Ustawienia (zmienne środowiskowe)

| Zmienna | Domyślnie | Znaczenie |
|---|---|---|
| `PPWC_E2E_BASE_URL` | `https://poczta.localhost` | Adres sklepu |
| `PPWC_E2E_IGNORE_HTTPS_ERRORS` | `1` | `1` akceptuje certyfikat lokalny (self-signed) |
| `PPWC_E2E_ADMIN_USER` / `PPWC_E2E_ADMIN_PASSWORD` | `admin` / brak | Konto z rolą Administrator albo Kierownik sklepu |
| `PPWC_E2E_BLOCKS_CHECKOUT` | `/checkout/` | Strona zamówienia na blokach |
| `PPWC_E2E_CLASSIC_CHECKOUT` | `/zamowienie-classic/` | Strona z `[woocommerce_checkout]` |
| `PPWC_E2E_PRODUCT_LIGHT` / `_NO_WEIGHT` / `_EXPENSIVE` | `13` / `31` / `32` | ID produktów |
| `PPWC_E2E_POINT_SEARCH` / `_POINT_PNI` | `01-560` / `536973` | Punkt z pobraniem i tekst, po którym go szukać |
| `PPWC_E2E_POINT_NO_COD_SEARCH` / `_POINT_NO_COD_PNI` | `26-415` / `531604` | Punkt bez pobrania |
| `PPWC_E2E_LOCKER_SEARCH` / `_LOCKER_PNI` | `08-440` / `213458` | Automat z pobraniem |
| `PPWC_E2E_EN` | `0` | `1` włącza testy z przesyłkami w EN |
| `PPWC_E2E_EN_CARD` | `458350` | Jedyna karta, na której testy mogą tworzyć przesyłki |
| `PPWC_E2E_EN_SEND` | `0` | `1` wysyła bufor zamiast usuwać przesyłkę |

## Testy z przesyłkami w Elektronicznym Nadawcy

`PPWC_E2E_EN=1` tworzy prawdziwą przesyłkę w EN. Przed tym test sprawdza, że wtyczka pracuje w środowisku testowym (INT) i że domyślną kartą jest `PPWC_E2E_EN_CARD`; w przeciwnym razie przerywa. Na koniec przesyłka jest usuwana. Z `PPWC_E2E_EN_SEND=1` bufor jest wysyłany do urzędu: wysłane zostają wszystkie przesyłki z dzisiejszego bufora tej karty.

Zamawianie kuriera nie jest zautomatyzowane, bo zamawia prawdziwy podjazd. Sprawdza się je ręcznie na ekranie Poczta Polska → Bufory.

## Dane testowe

Każde uruchomienie składa kilka zamówień jako gość `e2e@example.com` (Jan Testowy, Łódź). To dane fikcyjne; zamówienia można usunąć w WooCommerce → Zamówienia.
