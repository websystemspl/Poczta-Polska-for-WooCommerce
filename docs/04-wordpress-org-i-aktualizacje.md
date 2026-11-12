# 4. Instalacja z katalogu WordPress.org i aktualizacje

## 4.1. Instalacja z katalogu

Wtyczka została zgłoszona do katalogu WordPress.org 10.11.2026. Po zatwierdzeniu przez zespół WordPress.org będzie dostępna pod nazwą **Poczta Polska for WooCommerce** (identyfikator `poczta-polska-for-woocommerce`).

1. W panelu WordPressa: **Wtyczki → Dodaj nową**.
2. W polu wyszukiwania wpisz „Poczta Polska for WooCommerce”.
3. Przy wtyczce kliknij **Zainstaluj teraz**, a potem **Włącz**.

WordPress sam pilnuje wymagań z nagłówka wtyczki: wymaganej wersji WordPressa, PHP i obecności WooCommerce (`Requires Plugins: woocommerce`). Dalej postępuj według rozdziału [1.3](01-instalacja-i-konfiguracja.md#13-konfiguracja-wtyczki).

Bez dostępu do katalogu (np. sklep bez połączenia z WordPress.org) wtyczkę instaluje się z pliku ZIP: **Wtyczki → Dodaj nową → Wyślij wtyczkę na serwer**. Pliki ZIP kolejnych wersji są też w wydaniach repozytorium na GitHubie.

## 4.2. Aktualizacje z katalogu

Gdy w katalogu pojawi się nowa wersja, WordPress pokazuje powiadomienie na liście wtyczek. Aktualizację uruchamia się:

- ręcznie: **Wtyczki → Zaktualizuj teraz** przy wtyczce albo **Kokpit → Aktualizacje**,
- automatycznie: **Włącz automatyczne aktualizacje** przy wtyczce na liście wtyczek.

Przy aktualizacji:

- ustawienia, przesyłki, bufory, kopia punktów i rejestr operacji zostają,
- jeśli nowa wersja zmienia tabele, wtyczka aktualizuje je przy pierwszym wejściu do panelu (bez ponownego włączania),
- tłumaczenie pl_PL jest częścią paczki i aktualizuje się razem z wtyczką.

Zalecenia:

1. Przeczytaj listę zmian (zakładka **Changelog** w katalogu albo `CHANGELOG.md`). Zmiana pierwszej cyfry wersji (np. 1.x → 2.0) może wymagać czynności po aktualizacji - są wtedy opisane w changelogu.
2. Zrób kopię zapasową bazy danych.
3. Na sklepie z dużym ruchem sprawdź aktualizację najpierw na kopii sklepu (staging), najlepiej podłączonej do środowiska testowego EN.
4. Nie aktualizuj w trakcie wysyłania buforów. Przesyłki utworzone przed aktualizacją można dalej obsługiwać normalnie.
5. Jeśli motyw nadpisuje szablony wtyczki (`twoj-motyw/poczta-polska-for-woocommerce/`), porównaj ich `@version` z wersjami w `templates/` nowej wersji i przenieś zmiany.

## 4.3. Aktualizacja z pliku ZIP

**Wtyczki → Dodaj nową → Wyślij wtyczkę na serwer**, wybierz nowszy plik ZIP i potwierdź **Zastąp bieżącą wersją przesłaną**. Dane zostają tak samo jak przy aktualizacji z katalogu.

## 4.4. Odinstalowanie

**Wyłączenie** wtyczki (Wtyczki → Wyłącz) zatrzymuje zadania w tle i usuwa metody Poczty Polskiej z zamówienia, ale zostawia wszystkie dane. Po ponownym włączeniu wtyczka działa jak wcześniej.

**Usunięcie** wtyczki (Wtyczki → Usuń, po wyłączeniu) zawsze kasuje:

- ustawienia, w tym zaszyfrowane hasła EN,
- kopię punktów odbioru (tabela `ppwc_points`),
- metody Poczty Polskiej ze stref wysyłki i ich ustawienia,
- zaplanowane zadania, dane tymczasowe i blokady.

Przesyłki (numery nadania, bufory, stan etykiet, stany zbiorów) i rejestr operacji zostają, bo numery nadania należą do historii zamówień. Usuwa je dopiero zaznaczenie **Usuń wszystkie dane przy odinstalowaniu** w zakładce „Logi i dane” przed usunięciem wtyczki.

Usunięcie wtyczki nie zmienia zamówień: punkt odbioru zapisany w zamówieniu i pozycja wysyłki zostają. W sieci WordPress (multisite) dane są usuwane na wszystkich stronach.

Przesyłki w Elektronicznym Nadawcy nie są usuwane przy odinstalowaniu. Niewysłane bufory trzeba wysłać albo usunąć wcześniej, na ekranie Bufory albo w EN.
