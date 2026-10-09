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
 * Upgrade steps for the qbank_comp_ext plugin.
 *
 * @package    qbank_comp_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the plugin from an old version.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool Always true on success.
 */
function xmldb_qbank_comp_ext_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026100904) {
        // Enforce one mapping row per (question, course, competency) triple.
        // Remove any duplicates created by historical races, then make the index unique.
        $table = new xmldb_table('qbank_comp_ext_qmap');

        $dupids = $DB->get_fieldset_sql("
            SELECT m.id
              FROM {qbank_comp_ext_qmap} m
              JOIN {qbank_comp_ext_qmap} newer
                ON newer.questionid = m.questionid
               AND newer.courseid = m.courseid
               AND newer.competencyid = m.competencyid
               AND newer.id > m.id
        ");
        if (!empty($dupids)) {
            foreach (array_chunk($dupids, 1000) as $chunk) {
                $DB->delete_records_list('qbank_comp_ext_qmap', 'id', $chunk);
            }
        }

        $newidx = new xmldb_index(
            'question_course_comp_idx',
            XMLDB_INDEX_UNIQUE,
            ['questionid', 'courseid', 'competencyid']
        );
        if (!$dbman->find_index_name($table, $newidx)) {
            $dbman->add_index($table, $newidx);
        }

        // Qbank savepoint reached.
        upgrade_plugin_savepoint(true, 2026100904, 'qbank', 'comp_ext');
    }

    return true;
}
