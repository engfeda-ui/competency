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
 * @package    qbank_yetkinlik
 * @copyright  2026 Hakan Çiğci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qbank_yetkinlik\external;

use stdClass;
use context_course;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;

/**
 * External service class for saving question competencies.
 *
 * @package    qbank_yetkinlik
 * @copyright  2026 Hakan Çiğci {@link https://hakancigci.com.tr}
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
            'questionid'   => new external_value(PARAM_INT, 'The ID of the question'),
            'competencyid' => new external_value(PARAM_INT, 'The ID of the competency'),
            'courseid'     => new external_value(PARAM_INT, 'The ID of the course'),
        ]);
    }

    /**
     * Main method to execute the competency saving process.
     *
     * @param int $questionid The ID of the question.
     * @param int $competencyid The ID of the competency.
     * @param int $courseid The ID of the course.
     * @return bool True on success.
     */
    public static function execute($questionid, $competencyid, $courseid) {
        global $DB;

        // Validate the parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'questionid'   => $questionid,
            'competencyid' => $competencyid,
            'courseid'     => $courseid,
        ]);

        // Security and Context check.
        $context = context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);

        $table = 'qbank_yetkinlik_qmap';
        $conditions = [
            'questionid' => $params['questionid'],
            'courseid'   => $params['courseid'],
        ];
        $record = $DB->get_record($table, $conditions);

        if ($params['competencyid'] == 0) {
            if ($record) {
                $DB->delete_records($table, ['id' => $record->id]);
            }
        } else {
            if ($record) {
                $record->competencyid = $params['competencyid'];
                $DB->update_record($table, $record);
            } else {
                $newrecord = new stdClass();
                $newrecord->questionid   = $params['questionid'];
                $newrecord->competencyid = $params['competencyid'];
                $newrecord->courseid     = $params['courseid'];
                $newrecord->timecreated  = time();
                $DB->insert_record($table, $newrecord);
            }
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
