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
 * This file defines the setting form for the quiz responses report.
 *
 * @package    mod_ddtaquiz
 * @copyright  2017 Luca Gladiator <lucamarius.gladiator@stud.tu-darmstadt.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_ddtaquiz\report;

defined('MOODLE_INTERNAL') || die();


/**
 * Quiz responses report settings form.
 *
 * @copyright  2017 Luca Gladiator <lucamarius.gladiator@stud.tu-darmstadt.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class responses_form extends attempts_form {

    protected function other_preference_fields(\MoodleQuickForm $mform) {
        $mform->addGroup(array(
            $mform->createElement('advcheckbox', 'qtext', '',
                get_string('questiontext', 'ddtaquiz')),
            $mform->createElement('advcheckbox', 'resp', '',
                get_string('response', 'ddtaquiz')),
            $mform->createElement('advcheckbox', 'right', '',
                get_string('rightanswer', 'ddtaquiz')),
        ), 'coloptions', get_string('showthe', 'ddtaquiz'), array(' '), false);
        $mform->disabledIf('qtext', 'attempts', 'eq', attempts::ENROLLED_WITHOUT);
        $mform->disabledIf('resp',  'attempts', 'eq', attempts::ENROLLED_WITHOUT);
        $mform->disabledIf('right', 'attempts', 'eq', attempts::ENROLLED_WITHOUT);
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if ($data['attempts'] != attempts::ENROLLED_WITHOUT && !(
                $data['qtext'] || $data['resp'] || $data['right'])) {
            $errors['coloptions'] = get_string('reportmustselectstate', 'ddtaquiz');
        }

        return $errors;
    }
}
