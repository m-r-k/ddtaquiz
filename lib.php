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
 * Library of interface functions and constants for module ddtaquiz.
 *
 * All the core Moodle functions, neeeded to allow the module to work
 * integrated in Moodle should be placed here.
 *
 * All the ddtaquiz specific functions, needed to implement all the module
 * logic, should go to locallib.php. This will help to save some memory when
 * Moodle is performing actions across all modules.
 *
 * @package    mod_ddtaquiz
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/* Moodle core API */

/**
 * Returns the information on whether the module supports a feature.
 *
 * See {@link plugin_supports()} for more info.
 *
 * @param string $feature FEATURE_xx constant for requested feature.
 * @return mixed true if the feature is supported, null if unknown.
 */
function ddtaquiz_supports($feature) {

    switch($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_USES_QUESTIONS:
            return true;
        default:
            return null;
    }
}

/**
 * Saves a new instance of the ddtaquiz into the database.
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param stdClass $ddtaquiz Submitted data from the form in mod_form.php.
 * @return int The id of the newly inserted ddtaquiz record.
 * @throws coding_exception
 * @throws dml_exception
 */
function ddtaquiz_add_instance(stdClass $ddtaquiz) {
    global $DB;

    $ddtaquiz->timecreated = time();

    $mainblock = new stdClass();
    $mainblock->name = $ddtaquiz->name;
    $mainblockid = $DB->insert_record('ddtaquiz_block', $mainblock);

    $ddtaquiz->mainblock = $mainblockid;
    $ddtaquiz->id = $DB->insert_record('ddtaquiz', $ddtaquiz);

    ddtaquiz_grade_item_update($ddtaquiz);

    return $ddtaquiz->id;
}

/**
 * Updates an instance of the ddtaquiz in the database.
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param stdClass $ddtaquiz An object from the form in mod_form.php.
 * @return boolean Success/Fail.
 * @throws coding_exception
 * @throws dml_exception
 */
function ddtaquiz_update_instance(stdClass $ddtaquiz) {
    global $DB;

    $ddtaquiz->timemodified = time();
    $ddtaquiz->id = $ddtaquiz->instance;

    // You may have to add extra stuff in here.

    $result = $DB->update_record('ddtaquiz', $ddtaquiz);

    ddtaquiz_grade_item_update($ddtaquiz);

    return $result;
}

/**
 * This standard function will check all instances of this module.
 * and make sure there are up-to-date events created for each of them.
 * If courseid = 0, then every ddtaquiz event in the site is checked, else
 * only ddtaquiz events belonging to the course specified are checked.
 * This is only required if the module is generating calendar events.
 *
 * @param int $courseid the Course ID.
 * @return bool
 * @throws dml_exception
 */
function ddtaquiz_refresh_events($courseid = 0) {
    global $DB;

    if ($courseid == 0) {
        if (!$ddtaquizs = $DB->get_records('ddtaquiz')) {
            return true;
        }
    } else {
        if (!$ddtaquizs = $DB->get_records('ddtaquiz', array('course' => $courseid))) {
            return true;
        }
    }

    return true;
}

/**
 * Removes an instance of the ddtaquiz from the database.
 *
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance.
 * @return boolean Success/Failure.
 * @throws dml_exception
 */
function ddtaquiz_delete_instance($id) {
    global $DB;

    if (! $ddtaquiz = $DB->get_record('ddtaquiz', array('id' => $id))) {
        return false;
    }

    // Delete any dependent records here.

    $DB->delete_records('ddtaquiz', array('id' => $ddtaquiz->id));

    ddtaquiz_grade_item_delete($ddtaquiz);

    return true;
}

/**
 * Returns a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 *
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @return stdClass|null information about what a user has done with a given particular instance of this module.
 */
function ddtaquiz_user_outline() {

    $return = new stdClass();
    $return->time = 0;
    $return->info = '';
    return $return;
}

/**
 * Prints a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * It is supposed to echo directly without returning a value.
 *
 * @param stdClass $course the current course record.
 * @param stdClass $user the record of the user we are generating report for.
 * @param cm_info $mod course module info.
 * @param stdClass $ddtaquiz the module instance record.
 */
function ddtaquiz_user_complete($course, $user, $mod, $ddtaquiz) {
}

/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in ddtaquiz activities and print it out.
 *
 * @return boolean True if anything was printed, otherwise false.
 */
function ddtaquiz_print_recent_activity() {
    return false;
}

/**
 * Prepares the recent activity data.
 *
 * This callback function is supposed to populate the passed array with
 * custom activity records. These records are then rendered into HTML via
 * {@link ddtaquiz_print_recent_mod_activity()}.
 *
 * Returns void, it adds items into $activities and increases $index.
 *
 * @param array $activities sequentially indexed array of objects with added 'cmid' property.
 * @param int $index the index in the $activities to use for the next record.
 * @param int $timestart append activity since this time.
 * @param int $courseid the id of the course we produce the report for.
 * @param int $cmid course module id.
 * @param int $userid check for a particular user's activity only, defaults to 0 (all users).
 * @param int $groupid check for a particular group's activity only, defaults to 0 (all groups).
 */
function ddtaquiz_get_recent_mod_activity(&$activities, &$index, $timestart, $courseid, $cmid, $userid=0, $groupid=0) {
}

/**
 * Prints single activity item prepared by {@link ddtaquiz_get_recent_mod_activity()}.
 *
 * @param stdClass $activity activity record with added 'cmid' property.
 * @param int $courseid the id of the course we produce the report for.
 * @param bool $detail print detailed report.
 * @param array $modnames as returned by {@link get_module_types_names()}.
 * @param bool $viewfullnames display users' full names.
 */
function ddtaquiz_print_recent_mod_activity($activity, $courseid, $detail, $modnames, $viewfullnames) {
}

/**
 * Function to be run periodically according to the moodle cron.
 *
 * This function searches for things that need to be done, such
 * as sending out mail, toggling flags etc ...
 *
 * Note that this has been deprecated in favour of scheduled task API.
 *
 * @return boolean
 */
function ddtaquiz_cron () {
    return true;
}

/**
 * Returns all other caps used in the module.
 *
 * For example, this could be array('moodle/site:accessallgroups') if the
 * module uses that capability.
 *
 * @return array
 */
function ddtaquiz_get_extra_capabilities() {
    return array();
}

/* Gradebook API */

/**
 * Is a given scale used by the instance of ddtaquiz?
 *
 * This function returns if a scale is being used by one ddtaquiz
 * if it has support for grading and scales.
 *
 * @param int $ddtaquizid ID of an instance of this module.
 * @param int $scaleid ID of the scale.
 * @return bool true if the scale is used by the given ddtaquiz instance.
 * @throws dml_exception
 */
function ddtaquiz_scale_used($ddtaquizid, $scaleid) {
    global $DB;

    if ($scaleid and $DB->record_exists('ddtaquiz', array('id' => $ddtaquizid, 'grade' => -$scaleid))) {
        return true;
    } else {
        return false;
    }
}

/**
 * Checks if scale is being used by any instance of ddtaquiz.
 *
 * This is used to find out if scale used anywhere.
 *
 * @param int $scaleid ID of the scale.
 * @return boolean true if the scale is used by any ddtaquiz instance.
 * @throws dml_exception
 */
function ddtaquiz_scale_used_anywhere($scaleid) {
    global $DB;

    if ($scaleid and $DB->record_exists('ddtaquiz', array('grade' => -$scaleid))) {
        return true;
    } else {
        return false;
    }
}

/**
 * Creates or updates grade item for the given ddtaquiz instance
 *
 * Needed by {@link grade_update_mod_grades()}.
 *
 * @param stdClass $ddtaquiz instance object with extra cmidnumber and modname property.
 * @param mixed $grades Grade (object, array) or several grades (arrays of arrays or objects),
 *  NULL if updating grade_item definition only. If $grades equals 'reset'resets grades in the gradebook.
 * @throws coding_exception
 */
function ddtaquiz_grade_item_update(stdClass $ddtaquiz, $grades=null) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    $item = array();
    $item['itemname'] = clean_param($ddtaquiz->name, PARAM_NOTAGS);
    $item['gradetype'] = GRADE_TYPE_VALUE;

    if ($ddtaquiz->grade > 0) {
        $item['gradetype'] = GRADE_TYPE_VALUE;
        $item['grademax']  = $ddtaquiz->grade;
        $item['grademin']  = 0;
    } else if ($ddtaquiz->grade < 0) {
        $item['gradetype'] = GRADE_TYPE_SCALE;
        $item['scaleid']   = -$ddtaquiz->grade;
    } else {
        $item['gradetype'] = GRADE_TYPE_NONE;
    }

    if ($grades === 'reset') {
        $item['reset'] = true;
    }

    grade_update('mod/ddtaquiz', $ddtaquiz->course, 'mod', 'ddtaquiz',
        $ddtaquiz->id, 0, $grades, $item);
}

/**
 * Delete grade item for given ddtaquiz instance.
 *
 * @param stdClass $ddtaquiz instance object.
 * @return int
 */
function ddtaquiz_grade_item_delete($ddtaquiz) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    return grade_update('mod/ddtaquiz', $ddtaquiz->course, 'mod', 'ddtaquiz',
        $ddtaquiz->id, 0, null, array('deleted' => 1));
}

/**
 * Update ddtaquiz grades in the gradebook.
 *
 * Needed by {@link grade_update_mod_grades()}.
 *
 * @param stdClass $ddtaquiz instance object with extra cmidnumber and modname property.
 * @param int $userid update grade of specific user only, 0 means all participants.
 * @throws dml_exception
 */
function ddtaquiz_update_grades(stdClass $ddtaquiz, $userid = 0) {
    global $CFG, $DB;
    require_once($CFG->libdir.'/gradelib.php');

    // Populate array of grade objects indexed by userid.
    $grades = ddtaquiz_get_user_grades($ddtaquiz, $userid);

    grade_update('mod/ddtaquiz', $ddtaquiz->course, 'mod', 'ddtaquiz', $ddtaquiz->id, 0, $grades);
}

/* File API */

/**
 * Returns the lists of all browsable file areas within the given module context.
 *
 * The file area 'intro' for the activity introduction field is added automatically
 * by {@link file_browser::get_file_info_context_module()}.
 *
 * @return array of [(string)filearea] => (string)description
 */
function ddtaquiz_get_file_areas() {
    return array();
}

/**
 * File browsing support for ddtaquiz file areas
 *
 * @package mod_ddtaquiz
 * @category files
 *
 * @param file_browser $browser
 * @param array $areas
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @param string $filearea
 * @param int $itemid
 * @param string $filepath
 * @param string $filename
 * @return file_info instance or null if not found
 */
function ddtaquiz_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename) {
    return null;
}

/**
 * Serves the files from the ddtaquiz file areas.
 *
 * @package mod_ddtaquiz
 * @category files
 *
 * @param stdClass $course the course object.
 * @param stdClass $cm the course module object.
 * @param stdClass $context the ddtaquiz's context.
 * @throws coding_exception
 * @throws moodle_exception
 * @throws require_login_exception
 */
function ddtaquiz_pluginfile($course, $cm, $context) {
    if ($context->contextlevel != CONTEXT_MODULE) {
        send_file_not_found();
    }

    require_login($course, true, $cm);

    send_file_not_found();
}

/* Navigation API */

/**
 * Extends the settings navigation with the ddtaquiz settings.
 *
 * This function is called when the context for the page is a ddtaquiz module. This is not called by AJAX
 * so it is safe to rely on the $PAGE.
 *
 * @param navigation_node $ddtaquiznode ddtaquiz administration node.
 * @throws coding_exception
 * @throws moodle_exception
 */
function ddtaquiz_extend_settings_navigation($settings, navigation_node $ddtaquiznode=null) {
    global $PAGE, $CFG;

    require_once($CFG->dirroot . '/question/editlib.php');

    // We want to add these new nodes after the Edit settings node, and before the
    // Locally assigned roles node. Of course, both of those are controlled by capabilities.
    $keys = $ddtaquiznode->get_children_key_list();
    $beforekey = null;
    $i = array_search('modedit', $keys);
    if ($i === false and array_key_exists(0, $keys)) {
        $beforekey = $keys[0];
    } else if (array_key_exists($i + 1, $keys)) {
        $beforekey = $keys[$i + 1];
    }

    // Edit Quiz button.
    if (has_capability('mod/ddtaquiz:manage', $PAGE->cm->context)) {
        $node = navigation_node::create(get_string('editquiz', 'ddtaquiz'),
                new moodle_url('/mod/ddtaquiz/edit.php', array('cmid' => $PAGE->cm->id)),
                navigation_node::TYPE_SETTING, null, 'mod_ddtaquiz_edit',
                new pix_icon('t/edit', ''));
        $ddtaquiznode->add_node($node, $beforekey);
    }

    // Preview Quiz button.
    if (has_capability('mod/ddtaquiz:preview', $PAGE->cm->context)) {
        $url = new moodle_url('/mod/ddtaquiz/startattempt.php',
            array('cmid' => $PAGE->cm->id, 'sesskey' => sesskey()));
        $node = navigation_node::create(get_string('preview', 'ddtaquiz'), $url,
            navigation_node::TYPE_SETTING, null, 'mod_ddtaquiz_preview',
            new pix_icon('i/preview', ''));
        $ddtaquiznode->add_node($node, $beforekey);
    }

    // Report buttons.
    if (has_any_capability(array('mod/ddtaquiz:viewreports', 'mod/ddtaquiz:grade'), $PAGE->cm->context)) {
        $url = new moodle_url('/mod/ddtaquiz/report.php',
            array('id' => $PAGE->cm->id, 'mode' => 'overview'));
        $reportnode = $ddtaquiznode->add_node(navigation_node::create(get_string('results', 'ddtaquiz'), $url,
            navigation_node::TYPE_SETTING,
            null, null, new pix_icon('i/report', '')), $beforekey);

        $reportnode->add_node(navigation_node::create(get_string('grades', 'ddtaquiz'), $url,
            navigation_node::TYPE_SETTING,
            null, null, new pix_icon('i/item', '')));

        $url = new moodle_url('/mod/ddtaquiz/report.php',
        array('id' => $PAGE->cm->id, 'mode' => 'responses'));
    $reportnode->add_node(navigation_node::create(get_string('responses', 'ddtaquiz'), $url,
        navigation_node::TYPE_SETTING,
        null, null, new pix_icon('i/item', '')));

        $url = new moodle_url('/mod/ddtaquiz/report.php',
            array('id' => $PAGE->cm->id, 'mode' => 'combined'));
        $reportnode->add_node(navigation_node::create(get_string('combinedview', 'ddtaquiz'), $url,
            navigation_node::TYPE_SETTING,
            null, null, new pix_icon('i/item', '')));
}
}

/**
 * Return grade for given user or all users.
 *
 * @param stdClass $ddtaquiz id of ddtaquiz.
 * @param int $userid optional user id, 0 means all users.
 * @return array array of grades, false if none.
 * @throws dml_exception
 */
function ddtaquiz_get_user_grades(stdClass $ddtaquiz, $userid = 0) {
    global $CFG, $DB;

    $params = array($ddtaquiz->id);
    $usertest = '';
    if ($userid) {
        $params[] = $userid;
        $usertest = 'AND u.id = ?';
    }
    return $DB->get_records_sql("
            SELECT
                u.id,
                u.id AS userid,
                qg.grade AS rawgrade,
                qg.timemodified AS dategraded,
                MAX(qa.timefinish) AS datesubmitted

            FROM {user} u
            JOIN {ddtaquiz_grades} qg ON u.id = qg.userid
            JOIN {ddtaquiz_attempts} qa ON qa.quiz = qg.quiz AND qa.userid = u.id

            WHERE qg.quiz = ?
            $usertest
            GROUP BY u.id, qg.grade, qg.timemodified", $params);
}