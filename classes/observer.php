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
 * Event observers for qbank_comp_ext.
 *
 * @package    qbank_comp_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qbank_comp_ext;

use stdClass;

/**
 * Event observers for mapping question competencies.
 *
 * @package    qbank_comp_ext
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

        $tag = $DB->get_record('tag', ['id' => $taginstance->tagid], 'id, name, rawname');
        if (!$tag) {
            return;
        }

        $rawname = !empty($tag->rawname) ? $tag->rawname : $tag->name;
        $parts = preg_split('/[,;]+/', $rawname);

        $courseid = self::resolve_course_id($taginstance->contextid);
        if ($courseid <= 0) {
            return;
        }

        $table = 'qbank_comp_ext_qmap';

        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) {
                continue;
            }
            if (stripos($part, 'comp-') !== 0 && stripos($part, 'comp_') !== 0) {
                continue;
            }

            $competencycode = substr($part, 5);

            // Find competency scoped to this course by idnumber or shortname (case-insensitive).
            $competency = $DB->get_record_sql("
                SELECT c.*
                  FROM {competency} c
                  JOIN {competency_coursecomp} cc ON cc.competencyid = c.id
                 WHERE cc.courseid = :courseid
                   AND (LOWER(c.idnumber) = LOWER(:p1) OR LOWER(c.idnumber) = LOWER(:p2)
                     OR LOWER(c.shortname) = LOWER(:p3) OR LOWER(c.shortname) = LOWER(:p4))
            ", [
                'courseid' => $courseid,
                'p1'       => $part,
                'p2'       => $competencycode,
                'p3'       => $part,
                'p4'       => $competencycode,
            ], IGNORE_MULTIPLE);

            if (!$competency) {
                continue;
            }

            // Add the mapping only if it does not already exist for this combination.
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

        $tag = $DB->get_record('tag', ['id' => $taginstance->tagid], 'id, name, rawname');
        if (!$tag) {
            return;
        }

        $rawname = !empty($tag->rawname) ? $tag->rawname : $tag->name;
        $parts = preg_split('/[,;]+/', $rawname);

        $courseid = self::resolve_course_id($taginstance->contextid);
        if ($courseid <= 0) {
            return;
        }

        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) {
                continue;
            }
            if (stripos($part, 'comp-') !== 0 && stripos($part, 'comp_') !== 0) {
                continue;
            }

            $competencycode = substr($part, 5);

            $competency = $DB->get_record_sql("
                SELECT c.*
                  FROM {competency} c
                  JOIN {competency_coursecomp} cc ON cc.competencyid = c.id
                 WHERE cc.courseid = :courseid
                   AND (LOWER(c.idnumber) = LOWER(:p1) OR LOWER(c.idnumber) = LOWER(:p2)
                     OR LOWER(c.shortname) = LOWER(:p3) OR LOWER(c.shortname) = LOWER(:p4))
            ", [
                'courseid' => $courseid,
                'p1'       => $part,
                'p2'       => $competencycode,
                'p3'       => $part,
                'p4'       => $competencycode,
            ], IGNORE_MULTIPLE);

            if (!$competency) {
                continue;
            }

            $DB->delete_records('qbank_comp_ext_qmap', [
                'questionid'   => $taginstance->itemid,
                'courseid'     => $courseid,
                'competencyid' => $competency->id,
            ]);
        }
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
