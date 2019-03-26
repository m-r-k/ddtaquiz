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
 * This file defines the setting form for the quiz combined report.
 *
 * @package    mod_ddtaquiz
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_ddtaquiz\report;

defined('MOODLE_INTERNAL') || die();


/**
 * Quiz combined report settings form.
 *
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class combined_form extends attempts_form {

    /**
     * In this area it's possible to add elements to select attempts which should be displayed
     * @param \MoodleQuickForm $mform
     */
    protected function other_attempt_fields(\MoodleQuickForm $mform) {
    }

    /**
     * In this area add elements to set up additional display options.
     * @param \MoodleQuickForm $mform
     * @throws \coding_exception
     */
    protected function other_preference_fields(\MoodleQuickForm $mform) {
        $mform->addElement('selectyesno', 'displayCorrectAnswers',
            get_string('displaycorrectanswers', 'ddtaquiz'));

        $mform->addElement('selectyesno', 'displayResponses',
            get_string('displayResponses', 'ddtaquiz'));

        $mform->addElement('selectyesno', 'displayAchievedPoints',
            get_string('displayachievedpoints', 'ddtaquiz'));

        $mform->addElement('selectyesno', 'displayQuestionName',
            get_string('displayquestionname', 'ddtaquiz'));
    }

    protected function extra_options(\MoodleQuickForm $mform)
    {
        $mform->addElement('header', 'regradeHeader','Regrade');

        $mform->addElement('submit', 'regrade','Regrade All',['value'=>1]);
    }
}
