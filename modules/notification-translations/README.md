# Tłumaczenia powiadomień MJML

Moduł dostępny w **PWE Multilang → Tłumaczenia powiadomień**. Korzysta z tych samych uprawnień co pozostałe moduły. Można go wyłączyć w General bez wyłączania odczytu słowników JSON.

- Skanuje katalogi `modules/forms/form-templates/*/notifications/*` zawierające plik `.mjml`, również bez istniejącego słownika. Pozostałe powiadomienia są pomijane.
- Odczytuje `{{nazwa}}`, zachowuje również wpisy słownika jeszcze nieużywane w szablonie. `{{lang}}` pozostaje zmienną systemową.
- Pola języków wynikają z deklaracji `$template_langs` formularza i jawnych wersji językowych jego powiadomień. Edytor i test spójności korzystają ze wspólnego odczytu tej konfiguracji. Pola wszystkich skonfigurowanych języków są dostępne od razu, również gdy brakuje ich jeszcze w słowniku.
- Identyczne pełne zestawy tłumaczeń tworzą jeden wpis z listą wystąpień. Zmienne o tej samej nazwie z różnymi zakresami języków są łączone, gdy ich wspólne tłumaczenia są zgodne i dopasowanie jest jednoznaczne. Brak tłumaczenia i pusty tekst są rozróżniane w danych.
- Różne zestawy pod tą samą nazwą są pokazane obok siebie. Przed zapisem należy wybrać wspólną wersję albo nadać osobne nazwy (ręcznie lub zbiorczo). Wybranie wspólnej wersji zastępuje cały zestaw tłumaczeń pozostałych wariantów.
- Podpowiedzi są dostępne tylko w polskich polach i przeszukują nazwy oraz polskie treści. Użycie istniejącego wpisu przenosi wszystkie tłumaczenia i łączy wystąpienia. Nowa zmienna o istniejącej nazwie lub identycznej treści jest blokowana w formularzu dodawania.
- Wybór powiadomienia i dodanie zmiennej rozpoczyna jego słownik. Nowy plik MJML umieszczony w strukturze szablonów zostanie wykryty po odświeżeniu; edytor nie tworzy samych szablonów.

Biblioteka łączy tłumaczenia używane przez różne formularze, ale do każdego JSON trafiają tylko języki obsługiwane przez dane powiadomienie. Przy następnym zapisie wcześniejsze nadmiarowe języki zostaną usunięte z jego słownika. Tłumaczenia w powiadomieniach obsługujących te języki pozostają zachowane.

## Zapis i migracja

Otwarcie strony niczego nie zapisuje. Dopiero **Zapisz zmiany** zapisuje zmienione powiadomienia. Format `translations.json` zachowuje strukturę `język → zmienna → tekst`, Unicode, HTML i shortcody. JSON jest jedynym formatem lokalnych słowników powiadomień; niepoprawny JSON przerywa operację.

Słowniki powiadomień są przechowywane wyłącznie w `translations.json`. Edytor i generator odczytują JSON bez loaderów PHP; zapis nie tworzy plików `translations.php`. Zmigrowano również słowniki powiadomień niekorzystających z MJML, ale edytor nadal obsługuje tylko MJML. Każde powiadomienie korzysta wyłącznie z własnego `translations.json`; nie ma globalnego słownika ani globalnych wartości domyślnych. Tłumaczenia pól formularzy (`field-translations.php`) pozostają bez zmian.

Scalenie lub zmiana nazwy aktualizuje odwołania w MJML i istniejących HTML oraz usuwa zastąpione klucze ze słownika. Nie wymaga to ponownej kompilacji MJML. Zapis przygotowuje pliki tymczasowe, sprawdza rewizję plików, stosuje blokadę równoczesnego zapisu oraz wycofuje wykonane podmiany w przypadku błędu. Podmiana każdego pliku jest atomowa; cały zestaw plików nie stanowi transakcji dla zewnętrznych czytelników.

Edytor nie zapisuje tłumaczeń do bazy i nie synchronizuje automatycznie formularzy Gravity Forms. Aby istniejące formularze otrzymały zmienione treści, uruchom ich synchronizację w **Forms**. Aktualizacja/reinstalacja wtyczki może nadpisać jej pliki — zmienione słowniki i szablony należy zachować wraz z kodem wtyczki.

## Testy

Z katalogu wtyczki:

```sh
php modules/notification-translations/tests/store-test.php
php modules/notification-translations/tests/language-scope-test.php
node modules/notification-translations/tests/editor-test.cjs
```

Test PHP działa na tymczasowej kopii rzeczywistych szablonów bez WordPressa i bazy. Sprawdza zachowanie renderowanych treści po migracji (z zachowaniem braków tłumaczeń), odczyt JSON bez odtwarzania loaderów PHP, propagację wspólnych zmian, brak zapisów bez zmian, izolację pozostałych plików, konflikty rewizji, walidację, dodawanie języka i ponowne użycie zmiennej. Test JS używa lekkiego modelu DOM i sprawdza interakcje edytora; nie zastępuje sprawdzenia wyglądu w przeglądarce.
