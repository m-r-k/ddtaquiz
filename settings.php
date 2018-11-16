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


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/ddtaquiz/lib.php');

// First get a list of ddtaquiz reports with there own settings pages. If there none,
// we use a simpler overall menu structure.
$reports = core_component::get_plugin_list_with_file('ddtaquiz', 'settings.php', false);
$reportsbyname = array();
foreach ($reports as $report => $reportdir) {
    $strreportname = get_string($report . 'report', 'ddtaquiz_'.$report);
    $reportsbyname[$strreportname] = $report;
}
core_collator::ksort($reportsbyname);

// First get a list of ddtaquiz reports with there own settings pages. If there none,
// we use a simpler overall menu structure.
$rules = core_component::get_plugin_list_with_file('ddtaquizaccess', 'settings.php', false);
$rulesbyname = array();
foreach ($rules as $rule => $ruledir) {
    $strrulename = get_string('pluginname', 'ddtaquizaccess_' . $rule);
    $rulesbyname[$strrulename] = $rule;
}
core_collator::ksort($rulesbyname);

// Create the ddtaquiz settings page.
if (empty($reportsbyname) && empty($rulesbyname)) {
    $pagetitle = get_string('modulename', 'ddtaquiz');
} else {
    $pagetitle = get_string('generalsettings', 'admin');
}
$ddtaquizsettings = new admin_settingpage('modsettingddtaquiz', $pagetitle, 'moodle/site:config');

if ($ADMIN->fulltree) {
    // Introductory explanation that all the settings are defaults for the add ddtaquiz form.
    $ddtaquizsettings->add(new admin_setting_heading('ddtaquizintro', '', get_string('configintro', 'ddtaquiz')));

    // Time limit.
    $ddtaquizsettings->add(new admin_setting_configduration_with_advanced('ddtaquiz/timelimit',
            get_string('timelimit', 'ddtaquiz'), get_string('configtimelimitsec', 'ddtaquiz'),
            array('value' => '0', 'adv' => false), 60));

    // What to do with overdue attempts.
    $ddtaquizsettings->add(new mod_ddtaquiz_admin_setting_overduehandling('ddtaquiz/overduehandling',
            get_string('overduehandling', 'ddtaquiz'), get_string('overduehandling_desc', 'ddtaquiz'),
            array('value' => 'autosubmit', 'adv' => false), null));

    // Grace period time.
    $ddtaquizsettings->add(new admin_setting_configduration_with_advanced('ddtaquiz/graceperiod',
            get_string('graceperiod', 'ddtaquiz'), get_string('graceperiod_desc', 'ddtaquiz'),
            array('value' => '86400', 'adv' => false)));

    // Minimum grace period used behind the scenes.
    $ddtaquizsettings->add(new admin_setting_configduration('ddtaquiz/graceperiodmin',
            get_string('graceperiodmin', 'ddtaquiz'), get_string('graceperiodmin_desc', 'ddtaquiz'),
            60, 1));

    // Number of attempts.
    $options = array(get_string('unlimited'));
    for ($i = 1; $i <= DDTAQUIZ_MAX_ATTEMPT_OPTION; $i++) {
        $options[$i] = $i;
    }
    $ddtaquizsettings->add(new admin_setting_configselect_with_advanced('ddtaquiz/attempts',
            get_string('attemptsallowed', 'ddtaquiz'), get_string('configattemptsallowed', 'ddtaquiz'),
            array('value' => 0, 'adv' => false), $options));

    // Grading method.
    $ddtaquizsettings->add(new mod_ddtaquiz_admin_setting_grademethod('ddtaquiz/grademethod',
            get_string('grademethod', 'ddtaquiz'), get_string('configgrademethod', 'ddtaquiz'),
            array('value' => DDTAQUIZ_GRADEHIGHEST, 'adv' => false), null));

    // Maximum grade.
    $ddtaquizsettings->add(new admin_setting_configtext('ddtaquiz/maximumgrade',
            get_string('maximumgrade'), get_string('configmaximumgrade', 'ddtaquiz'), 10, PARAM_INT));

    // Questions per page.
    $perpage = array();
    $perpage[0] = get_string('never');
    $perpage[1] = get_string('aftereachquestion', 'ddtaquiz');
    for ($i = 2; $i <= DDTAQUIZ_MAX_QPP_OPTION; ++$i) {
        $perpage[$i] = get_string('afternquestions', 'ddtaquiz', $i);
    }
    $ddtaquizsettings->add(new admin_setting_configselect_with_advanced('ddtaquiz/questionsperpage',
            get_string('newpageevery', 'ddtaquiz'), get_string('confignewpageevery', 'ddtaquiz'),
            array('value' => 1, 'adv' => false), $perpage));

    // Navigation method.
    $ddtaquizsettings->add(new admin_setting_configselect_with_advanced('ddtaquiz/navmethod',
            get_string('navmethod', 'ddtaquiz'), get_string('confignavmethod', 'ddtaquiz'),
            array('value' => DDTAQUIZ_NAVMETHOD_FREE, 'adv' => true), ddtaquiz_get_navigation_options()));

    // Shuffle within questions.
    $ddtaquizsettings->add(new admin_setting_configcheckbox_with_advanced('ddtaquiz/shuffleanswers',
            get_string('shufflewithin', 'ddtaquiz'), get_string('configshufflewithin', 'ddtaquiz'),
            array('value' => 1, 'adv' => false)));

    // Preferred behaviour.
    $ddtaquizsettings->add(new admin_setting_question_behaviour('ddtaquiz/preferredbehaviour',
            get_string('howquestionsbehave', 'question'), get_string('howquestionsbehave_desc', 'ddtaquiz'),
            'deferredfeedback'));

    // Can redo completed questions.
    $ddtaquizsettings->add(new admin_setting_configselect_with_advanced('ddtaquiz/canredoquestions',
            get_string('canredoquestions', 'ddtaquiz'), get_string('canredoquestions_desc', 'ddtaquiz'),
            array('value' => 0, 'adv' => true),
            array(0 => get_string('no'), 1 => get_string('canredoquestionsyes', 'ddtaquiz'))));

    // Each attempt builds on last.
    $ddtaquizsettings->add(new admin_setting_configcheckbox_with_advanced('ddtaquiz/attemptonlast',
            get_string('eachattemptbuildsonthelast', 'ddtaquiz'),
            get_string('configeachattemptbuildsonthelast', 'ddtaquiz'),
            array('value' => 0, 'adv' => true)));

    // Review options.
    $ddtaquizsettings->add(new admin_setting_heading('reviewheading',
            get_string('reviewoptionsheading', 'ddtaquiz'), ''));
    foreach (mod_ddtaquiz_admin_review_setting::fields() as $field => $name) {
        $default = mod_ddtaquiz_admin_review_setting::all_on();
        $forceduring = null;
        if ($field == 'attempt') {
            $forceduring = true;
        } else if ($field == 'overallfeedback') {
            $default = $default ^ mod_ddtaquiz_admin_review_setting::DURING;
            $forceduring = false;
        }
        $ddtaquizsettings->add(new mod_ddtaquiz_admin_review_setting('ddtaquiz/review' . $field,
                $name, '', $default, $forceduring));
    }

    // Show the user's picture.
    $ddtaquizsettings->add(new mod_ddtaquiz_admin_setting_user_image('ddtaquiz/showuserpicture',
            get_string('showuserpicture', 'ddtaquiz'), get_string('configshowuserpicture', 'ddtaquiz'),
            array('value' => 0, 'adv' => false), null));

    // Decimal places for overall grades.
    $options = array();
    for ($i = 0; $i <= DDTAQUIZ_MAX_DECIMAL_OPTION; $i++) {
        $options[$i] = $i;
    }
    $ddtaquizsettings->add(new admin_setting_configselect_with_advanced('ddtaquiz/decimalpoints',
            get_string('decimalplaces', 'ddtaquiz'), get_string('configdecimalplaces', 'ddtaquiz'),
            array('value' => 2, 'adv' => false), $options));

    // Decimal places for question grades.
    $options = array(-1 => get_string('sameasoverall', 'ddtaquiz'));
    for ($i = 0; $i <= DDTAQUIZ_MAX_Q_DECIMAL_OPTION; $i++) {
        $options[$i] = $i;
    }
    $ddtaquizsettings->add(new admin_setting_configselect_with_advanced('ddtaquiz/questiondecimalpoints',
            get_string('decimalplacesquestion', 'ddtaquiz'),
            get_string('configdecimalplacesquestion', 'ddtaquiz'),
            array('value' => -1, 'adv' => true), $options));

    // Show blocks during ddtaquiz attempts.
    $ddtaquizsettings->add(new admin_setting_configcheckbox_with_advanced('ddtaquiz/showblocks',
            get_string('showblocks', 'ddtaquiz'), get_string('configshowblocks', 'ddtaquiz'),
            array('value' => 0, 'adv' => true)));

    // Password.
    $ddtaquizsettings->add(new admin_setting_configtext_with_advanced('ddtaquiz/password',
            get_string('requirepassword', 'ddtaquiz'), get_string('configrequirepassword', 'ddtaquiz'),
            array('value' => '', 'adv' => false), PARAM_TEXT));

    // IP restrictions.
    $ddtaquizsettings->add(new admin_setting_configtext_with_advanced('ddtaquiz/subnet',
            get_string('requiresubnet', 'ddtaquiz'), get_string('configrequiresubnet', 'ddtaquiz'),
            array('value' => '', 'adv' => true), PARAM_TEXT));

    // Enforced delay between attempts.
    $ddtaquizsettings->add(new admin_setting_configduration_with_advanced('ddtaquiz/delay1',
            get_string('delay1st2nd', 'ddtaquiz'), get_string('configdelay1st2nd', 'ddtaquiz'),
            array('value' => 0, 'adv' => true), 60));
    $ddtaquizsettings->add(new admin_setting_configduration_with_advanced('ddtaquiz/delay2',
            get_string('delaylater', 'ddtaquiz'), get_string('configdelaylater', 'ddtaquiz'),
            array('value' => 0, 'adv' => true), 60));

    // Browser security.
    $ddtaquizsettings->add(new mod_ddtaquiz_admin_setting_browsersecurity('ddtaquiz/browsersecurity',
            get_string('showinsecurepopup', 'ddtaquiz'), get_string('configpopup', 'ddtaquiz'),
            array('value' => '-', 'adv' => true), null));

    $ddtaquizsettings->add(new admin_setting_configtext('ddtaquiz/initialnumfeedbacks',
            get_string('initialnumfeedbacks', 'ddtaquiz'), get_string('initialnumfeedbacks_desc', 'ddtaquiz'),
            2, PARAM_INT, 5));

    // Allow user to specify if setting outcomes is an advanced setting.
    if (!empty($CFG->enableoutcomes)) {
        $ddtaquizsettings->add(new admin_setting_configcheckbox('ddtaquiz/outcomes_adv',
            get_string('outcomesadvanced', 'ddtaquiz'), get_string('configoutcomesadvanced', 'ddtaquiz'),
            '0'));
    }

    // Autosave frequency.
    $ddtaquizsettings->add(new admin_setting_configduration('ddtaquiz/autosaveperiod',
            get_string('autosaveperiod', 'ddtaquiz'), get_string('autosaveperiod_desc', 'ddtaquiz'), 60, 1));
}

// Now, depending on whether any reports have their own settings page, add
// the ddtaquiz setting page to the appropriate place in the tree.
if (empty($reportsbyname) && empty($rulesbyname)) {
    $ADMIN->add('modsettings', $ddtaquizsettings);
} else {
    $ADMIN->add('modsettings', new admin_category('modsettingsddtaquizcat',
            get_string('modulename', 'ddtaquiz'), $module->is_enabled() === false));
    $ADMIN->add('modsettingsddtaquizcat', $ddtaquizsettings);

    // Add settings pages for the ddtaquiz report subplugins.
    foreach ($reportsbyname as $strreportname => $report) {
        $reportname = $report;

        $settings = new admin_settingpage('modsettingsddtaquizcat'.$reportname,
                $strreportname, 'moodle/site:config', $module->is_enabled() === false);
        if ($ADMIN->fulltree) {
            include($CFG->dirroot . "/mod/ddtaquiz/report/$reportname/settings.php");
        }
        if (!empty($settings)) {
            $ADMIN->add('modsettingsddtaquizcat', $settings);
        }
    }

    // Add settings pages for the ddtaquiz access rule subplugins.
    foreach ($rulesbyname as $strrulename => $rule) {
        $settings = new admin_settingpage('modsettingsddtaquizcat' . $rule,
                $strrulename, 'moodle/site:config', $module->is_enabled() === false);
        if ($ADMIN->fulltree) {
            include($CFG->dirroot . "/mod/ddtaquiz/accessrule/$rule/settings.php");
        }
        if (!empty($settings)) {
            $ADMIN->add('modsettingsddtaquizcat', $settings);
        }
    }
}

$settings = null; // We do not want standard settings link.
