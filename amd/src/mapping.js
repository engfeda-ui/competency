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
 * Ajax for competency records.
 *
 * @module      local_yetkinlik/charts
 * @copyright   2026 Hakan Çiğci {@link https://hakancigci.com.tr}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';

export const init = () => {
    const selects = document.querySelectorAll('.yetkinlik-select');
    selects.forEach(select => {
        select.addEventListener('change', async(e) => {
            const target = e.target;
            try {
                await Ajax.call([{
                    methodname: 'qbank_yetkinlik_save_question_competency',
                    args: {
                        questionid: target.dataset.questionid,
                        competencyid: target.value,
                        courseid: target.dataset.courseid
                    }
                }])[0];
                // Başarılı!
            } catch (error) {
                Notification.exception(error);
            }
        });
    });
};
