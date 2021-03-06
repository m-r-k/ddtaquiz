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
 * The main ddtaquiz configuration form.
 *
 * It uses the standard core Moodle formslib. For more info about them, please
 * visit: http://docs.moodle.org/en/Development:lib/formslib.php
 *
 * @package    mod_ddtaquiz
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot.'/mod/ddtaquiz/locallib.php');

/**
 * Module instance settings form.
 *
 * @package    mod_ddtaquiz
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_ddtaquiz_mod_form extends moodleform_mod {

    /**
     * Defines forms elements.
     */
    public function definition() {
        global $CFG;
        //global quiz config
        $quizconfig = get_config('ddtaquiz');
        $mform = $this->_form;

        // Adding the "general" fieldset, where all the common settings are showed.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Adding the standard "name" field.
        $mform->addElement('text', 'name', get_string('ddtaquizname', 'ddtaquiz'), array('size' => '64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('name', 'ddtaquizname', 'ddtaquiz');

        // Adding the standard "intro" and "introformat" fields.
        if ($CFG->branch >= 29) {
            $this->standard_intro_elements();
        } else {
            $this->add_intro_editor();
        }

        // Add standard grading elements.
        $this->standard_grading_coursemodule_elements();

        $gradingoptions = array(
            0 => get_string('grademethod_oneattempt', 'ddtaquiz'),
            1 => get_string('grademethod_bestattempt', 'ddtaquiz'),
            2 => get_string('grademethod_lastattempt', 'ddtaquiz')
        );
        $mform->addElement('select', 'grademethod', get_string('grademethod', 'ddtaquiz'), $gradingoptions);
        $mform->setDefault('grademethod', 0);

        //Direct Feedback
        $mform->addElement('header', 'directfeedbackheader',get_string('directfeedbackheader', 'ddtaquiz') );
        $feedbackDisplayoptions = array(
            0 => "Don't show",
            1 => "Show",
        );
        $mform->addElement('select', 'directfeedback' , get_string('directfeedback', 'ddtaquiz'),$feedbackDisplayoptions);
        $mform->setDefault('directfeedback', $quizconfig->directfeedback);
        $mform->addHelpButton('directfeedback', 'directfeedback', 'ddtaquiz');

        //Quiz Modes
        $mform->addElement('header', 'quizModeHeader',get_string('quizmodeheader', 'ddtaquiz') );
        $quizModeDisplayoptions = array(
            0 => "DDTA Mode",
            1 => "BinDif Mode",
            2=> "Point Mode",
        );
        $mform->addElement('select', 'quizmodes' , get_string('quizmodes', 'ddtaquiz'),$quizModeDisplayoptions);
        $mform->setDefault('quizmodes', $quizconfig->quizmodes);
        $mform->addHelpButton('quizmodes', 'quizmodes', 'ddtaquiz');
        $mform->addElement('text', 'minpointsforbindif', get_string('minpointsforbindifname', 'ddtaquiz'));
        $mform->setType('minpointsforbindif', PARAM_INT);
        $mform->setDefault('minpointsforbindif', $quizconfig->minpointsforbindif);

        //Timing
        $mform->addElement('header', 'timing', get_string('timing', 'ddtaquiz'), 'ddtaquiz');

        // Time limit.
        $mform->addElement('duration', 'timelimit', get_string('timelimit', 'ddtaquiz'),
            array('optional' => true));
        $mform->addHelpButton('timelimit', 'timelimit', 'ddtaquiz');
        $mform->setAdvanced('timelimit', $quizconfig->timelimit_adv);
        $mform->setDefault('timelimit', $quizconfig->timelimit);

        // What to do with overdue attempts.
        $mform->addElement('select', 'overduehandling',
            get_string('overduehandling', 'ddtaquiz'),
            ddtaquiz_get_overdue_handling_options());
        $mform->addHelpButton('overduehandling', 'overduehandling', 'ddtaquiz');
        $mform->setAdvanced('overduehandling', $quizconfig->overduehandling_adv);
        $mform->setDefault('overduehandling', $quizconfig->overduehandling);
        // Grace period time.
        $mform->addElement('duration', 'graceperiod',get_string('graceperiod', 'ddtaquiz'),
            array('optional' => true));
        $mform->addHelpButton('graceperiod', 'graceperiod', 'ddtaquiz');
        $mform->setAdvanced('graceperiod', $quizconfig->graceperiod_adv);
        $mform->setDefault('graceperiod', $quizconfig->graceperiod);
        $mform->disabledIf('graceperiod', 'overduehandling', 'neq', 'graceperiod');

        //Domains
        $mform->addElement('header', 'domainHeader',get_string('domainHeader', 'ddtaquiz'));
        $mform->addElement('text', 'domains', get_string('domainHelp', 'ddtaquiz'));
        $mform->setType('domains', PARAM_TEXT);
        $mform->setDefault('domains', $quizconfig->domains);

        // Add standard elements, common to all modules.
        $this->standard_coursemodule_elements();

        // Add standard buttons, common to all modules.
        $this->add_action_buttons();


    }
}
