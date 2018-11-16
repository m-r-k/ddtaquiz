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
 * This file defines the setting form for the ddtaquiz responses report.
 *
 * @package   ddtaquiz_responses
 * @copyright 2008 Jean-Michel Vedrine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/ddtaquiz/report/attemptsreport_form.php');


/**
 * Ddtaquiz responses report settings form.
 *
 * @copyright 2008 Jean-Michel Vedrine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ddtaquiz_responses_settings_form extends mod_ddtaquiz_attempts_report_form {

    protected function other_preference_fields(MoodleQuickForm $mform) {
        $mform->addGroup(array(
            $mform->createElement('advcheckbox', 'qtext', '',
                get_string('questiontext', 'ddtaquiz_responses')),
            $mform->createElement('advcheckbox', 'resp', '',
                get_string('response', 'ddtaquiz_responses')),
            $mform->createElement('advcheckbox', 'right', '',
                get_string('rightanswer', 'ddtaquiz_responses')),
        ), 'coloptions', get_string('showthe', 'ddtaquiz_responses'), array(' '), false);
        $mform->disabledIf('qtext', 'attempts', 'eq', ddtaquiz_attempts_report::ENROLLED_WITHOUT);
        $mform->disabledIf('resp',  'attempts', 'eq', ddtaquiz_attempts_report::ENROLLED_WITHOUT);
        $mform->disabledIf('right', 'attempts', 'eq', ddtaquiz_attempts_report::ENROLLED_WITHOUT);
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if ($data['attempts'] != ddtaquiz_attempts_report::ENROLLED_WITHOUT && !(
                $data['qtext'] || $data['resp'] || $data['right'])) {
            $errors['coloptions'] = get_string('reportmustselectstate', 'ddtaquiz');
        }

        return $errors;
    }

    protected function other_attempt_fields(MoodleQuickForm $mform) {
        parent::other_attempt_fields($mform);
        if (ddtaquiz_allows_multiple_tries($this->_customdata['ddtaquiz'])) {
            $mform->addElement('select', 'whichtries', get_string('whichtries', 'question'), array(
                                           question_attempt::FIRST_TRY    => get_string('firsttry', 'question'),
                                           question_attempt::LAST_TRY     => get_string('lasttry', 'question'),
                                           question_attempt::ALL_TRIES    => get_string('alltries', 'question'))
            );
            $mform->setDefault('whichtries', question_attempt::LAST_TRY);
            $mform->disabledIf('whichtries', 'attempts', 'eq', ddtaquiz_attempts_report::ENROLLED_WITHOUT);
        }
    }
}
