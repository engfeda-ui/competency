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
 * AMD module for multi-competency question mapping.
 *
 * Listens for changes on `.competency-multiselect` elements (rendered by
 * competency_column) and sends the full selected set of competency IDs to
 * the web service as an array, replacing all existing mappings atomically.
 *
 * @module      qbank_competency/mapping
 * @copyright   2026 Mahmoud Salem
 * @copyright   based on work by 2026 Hakan Çiğci {@link https://hakancigci.com.tr}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';

/**
 * Collect all currently selected option values from a <select multiple>.
 *
 * @param {HTMLSelectElement} selectEl
 * @returns {number[]}
 */
const getSelectedIds = (selectEl) => {
    return Array.from(selectEl.selectedOptions).map(opt => parseInt(opt.value, 10));
};

/**
 * Debounce helper — prevents rapid successive AJAX calls while the user
 * is still interacting with the autocomplete widget.
 *
 * @param {Function} fn
 * @param {number}   delay  ms
 * @returns {Function}
 */
const debounce = (fn, delay) => {
    let timer;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), delay);
    };
};

export const init = () => {
    // Use event delegation on the document so elements added dynamically
    // (e.g. after pagination in the question bank) are also handled.
    document.addEventListener('change', debounce(async(e) => {
        const target = e.target;

        // Only act on our multi-select elements.
        if (!target.classList.contains('competency-multiselect')) {
            return;
        }

        const questionid = parseInt(target.dataset.questionid, 10);
        const courseid   = parseInt(target.dataset.courseid,   10);
        const competencyids = getSelectedIds(target);

        try {
            await Ajax.call([{
                methodname: 'qbank_competency_save_question_competency',
                args: {
                    questionid,
                    competencyids,   // Full array — replaces all existing mappings.
                    courseid,
                },
            }])[0];
            // Success — no UI action needed; the widget already shows selections.
        } catch (error) {
            Notification.exception(error);
        }
    }, 400)); // 400 ms debounce — core autocomplete fires multiple change events.
};
