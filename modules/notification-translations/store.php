<?php
/** File-only dictionaries. Notification dictionaries are read and written directly as JSON. */
if (!defined('ABSPATH')) { exit; }
require_once __DIR__ . '/template-languages.php';
final class PWE_Multilang_Notification_Translation_Store
{
    public static function read(string $directory): array
    {
        $json = $directory . '/translations.json';
        if (is_file($json)) {
            $data = json_decode(self::contents($json), true, 512, JSON_THROW_ON_ERROR);
        } else {
            $data = [];
        }
        self::validate($data);
        return $data;
    }

    public static function validate($data): void
    {
        if (!is_array($data)) { throw new RuntimeException('Nieprawidłowy słownik tłumaczeń.'); }
        foreach ($data as $lang => $values) {
            if (!is_string($lang) || !preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/D', $lang) || !is_array($values)) {
                throw new RuntimeException('Nieprawidłowy język słownika.');
            }
            foreach ($values as $key => $value) {
                if (!is_string($key) || !is_string($value)) { throw new RuntimeException('Tłumaczenia muszą być tekstem.'); }
            }
        }
    }

    /** Blank translations do not establish that two different variables are equivalent. */
    private static function hasContent(array $values): bool
    {
        foreach ($values as $value) {
            if (trim($value) !== '') { return true; }
        }
        return false;
    }

    public static function contents(string $path): string
    {
        $text = file_get_contents($path);
        if ($text === false) { throw new RuntimeException('Nie można odczytać pliku: ' . basename($path)); }
        return $text;
    }

    public static function root(): string
    {
        return PWE_MULTILANG_PATH . 'modules/forms/form-templates';
    }

    public static function scan(): array
    {
        $root = self::root();
        $notifications = [];
        $rows = [];
        $revision = [];
        foreach (glob($root . '/*/notifications/*', GLOB_ONLYDIR) ?: [] as $dir) {
            if (is_link($dir) || realpath($dir) !== $dir) { continue; }
            $mjml = glob($dir . '/*.mjml') ?: [];
            if (!$mjml) { continue; }
            $id = substr($dir, strlen($root) + 1);
            $form = explode('/', $id)[0];
            $local = self::read($dir);
            $formDir = $root . '/' . $form;
            $formFile = $formDir . '/' . $form . '.php';
            $langs = PWE_Multilang_Notification_Template_Languages::resolve($formDir, basename($dir));
            $revision[$formFile] = hash_file('sha256', $formFile);
            $keys = [];
            foreach ($mjml as $file) {
                preg_match_all('/\{\{\s*([a-zA-Z_][a-zA-Z0-9_.-]*)\s*\}\}/', self::contents($file), $matches);
                $keys = array_merge($keys, $matches[1]);
            }
            // Keep manually added library entries even before a placeholder is inserted.
            foreach ($local as $values) { $keys = array_merge($keys, array_keys($values)); }
            $keys = array_values(array_diff(array_unique($keys), ['lang']));
            $notifications[$id] = ['id' => $id, 'label' => $form . ' / ' . basename($dir), 'languages' => $langs];
            foreach (array_merge($mjml, glob($dir . '/*.html') ?: [], glob($dir . '/translations.*') ?: []) as $file) {
                if (is_link($file)) { throw new RuntimeException('Dowiązania w katalogu powiadomienia nie są obsługiwane.'); }
                $revision[$file] = hash_file('sha256', $file);
            }
            foreach ($keys as $key) {
                $values = [];
                foreach ($langs as $lang) {
                    if (array_key_exists($key, $local[$lang] ?? [])) { $values[$lang] = $local[$lang][$key]; }
                }
                ksort($values);
                $rows[] = ['key' => $key, 'values' => $values, 'refs' => [['id' => $id, 'key' => $key]]];
            }
        }
        // Most complete dictionaries establish groups first; subsets can join only an unambiguous group.
        usort($rows, static fn($a, $b) => count($b['values']) <=> count($a['values']));
        $groups = [];
        foreach ($rows as $row) {
            $matches = [];
            foreach ($groups as $i => $group) {
                $sameKey = in_array($row['key'], array_column($group['refs'], 'key'), true);
                $identical = self::hasContent($row['values']) && $row['values'] === $group['values'];
                $compatible = true;
                foreach ($row['values'] as $lang => $value) {
                    if (array_key_exists($lang, $group['values']) && $group['values'][$lang] !== $value) { $compatible = false; break; }
                }
                if ($identical || ($sameKey && $compatible)) { $matches[] = $i; }
            }
            if (count($matches) === 1) {
                $i = $matches[0];
                $groups[$i]['values'] = array_replace($groups[$i]['values'], $row['values']);
                ksort($groups[$i]['values']);
                $groups[$i]['refs'] = array_merge($groups[$i]['refs'], $row['refs']);
            } else { $groups[] = $row; }
        }
        $rows = $groups;
        foreach ($rows as &$row) { $row['values'] = (object) $row['values']; }
        unset($row);
        ksort($notifications);
        ksort($revision);
        usort($rows, static fn($a, $b) => strcmp($a['key'], $b['key']));
        return ['revision' => hash('sha256', json_encode($revision)), 'notifications' => (object) $notifications, 'rows' => $rows];
    }

    public static function save(array $request): array
    {
        $lock = fopen(self::root() . '/.notification-translations.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) { throw new RuntimeException('Trwa inny zapis. Spróbuj ponownie.'); }
        try { return self::saveLocked($request); }
        finally { flock($lock, LOCK_UN); fclose($lock); }
    }

    private static function saveLocked(array $request): array
    {
        $snapshot = self::scan();
        if (($request['revision'] ?? '') !== $snapshot['revision']) {
            throw new RuntimeException('Pliki zmieniły się od otwarcia edytora. Odśwież stronę przed zapisem.');
        }
        $notifications = (array) $snapshot['notifications'];
        $rows = $request['rows'] ?? null;
        if (!is_array($rows) || count($rows) > 10000) { throw new RuntimeException('Nieprawidłowa lista zmiennych.'); }
        $original = [];
        foreach ($snapshot['rows'] as $row) {
            foreach ($row['refs'] as $ref) { $original[$ref['id']][$ref['key']] = (array) $row['values']; }
        }
        $seen = $names = $signatures = $maps = $desired = [];
        foreach ($rows as $row) {
            $key = $row['key'] ?? '';
            if (!is_string($key) || $key === 'lang' || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_.-]{0,127}$/D', $key)) {
                throw new RuntimeException('Nieprawidłowa nazwa zmiennej: ' . (is_scalar($key) ? $key : ''));
            }
            if (isset($names[$key])) { throw new RuntimeException('Rozwiąż konflikt nazwy: ' . $key . ' (scal lub nadaj osobne nazwy).'); }
            $names[$key] = true;
            $values = $row['values'] ?? null;
            if (!is_array($values)) { throw new RuntimeException('Nieprawidłowe wartości zmiennej.'); }
            $validation = [];
            foreach ($values as $lang => $value) { $validation[$lang] = [$key => $value]; }
            self::validate($validation);
            ksort($values);
            $signature = hash('sha256', json_encode($values));
            if (self::hasContent($values)) {
                if (isset($signatures[$signature])) { throw new RuntimeException('Identyczne tłumaczenia: scal ' . $key . ' z ' . $signatures[$signature] . '.'); }
                $signatures[$signature] = $key;
            }
            if (empty($row['refs']) || !is_array($row['refs'])) { throw new RuntimeException('Zmienna musi należeć do powiadomienia.'); }
            foreach ($row['refs'] as $ref) {
                $id = $ref['id'] ?? '';
                $old = $ref['key'] ?? '';
                if (!is_string($id) || !isset($notifications[$id]) || !is_string($old) || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_.-]{0,127}$/D', $old) || $old === 'lang') {
                    throw new RuntimeException('Nieznane powiadomienie MJML lub zmienna.');
                }
                if (isset($seen[$id][$old])) { throw new RuntimeException('Powtórzone przypisanie zmiennej.'); }
                $seen[$id][$old] = true;
                $maps[$id][$old] = $key;
                foreach ($values as $lang => $value) {
                    if (in_array($lang, $notifications[$id]['languages'], true)) { $desired[$id][$lang][$key] = $value; }
                }
            }
        }
        foreach ($original as $id => $entries) {
            foreach ($entries as $key => $values) {
                if (!isset($seen[$id][$key])) { throw new RuntimeException('Nie można usunąć używanej zmiennej. Użyj scalania.'); }
            }
        }
        $changes = [];
        $changedIds = [];
        foreach ($maps as $id => $mapping) {
            $dir = self::root() . '/' . $id;
            $previous = self::read($dir);
            $local = array_intersect_key($previous, array_fill_keys($notifications[$id]['languages'], true));
            $renamed = false;
            foreach ($mapping as $old => $key) { if ($old !== $key) { $renamed = true; } }
            foreach ($local as &$entries) {
                foreach ($mapping as $old => $new) { unset($entries[$old]); }
            }
            unset($entries);
            foreach ($desired[$id] ?? [] as $lang => $entries) { $local[$lang] = array_replace($local[$lang] ?? [], $entries); }
            foreach ($local as $lang => &$entries) { $entries['lang'] = $lang; }
            unset($entries);
            self::validate($local);
            if (!$renamed && $local == $previous) { continue; }
            $changes[$dir . '/translations.json'] = json_encode($local, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
            foreach (array_merge(glob($dir . '/*.mjml') ?: [], glob($dir . '/*.html') ?: []) as $file) {
                $text = self::contents($file);
                $updated = preg_replace_callback('/\{\{\s*([a-zA-Z_][a-zA-Z0-9_.-]*)\s*\}\}/', static function ($m) use ($mapping) {
                    return isset($mapping[$m[1]]) && $mapping[$m[1]] !== $m[1] ? '{{' . $mapping[$m[1]] . '}}' : $m[0];
                }, $text);
                if ($text !== $updated) { $changes[$file] = $updated; }
            }
            $changedIds[] = $id;
        }
        self::commit($changes);
        return ['changed' => $changedIds, 'state' => self::scan()];
    }

    /** Stage all files before replacing; roll back completed replacements on failure. */
    private static function commit(array $changes): void
    {
        $staged = $previous = $done = [];
        try {
            foreach ($changes as $path => $content) {
                if (is_link($path) || !is_writable(dirname($path)) || (is_file($path) && !is_writable($path))) {
                    throw new RuntimeException('Brak możliwości zapisu: ' . basename($path));
                }
                $previous[$path] = is_file($path) ? self::contents($path) : null;
                if ($previous[$path] === $content) { continue; }
                $temp = tempnam(dirname($path), '.pwe-translations-');
                if (!$temp) { throw new RuntimeException('Nie można przygotować pliku.'); }
                $staged[$path] = $temp;
                if (file_put_contents($temp, $content, LOCK_EX) !== strlen($content)) { throw new RuntimeException('Niepełny zapis pliku.'); }
                chmod($temp, is_file($path) ? (fileperms($path) & 0777) : 0644);
            }
            foreach ($staged as $path => $temp) {
                if (!rename($temp, $path)) { throw new RuntimeException('Nie udało się zastąpić pliku.'); }
                $done[] = $path;
                if (function_exists('opcache_invalidate') && substr($path, -4) === '.php') { opcache_invalidate($path, true); }
            }
        } catch (Throwable $error) {
            $rollbackFailed = false;
            foreach (array_reverse($done) as $path) {
                if ($previous[$path] === null) { $rollbackFailed = !unlink($path) || $rollbackFailed; }
                else { $rollbackFailed = file_put_contents($path, $previous[$path], LOCK_EX) === false || $rollbackFailed; }
                if (function_exists('opcache_invalidate')) { opcache_invalidate($path, true); }
            }
            throw new RuntimeException($error->getMessage() . ($rollbackFailed ? ' Uwaga: niepełne przywrócenie plików; sprawdź je przed ponownym zapisem.' : ' Zmiany wycofano.'), 0, $error);
        } finally {
            foreach ($staged as $temp) { if (is_file($temp)) { unlink($temp); } }
        }
    }
}
