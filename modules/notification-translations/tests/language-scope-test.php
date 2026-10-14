<?php
/** Run with PHP CLI; fixtures never touch real notification dictionaries. */
if (PHP_SAPI !== 'cli') { exit; }
$plugin = dirname(__DIR__, 3) . '/';
$temp = sys_get_temp_dir() . '/pwe-language-scope-' . bin2hex(random_bytes(6));
define('ABSPATH', $temp . '/');
define('PWE_MULTILANG_PATH', $temp . '/');
require $plugin . 'modules/notification-translations/store.php';
function ensure($ok, $message) { if (!$ok) { throw new RuntimeException($message); } }
function snapshot() { return json_decode(json_encode(PWE_Multilang_Notification_Translation_Store::scan()), true); }
function removeFixture($dir) {
    foreach (new DirectoryIterator($dir) as $file) {
        if ($file->isDot()) { continue; }
        if ($file->isDir()) { removeFixture($file->getPathname()); } else { unlink($file->getPathname()); }
    }
    rmdir($dir);
}
try {
    $root = PWE_Multilang_Notification_Translation_Store::root();
    foreach (['small' => ['pl', 'en'], 'large' => ['pl', 'en', 'cs', 'hu']] as $form => $langs) {
        $dir = $root . '/' . $form . '/notifications/notice';
        mkdir($dir, 0700, true);
        // Resolver accepts the literal short-array declarations used by built-in templates.
        file_put_contents($root . '/' . $form . '/' . $form . '.php', '<?php $template_langs = [' . implode(',', array_map(fn($l) => "'$l'", $langs)) . '];');
        file_put_contents($dir . '/notice.mjml', '<mjml>{{shared}}</mjml>');
        $data = [];
        foreach ($langs as $lang) { $data[$lang] = ['lang' => $lang, 'shared' => 'value-' . $lang]; }
        file_put_contents($dir . '/translations.json', json_encode($data));
    }
    $state = snapshot();
    ensure(count($state['rows']) === 1 && count($state['rows'][0]['refs']) === 2, 'Different language scopes split shared variable');
    ensure(count($state['rows'][0]['values']) === 4, 'Library lost translations');
    $state['rows'][0]['values']['cs'] = 'updated-cs';
    $saved = PWE_Multilang_Notification_Translation_Store::save($state);
    ensure($saved['changed'] === ['large/notifications/notice'], 'CS edit rewrote PL/EN notification');
    $state = snapshot(); $state['rows'][0]['values']['en'] = 'updated-en';
    $saved = PWE_Multilang_Notification_Translation_Store::save($state);
    ensure(count($saved['changed']) === 2, 'Shared EN edit did not update both notifications');
    $smallDir = $root . '/small/notifications/notice';
    $small = PWE_Multilang_Notification_Translation_Store::read($smallDir);
    ensure(array_keys($small) === ['pl', 'en'], 'Foreign languages leaked into small dictionary');
    $small['cs'] = ['shared' => 'stale-cs'];
    file_put_contents($smallDir . '/translations.json', json_encode($small));
    $saved = PWE_Multilang_Notification_Translation_Store::save(snapshot());
    ensure($saved['changed'] === ['small/notifications/notice'], 'Existing surplus languages not cleaned on save');
    ensure(count(snapshot()['rows']) === 1, 'Reload split shared variable');
    ensure(PWE_Multilang_Notification_Translation_Store::read($root . '/large/notifications/notice')['cs']['shared'] === 'updated-cs', 'Cleanup changed valid CS translation');
    ensure(PWE_Multilang_Notification_Translation_Store::save(snapshot())['changed'] === [], 'No-op changed files');
    $largeDir = $root . '/large/notifications/notice';
    $large = PWE_Multilang_Notification_Translation_Store::read($largeDir);
    unset($large['hu']);
    file_put_contents($largeDir . '/translations.json', json_encode($large));
    $state = snapshot();
    $state['rows'][0]['values']['hu'] = 'new-hu';
    PWE_Multilang_Notification_Translation_Store::save($state);
    ensure(PWE_Multilang_Notification_Translation_Store::read($largeDir)['hu']['lang'] === 'hu', 'New language missing automatic lang');
    ensure(PWE_Multilang_Notification_Translation_Store::save(snapshot())['changed'] === [], 'Automatic lang breaks no-op');
    echo "PASS: shared library across language scopes, filtered writes, isolated CS edit, shared EN edit, surplus cleanup, reload and no-op.\n";
} finally { if (is_dir($temp)) { removeFixture($temp); } }
