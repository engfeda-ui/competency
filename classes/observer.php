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
 * Event observers for qbank_competency.
 *
 * @package    qbank_competency
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qbank_competency;

use stdClass;

/**
 * Event observers for mapping question competencies.
 *
 * @package    qbank_competency
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Observer for core tag_added event.
     *
     * @param \core\event\tag_added $event The event object.
     * @return void
     */
    public static function tag_added(\core\event\tag_added $event) {
        global $DB;

        $taginstanceid = $event->objectid;
        $taginstance = $event->get_record_snapshot('tag_instance', $taginstanceid);
        if (!$taginstance) {
            $taginstance = $DB->get_record('tag_instance', ['id' => $taginstanceid]);
        }

        if (!$taginstance || $taginstance->component !== 'core_question' || $taginstance->itemtype !== 'question') {
            return;
        }

        $tagname = $DB->get_field('tag', 'name', ['id' => $taginstance->tagid]);
        if (!$tagname || strpos($tagname, 'comp-') !== 0) {
            return;
        }

        $competencycode = substr($tagname, 5);

        // Find competency by idnumber or shortname.
        $competency = $DB->get_record_select('competency', 'idnumber = ? OR idnumber = ? OR shortname = ? OR shortname = ?', [
            $tagname,
            $competencycode,
            $tagname,
            $competencycode,
        ], '*', IGNORE_MULTIPLE);

        if (!$competency) {
            return;
        }

        $courseid = self::resolve_course_id($taginstance->contextid);
        if ($courseid <= 0) {
            return;
        }

        // Add the mapping only if it does not already exist for this
        // exact (question, course, competency) combination.
        // This supports multi-competency: adding comp-A and comp-B as
        // separate tags will create TWO rows in qbank_competency_qmap.
        $table = 'qbank_competency_qmap';
        $exists = $DB->record_exists($table, [
            'questionid'   => $taginstance->itemid,
            'courseid'     => $courseid,
            'competencyid' => $competency->id,
        ]);

        if (!$exists) {
            $newrecord               = new stdClass();
            $newrecord->questionid   = $taginstance->itemid;
            $newrecord->competencyid = $competency->id;
            $newrecord->courseid     = $courseid;
            $newrecord->timecreated  = time();
            $DB->insert_record($table, $newrecord);
        }
    }

    /**
     * Observer for core tag_removed event.
     *
     * @param \core\event\tag_removed $event The event object.
     * @return void
     */
    public static function tag_removed(\core\event\tag_removed $event) {
        global $DB;

        $taginstanceid = $event->objectid;
        $taginstance = $event->get_record_snapshot('tag_instance', $taginstanceid);
        if (!$taginstance) {
            return;
        }

        if ($taginstance->component !== 'core_question' || $taginstance->itemtype !== 'question') {
            return;
        }

        $tagname = $DB->get_field('tag', 'name', ['id' => $taginstance->tagid]);
        if (!$tagname || strpos($tagname, 'comp-') !== 0) {
            return;
        }

        $competencycode = substr($tagname, 5);

        $competency = $DB->get_record_select('competency', 'idnumber = ? OR idnumber = ? OR shortname = ? OR shortname = ?', [
            $tagname,
            $competencycode,
            $tagname,
            $competencycode,
        ], '*', IGNORE_MULTIPLE);

        if (!$competency) {
            return;
        }

        $courseid = self::resolve_course_id($taginstance->contextid);
        if ($courseid <= 0) {
            return;
        }

        $DB->delete_records('qbank_competency_qmap', [
            'questionid'   => $taginstance->itemid,
            'courseid'     => $courseid,
            'competencyid' => $competency->id,
        ]);
    }

    /**
     * Resolve the course ID from a given context ID.
     * Returns 0 if the context cannot be mapped to a real course.
     *
     * NOTE: Does NOT fall back to $COURSE global or optional_param() — both are
     * unsafe in event handlers that may run from CLI/cron with no HTTP request.
     *
     * @param int $contextid The context ID.
     * @return int The course ID, or 0 if not found.
     */
    protected static function resolve_course_id($contextid) {
        $context = \context::instance_by_id($contextid, IGNORE_MISSING);
        if (!$context) {
            return 0;
        }

        $coursecontext = $context->get_course_context(false);
        if (!$coursecontext || $coursecontext->instanceid <= 1) {
            return 0;
        }

        return (int)$coursecontext->instanceid;
    }
}
