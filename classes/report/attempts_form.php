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
 * Base class for the settings form for reports.
 *
 *
 * @package    mod_ddtaquiz
 * @copyright  2017 Luca Gladiator <lucamarius.gladiator@stud.tu-darmstadt.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_ddtaquiz\report;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');


/**
 * Base class for the settings form for reports.
 *
 * @copyright 2012 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class attempts_form extends \moodleform {

    /**
     * TODO
     * @throws \coding_exception
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'preferencespage',
            get_string('reportwhattoinclude', 'ddtaquiz'));

        $this->standard_attempt_fields($mform);
        $this->other_attempt_fields($mform);

        $mform->addElement('header', 'preferencesuser',
            get_string('reportdisplayoptions', 'ddtaquiz'));

        $this->standard_preference_fields($mform);
        $this->other_preference_fields($mform);

        $mform->addElement('submit', 'submitbutton',
            get_string('showreport', 'ddtaquiz'));
    }

    /**
     * TODO (aus mod/quiz)
     * @param \MoodleQuickForm $mform
     * @throws \coding_exception
     */
    protected function standard_attempt_fields(\MoodleQuickForm $mform) {

        $mform->addElement('select', 'attempts', get_string('reportattemptsfrom', 'ddtaquiz'), array(
            attempts::ENROLLED_WITH     => get_string('reportuserswith', 'ddtaquiz'),
            attempts::ENROLLED_WITHOUT  => get_string('reportuserswithout', 'ddtaquiz'),
            attempts::ENROLLED_ALL      => get_string('reportuserswithorwithout', 'ddtaquiz'),
            attempts::ALL_WITH          => get_string('reportusersall', 'ddtaquiz'),
        ));

        $stategroup = array(
            $mform->createElement('advcheckbox', 'stateinprogress', '',
                get_string('stateinprogress', 'ddtaquiz')),
            $mform->createElement('advcheckbox', 'statefinished', '',
                get_string('statefinished', 'ddtaquiz')),
        );
        $mform->addGroup($stategroup, 'stateoptions',
            get_string('reportattemptsthatare', 'ddtaquiz'), array(' '), false);
        $mform->setDefault('stateinprogress', 1);
        $mform->setDefault('statefinished',   1);
        $mform->disabledIf('stateinprogress', 'attempts', 'eq', attempts::ENROLLED_WITHOUT);
        $mform->disabledIf('statefinished',   'attempts', 'eq', attempts::ENROLLED_WITHOUT);
    }

    /**
     * TODO (aus mod/quiz)
     * @param \MoodleQuickForm $mform
     */
    protected function other_attempt_fields(\MoodleQuickForm $mform) {
    }

    /**
     * TODO (aus mod/quiz)
     * @param \MoodleQuickForm $mform
     * @throws \coding_exception
     */
    protected function standard_preference_fields(\MoodleQuickForm $mform) {
        $mform->addElement('text', 'pagesize', get_string('pagesize', 'ddtaquiz'));
        $mform->setType('pagesize', PARAM_INT);
    }

    /**
     * TODO (aus mod/quiz)
     * @param \MoodleQuickForm $mform
     */
    protected function other_preference_fields(\MoodleQuickForm $mform) {
    }

    /**
     * TODO (aus mod/quiz)
     * @param array $data
     * @param array $files
     * @return array
     * @throws \coding_exception
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if ($data['attempts'] != attempts::ENROLLED_WITHOUT && !(
            $data['stateinprogress'] || $data['stateoverdue'] || $data['statefinished'] || $data['stateabandoned'])) {
                $errors['stateoptions'] = get_string('reportmustselectstate', 'ddtaquiz');
        }

            return $errors;
    }
}