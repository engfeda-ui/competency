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

        $table = 'qbank_comp_ext_qmap';
        $now   = time();

        // Delete ALL existing mappings for this question+course atomically.
        $DB->delete_records($table, [
            'questionid' => $params['questionid'],
            'courseid'   => $params['courseid'],
        ]);

        // Re-insert one record per competency ID (deduplicated).
        $seen = [];
        foreach ($params['competencyids'] as $compid) {
            if ($compid <= 0 || isset($seen[$compid])) {
                continue; // Skip 0 / duplicates.
            }
            $seen[$compid] = true;

            $record               = new stdClass();
            $record->questionid   = $params['questionid'];
            $record->competencyid = $compid;
            $record->courseid     = $params['courseid'];
            $record->timecreated  = $now;
            $DB->insert_record($table, $record);
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
