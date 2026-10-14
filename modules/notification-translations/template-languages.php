<?php
if (!defined('ABSPATH')) { exit; }
final class PWE_Multilang_Notification_Template_Languages
{
    /** Read declarations without executing template generation or filtering by active WPML languages. */
    public static function resolve(string $directory, string $notification): array
    {
        $file = $directory . '/' . basename($directory) . '.php';
        $source = is_file($file) ? file_get_contents($file) : false;
        if ($source === false) { throw new RuntimeException('Brak pliku szablonu formularza.'); }
        $code = '';
        foreach (token_get_all($source) as $token) {
            if (is_array($token)) {
                if (!in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) { $code .= $token[1]; }
            } else { $code .= $token; }
        }
        if (!preg_match('/\$template_langs\s*=\s*\[([^\]]*)\]/s', $code, $declaration)) {
            throw new RuntimeException('Nie można ustalić języków z deklaracji $template_langs szablonu formularza.');
        }
        preg_match_all('/[\'"]([a-z]{2,3}(?:-[a-z0-9]{2,8})*)[\'"]/', $declaration[1], $matches);
        $remainder = preg_replace('/[\'"][a-z]{2,3}(?:-[a-z0-9]{2,8})*[\'"]|[\s,]/', '', $declaration[1]);
        if ($remainder !== '' || !$matches[1]) { throw new RuntimeException('Nieprawidłowa deklaracja języków szablonu formularza.'); }
        $langs = $matches[1];
        // Custom templates can define additional, explicit language-specific notifications (e.g. Call Centre EN).
        preg_match_all('/::Notification\s*\((.*?)\n\s*\),/s', $code, $notifications);
        foreach ($notifications[1] as $block) {
            if (!preg_match('/\btemplate\s*:\s*[\'"]([^\'"]+)[\'"]/', $block, $template)) { continue; }
            $fullBase = preg_replace('/\.html$/', '', $template[1]);
            $base = preg_replace('/-[a-z]{2}$/', '', $fullBase);
            if ($notification !== $fullBase && $notification !== $base) { continue; }
            if (preg_match('/-([a-z]{2})\.html$/', $template[1], $language)) { $langs[] = $language[1]; }
            elseif (preg_match('/\bname\s*:\s*[\'"]([^\'"]+)[\'"]/', $block, $name)
                && preg_match('/-\s*([A-Z]{2})(?:\s-|$)/', $name[1], $language)) {
                $langs[] = strtolower($language[1]);
            }
        }
        return array_values(array_unique($langs));
    }

}
