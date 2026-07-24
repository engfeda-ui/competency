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
 * Version details for the qbank_comp_ext plugin.
 *
 * @package    qbank_comp_ext
 * @copyright  2026 Mahmoud Salem
 * @copyright  based on work by 2026 Hakan Çiğci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/** @var stdClass $plugin */
$plugin->component = 'qbank_comp_ext';    // Full name of the plugin (category_name).
$plugin->version   = 2026072501;           // The current module version (YYYYMMDDXX).
$plugin->requires  = 2024042210;           // Requires Moodle 4.5 or later.
$plugin->maturity  = MATURITY_STABLE;       // Maturity level of the plugin.
$plugin->release   = '2.3.0';              // Human-readable version name.
