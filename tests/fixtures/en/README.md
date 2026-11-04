Odpowiedzi WebAPI Elektronicznego Nadawcy zapisane z konta testowego INT (2026-11-02).
`getPlacowkiPocztowe.xml` jest skrócona do jednej placówki na każdą kombinację: sieć, punkt wydania przesyłki biznesowej, pobranie.
Dane osobowe w tych plikach nie występują (karty, urzędy nadania i placówki są danymi PP). Nazwa użytkownika konta i numer drugiej karty są zastąpione wartościami testowymi.
`getEnvelopeBufor-multiparcel.xml` i `getEnvelopeContentShort.xml` (2026-11-03): bufor po zmianie daty na INT z przesyłką wielopaczkową i zawartość wysłanej koperty 7817740. Dane adresowe w buforze zastąpione wartościami testowymi.
`addShipment-missing-buffer.xml` (2026-11-03): odpowiedź `addShipment` dla usuniętego bufora, błąd 20603 bez GUID.
