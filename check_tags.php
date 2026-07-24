<?php
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/config.php');
global $DB;

echo "=== TAGS ON QUESTIONS ===\n";
$tags = $DB->get_records_sql("
    SELECT ti.id, ti.itemid as questionid, ti.contextid, t.name as tagname
      FROM {tag_instance} ti
      JOIN {tag} t ON ti.tagid = t.id
     WHERE ti.itemtype = 'question'
");
echo "Total question tags: " . count($tags) . "\n";
foreach ($tags as $t) {
    echo "  - Question ID {$t->questionid} (context {$t->contextid}): Tag = {$t->tagname}\n";
}

echo "\n=== ALL COMPETENCIES IN SYSTEM ===\n";
$comps = $DB->get_records('competency', null, 'idnumber', 'id, shortname, idnumber');
echo "Total competencies: " . count($comps) . "\n";
foreach ($comps as $c) {
    echo "  - Comp ID {$c->id}: shortname = {$c->shortname}, idnumber = {$c->idnumber}\n";
}

echo "\n=== COURSE COMPETENCIES FOR COURSE 2 ===\n";
$coursecomps = $DB->get_records('competency_coursecomp', ['courseid' => 2]);
echo "Course 2 competencies count: " . count($coursecomps) . "\n";
foreach ($coursecomps as $cc) {
    echo "  - Course 2 has Competency ID {$cc->competencyid}\n";
}

echo "\n=== QBANK_COMP_EXT_QMAP ROWS ===\n";
$qmaps = $DB->get_records('qbank_comp_ext_qmap');
echo "Total qmap rows: " . count($qmaps) . "\n";
foreach ($qmaps as $qm) {
    echo "  - Qmap ID {$qm->id}: Course {$qm->courseid}, Question {$qm->questionid} -> Competency {$qm->competencyid}\n";
}
