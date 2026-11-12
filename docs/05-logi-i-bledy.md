# 5. Logi i obsługa błędów

## 5.1. Log WooCommerce

Wtyczka zapisuje zdarzenia w logu WooCommerce ze źródłem **poczta-polska**: **WooCommerce → Status → Logi**, a potem wybór źródła `poczta-polska`. Link do logów jest też w zakładce „Logi i dane” ustawień wtyczki. Pliki logów (lub wpisy w bazie, zależnie od ustawień WooCommerce) WooCommerce usuwa po czasie określonym w swoich ustawieniach.

Poziom szczegółowości ustawia się w zakładce „Logi i dane”:

| Poziom | Co trafia do logu |
|---|---|
| Tylko błędy | nieudane wywołania EN (operacja, czas, komunikat błędu), błędy odświeżania punktów, zerwane połączenia przy tworzeniu przesyłek, błędy odczytu zbiorów i listy buforów |
| Błędy i operacje | jak wyżej oraz odświeżenie punktów każdego województwa (liczba punktów) i problemy ze sprawdzaniem ważności hasła |
| Wszystko (diagnostyka) | jak wyżej oraz każde wywołanie EN z nazwą operacji i czasem trwania |

Treść żądań i odpowiedzi EN nie jest zapisywana. Gdy w kontekście wpisu pojawia się pole wrażliwe (hasło, nagłówek autoryzacji, numer rachunku, telefon, e-mail, imię, nazwisko, nazwa, adres), wartość jest zastępowana `***`.

Poziom „Wszystko” służy do diagnozy; po jej zakończeniu warto wrócić do „Tylko błędy”.

## 5.2. Rejestr operacji

Ważne operacje administracyjne zapisują się z użytkownikiem i datą w tabeli `ppwc_audit_log`. 50 ostatnich wpisów widać w zakładce „Logi i dane”.

| Operacja | Kiedy |
|---|---|
| `settings_updated` | zapis ustawień (zapisywane są nazwy zmienionych pól, bez wartości) |
| `order_point_changed` | zmiana punktu odbioru w zamówieniu |
| `shipment_deleted` | usunięcie przesyłki |
| `buffer_deleted` | usunięcie bufora |
| `buffer_sent` | wysłanie bufora do urzędu (numer zbioru, liczba przesyłek) |
| `buffer_date_changed` | przestawienie daty bufora (ile przesyłek dostało nowe numery) |
| `buffer_missing` | bufor zniknął z EN (usunięty albo wysłany poza wtyczką) |
| `courier_ordered` | zamówienie kuriera |
| `points_refresh_queued` | ręczne uruchomienie odświeżania punktów |

## 5.3. Najczęstsze błędy

Komunikaty z Elektronicznego Nadawcy wtyczka pokazuje po polsku, w brzmieniu z EN, z numerem błędu: „… (błąd EN 84049)”. Błędy przy pojedynczych przesyłkach pojawiają się przy tych przesyłkach, także przy operacjach zbiorczych.

| Komunikat / błąd | Przyczyna | Co zrobić |
|---|---|---|
| „Elektroniczny Nadawca odrzucił login lub hasło.” | błędne dane albo wygasłe hasło EN | sprawdź dane w zakładce „Połączenie” i aktywne środowisko; zmień hasło w EN i wpisz nowe |
| „Wpisz login i hasło do Elektronicznego Nadawcy w ustawieniach wtyczki.” | brak danych dla aktywnego środowiska | uzupełnij zakładkę „Połączenie” |
| brak połączenia, przekroczony czas | EN niedostępny albo serwer sklepu nie łączy się z EN | sprawdź połączenie wychodzące HTTPS; przesyłka zostaje w stanie „Niepotwierdzona” - po powrocie EN kliknij **Sprawdź w EN** |
| błąd 84049 | punkt nie przyjmuje pobrania | wybierz inny punkt albo inną płatność |
| błąd 30707 | brak telefonu komórkowego przy odbiorze w punkcie | wpisz komórkę w panelu przesyłki |
| błąd 84024 | Pocztex bez zawartości | uzupełnij „Zawartość” w zakładce „Parametry przesyłek” |
| błąd 84137, 84135 | format niedozwolony dla usługi (XL w automacie, 2XL poza kurierem) | zmień format w panelu przesyłki albo w ustawieniach metody |
| błąd 95901 | brak profilu nadawcy w buforze | wybierz profil w zakładce „Umowa i nadanie” |
| błąd 85303 | bufor z datą z przeszłości | wtyczka nie tworzy takich buforów; jeśli bufor ma starą datę, użyj **Zmień datę na dzisiejszą** |
| błąd 96001, 20603 | bufor nie istnieje w EN | wtyczka oznacza przesyłki z tego bufora jako odrzucone; sprawdź je w EN i utwórz ponownie |
| błąd 50701 przy usuwaniu | przesyłki już nie ma w EN | brak działania: wtyczka traktuje przesyłkę jako usuniętą |
| lista „Popraw przed utworzeniem przesyłki” | dane zamówienia nie spełniają wymagań EN (np. kod pocztowy, długość nazwy lub ulicy, brak komórki przy punkcie, waga poza limitem, brak rachunku do pobrania) | popraw zamówienie albo ustawienia; wtyczka sprawdza to przed wysłaniem do EN |
| „Usługi krajowe doręczają tylko na adresy w Polsce.” | adres zagraniczny | usługi zagraniczne nie są w tej wersji obsługiwane |

Pełna lista kodów błędów EN jest w dokumentacji WebAPI Elektronicznego Nadawcy.

## 5.4. Komunikaty w panelu

| Komunikat | Znaczenie |
|---|---|
| brak rozszerzenia PHP SOAP | wtyczka nie połączy się z EN; poproś administratora serwera o włączenie rozszerzenia `soap` |
| PHP bez poprawek bezpieczeństwa | wersja PHP starsza niż załatana w danej gałęzi; zaktualizuj PHP, chyba że dystrybucja wprowadziła poprawki w starszej wersji |
| hasło EN wkrótce wygaśnie | na 14 dni przed wygaśnięciem; zmień hasło w EN i wpisz nowe w ustawieniach |
| produkty bez wagi | przesyłka eCommerce nie jest dla nich oferowana, a Pocztex użyje wagi domyślnej |

## 5.5. Zgłaszanie problemu

Do zgłoszenia dołącz:

1. opis: co zrobiono, czego oczekiwano, co się stało, data i godzina,
2. numer zamówienia, numer nadania albo numer bufora lub zbioru,
3. treść komunikatu z ekranu,
4. fragment logu `poczta-polska` z tego czasu (dane wrażliwe są w nim zamaskowane),
5. raport systemowy z **WooCommerce → Status → Pobierz raport systemowy** (wersje WordPressa, WooCommerce, PHP i wtyczek).

Nie wysyłaj loginu ani hasła do Elektronicznego Nadawcy.
