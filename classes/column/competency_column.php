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
 * Competency column for Question Bank.
 *
 * @package    qbank_yetkinlik
 * @copyright  2026 Hakan Çiğci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qbank_yetkinlik\column;

use core_question\local\bank\column_base;
use html_writer;
use stdClass;

/**
 * Competency column for Question Bank.
 *
 * @package    qbank_yetkinlik
 * @author     Hakan Çiğci
 */
class competency_column extends column_base {
    /** @var array $competencyoptions Store available competencies for the course. */
    protected $competencyoptions = null;

    /**
     * Initialize the column.
     *
     * @return void
     */
    public function init(): void {
        parent::init();
        global $PAGE;
        $PAGE->requires->js_call_amd('qbank_yetkinlik/mapping', 'init');
    }

    /**
     * Column internal name.
     *
     * @return string
     */
    public function get_name(): string {
        return 'yetkinlik';
    }

    /**
     * Column title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string('competency', 'qbank_yetkinlik');
    }

    /**
     * Display the content of the column.
     *
     * @param stdClass $question The question object.
     * @param string $rowclasses CSS classes for the row.
     * @return void
     */
    protected function display_content($question, $rowclasses): void {
        global $DB, $PAGE;

        $courseid = $this->qbank->id ?? $this->qbank->course->id ?? $PAGE->course->id;
        $questionid = $question->id;

        if ($this->competencyoptions === null) {
            $this->competencyoptions = $DB->get_records_sql_menu("
                SELECT c.id, c.shortname
                  FROM {competency} c
                  JOIN {competency_coursecomp} cc ON cc.competencyid = c.id
                 WHERE cc.courseid = ?
                 ORDER BY c.shortname
            ", [$courseid]);
        }

        if (!$this->competencyoptions) {
            echo html_writer::tag('span', '-', ['class' => 'text-muted']);
            return;
        }

        $current = $DB->get_field('qbank_yetkinlik_qmap', 'competencyid', [
            'courseid'   => $courseid,
            'questionid' => $questionid,
        ]);

        $elementid = 'competency_' . $questionid;
        $options = [0 => '—'] + $this->competencyoptions;

        echo html_writer::select($options, $elementid, $current, false, [
            'id'              => $elementid,
            'class'           => 'yetkinlik-select custom-select',
            'data-questionid' => $questionid,
            'data-courseid'   => $courseid,
        ]);

        $PAGE->requires->js_call_amd('core/form-autocomplete', 'enhance', [
            '#' . $elementid,
            false,
            '',
            get_string('search'),
            false,
            true,
            get_string('noresults', 'moodle'),
        ]);
    }

    /**
     * Additional CSS classes for the cell.
     *
     * @return array
     */
    public function get_extra_classes(): array {
        return ['pe-2', 'qbank_yetkinlik_column'];
    }
}
