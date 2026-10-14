# PWE Multilang

**PWE Multilang** to modułowa wtyczka WordPress do zarządzania wielojęzycznymi stronami, formularzami Gravity Forms oraz tłumaczeniami powiadomień dla serwisów Ptak Warsaw Expo. Współpracuje z **WPML** i umożliwia synchronizację treści na podstawie konfiguracji języków dostępnej na stronie.

![Wersja](https://img.shields.io/badge/version-1.2.0-blue) ![PHP](https://img.shields.io/badge/PHP-%E2%89%A58.0-777bb4) ![WordPress](https://img.shields.io/badge/platform-WordPress-21759b) ![Licencja](https://img.shields.io/badge/license-GPL--2.0--or--later-green)

> Wtyczka jest przeznaczona do środowiska WordPress z odpowiednio skonfigurowanymi WPML i Gravity Forms. Część funkcji bazuje na integracjach i szablonach specyficznych dla serwisów PWE.

## Funkcjonalności

- **Języki WPML** – odczyt języków docelowych i synchronizacja aktywnych języków witryny.
- **Strony** – generowanie i synchronizacja wielojęzycznych stron.
- **Formularze** – generowanie, wyszukiwanie i synchronizacja formularzy Gravity Forms z szablonów PHP; obsługa powiadomień, potwierdzeń, logów oraz mechanizmów blokowania operacji.
- **Tłumaczenia pól formularzy** – obsługa językowych etykiet i tekstów pól na frontendzie.
- **Tłumaczenia powiadomień** – edytor słowników dla szablonów MJML, przechowywanych w plikach `translations.json`.
- **GF Addon** – dedykowane rozszerzenia funkcjonalności Gravity Forms.
- **Tools** – narzędzia do wpisów i powiadomień testowych.
- **Raportowanie** – raporty przebiegu synchronizacji i jej błędów.
- **Aktualizacje** – integracja z GitHub przez Plugin Update Checker.

## Wymagania

| Komponent | Wymaganie |
| --- | --- |
| WordPress | Działająca instalacja WordPress |
| PHP | **8.0 lub nowszy** |
| WPML | Wymagany do funkcji wielojęzycznych |
| Gravity Forms | Wymagany do generowania i synchronizacji formularzy |
| Dostęp do plików | Prawo odczytu szablonów i zapisu słowników JSON przy edycji tłumaczeń |

Wtyczka nie deklaruje w kodzie minimalnej wersji WordPress, WPML ani Gravity Forms; zgodność należy sprawdzić w docelowym środowisku.

## Instalacja

1. Pobierz paczkę z zakładki [Releases](https://github.com/ptak-warsaw-expo-dev/pwe-multilang/releases) lub przygotuj archiwum katalogu `pwe-multilang`.
2. W WordPressie przejdź do **Wtyczki → Dodaj nową → Wyślij wtyczkę na serwer** i wybierz ZIP, ewentualnie skopiuj katalog do `wp-content/plugins/`.
3. Aktywuj **PWE Multilang**.
4. Sprawdź konfigurację WPML, Gravity Forms i źródła języków.
5. W panelu **PWE Multilang** włącz potrzebne moduły i wykonaj pierwszą synchronizację zgodnie z procedurą wdrożeniową.

**Ważne:** przed uruchomieniem synchronizacji na działającej witrynie wykonaj kopię zapasową bazy i plików.

## Źródło języków i synchronizacja

Mechanizm synchronizacji korzysta z shortcode’u **`[pwe_available_languages]`**, który musi być udostępniany przez środowisko strony. Jeśli shortcode nie istnieje lub zwraca nieprawidłową/pustą listę, synchronizacja kończy się błędem bez zmiany języków, stron i formularzy.

Orkiestrator synchronizacji znajduje się w `core/sync-orchestrator.php`. Obsługuje rozpoznawanie zmian konfiguracji, blokadę równoległych operacji oraz ręczne wymuszenie ponownej synchronizacji. Pełna synchronizacja wymaga aktywnych modułów **Pages** i **Forms**.

> Wtyczka nie definiuje shortcode’u `pwe_available_languages` w głównym pliku; należy zapewnić jego dostępność za pośrednictwem właściwej integracji strony.

## Moduły

Moduły są zarejestrowane w `core/module-registry.php` i domyślnie włączone. Można nimi zarządzać w ustawieniach ogólnych PWE Multilang.

| Moduł | Katalog | Zadanie |
| --- | --- | --- |
| Forms | `modules/forms/` | Szablony, generowanie i synchronizacja formularzy |
| Form translations | `modules/form-translations/` | Tłumaczenia pól formularzy na frontendzie |
| Tłumaczenia powiadomień | `modules/notification-translations/` | Edycja słowników JSON dla MJML |
| Pages | `modules/pages/` | Tworzenie stron i tłumaczeń WPML |
| Tools | `modules/tools/` | Testowe wpisy i powiadomienia |
| GF Addon | `modules/gf-addon/` | Rozszerzenia Gravity Forms |

### Szablony formularzy

Szablony znajdują się w `modules/forms/form-templates/`. Każdy typ formularza posiada własny katalog z definicją PHP i, zależnie od potrzeb, tłumaczeniami pól oraz szablonami powiadomień. Mechanizm formularzy obejmuje m.in. przygotowanie pól, powiadomień i potwierdzeń, wykrywanie istniejących formularzy, porównywanie zmian, synchronizację i logowanie.

### Tłumaczenia powiadomień MJML

Edytor znajduje się w **PWE Multilang → Tłumaczenia powiadomień**.

- Wykrywa szablony `.mjml` w katalogach `modules/forms/form-templates/*/notifications/*`.
- Odczytuje zmienne `{{nazwa}}`; `{{lang}}` jest zmienną systemową.
- Przechowuje słowniki lokalnie w `translations.json` w układzie **język → zmienna → tekst**.
- Pozwala wykrywać wspólne tłumaczenia, konflikty oraz zmieniać nazwy zmiennych z aktualizacją odwołań w szablonach.
- Zapisuje zmiany dopiero po użyciu akcji zapisu; weryfikuje rewizje i chroni przed konfliktami równoczesnej edycji.

**Istotne:** zapis tłumaczeń nie synchronizuje automatycznie istniejących formularzy Gravity Forms. Po zmianie treści powiadomień należy przeprowadzić synchronizację formularzy w module **Forms**. Aktualizacja wtyczki może nadpisać lokalnie zmodyfikowane słowniki i szablony — należy utrzymywać je w repozytorium lub kopii zapasowej.

Szczegółowy opis: [`modules/notification-translations/README.md`](modules/notification-translations/README.md).

## Struktura projektu

```text
pwe-multilang/
├── pwe-multilang.php               # Punkt wejścia i inicjalizacja
├── assets/                         # CSS i JavaScript panelu
├── core/
│   ├── admin/                      # Administracja i interfejs
│   ├── functions/                  # Funkcje integracyjne
│   ├── site-group/                 # Logika grup witryn
│   ├── wpml/                       # Obsługa języków WPML
│   ├── module-registry.php         # Rejestr i ustawienia modułów
│   ├── sync-orchestrator.php       # Orkiestracja synchronizacji
│   ├── sync-mail-report.php        # Raporty synchronizacji
│   └── plugin-update-checker/      # Biblioteka aktualizacji
├── modules/
│   ├── forms/
│   │   ├── core/                   # Generowanie i synchronizacja
│   │   └── form-templates/         # Definicje formularzy i szablony
│   ├── form-translations/
│   ├── notification-translations/
│   ├── pages/
│   ├── tools/
│   └── gf-addon/
└── website-translation.json
```

## Aktualizacje

Wtyczka integruje bibliotekę **Plugin Update Checker** z repozytorium [`ptak-warsaw-expo-dev/pwe-multilang`](https://github.com/ptak-warsaw-expo-dev/pwe-multilang). Kod przewiduje uwierzytelnienie GitHub za pomocą konfiguracji środowiska. Nie należy umieszczać tokenów dostępowych w README, commitach ani publicznych plikach konfiguracyjnych.

## Dobre praktyki wdrożeniowe

- Przed synchronizacją lub aktualizacją wykonuj backup bazy oraz katalogu wtyczki.
- Testuj zmiany najpierw na środowisku stagingowym.
- Nie edytuj szablonów i słowników bezpośrednio na produkcji bez przeniesienia zmian do repozytorium.
- Po aktualizacji słowników powiadomień wykonaj synchronizację powiązanych formularzy.
- Zachowuj testy w repozytorium; w paczkach produkcyjnych można je pominąć.

## Autor i licencja

**Autor:** [Piotr Krupniewski](https://github.com/PiotrKrupniewski)  
**Repozytorium:** [PWE Multilang](https://github.com/ptak-warsaw-expo-dev/pwe-multilang)  
**Licencja:** GNU General Public License v2 lub nowsza ([GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html)).
