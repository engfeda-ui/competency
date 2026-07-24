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
 * Supports multi-competency: a single question can be mapped to multiple
 * competencies within the same course. A token-style multi-select widget
 * (driven by core/form-autocomplete) is rendered per question row.
 *
 * @package    qbank_comp_ext
 * @copyright  2026 Mahmoud Salem
 * @copyright  based on work by 2026 Hakan Çiğci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qbank_comp_ext\column;

use core_question\local\bank\column_base;
use html_writer;
use stdClass;

/**
 * Competency column for Question Bank — multi-competency aware.
 *
 * @package    qbank_comp_ext
 * @author     Mahmoud Salem
 */
class competency_column extends column_base {
    /** @var array|null $competencyoptions Store available competencies for the course. */
    protected $competencyoptions = null;

    /**
     * Initialize the column.
     *
     * @return void
     */
    public function init(): void {
        parent::init();
        global $PAGE;
        $PAGE->requires->js_call_amd('qbank_comp_ext/mapping', 'init');
    }

    /**
     * Column internal name.
     *
     * @return string
     */
    public function get_name(): string {
        return 'competency';
    }

    /**
     * Column title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string('competency', 'qbank_comp_ext');
    }

    /**
     * Display the content of the column.
     *
     * Renders a multi-select (tokens) widget that shows all competencies
     * currently mapped to the question and allows adding/removing mappings.
     *
     * @param stdClass $question The question object.
     * @param string   $rowclasses CSS classes for the row.
     * @return void
     */
    protected function display_content($question, $rowclasses): void {
        global $DB, $PAGE;

        $courseid   = $this->qbank->course->id ?? $PAGE->course->id;
        $questionid = $question->id;

        // Lazy-load competency list for this course.
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

        // Fetch ALL current mappings for this question in this course.
        $currentmappings = $DB->get_records('qbank_comp_ext_qmap', [
            'courseid'   => $courseid,
            'questionid' => $questionid,
        ], '', 'competencyid');

        // Build array of selected competency IDs.
        $selectedids = array_keys($currentmappings);

        // Auto-sync question tags to competencies if not already mapped in database.
        $qtags = $DB->get_records_sql("
            SELECT t.name, t.rawname
              FROM {tag_instance} ti
              JOIN {tag} t ON ti.tagid = t.id
             WHERE ti.itemid = ?
        ", [$questionid]);

        if (!empty($qtags)) {
            foreach ($qtags as $qtag) {
                $tagname   = strtolower($qtag->name);
                $rawname   = strtolower($qtag->rawname);
                $cleanname = (strpos($tagname, 'comp-') === 0) ? substr($tagname, 5) : $tagname;

                foreach ($this->competencyoptions as $compid => $shortname) {
                    $complower = strtolower($shortname);
                    if ($complower === $tagname || $complower === $rawname || $complower === $cleanname || ('comp-' . $complower) === $tagname) {
                        if (!in_array($compid, $selectedids)) {
                            $rec = (object)[
                                'questionid'   => $questionid,
                                'courseid'     => $courseid,
                                'competencyid' => $compid,
                                'timecreated'  => time(),
                            ];
                            try {
                                $DB->insert_record('qbank_comp_ext_qmap', $rec);
                                $selectedids[] = $compid;
                            } catch (\Throwable $e) {
                                unset($e);
                            }
                        }
                    }
                }
            }
        }

        // Render a <select multiple> element.
        // core/form-autocomplete will enhance it into a token/tag widget.
        $elementid = 'competency_multi_' . $questionid;

        $options = '';
        foreach ($this->competencyoptions as $compid => $shortname) {
            $selected = in_array($compid, $selectedids) ? ' selected' : '';
            $options .= html_writer::tag('option', htmlspecialchars($shortname), [
                'value' => $compid,
            ] + ($selected ? ['selected' => 'selected'] : []));
        }

        $attrs = [
            'id'               => $elementid,
            'name'             => $elementid . '[]',
            'multiple'         => 'multiple',
            'class'            => 'competency-multiselect',
            'data-questionid'  => $questionid,
            'data-courseid'    => $courseid,
            'style'            => 'min-width:180px',
        ];

        echo html_writer::tag('select', $options, $attrs);

        // Enhance with autocomplete token UI.
        $PAGE->requires->js_call_amd('core/form-autocomplete', 'enhance', [
            '#' . $elementid,
            true, // Tags = true to allow token display.
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
        return ['pe-2', 'qbank_comp_ext_column'];
    }
}
