# PWE Multilang

> Wewnętrzna, modułowa wtyczka WordPress do zarządzania wielojęzycznymi stronami i formularzami w ekosystemie PWE / Ptak Warsaw Expo.

![Version](https://img.shields.io/badge/version-1.1.0-blue.svg)
![PHP](https://img.shields.io/badge/PHP-%3E%3D%208.0-777bb4.svg)
![WordPress](https://img.shields.io/badge/WordPress-plugin-21759b.svg)
![License](https://img.shields.io/badge/license-GPL--2.0--or--later-green.svg)

## Spis treści

- [O wtyczce](#o-wtyczce)
- [Najważniejsze funkcje](#najważniejsze-funkcje)
- [Wymagania i integracje](#wymagania-i-integracje)
- [Instalacja](#instalacja)
- [Panel administracyjny](#panel-administracyjny)
- [Moduły](#moduły)
- [Pages](#pages)
- [Forms](#forms)
- [Form translations](#form-translations)
- [Replace Content](#replace-content)
- [Resend](#resend)
- [Tests](#tests)
- [GF Addon](#gf-addon)
- [Grupy serwisów](#grupy-serwisów)
- [Języki](#języki)
- [Synchronizacja po aktualizacji wtyczki](#synchronizacja-po-aktualizacji-wtyczki)
- [Aktualizacje z GitHuba](#aktualizacje-z-githuba)
- [Struktura projektu](#struktura-projektu)
- [Hooki rozszerzające](#hooki-rozszerzające)
- [Logi](#logi)
- [Development](#development)
- [Checklist przed wydaniem](#checklist-przed-wydaniem)
- [Licencja](#licencja)

## O wtyczce

**PWE Multilang** automatyzuje utrzymanie wielojęzycznych stron WordPress oraz formularzy Gravity Forms na serwisach PWE.

Wtyczka łączy kilka wcześniej niezależnych procesów w jeden panel administracyjny:

- generowanie brakujących stron i tłumaczeń WPML,
- generowanie oraz synchronizacja formularzy Gravity Forms z template'ów zapisanych w kodzie,
- dynamiczne tłumaczenia pól formularzy,
- synchronizacja wybranych formularzy po aktualizacji wtyczki,
- kontrola dostępności stron i formularzy dla konkretnych grup serwisów,
- zamiana treści wskazanych stron na shortcode'y PWE,
- masowe ponowne wysyłanie powiadomień Gravity Forms,
- narzędzia testowe,
- rozszerzenia interfejsu i logiki warunkowej Gravity Forms,
- aktualizacje wtyczki z release'ów GitHub.

Projekt jest **ściśle powiązany z ekosystemem PWE**. Nie jest uniwersalnym generatorem stron/formularzy przeznaczonym do instalacji na dowolnym WordPressie bez dodatkowej konfiguracji.

## Najważniejsze funkcje

### Wielojęzyczne strony

- źródło konfiguracji: `website-translation.json`,
- tworzenie brakujących tłumaczeń WPML,
- EN jako strona wzorcowa,
- PL i EN są pomijane podczas generowania brakujących tłumaczeń,
- kopiowanie wybranych metadanych strony wzorcowej,
- integracja z metadanymi motywu Uncode,
- integracja z ustawieniami WP Rocket,
- automatyczne wykrywanie template'ów formularzy wykorzystanych na utworzonych stronach,
- mechanizm etapów tworzenia strony ułatwiający wznowienie operacji po błędzie.

### Formularze Gravity Forms

- formularze definiowane jako template'y PHP,
- automatyczne wykrywanie template'ów z katalogu `includes/forms/form-templates/`,
- obsługa osobnych formularzy dla języków,
- tworzenie nowych i synchronizacja istniejących formularzy,
- synchronizacja całego formularza albo wybranych elementów,
- obsługa pól, notifications, confirmations i QR feeds,
- stabilna identyfikacja formularza niezależna od samego tytułu,
- blokada równoległych operacji,
- oznaczanie formularzy jako zarządzane przez PWE Multilang,
- wykrywanie ręcznych modyfikacji,
- blokada zapisu zarządzanych formularzy bezpośrednio z edytora Gravity Forms,
- migracja starszych formularzy do modelu zarządzanego,
- rozszerzalny system zewnętrznych patchy.

### Bezpieczeństwo operacji administracyjnych

Operacje mutujące dane korzystają z mechanizmów WordPress, m.in.:

- nonce dla formularzy administracyjnych,
- nonce dla AJAX,
- sanitizacji parametrów wejściowych,
- blokad operacji synchronizacyjnych,
- blokad jobów masowego resend,
- per-user job state dla procesu Replace Content,
- MySQL advisory lock w procesie Replace Content.

## Wymagania i integracje

### Podstawowe

| Komponent | Status | Zastosowanie |
|---|---|---|
| WordPress | wymagany | środowisko uruchomieniowe wtyczki |
| PHP `>= 8.0` | wymagany | zadeklarowane w nagłówku wtyczki |
| Gravity Forms | wymagany dla funkcji formularzy | generowanie, synchronizacja, testy i resend |
| WPML | wymagany dla pełnej obsługi wielojęzycznych stron | języki, TRID, tłumaczenia i przełączanie kontekstu języka |

Wtyczka stara się nie powodować błędu krytycznego, gdy Gravity Forms nie jest dostępny — moduły zależne od `GFAPI` wykonują odpowiednie kontrole. Bez Gravity Forms większość funkcji formularzowych pozostaje jednak nieaktywna.

### Integracje PWE

W środowisku produkcyjnym wykorzystywane są również:

- shortcode `[trade_fair_group]` — określenie grupy bieżącego serwisu,
- shortcode `[trade_fair_feed_prefix]` — prefiks feedu QR,
- shortcode'y `pwe-elements-auto-switch-*` używane przez **Replace Content**,
- Gravity Forms add-on obsługujący feed `pwe_qr` lub zgodność z legacy `qr-code`.

### Integracje opcjonalne

- **Uncode** — kopiowanie/ustawianie metadanych layoutu, nagłówka i tytułu,
- **WP Rocket** — czyszczenie cache oraz przenoszenie wybranych ustawień per-page,
- **GitHub Releases** — dystrybucja aktualizacji przez dołączoną bibliotekę Plugin Update Checker.

## Instalacja

1. Pobierz release wtyczki lub sklonuj repozytorium.
2. Umieść katalog w:

```text
wp-content/plugins/pwe-multilang/
```

3. Aktywuj **PWE Multilang** w panelu WordPress.
4. Upewnij się, że wymagane dla danego serwisu integracje są aktywne — w szczególności Gravity Forms i WPML.
5. Otwórz **PWE Multilang → General** i sprawdź aktywne moduły.
6. Przed pierwszą synchronizacją zweryfikuj grupę serwisu wyświetlaną w panelu.

> [!CAUTION]
> Część modułów wykonuje operacje masowe na stronach, formularzach lub powiadomieniach. Pierwsze uruchomienie nowej wersji należy wykonać na środowisku stagingowym albo po wykonaniu backupu bazy danych.

## Panel administracyjny

Główne menu **PWE Multilang** zawiera ekran `General` oraz ekrany aktywnych modułów.

Ustawienia aktywności modułów są przechowywane w opcji:

```text
pwe_multilang_modules
```

Rejestr modułów znajduje się w:

```text
includes/core/module-registry.php
```

## Moduły

| Moduł | Domyślnie | Frontend | Przeznaczenie |
|---|---:|---:|---|
| `forms` | włączony | nie | generator i synchronizacja Gravity Forms |
| `form_translations` | włączony | tak | dynamiczne tłumaczenia pól formularzy |
| `pages` | włączony | nie | generowanie stron/tłumaczeń WPML |
| `replace_content` | włączony | nie | zamiana treści stron na shortcode'y |
| `tests` | włączony | nie | narzędzia testowe i diagnostyczne |
| `resend` | włączony | nie | masowe ponowne wysyłanie notifications |
| `gf_addon` | włączony | tak | rozszerzenia Gravity Forms |

Moduły administracyjne są ładowane wyłącznie w `wp-admin`. Moduły frontendowe są ładowane także poza panelem, jeżeli pozostają aktywne.

## Pages

Moduł **Pages** tworzy brakujące tłumaczenia stron na podstawie pliku:

```text
website-translation.json
```

Plik zawiera mapę logicznych kluczy stron oraz dane dla poszczególnych języków.

Przykładowa koncepcja:

```json
{
  "kontakt": {
    "pl": {},
    "en": {},
    "de": {}
  }
}
```

Aktualna konfiguracja zawiera 11 typów stron i 14 kodów językowych.

### Zasada źródła

- `EN` jest wzorcem strony,
- `PL` i `EN` nie są tworzone przez operację generowania tłumaczeń,
- dla pozostałych aktywnych języków WPML wtyczka sprawdza istniejące tłumaczenie,
- jeżeli tłumaczenia brakuje, tworzona jest nowa strona i dołączana do odpowiedniego TRID WPML.

### Odporność na przerwanie

Proces wykorzystuje metadane stanu tworzenia strony, dzięki czemu poszczególne kroki można bezpieczniej wznowić zamiast traktować cały proces jako jedną niepodzielną operację.

### Metadane

Podczas tworzenia stron kopiowane są wybrane metadane z wzorca. Kod zawiera dedykowaną obsługę m.in.:

- ustawień layoutu Uncode,
- wybranych ustawień WP Rocket,
- metadanych niezbędnych dla wygenerowanej strony.

Po utworzeniu stron moduł może zsynchronizować istniejące formularze, których template'y zostały wykryte w treści nowo utworzonych stron.

## Forms

Moduł **Forms** traktuje kod jako źródło prawdy dla zarządzanych formularzy Gravity Forms.

Template'y znajdują się w:

```text
includes/forms/form-templates/{template-slug}/
```

Aktualnie dostępnych jest 12 template'ów:

```text
badge_generator_local
ceremonia_medalowa
napisz_do_nas
pw_potencjalny_wystawca_aktywacja
rejestracja
rejestracja_fb
rejestracja_gosci_wystawcow
rejestracja_wystawcow_badge
rejestracja_zaproszen_call_centre
voucher_generator
zostan_wystawca
zostan_wystawca_krok2
```

### Template formularza

Każdy template zwraca payload opisujący formularz i powinien zawierać własny `template_version`, np.:

```php
'template_version' => '1.0.1',
```

> [!IMPORTANT]
> Po zmianie template'u, która ma zostać automatycznie wdrożona do istniejących formularzy, **należy podbić `template_version` tego template'u**. Sama zmiana wersji całej wtyczki nie oznacza automatycznie, że wszystkie formularze zostaną nadpisane.

### Zarządzane formularze

Formularze utworzone przez PWE Multilang otrzymują flagę:

```text
pwe_multilang_managed
```

Wtyczka posiada także flagę wykrywającą ręczne modyfikacje.

Dla formularzy zarządzanych mechanizm locka przechwytuje operacje zapisu w panelu Gravity Forms, m.in.:

- zapis formularza,
- zapis settings,
- zapis notifications,
- zapis confirmations,
- usuwanie powiązanego feedu QR.

Dzięki temu template PHP pozostaje źródłem prawdy, a formularz nie rozjeżdża się względem konfiguracji w repozytorium.

### Zakres synchronizacji

Kod obsługuje synchronizację m.in.:

- całych formularzy,
- pól,
- notifications,
- confirmations,
- feedów/elementów QR,
- wybranych template'ów,
- tylko brakujących formularzy (`add-only`),
- formularzy ręcznie zmodyfikowanych po świadomej decyzji operatora.

### Identyfikacja i merge

Warstwa Forms zawiera osobne mechanizmy odpowiedzialne za:

- identyfikację template'u i targetu językowego,
- wyszukiwanie istniejących formularzy,
- przydzielanie ID pól,
- przepisywanie merge tagów,
- przygotowanie notifications,
- przygotowanie confirmations,
- porównywanie payloadów,
- zapis nowego formularza i aktualizację istniejącego.

### Lock operacji

Operacje generatora korzystają z locka zapisywanego w opcji WordPress. Lock jest tokenizowany, ma TTL i może być odświeżany w trakcie dłuższej operacji. Chroni to formularze przed równoległą synchronizacją z kilku requestów.

### Migracja starszych formularzy

Moduł zawiera narzędzie migracji do modelu zarządzanego. Legacy formularz może zostać zarchiwizowany/oznaczony jako stary, a następnie zastąpiony formularzem utworzonym z aktualnego template'u. Kod zawiera również obsługę rollbacku, jeśli synchronizacja nowego formularza się nie powiedzie.

## Form translations

`form_translations` działa również po stronie frontendu.

Moduł dynamicznie dobiera tłumaczenia pól na podstawie bieżącego języka WPML i danych dostarczonych przez template'y/form translation maps.

Dzięki temu część tłumaczeń może być utrzymywana razem z definicją formularza zamiast ręcznie w panelu Gravity Forms.

## Replace Content

Moduł **Replace Content** zamienia treść wybranych stron na skonfigurowane shortcode'y PWE.

Mapa znajduje się w:

```text
includes/replace-content/replace-content-map.php
```

Konfigurację można rozszerzyć filtrem:

```php
pwe_multilang_replace_content_map
```

Proces:

1. lokalizuje stronę źródłową,
2. pobiera powiązane tłumaczenia WPML,
3. ustawia właściwy shortcode jako treść każdej wersji,
4. aktualizuje wymagane metadane strony,
5. czyści cache posta,
6. korzysta z `rocket_clean_post()`, jeżeli WP Rocket jest dostępny.

Operacja jest realizowana krokowo przez AJAX. Stan joba jest powiązany z użytkownikiem, a równoległe wykonanie jest ograniczane dodatkowym MySQL advisory lockiem.

Hook wykonywany po aktualizacji strony:

```php
pwe_multilang_replace_content_page_updated
```

## Resend

Moduł **Resend** służy do kontrolowanego ponownego wysyłania powiadomień Gravity Forms dla istniejących entries.

Mechanizm obsługuje m.in.:

- wybór formularzy,
- batch processing,
- minimalny wiek wpisu,
- stan `running`, `paused`, `blocked`, `completed`,
- checkpoint po kolejnych kandydatach,
- persistent job state,
- lock joba,
- znaczniki w entry meta ograniczające ponowne przetworzenie tego samego powiadomienia.

Domyślne parametry w kodzie:

- batch: `100`,
- minimalny wiek entry: `14 dni`,
- batch jest ograniczony maksymalnie do `200`,
- opóźnienie kolejnego requestu jest ograniczone do zakresu `3–60 s`.

Kontynuacja joba odbywa się z poziomu przeglądarki: JavaScript po zadanym opóźnieniu wykonuje kolejny POST do panelu administracyjnego.

> [!WARNING]
> Status oznaczający poprawne przekazanie wiadomości przez mechanizm WordPress/SMTP nie jest gwarancją dostarczenia wiadomości do skrzynki odbiorcy.

## Tests

Moduł **Tests** zawiera narzędzia administracyjne, a nie klasyczny zestaw testów jednostkowych.

Umożliwia m.in.:

- generowanie testowych entries Gravity Forms,
- wysyłanie testowych notifications,
- diagnostykę mechanizmu synchronizacji po aktualizacji,
- resetowanie/seedowanie zapisanych wersji template'ów,
- ręczne uruchamianie wybranych scenariuszy sync.

Moduł jest przeznaczony przede wszystkim do developmentu, stagingu i kontrolowanej diagnostyki.

## GF Addon

Moduł **GF Addon** rejestruje rozszerzenia Gravity Forms zarządzane w kodzie.

Aktualnie aktywne są:

### GF notifications UI

Ulepsza interfejs ekranów konfiguracji notifications i confirmations Gravity Forms.

### Conditional logic: does NOT contain

Dodaje operator warunkowy:

```text
does NOT contain
```

do logiki warunkowej Gravity Forms wraz z obsługą po stronie PHP i JavaScript.

W kodzie znajduje się także implementacja **conditional rule groups**, ale jej rejestracja jest obecnie wyłączona i funkcja nie jest aktywnym elementem wtyczki.

Dodatkowe rozszerzenia można rejestrować przez akcję:

```php
pwe_multilang_gf_addon_register_features
```

## Grupy serwisów

Bieżąca grupa serwisu jest pobierana z:

```text
[trade_fair_group]
```

Obsługiwane grupy:

```text
gr1
gr2
gr3
b2c
b2c-new
week
```

Dostępność poszczególnych stron i template'ów formularzy definiuje:

```text
includes/core/site-group-resource-matrix.php
```

Panel General waliduje spójność macierzy z faktycznie istniejącymi kluczami stron i katalogami template'ów.

### Tryb legacy

Jeżeli shortcode `trade_fair_group` nie istnieje albo nie zwróci rozpoznanej grupy, resolver przechodzi do trybu:

```text
legacy
```

W trybie legacy filtr grupowy nie ogranicza zasobów. Jest to mechanizm zgodności wstecznej i podczas wdrożenia nowego serwisu należy zawsze sprawdzić, czy grupa została prawidłowo wykryta.

Dla nierozpoznanej, niepustej wartości wykonywana jest akcja:

```php
pwe_multilang_unknown_site_group
```

## Języki

Globalny katalog wtyczki obejmuje obecnie:

```text
pl  Polski
en  English
cs  Čeština
de  Deutsch
it  Italiano
lt  Lietuvių
lv  Latviešu
sk  Slovenčina
uk  Українська
ro  Română
et  Eesti
hu  Magyar
fr  Français
es  Español
```

Nie każdy template formularza musi implementować komplet języków z katalogu. Ostateczna lista targetów zależy od konfiguracji danego template'u i aktywnych języków WPML.

## Synchronizacja po aktualizacji wtyczki

Forms posiada mechanizm automatycznej synchronizacji wykonywany po zmianie wersji wtyczki.

Stan jest przechowywany w opcjach:

```text
pwe_mlg_last_plugin_version
pwe_mlg_form_template_versions
pwe_mlg_form_update_sync_last_result
pwe_mlg_form_update_sync_retry_after
```

Przebieg:

1. wykrywana jest nowa wersja wtyczki,
2. odczytywane są `template_version` wszystkich dostępnych template'ów,
3. porównywane są z wersjami zapisanymi wcześniej,
4. synchronizowane są tylko template'y ze zmienioną wersją,
5. poprawnie zsynchronizowane template'y aktualizują zapisany stan,
6. przy częściowym błędzie ustawiany jest retry po 300 sekundach.

Przy pierwszym uruchomieniu mechanizm może zapisać baseline wersji bez masowego nadpisywania istniejących formularzy.

## Aktualizacje z GitHuba

Wtyczka korzysta z dołączonej biblioteki **Plugin Update Checker 4.9** i sprawdza release'y repozytorium:

```text
https://github.com/ptak-warsaw-expo-dev/pwe-multilang
```

Release assets są włączone jako źródło paczki aktualizacyjnej.

Dla prywatnego repozytorium obecna implementacja próbuje pobrać token GitHub z projektowej tabeli WordPress:

```text
{wp_prefix}custom_klavio_setup
```

dla rekordu:

```text
klavio_list_name = github_secret_2
```

Szczegóły implementacji znajdują się w:

```text
includes/plugin-update-checker.php
```

## Struktura projektu

```text
pwe-multilang/
├── pwe-multilang.php
├── website-translation.json
├── assets/
│   ├── admin.css
│   ├── css/
│   └── js/
├── includes/
│   ├── admin/
│   ├── core/
│   ├── forms/
│   │   ├── core/
│   │   ├── form-patches/
│   │   └── form-templates/
│   ├── gf-addon/
│   ├── gravity-forms/
│   ├── pages/
│   ├── replace-content/
│   ├── resend/
│   └── tests/
└── plugin-update-checker/
```

### Odpowiedzialności katalogów

| Katalog | Odpowiedzialność |
|---|---|
| `includes/core/` | registry modułów, języki, rok, grupy serwisów |
| `includes/admin/` | wspólny panel i UI administracyjne |
| `includes/pages/` | tworzenie oraz synchronizacja stron WPML |
| `includes/forms/` | template'y, generator, sync, locki, migracja, tłumaczenia |
| `includes/gf-addon/` | rozszerzenia zachowania Gravity Forms |
| `includes/replace-content/` | mapowanie i masowa zamiana treści stron |
| `includes/resend/` | job masowego ponownego wysyłania notifications |
| `includes/tests/` | narzędzia testowe/diagnostyczne |
| `plugin-update-checker/` | vendored mechanizm aktualizacji z GitHuba |

## Hooki rozszerzające

Najważniejsze własne hooki udostępniane przez wtyczkę:

### `pwe_multilang_replace_content_map`

Modyfikacja mapy stron obsługiwanych przez Replace Content.

```php
add_filter('pwe_multilang_replace_content_map', function (array $map): array {
    $map['/moja-strona/'] = '[moj-shortcode]';

    return $map;
});
```

### `pwe_multilang_replace_content_page_updated`

Akcja po zaktualizowaniu konkretnej strony:

```php
add_action(
    'pwe_multilang_replace_content_page_updated',
    function (int $post_id, string $shortcode): void {
        // Integracja projektowa.
    },
    10,
    2
);
```

### `pwe_multilang_unknown_site_group`

Akcja wykonywana dla nierozpoznanej wartości zwróconej przez `trade_fair_group`.

### `pwe_mlg_external_form_patches`

Pozwala zarejestrować zewnętrzne profile patchy formularzy.

### `pwe_mlg_external_patches_allow_unmanaged`

Pozwala kontrolować, czy zewnętrzne patche mogą objąć formularze niezarządzane przez PWE Multilang.

### `pwe_mlg_forms_after_template_list`

Akcja umożliwiająca rozszerzenie ekranu generatora po liście template'ów.

### `pwe_multilang_gf_addon_register_features`

Pozwala zarejestrować dodatkowe funkcje w registry modułu GF Addon.

### `pwe_multilang_compile_conditional_logic`

Filtr kompilujący logikę warunkową Gravity Forms wykorzystywaną przez warstwę helperów PWE.

## Logi

Log błędów/ostrzeżeń generatora formularzy jest zapisywany do:

```text
wp-content/uploads/pwe-element/mailing/pwe-multilang.log
```

Log zawiera komunikaty `ERROR` i `WARN` oraz opcjonalny kontekst JSON.

> [!IMPORTANT]
> Na serwerze produkcyjnym katalog/log powinien być chroniony przed publicznym dostępem HTTP i powinien podlegać rotacji. Nie należy logować sekretów ani danych osobowych.

## Development

### Zmiana istniejącego template'u formularza

1. Zmień odpowiedni template w `includes/forms/form-templates/{slug}/`.
2. Zwiększ `template_version` tylko dla tego template'u.
3. Przetestuj różnicę i synchronizację na stagingu.
4. Zweryfikuj formularze oznaczone jako ręcznie zmodyfikowane.
5. Zwiększ wersję wtyczki w `pwe-multilang.php` oraz `PWE_MULTILANG_VERSION`.
6. Utwórz release GitHub z poprawną paczką ZIP.

### Dodanie nowego template'u

1. Utwórz katalog:

```text
includes/forms/form-templates/moj_template/
```

2. Dodaj główny plik template'u:

```text
moj_template.php
```

3. Opcjonalnie dodaj:

```text
field-translations.php
```

4. Ustaw `template_version`.
5. Dodaj template do macierzy grup serwisów w:

```text
includes/core/site-group-resource-matrix.php
```

6. Otwórz **PWE Multilang → General** i upewnij się, że walidator macierzy nie zgłasza brakującego przypisania.

### Dodanie nowej strony

1. Dodaj klucz i wszystkie wymagane dane językowe do `website-translation.json`.
2. Dodaj klucz do `PAGE_GROUPS` w `site-group-resource-matrix.php`.
3. Zweryfikuj stronę wzorcową EN.
4. Uruchom synchronizację na stagingu.

### Zasada release'ów

Zmiana wersji pluginu i zmiana `template_version` pełnią różne role:

- **wersja pluginu** — informuje WordPress/GitHub o nowym wydaniu,
- **`template_version`** — informuje synchronizator, który konkretny template formularza wymaga aktualizacji.

## Checklist przed wydaniem

- [ ] zwiększona wersja w nagłówku `pwe-multilang.php`,
- [ ] zwiększona wartość `PWE_MULTILANG_VERSION`,
- [ ] podbite `template_version` dla zmienionych formularzy,
- [ ] poprawny `website-translation.json`,
- [ ] macierz grup serwisów zgodna z listą stron i formularzy,
- [ ] brak debugowego outputu przeznaczonego wyłącznie do developmentu,
- [ ] test generowania/synchronizacji na stagingu,
- [ ] test formularzy ręcznie zmodyfikowanych,
- [ ] test WPML dla aktywnych języków,
- [ ] test QR feeds, jeżeli release zmienia QR,
- [ ] test Replace Content, jeżeli release zmienia mapę shortcode'ów,
- [ ] test Resend, jeżeli release zmienia powiadomienia/job runner,
- [ ] backup przed wdrożeniem zmian masowych,
- [ ] release GitHub zawiera poprawny ZIP wtyczki.

## Licencja

Ten projekt jest objęty licencją GPL v2 lub nowszą. Szczegóły: https://www.gnu.org/licenses/gpl-2.0.html
