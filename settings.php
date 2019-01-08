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
 * Administration settings definitions for the ddtaquiz module.
 *
 * @package   mod_ddtaquiz
 * @copyright 2010 Petr Skoda
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
//
//
//defined('MOODLE_INTERNAL') || die();
//
//require_once($CFG->dirroot . '/mod/ddtaquiz/lib.php');
//
//$ddtaquizsettings = new admin_settingpage('modsettingquiz', $pagetitle, 'moodle/site:config');
//
//if ($ADMIN->fulltree) {
//    // Introductory explanation that all the settings are defaults for the add ddtaquiz form.
//    $ddtaquizsettings->add(new admin_setting_heading('ddtaquizintro', '', get_string('configintro', 'ddtaquiz')));
//
//    // Time limit.
//    $ddtaquizsettings->add(new admin_setting_configduration_with_advanced('ddtaquiz/timelimit',
//            get_string('timelimit', 'ddtaquiz'), get_string('configtimelimitsec', 'ddtaquiz'),
//            array('value' => '0', 'adv' => false), 60));
//    // Grace period time.
//    $ddtaquizsettings->add(new admin_setting_configduration_with_advanced('ddtaquiz/graceperiod',
//            get_string('graceperiod', 'ddtaquiz'), get_string('graceperiod_desc', 'ddtaquiz'),
//            array('value' => '86400', 'adv' => false)));
//
//    // Minimum grace period used behind the scenes.
//    $ddtaquizsettings->add(new admin_setting_configduration('ddtaquiz/graceperiodmin',
//            get_string('graceperiodmin', 'ddtaquiz'), get_string('graceperiodmin_desc', 'ddtaquiz'),
//            60, 1));
//
//
//    // Maximum grade.
//    $ddtaquizsettings->add(new admin_setting_configtext('ddtaquiz/maximumgrade',
//            get_string('maximumgrade'), get_string('configmaximumgrade', 'ddtaquiz'), 10, PARAM_INT));
//
//
//    // Shuffle within questions.
//    $ddtaquizsettings->add(new admin_setting_configcheckbox_with_advanced('ddtaquiz/shuffleanswers',
//            get_string('shufflewithin', 'ddtaquiz'), get_string('configshufflewithin', 'ddtaquiz'),
//            array('value' => 1, 'adv' => false)));
//
//    // Preferred behaviour.
//    $ddtaquizsettings->add(new admin_setting_question_behaviour('ddtaquiz/preferredbehaviour',
//            get_string('howquestionsbehave', 'question'), get_string('howquestionsbehave_desc', 'ddtaquiz'),
//            'deferredfeedback'));
//
//    // Can redo completed questions.
//    $ddtaquizsettings->add(new admin_setting_configselect_with_advanced('ddtaquiz/canredoquestions',
//            get_string('canredoquestions', 'ddtaquiz'), get_string('canredoquestions_desc', 'ddtaquiz'),
//            array('value' => 0, 'adv' => true),
//            array(0 => get_string('no'), 1 => get_string('canredoquestionsyes', 'ddtaquiz'))));
//
//    // Each attempt builds on last.
//    $ddtaquizsettings->add(new admin_setting_configcheckbox_with_advanced('ddtaquiz/attemptonlast',
//            get_string('eachattemptbuildsonthelast', 'ddtaquiz'),
//            get_string('configeachattemptbuildsonthelast', 'ddtaquiz'),
//            array('value' => 0, 'adv' => true)));
//
//
//    // Show blocks during ddtaquiz attempts.
//    $ddtaquizsettings->add(new admin_setting_configcheckbox_with_advanced('ddtaquiz/showblocks',
//            get_string('showblocks', 'ddtaquiz'), get_string('configshowblocks', 'ddtaquiz'),
//            array('value' => 0, 'adv' => true)));
//
//    // Password.
//    $ddtaquizsettings->add(new admin_setting_configtext_with_advanced('ddtaquiz/password',
//            get_string('requirepassword', 'ddtaquiz'), get_string('configrequirepassword', 'ddtaquiz'),
//            array('value' => '', 'adv' => false), PARAM_TEXT));
//
//    // IP restrictions.
//    $ddtaquizsettings->add(new admin_setting_configtext_with_advanced('ddtaquiz/subnet',
//            get_string('requiresubnet', 'ddtaquiz'), get_string('configrequiresubnet', 'ddtaquiz'),
//            array('value' => '', 'adv' => true), PARAM_TEXT));
//
//    // Enforced delay between attempts.
//    $ddtaquizsettings->add(new admin_setting_configduration_with_advanced('ddtaquiz/delay1',
//            get_string('delay1st2nd', 'ddtaquiz'), get_string('configdelay1st2nd', 'ddtaquiz'),
//            array('value' => 0, 'adv' => true), 60));
//    $ddtaquizsettings->add(new admin_setting_configduration_with_advanced('ddtaquiz/delay2',
//            get_string('delaylater', 'ddtaquiz'), get_string('configdelaylater', 'ddtaquiz'),
//            array('value' => 0, 'adv' => true), 60));
//
//    $ddtaquizsettings->add(new admin_setting_configtext('ddtaquiz/initialnumfeedbacks',
//            get_string('initialnumfeedbacks', 'ddtaquiz'), get_string('initialnumfeedbacks_desc', 'ddtaquiz'),
//            2, PARAM_INT, 5));
//
//    // Allow user to specify if setting outcomes is an advanced setting.
//    if (!empty($CFG->enableoutcomes)) {
//        $ddtaquizsettings->add(new admin_setting_configcheckbox('ddtaquiz/outcomes_adv',
//            get_string('outcomesadvanced', 'ddtaquiz'), get_string('configoutcomesadvanced', 'ddtaquiz'),
//            '0'));
//    }
//
//    // Autosave frequency.
//    $ddtaquizsettings->add(new admin_setting_configduration('ddtaquiz/autosaveperiod',
//            get_string('autosaveperiod', 'ddtaquiz'), get_string('autosaveperiod_desc', 'ddtaquiz'), 60, 1));
//}
//
//
////$settings = null; // We do not want standard settings link.
///
defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configcheckbox('ddtaquiz/directFeedback',
        get_string('directFeedback', 'ddtaquiz'), get_string('directFeedbackDesc', 'ddtaquiz'), 1));
}