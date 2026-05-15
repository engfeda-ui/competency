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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Question bank plugin features definition.
 *
 * @package    qbank_yetkinlik
 * @copyright  2026 Hakan Çiğci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qbank_yetkinlik;

use core_question\local\bank\plugin_features_base;
use core_question\local\bank\view;
use qbank_yetkinlik\column\competency_column;

/**
 * Class plugin_feature
 *
 * This class defines the features provided by the qbank_yetkinlik plugin
 * to the question bank view.
 *
 * @package    qbank_yetkinlik
 * @author     Hakan Çiğci
 */
class plugin_feature extends plugin_features_base {
    /**
     * Define the columns provided by this plugin to the question bank.
     *
     * @param view $qbank The question bank view object.
     * @return array Array of column objects.
     */
    public function get_question_columns(view $qbank): array {
        return [
            new competency_column($qbank),
        ];
    }
}
