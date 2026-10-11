<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * External API class for saving question competency mapping.
 *
 * Supports multi-competency: a single question can be mapped to multiple
 * competencies simultaneously within the same course. The caller passes the
 * FULL desired set of competency IDs for the question; this method replaces
 * all existing mappings with that set atomically.
 *
 * @package    qbank_comp_ext
 * @copyright  2026 Mahmoud Salem
 * @copyright  based on work by 2026 Hakan Çiğci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qbank_comp_ext\external;

use stdClass;
use context_course;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_multiple_structure;

/**
 * External service class for saving question competencies (multi-competency).
 *
 * @package    qbank_comp_ext
 * @copyright  2026 Mahmoud Salem
 * @copyright  based on work by 2026 Hakan Çiğci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_question_competency extends external_api {
    /**
     * Parameter definitions for the execute method.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'questionid'    => new external_value(PARAM_INT, 'The ID of the question'),
            'competencyids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'A competency ID'),
                'Array of competency IDs to map to this question (empty array = remove all mappings)'
            ),
            'courseid'      => new external_value(PARAM_INT, 'The ID of the course'),
        ]);
    }

    /**
     * Save the full set of competency mappings for a question.
     *
     * This is a full-replace operation:
     *   - Existing mappings for (questionid, courseid) are deleted.
     *   - New records are inserted for each ID in $competencyids.
     *   - If $competencyids is empty, all mappings are removed.
     *
     * @param int   $questionid    The ID of the question.
     * @param int[] $competencyids Array of competency IDs to map (may be empty).
     * @param int   $courseid      The ID of the course.
     * @return bool True on success.
     */
    public static function execute($questionid, $competencyids, $courseid) {
        global $DB;

        // Validate the parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'questionid'    => $questionid,
            'competencyids' => $competencyids,
            'courseid'      => $courseid,
        ]);

        // Security and Context check.
        $context = context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);

        // Ownership validation: the question must exist and belong to this course
        // (or to a shared system bank / parent category); competencies must exist and be linked to it.
        if ($DB->get_manager()->table_exists('question_bank_entries')) {
            $sql = "SELECT q.id, qc.contextid
                      FROM {question} q
                      JOIN {question_versions} qv ON qv.questionid = q.id
                      JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                      JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                     WHERE q.id = :questionid";
            $qrec = $DB->get_record_sql($sql, ['questionid' => $params['questionid']], MUST_EXIST);
            $contextid = $qrec->contextid;
        } else {
            $question = $DB->get_record('question', ['id' => $params['questionid']], 'id, category', MUST_EXIST);
            $category = $DB->get_record('question_categories', ['id' => $question->category], 'id, contextid', MUST_EXIST);
            $contextid = $category->contextid;
        }

        $questioncontext = \context::instance_by_id($contextid, MUST_EXIST);
        $coursecontext = context_course::instance($params['courseid']);
        $questioncourse = $questioncontext->get_course_context(false);

        $isvalid = false;
        if ($questioncourse && (int)$questioncourse->instanceid === (int)$params['courseid']) {
            $isvalid = true;
        } else if ($questioncontext->is_parent_of($coursecontext, true)) {
            $isvalid = true;
        }

        if (!$isvalid) {
            throw new \invalid_parameter_exception('questionnotincourse');
        }

        $validcompids = [];
        foreach ($params['competencyids'] as $compid) {
            $compid = (int)$compid;
            if ($compid <= 0 || isset($validcompids[$compid])) {
                continue;
            }
            $linked = $DB->record_exists_sql(
                "SELECT 1 FROM {competency} c
                  JOIN {competency_coursecomp} cc ON cc.competencyid = c.id
                 WHERE c.id = :compid AND cc.courseid = :courseid",
                ['compid' => $compid, 'courseid' => $params['courseid']]
            );
            if (!$linked) {
                throw new \invalid_parameter_exception('competencynotincourse');
            }
            $validcompids[$compid] = true;
        }

        $table = 'qbank_comp_ext_qmap';
        $now   = time();

        // Atomic full replace: delete + re-insert inside one transaction.
        $transaction = $DB->start_delegated_transaction();

        // Delete ALL existing mappings for this question+course atomically.
        $DB->delete_records($table, [
            'questionid' => $params['questionid'],
            'courseid'   => $params['courseid'],
        ]);

        // Re-insert one record per validated competency ID (deduplicated).
        foreach (array_keys($validcompids) as $compid) {
            $record               = new stdClass();
            $record->questionid   = $params['questionid'];
            $record->competencyid = $compid;
            $record->courseid     = $params['courseid'];
            $record->timecreated  = $now;
            $DB->insert_record($table, $record);
        }

        $transaction->allow_commit();

        // Synchronize question tags so tag badges and filters stay aligned.
        if (class_exists('\core_tag_tag')) {
            $existingtags = \core_tag_tag::get_item_tags_array('core_question', 'question', $params['questionid']);
            $newtags = [];
            foreach ($existingtags as $tagname) {
                $iscomp = false;
                $parts = preg_split('/[,;]+/', $tagname);
                foreach ($parts as $p) {
                    $p = trim($p);
                    if (stripos($p, 'comp-') === 0 || stripos($p, 'comp_') === 0) {
                        $iscomp = true;
                        break;
                    }
                }
                if (!$iscomp) {
                    $newtags[] = $tagname;
                }
            }
            if (!empty($validcompids)) {
                $compnames = $DB->get_records_list('competency', 'id', array_keys($validcompids), '', 'id, shortname');
                foreach ($compnames as $c) {
                    $newtags[] = 'comp-' . $c->shortname;
                }
            }
            \core_tag_tag::set_item_tags('core_question', 'question', $params['questionid'], $questioncontext, $newtags);
        }

        return true;
    }

    /**
     * Return value definition for the execute method.
     *
     * @return external_value
     */
    public static function execute_returns() {
        return new external_value(PARAM_BOOL, 'Success status');
    }
}
