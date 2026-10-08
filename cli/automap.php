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
 * CLI script to auto-map questions to course competencies using semantic token matching.
 *
 * Usage:
 *   php local/comp_report_ext/cli/automap.php --courseid=X
 *   php question/bank/comp_ext/cli/automap.php --courseid=X
 *
 * @package    qbank_comp_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params([
    'help'     => false,
    'courseid' => 0,
    'all'      => false,
], [
    'h' => 'help',
    'c' => 'courseid',
    'a' => 'all',
]);

if ($options['help'] || (empty($options['courseid']) && empty($options['all']))) {
    $help = "Auto-map course questions to course-linked competencies.

Options:
  -h, --help            Print out this help
  -c, --courseid=INT    Course ID to run auto-mapping on
  -a, --all             Run on all courses with linked competencies

Example:
  php question/bank/comp_ext/cli/automap.php --courseid=3
";
    cli_writeln($help);
    exit(0);
}

global $DB;

$courseids = [];
if (!empty($options['courseid'])) {
    $courseids[] = (int)$options['courseid'];
} else if (!empty($options['all'])) {
    $courses = $DB->get_records_sql("SELECT DISTINCT courseid FROM {competency_coursecomp}");
    $courseids = array_keys($courses);
}

cli_writeln("Starting Question Competency Auto-Mapping...");

$totalmapped = 0;
foreach ($courseids as $cid) {
    $res = \qbank_comp_ext\auto_mapper::auto_map_course_questions($cid);
    cli_writeln("Course {$cid}: mapped {$res['mapped']} of {$res['total']} unmapped questions.");
    $totalmapped += $res['mapped'];
}

cli_writeln("Done! Total new question-competency mappings created: {$totalmapped}");
exit(0);
