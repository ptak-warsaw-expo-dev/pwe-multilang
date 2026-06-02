# PWE Multilang

WordPress plugin automatyzujący tworzenie wielojęzycznych stron i formularzy dla środowiska opartego o WPML oraz Gravity Forms.

## Funkcje

### 🌍 Zarządzanie wielojęzycznością

- integracja z WPML,
- automatyczne tworzenie brakujących tłumaczeń stron,
- synchronizacja stron na podstawie pliku `website-translation.json`,
- zachowanie powiązań językowych między stronami.

### 📝 Generator formularzy Gravity Forms

- automatyczne generowanie formularzy dla wielu języków,
- kopiowanie pól formularzy,
- kopiowanie ustawień formularzy,
- kopiowanie potwierdzeń (Confirmations),
- kopiowanie powiadomień (Notifications),
- aktualizacja istniejących formularzy.

### 🔔 Tłumaczenia powiadomień Gravity Forms

- dedykowany interfejs administracyjny,
- filtrowanie powiadomień według języka,
- obsługa wielojęzycznych wiadomości e-mail.

### 🔄 Automatyczne aktualizacje

Plugin wykorzystuje GitHub Releases do automatycznego sprawdzania i instalowania aktualizacji.

---

## Wymagania

### WordPress

- WordPress 6.x+

### Wtyczki

Wymagane:

- WPML
- Gravity Forms

Opcjonalne:

- Gravity Forms Add-ons wykorzystywane w formularzach źródłowych

---

## Instalacja

### 1. Instalacja ręczna

Pobierz repozytorium:

```bash
git clone https://github.com/ptak-warsaw-expo-dev/pwe-multilang.git
```

Skopiuj katalog do:

```text
wp-content/plugins/pwe-multilang
```

Aktywuj wtyczkę w panelu WordPress.

### 2. Instalacja przez ZIP

1. Pobierz najnowsze wydanie z sekcji Releases.
2. Przejdź do:

```text
WordPress → Wtyczki → Dodaj nową → Wyślij wtyczkę
```

3. Wgraj plik ZIP.
4. Aktywuj wtyczkę.

---

## Konfiguracja

Po aktywacji pojawi się menu:

```text
PWE Multilang
```

### Zakładki

#### General

Podstawowe informacje o wtyczce.

#### Forms

Generator formularzy wielojęzycznych.

#### Pages

Synchronizacja i tworzenie stron na podstawie konfiguracji tłumaczeń.

---

## Generowanie formularzy

Przejdź do:

```text
PWE Multilang → Forms
```

1. Wybierz rok.
2. Kliknij **Generate Forms**.
3. Wtyczka wygeneruje komplet formularzy dla skonfigurowanych języków.

Podczas generowania kopiowane są:

- pola formularza,
- ustawienia formularza,
- powiadomienia,
- potwierdzenia,
- ustawienia dodatkowe.

---

## Synchronizacja stron

Przejdź do:

```text
PWE Multilang → Pages
```

Wtyczka wykorzystuje plik:

```text
website-translation.json
```

który definiuje:

- dostępne języki,
- slugi stron,
- nazwy stron,
- adresy URL.

Na tej podstawie tworzone są brakujące tłumaczenia WPML.

Przykład:

```json
{
  "home": {
    "pl": {
      "label": "Strona główna",
      "url": "/"
    },
    "en": {
      "label": "Home",
      "url": "/"
    }
  }
}
```

---

## Architektura

```text
pwe-multilang
│
├── includes
│   ├── admin
│   ├── forms
│   └── pages
│
├── plugin-update-checker
│
├── website-translation.json
│
└── pwe-multilang.php
```

### Główne moduły

| Moduł | Odpowiedzialność |
|---------|---------|
| Admin | Panel administracyjny |
| Forms | Generowanie formularzy Gravity Forms |
| Pages | Synchronizacja stron WPML |
| Form Translations | Obsługa tłumaczeń formularzy |
| Updater | Aktualizacje z GitHub |

---

## Aktualizacje

Aktualizacje są pobierane automatycznie z GitHub Releases.

Wersja jest sprawdzana podczas działania WordPressa, a nowe wydania pojawiają się standardowo w panelu aktualizacji.

---

## Rozwój

### Klonowanie repozytorium

```bash
git clone https://github.com/ptak-warsaw-expo-dev/pwe-multilang.git
```

### Struktura kodu

Projekt oparty jest o:

- PHP 8+
- WordPress Coding Standards
- WPML API
- Gravity Forms API

---

## Autor

**Piotr Krupniewski**

GitHub:

https://github.com/PiotrKrupniewski

---

## Licencja

GPL v2 lub nowsza

https://www.gnu.org/licenses/gpl-2.0.html
