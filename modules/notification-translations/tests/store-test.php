<?php
/** Run: php modules/notification-translations/tests/store-test.php; no WordPress/database required. */
if (PHP_SAPI !== 'cli') { exit; }
$plugin = dirname(__DIR__, 3) . '/';
$temp = sys_get_temp_dir() . '/pwe-notification-test-' . bin2hex(random_bytes(6));
define('ABSPATH', $temp . '/');
define('PWE_MULTILANG_PATH', $temp . '/');
require $plugin . 'modules/notification-translations/store.php';
function check($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
function copyTree($source, $target) {
    mkdir($target, 0700, true);
    foreach (new DirectoryIterator($source) as $file) {
        if ($file->isDot() || $file->isLink()) { continue; }
        $to = $target . '/' . $file->getFilename();
        if ($file->isDir()) { copyTree($file->getPathname(), $to); } else { copy($file->getPathname(), $to); }
    }
}
function removeTree($path) {
    if (!is_dir($path)) { return; }
    foreach (new DirectoryIterator($path) as $file) {
        if ($file->isDot()) { continue; }
        if ($file->isDir()) { removeTree($file->getPathname()); } else { unlink($file->getPathname()); }
    }
    rmdir($path);
}
function state() { return json_decode(json_encode(PWE_Multilang_Notification_Translation_Store::scan()), true); }
function reject($request, $expected) {
    try { PWE_Multilang_Notification_Translation_Store::save($request); }
    catch (RuntimeException $error) { check(strpos($error->getMessage(), $expected) !== false, $error->getMessage()); return; }
    throw new RuntimeException('Expected rejection: ' . $expected);
}
function hashes() {
    $hashes=[];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(PWE_Multilang_Notification_Translation_Store::root(), FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->isFile() && $file->getFilename() !== '.notification-translations.lock') $hashes[$file->getPathname()] = hash_file('sha256', $file->getPathname());
    }
    return $hashes;
}
function rendered($state) {
    $out=[];
    foreach ($state['notifications'] as $id=>$notification) {
        $dir = PWE_Multilang_Notification_Translation_Store::root() . '/' . $id;
        $dict = PWE_Multilang_Notification_Translation_Store::read($dir);
        foreach (array_merge(glob($dir.'/*.html') ?: [], glob($dir.'/*.mjml') ?: []) as $file) {
            foreach ($notification['languages'] as $lang) {
                $values=array_replace($dict[$lang]??[], ['lang'=>$lang]);
                $text=file_get_contents($file);
                foreach ($values as $key=>$value) $text=str_replace('{{'.$key.'}}',$value,$text);
                // Renaming a missing placeholder preserves its missing status, not its spelling.
                $out[$file][$lang]=preg_replace('/\{\{[a-zA-Z_][a-zA-Z0-9_.-]*\}\}/', '{{MISSING_TRANSLATION}}', $text);
            }
        }
    }
    return $out;
}
try {
    copyTree($plugin . 'modules/forms/form-templates', $temp . '/modules/forms/form-templates');
    $initial = state(); check(count($initial['notifications']) > 0, 'No MJML notifications');
    $before = rendered($initial); $initialHashes=hashes();
    state(); check($initialHashes === hashes(), 'Scan wrote files');
    $counts=array_count_values(array_column($initial['rows'],'key'));
    foreach ($initial['rows'] as $i=>&$row) { if ($counts[$row['key']] > 1) $row['key'].='_variant_'.$i; }
    unset($row);
    $result=PWE_Multilang_Notification_Translation_Store::save($initial);
    $current=state();
    check($before === rendered($current), 'Migration changed rendered notification content');
    foreach ($result['changed'] as $id) {
        $dir=PWE_Multilang_Notification_Translation_Store::root().'/'.$id;
        check(is_file($dir.'/translations.json'), 'Missing JSON');
        check(!is_file($dir.'/translations.php'), 'Save recreated a PHP loader');
    }
    $stable=hashes(); $noop=PWE_Multilang_Notification_Translation_Store::save($current);
    check($noop['changed']===[] && hashes()===$stable, 'No-op save changed files');
    $shared=null;
    foreach ($current['rows'] as $i=>$row) {
        if (count(array_unique(array_column($row['refs'],'id'))) > 1) { $shared=$i; break; }
    }
    check($shared!==null,'No shared variable');
    $expected=array_values(array_unique(array_column($current['rows'][$shared]['refs'],'id'))); sort($expected);
    $current['rows'][$shared]['values']['pl']='Test: "wartość" <br> żółć \\ {{nested}}';
    $changed=PWE_Multilang_Notification_Translation_Store::save($current); $actual=$changed['changed']; sort($actual);
    check($actual===$expected, 'Shared edit affected wrong notifications');
    $after=hashes();
    foreach ($stable as $path=>$hash) {
        $affected=false; foreach ($expected as $id) { if (strpos($path,'/'.$id.'/')!==false) $affected=true; }
        if (!$affected) check(($after[$path]??null)===$hash,'Unrelated file changed');
    }
    reject($current, 'Pliki zmieniły');
    $current=state(); $invalid=$current;
    $invalid['rows'][0]['refs'][]=['id'=>'../../outside', 'key'=>'bad']; reject($invalid, 'Nieznane powiadomienie');
    $invalid=$current; $invalid['rows'][1]['key']=$invalid['rows'][0]['key']; reject($invalid,'Rozwiąż konflikt');
    $invalid=$current; array_pop($invalid['rows']); reject($invalid,'Nie można usunąć');
    $invalid=$current; $invalid['rows'][0]['values']['pl']=['bad']; reject($invalid,'muszą być tekstem');
    $invalid=$current; $invalid['rows'][1]['values']=$invalid['rows'][0]['values']; reject($invalid,'Identyczne tłumaczenia');
    $current['rows'][$shared]['values']['pt-br']='Olá!';
    PWE_Multilang_Notification_Translation_Store::save($current);
    check(strpos(json_encode(state()),'pt-br')===false, 'Unsupported language leaked into notification JSON');
    // Attach an existing variable to another MJML notification, preserving its entire dictionary.
    $current=state(); $rowIndex=0; $target=null;
    foreach (array_keys($current['notifications']) as $id) {
        if (!in_array($id,array_column($current['rows'][$rowIndex]['refs'],'id'),true)) { $target=$id; break; }
    }
    if ($target) {
        $current['rows'][$rowIndex]['refs'][]=['id'=>$target,'key'=>$current['rows'][$rowIndex]['key']];
        $result=PWE_Multilang_Notification_Translation_Store::save($current);
        check(in_array($target,$result['changed'],true),'Attaching variable failed');
    }
    // Choosing one version merges aliases, removes old dictionary keys and rewrites both sources.
    $current=state(); $winner=$current['rows'][0]; $loser=$current['rows'][1];
    $current['rows'][0]['refs']=array_merge($winner['refs'],$loser['refs']);
    array_splice($current['rows'],1,1);
    PWE_Multilang_Notification_Translation_Store::save($current);
    foreach ($loser['refs'] as $ref) {
        $dir=PWE_Multilang_Notification_Translation_Store::root().'/'.$ref['id'];
        $dictionary=PWE_Multilang_Notification_Translation_Store::read($dir);
        foreach ($winner['values'] as $lang=>$value) {
            check(($dictionary[$lang][$winner['key']]??null)===$value,'Merge did not propagate chosen translation');
        }
        foreach ($dictionary as $entries) check(!array_key_exists($loser['key'],$entries),'Merged alias left in dictionary');
        foreach (array_merge(glob($dir.'/*.mjml')?:[],glob($dir.'/*.html')?:[]) as $file) {
            check(strpos(file_get_contents($file),'{{'.$loser['key'].'}}')===false,'Merged placeholder left in template');
        }
    }
    // Invalid JSON must fail explicitly.
    $first=array_key_first(state()['notifications']);
    $json=PWE_Multilang_Notification_Translation_Store::root().'/'.$first.'/translations.json';
    $valid=file_get_contents($json); file_put_contents($json,'{invalid');
    try { PWE_Multilang_Notification_Translation_Store::read(dirname($json)); throw new RuntimeException('Invalid JSON accepted'); }
    catch (JsonException $expected) {} finally { file_put_contents($json,$valid); }
    // Independent missing variables must survive adding a language and rescanning.
    $fixtureId='zzz_empty/notifications/empty';
    $fixtureDir=PWE_Multilang_Notification_Translation_Store::root().'/'.$fixtureId;
    mkdir($fixtureDir,0700,true);
    file_put_contents(dirname($fixtureDir, 2).'/zzz_empty.php', '<?php $template_langs = ["pl", "en", "de"];');
    file_put_contents($fixtureDir.'/empty.mjml','<mjml>{{first_missing}}{{second_missing}}</mjml>');
    $current=state();
    foreach ($current['rows'] as &$row) {
        if (in_array($row['key'],['first_missing','second_missing'],true)) $row['values']['de']='';
    }
    unset($row);
    PWE_Multilang_Notification_Translation_Store::save($current);
    $current=state();
    $missing=array_values(array_filter($current['rows'],static fn($r)=>in_array($r['key'],['first_missing','second_missing'],true)));
    check(count($missing)===2,'Blank variables merged during scan');
    foreach($missing as $row) check(($row['values']['de']??null)==='' && count($row['refs'])===1,'Blank variable changed');
    check(PWE_Multilang_Notification_Translation_Store::save($current)['changed']===[],'Blank variables changed on no-op save');
    foreach ($current['rows'] as &$row) {
        if (in_array($row['key'],['first_missing','second_missing'],true)) $row['values']['de']=" \t ";
    }
    unset($row);
    PWE_Multilang_Notification_Translation_Store::save($current);
    check(count(array_filter(state()['rows'],static fn($r)=>in_array($r['key'],['first_missing','second_missing'],true)))===2,'Whitespace variables merged');
    // Runtime loader supports the migrated JSON even with the editor disabled.
    require $plugin . 'modules/forms/core/build/notification-templates.php';
    $method=new ReflectionMethod(PWE_Multilang_Form_Notification_Templates::class,'getTranslations');
    $method->setAccessible(true);
    $first=array_key_first(state()['notifications']); [$form,,$name]=explode('/',$first);
    $loaded=$method->invoke(null,'pl',$name,PWE_Multilang_Notification_Translation_Store::root().'/'.$form);
    check(is_array($loaded) && count($loaded)>0,'Runtime JSON loader failed');
    echo "PASS: real-template migration preserves rendering, JSON-only dictionaries, shared edits, no-op, isolated writes, stale revisions, validation, language addition, variable reuse, merge, invalid JSON, runtime loader.\n";
} finally { removeTree($temp); }
