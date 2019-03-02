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
 * This script deals with starting a new attempt at a quiz.
 *
 * Normally, it will end up redirecting to attempt.php - unless a password form is displayed.
 *
 * @package   mod_ddtaquiz
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot.'/mod/ddtaquiz/locallib.php');

// Get submitted parameters.
$cmid = required_param('cmid', PARAM_INT);

if (!$cm = get_coursemodule_from_id('ddtaquiz', $cmid)) {
    print_error('invalidcoursemodule');
}
if (!$course = $DB->get_record('course', array('id' => $cm->course))) {
    print_error("coursemisconf");
}

// Check login and sesskey.
require_login($course, false, $cm);
require_sesskey();

$context = context_module::instance($cmid);
$canpreview = has_capability('mod/ddtaquiz:preview', $context);

$ddtaquiz  = ddtaquiz::load($cm->instance);

if (attempt::may_start_new_attempt($ddtaquiz, $USER->id)) {
    $attempt = attempt::create($ddtaquiz, $USER->id, $canpreview);

    // Redirect to the attempt page.
    redirect($attempt->attempt_url());
} else {

    /** @var array $attempts */
    $attempts = attempt::get_user_attempts($ddtaquiz->get_id(), $USER->id);
    if (count($attempts) > 0) {
        redirect($attempts[count($attempts) - 1]->attempt_url());
    } else {
        redirect(new moodle_url('/mod/ddtaquiz/view.php', array('id' => $ddtaquiz->get_id())));
    }
}

