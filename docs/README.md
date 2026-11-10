# Poczta Polska for WooCommerce – dokumentacja

Dokumentacja wtyczki w wersji 0.2.3. Opisuje instalację, konfigurację, pracę sprzedawcy, budowę wtyczki, obsługę błędów oraz budowanie i wdrażanie nowych wersji.

| Rozdział | Dla kogo |
|---|---|
| [1. Instalacja i konfiguracja](01-instalacja-i-konfiguracja.md) | administrator sklepu |
| [2. Instrukcja dla sprzedawcy](02-instrukcja-sprzedawcy.md) | sprzedawca, obsługa zamówień |
| [3. Architektura i interfejsy API](03-architektura-i-api.md) | programista |
| [4. Instalacja z katalogu WordPress.org i aktualizacje](04-wordpress-org-i-aktualizacje.md) | administrator sklepu |
| [5. Logi i obsługa błędów](05-logi-i-bledy.md) | administrator, wsparcie techniczne |
| [6. Budowanie i wdrażanie](06-budowanie-i-wdrazanie.md) | programista, osoba wydająca wersje |

Pojęcia używane w dokumentacji:

- **EN** – Elektroniczny Nadawca, system Poczty Polskiej do nadawania przesyłek. Wtyczka łączy się z nim przez WebAPI.
- **Środowisko testowe (INT)** i **produkcyjne** – dwie osobne instancje EN z osobnymi danymi logowania. Przesyłki z INT nie są nadawane naprawdę.
- **Karta umowy** – umowa sprzedawcy z Pocztą Polską zapisana w EN. Na jednym koncie może być kilka kart.
- **Bufor** – w EN zbiór przesyłek przygotowanych do nadania jednego dnia. Po wysłaniu bufor staje się **zbiorem** (kopertą) w urzędzie nadania.
- **PNI** – numer placówki lub automatu Poczty Polskiej.
- **Numer nadania** – numer przesyłki widoczny na etykiecie (np. `PX6038626979`).
