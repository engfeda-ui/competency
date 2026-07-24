<?php
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/config.php');
global $DB;

echo "=== AUTOMATIC TAG SYNC FOR QBANK_COMP_EXT ===\n";

$tags = $DB->get_records_sql("
    SELECT ti.id, ti.itemid as questionid, ti.contextid, t.name as tagname
      FROM {tag_instance} ti
      JOIN {tag} t ON ti.tagid = t.id
     WHERE ti.itemtype = 'question' AND t.name LIKE 'comp-%'
");

echo "Found " . count($tags) . " question tags starting with 'comp-'\n";

$added = 0;
foreach ($tags as $t) {
    $tagname = $t->tagname;
    $code = substr($tagname, 5);

    // Case-insensitive lookup.
    $comp = $DB->get_record_sql("
        SELECT id, shortname, idnumber
          FROM {competency}
         WHERE LOWER(idnumber) = LOWER(?) OR LOWER(idnumber) = LOWER(?)
            OR LOWER(shortname) = LOWER(?) OR LOWER(shortname) = LOWER(?)
    ", [$tagname, $code, $tagname, $code]);

    if ($comp) {
        // Resolve course ID if possible, default to 2 or course context if context is 125.
        $courseid = 2; // Default active course.
        $ctx = context::instance_by_id($t->contextid, IGNORE_MISSING);
        if ($ctx && $cctx = $ctx->get_course_context(false)) {
            if ($cctx->instanceid > 1) {
                $courseid = $cctx->instanceid;
            }
        }

        $exists = $DB->record_exists('qbank_comp_ext_qmap', [
            'questionid'   => $t->questionid,
            'courseid'     => $courseid,
            'competencyid' => $comp->id,
        ]);

        if (!$exists) {
            $rec = (object)[
                'questionid'   => $t->questionid,
                'courseid'     => $courseid,
                'competencyid' => $comp->id,
                'timecreated'  => time(),
            ];
            $DB->insert_record('qbank_comp_ext_qmap', $rec);
            $added++;
            echo "  [MAPPED] Question {$t->questionid} -> Comp {$comp->id} ({$comp->shortname}) in Course {$courseid}\n";
        }
    } else {
        echo "  [NO COMP MATCH] Tag: {$tagname} (code: {$code})\n";
    }
}

echo "\nTotal new mappings inserted: {$added}\n";
$qmapcount = $DB->count_records('qbank_comp_ext_qmap');
echo "Total qmap rows in DB: {$qmapcount}\n";
