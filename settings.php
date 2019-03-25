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


defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    //Direct specific feedback
    $settings->add(new admin_setting_configselect('ddtaquiz/directfeedback',
        get_string('directfeedback', 'ddtaquiz'),
        get_string('directfeedbackdesc', 'ddtaquiz'), 0,array(0=>"Don't show",1=>'Show')));

    // Time limit.
    $settings->add(new admin_setting_configduration_with_advanced('ddtaquiz/timelimit',
        'Time limit',
        'Default time limit for quizzes in seconds. 0 mean no time limit.',
        array('value' => '0', 'adv' => false), 60));

    // What to do with overdue attempts.
    $settings->add(new mod_ddtaquiz_admin_setting_overduehandling('ddtaquiz/overduehandling',
        'When time expires',
        'What should happen by default if a student does not submit the quiz before time expires.',
        array('value' => 'autosubmit', 'adv' => false), null));

    // Grace period time.
    $settings->add(new admin_setting_configduration_with_advanced('ddtaquiz/graceperiod',
        'Submission grace period',
        'If what to do when time expires is set to \'Allow a grace period to submit, but not change any responses\', this is the default amount of extra time that is allowed.',
        array('value' => '86400', 'adv' => false)));

    // Minimum grace period used behind the scenes.
    $settings->add(new admin_setting_configduration('ddtaquiz/graceperiodmin',
        'Last submission grace period',
        'There is a potential problem right at the end of the quiz. On the one hand, we want to let students continue working right up until the last second - with the help of the timer that automatically submits the quiz when time runs out. On the other hand, the server may then be overloaded, and take some time to get to process the responses. Therefore, we will accept responses for up to this long after time expires, so they are not penalised for the server being slow. However, the student could cheat and get this many seconds to answer the quiz. You have to make a trade-off based on how much you trust the performance of your server during quizzes.',
        60, 1));

    // Domains for the domain specific feedback
    $settings->add(new admin_setting_configtext('ddtaquiz/domains',
        'Domains',
        'Enter the domains of your questions (semicolon-separated), to get a domain specific feedback.',
        ""));

    //Quiz Modes
    $settings->add(new admin_setting_configselect('ddtaquiz/quizmodes',
        get_string('quizmodes', 'ddtaquiz'),
        get_string('quizmodedesc', 'ddtaquiz'),
        0,
        array(0=>'DDTA Mode',1=>'BinDif Mode',2=>'Point Mode')));

    $settings->add(new admin_setting_configtext('ddtaquiz/minpointsforbindif',
        get_string('minpointsforbindifname', 'ddtaquiz'),
        get_string('minpointsforbindif', 'ddtaquiz'),
        100,
        PARAM_INT));



}