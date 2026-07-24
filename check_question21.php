<?php
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/config.php');
global $DB;

$qids = [21, 22, 23, 24];
foreach ($qids as $qid) {
    echo "=== QUESTION $qid ===\n";
    $tags = $DB->get_records_sql("
        SELECT ti.id, t.name, t.rawname
          FROM {tag_instance} ti
          JOIN {tag} t ON ti.tagid = t.id
         WHERE ti.itemid = ?
    ", [$qid]);
    foreach ($tags as $t) {
        echo "  Tag name: {$t->name}, rawname: {$t->rawname}\n";
    }
}
