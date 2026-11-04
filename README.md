# Poczta Polska for WooCommerce

Wtyczka łączy sklep WooCommerce z systemem **Elektroniczny Nadawca (EN)** Poczty Polskiej. Klient sklepu wybiera dostawę Pocztą Polską przy zamówieniu, a sprzedawca nadaje paczki prosto z panelu WordPressa, bez przepisywania danych do EN.

Wersja 0.2.1, przed odbiorem przez Pocztę Polską. Zmiany: [CHANGELOG.md](CHANGELOG.md).

## Co robi wtyczka

Sklep łączy się z EN bezpośrednio, przez WebAPI. Po drodze nie ma żadnego serwera pośredniego, klucza licencyjnego ani wysyłania danych do kogoś trzeciego.

### Dla klienta sklepu (zamówienie)

- Metody dostawy do wyboru:
  - **Pocztex Kurier**: kurier przywozi paczkę pod drzwi.
  - **Pocztex PUNKT**: odbiór w placówce Poczty albo u partnera (Żabka, Lewiatan, ABC i inne).
  - **Pocztex AUTOMAT**: odbiór z automatu paczkowego.
  - **Przesyłka eCommerce**: list polecony firmowy, do 2 kg.
- Każda metoda ma swoją cenę. Sprzedawca może dodać ich dowolnie dużo, w zwykłych strefach wysyłki WooCommerce.
- Każdej metodzie można ustawić limit wagi i kraje, do których działa. Usługa krajowa nie pokaże się przy adresie zagranicznym.
- Punkt odbioru klient wybiera na oficjalnej mapie Poczty Polskiej:
  - mapa pokazuje tylko punkty pasujące do metody, czyli placówki albo automaty,
  - bez wybranego punktu nie da się złożyć zamówienia,
  - mapa działa w klasycznym koszyku i w nowym koszyku na blokach.
- Wybrany punkt zapisuje się w zamówieniu. Widać go w panelu, w mailach i na koncie klienta.
- **Płatność przy odbiorze (pobranie):**
  - jest dostępna tylko przy Pocztexie, nie przy przesyłce eCommerce,
  - przy odbiorze w punkcie działa tylko w punktach, które obsługują pobranie, i tylko do ich limitu kwoty,
  - wtyczka sprawdza to także na serwerze, nie tylko w przeglądarce.
- Lista punktów odświeża się sama raz na dobę.

### Dla sprzedawcy (ustawienia)

- **Logowanie do EN:** osobne dane dla wersji produkcyjnej i testowej, z przełącznikiem między nimi. Wtyczka pozwala sprawdzić połączenie, ostrzega przed wygaśnięciem hasła i pokazuje zrozumiały komunikat przy błędnym loginie.
- **Umowy z Pocztą (karty):** wtyczka pobiera je z EN. Sprzedawca wybiera domyślną umowę i może przypisać inną do każdej metody dostawy.
- **Domyślne ustawienia paczek:**
  - Pocztex: rozmiar (od S do 2XL), waga, „ostrożnie”, zawartość, ubezpieczenie, opis,
  - eCommerce: gabaryt A albo B.
- **Sposób nadania** Pocztexu:
  - kurier odbiera paczki od sprzedawcy,
  - sprzedawca zanosi je do placówki,
  - sprzedawca nadaje je w automacie.

  Automat nadania wybiera się na mapie.
- **Urząd nadania** z listy urzędów z umowy w EN: domyślny w ustawieniach, a dla każdej przesyłki można wybrać inny.
- **Dane nadawcy** na etykiecie pochodzą z profilu nadawcy w EN, który wybiera się w ustawieniach. Osobny adres w ustawieniach służy kurierowi do odbioru paczek.

### Dla sprzedawcy (nadawanie paczek)

Paczki z jednego dnia trafiają w EN do „bufora”, czyli koszyka przesyłek do nadania. Wtyczka obsługuje go tak:

1. Tworzy przesyłkę z zamówienia. Dane adresata i nadawcy wpisują się same, a wtyczka sprawdza je przed wysłaniem do EN.
2. Pozwala poprawić przesyłkę, dopóki nie wydrukowano etykiety: telefon komórkowy i stacjonarny, punkt odbioru (na mapie), rodzaj przesyłki, format, liczbę paczek, wagę i urząd nadania.
3. Przenosi przesyłki między buforami i usuwa je, także w EN.
4. Drukuje etykiety. Po wydruku przesyłki nie da się już zmienić, można ją tylko usunąć.
5. Wysyła bufor do EN, czyli przekazuje Poczcie dane do nadania, i pokazuje stan wysłanego zbioru.
6. Zamawia kuriera po paczki.

Ponadto:
- Jedna przesyłka Pocztex może mieć kilka kartonów.
- Wiele zamówień można obsłużyć naraz z listy zamówień: utworzyć przesyłki, wydrukować etykiety (jeden PDF) albo usunąć przesyłki. Na ekranie „Bufory” można przenieść albo usunąć zaznaczone przesyłki i wysłać cały bufor.
- Numer nadania widać na liście zamówień.
- Wtyczka zauważa bufor usunięty albo wysłany w EN poza sklepem i oznacza jego przesyłki.
- Bufor z poprzedniego dnia wtyczka sama przestawia na dzisiejszą datę.
- Gdy połączenie zerwie się w trakcie, nie powstanie podwójna przesyłka.
- Gdy część paczek przejdzie, a część nie, błąd pokazuje się przy konkretnej przesyłce.
- Błędy z EN wyświetlają się jako zrozumiałe komunikaty po polsku.

### Dziennik zdarzeń

- Wymiana danych z EN zapisuje się w logu WooCommerce. Hasła, numery kont i dane osobowe są w nim zamaskowane.
- Ważne operacje zapisują się razem z nazwą użytkownika, który je wykonał: zmiana ustawień, usunięcie przesyłki albo bufora.

## Jak jest zbudowana

- Działa z nowym sposobem przechowywania zamówień w WooCommerce (HPOS) i z koszykiem na blokach.
- Nie zmienia plików WordPressa ani WooCommerce.
- Hasło do EN jest w bazie zaszyfrowane i nie wraca do formularza.
- W kodzie nie ma żadnych haseł ani kluczy.
- Połączenie z EN jest szyfrowane.
- Dane osobowe zostają w sklepie i w EN. Działają narzędzia prywatności WordPressa (eksport i usuwanie danych).
- Interfejs jest po polsku.

## Czego nie ma w pierwszej wersji

Wtyczka jest zbudowana tak, żeby te funkcje dało się dodać później:

- przesyłki paletowe,
- usługi zagraniczne i deklaracje celne,
- śledzenie przesyłek i powiadomienia dla klienta,
- automatyczna zmiana statusu zamówienia,
- książka nadawcza,
- zwroty,
- odbiór paczek spod adresu nadawcy (OPNA),
- reklamacje.

## Wymagania

- WordPress i WooCommerce w wersjach, które ich twórcy nadal wspierają,
- PHP 8.2 lub nowszy, z poprawkami bezpieczeństwa z 21.10.2026 (CVE-2026-109618 i CVE-2026-109619), czyli 8.2.35, 8.3.36, 8.4.27, 8.5.12 albo nowszy,
- rozszerzenie PHP `soap`.

## Praca nad kodem

Zasady (gałęzie, pull requesty, code review, numeracja wersji, changelog, testy): [CONTRIBUTING.md](CONTRIBUTING.md).

Narzędzia deweloperskie (nie trafiają do paczki ZIP):

```bash
composer install          # PHPCS, PHPStan, PHPUnit
composer lint             # standardy WordPress (PHPCS)
composer analyse          # analiza statyczna (PHPStan, poziom 8)
composer test             # testy jednostkowe (PHPUnit)
npm install && npx playwright install chromium
npm run test:e2e          # testy E2E w przeglądarce, instrukcja: tests/e2e/README.md
npm run test:k6           # test wydajnościowy zakupów (k6), instrukcja: tests/k6/README.md
composer build            # paczka ZIP w dist/
```

Każdy pull request sprawdza automatycznie GitHub Actions: PHPCS, PHPStan, PHPUnit na PHP 8.2–8.5, audyt zależności Composer i npm oraz zbudowanie paczki ZIP.

## Licencja

GPL-2.0-or-later
