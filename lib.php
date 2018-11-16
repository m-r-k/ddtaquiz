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
 * Library of functions for the ddtaquiz module.
 *
 * This contains functions that are called also from outside the ddtaquiz module
 * Functions that are only called by the ddtaquiz module itself are in {@link locallib.php}
 *
 * @package    mod_ddtaquiz
 * @copyright  1999 onwards Martin Dougiamas {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/eventslib.php');
require_once($CFG->dirroot . '/calendar/lib.php');


/**#@+
 * Option controlling what options are offered on the ddtaquiz settings form.
 */
define('DDTAQUIZ_MAX_ATTEMPT_OPTION', 10);
define('DDTAQUIZ_MAX_QPP_OPTION', 50);
define('DDTAQUIZ_MAX_DECIMAL_OPTION', 5);
define('DDTAQUIZ_MAX_Q_DECIMAL_OPTION', 7);
/**#@-*/

/**#@+
 * Options determining how the grades from individual attempts are combined to give
 * the overall grade for a user
 */
define('DDTAQUIZ_GRADEHIGHEST', '1');
define('DDTAQUIZ_GRADEAVERAGE', '2');
define('DDTAQUIZ_ATTEMPTFIRST', '3');
define('DDTAQUIZ_ATTEMPTLAST',  '4');
/**#@-*/

/**
 * @var int If start and end date for the ddtaquiz are more than this many seconds apart
 * they will be represented by two separate events in the calendar
 */
define('DDTAQUIZ_MAX_EVENT_LENGTH', 5*24*60*60); // 5 days.

/**#@+
 * Options for navigation method within ddtaquizzes.
 */
define('DDTAQUIZ_NAVMETHOD_FREE', 'free');
define('DDTAQUIZ_NAVMETHOD_SEQ',  'sequential');
/**#@-*/

/**
 * Event types.
 */
define('DDTAQUIZ_EVENT_TYPE_OPEN', 'open');
define('DDTAQUIZ_EVENT_TYPE_CLOSE', 'close');

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param object $ddtaquiz the data that came from the form.
 * @return mixed the id of the new instance on success,
 *          false or a string error message on failure.
 */
function ddtaquiz_add_instance($ddtaquiz) {
    global $DB;
    $cmid = $ddtaquiz->coursemodule;

    // Process the options from the form.
    $ddtaquiz->created = time();
    $result = ddtaquiz_process_options($ddtaquiz);
    if ($result && is_string($result)) {
        return $result;
    }

    // Try to store it in the database.
    $ddtaquiz->id = $DB->insert_record('ddtaquiz', $ddtaquiz);

    // Create the first section for this ddtaquiz.
    $DB->insert_record('ddtaquiz_sections', array('ddtaquizid' => $ddtaquiz->id,
            'firstslot' => 1, 'heading' => '', 'shufflequestions' => 0));

    // Do the processing required after an add or an update.
    ddtaquiz_after_add_or_update($ddtaquiz);

    return $ddtaquiz->id;
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param object $ddtaquiz the data that came from the form.
 * @return mixed true on success, false or a string error message on failure.
 */
function ddtaquiz_update_instance($ddtaquiz, $mform) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/mod/ddtaquiz/locallib.php');

    // Process the options from the form.
    $result = ddtaquiz_process_options($ddtaquiz);
    if ($result && is_string($result)) {
        return $result;
    }

    // Get the current value, so we can see what changed.
    $oldddtaquiz = $DB->get_record('ddtaquiz', array('id' => $ddtaquiz->instance));

    // We need two values from the existing DB record that are not in the form,
    // in some of the function calls below.
    $ddtaquiz->sumgrades = $oldddtaquiz->sumgrades;
    $ddtaquiz->grade     = $oldddtaquiz->grade;

    // Update the database.
    $ddtaquiz->id = $ddtaquiz->instance;
    $DB->update_record('ddtaquiz', $ddtaquiz);

    // Do the processing required after an add or an update.
    ddtaquiz_after_add_or_update($ddtaquiz);

    if ($oldddtaquiz->grademethod != $ddtaquiz->grademethod) {
        ddtaquiz_update_all_final_grades($ddtaquiz);
        ddtaquiz_update_grades($ddtaquiz);
    }

    $ddtaquizdateschanged = $oldddtaquiz->timelimit   != $ddtaquiz->timelimit
                     || $oldddtaquiz->timeclose   != $ddtaquiz->timeclose
                     || $oldddtaquiz->graceperiod != $ddtaquiz->graceperiod;
    if ($ddtaquizdateschanged) {
        ddtaquiz_update_open_attempts(array('ddtaquizid' => $ddtaquiz->id));
    }

    // Delete any previous preview attempts.
    ddtaquiz_delete_previews($ddtaquiz);

    // Repaginate, if asked to.
    if (!empty($ddtaquiz->repaginatenow)) {
        ddtaquiz_repaginate_questions($ddtaquiz->id, $ddtaquiz->questionsperpage);
    }

    return true;
}

/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id the id of the ddtaquiz to delete.
 * @return bool success or failure.
 */
function ddtaquiz_delete_instance($id) {
    global $DB;

    $ddtaquiz = $DB->get_record('ddtaquiz', array('id' => $id), '*', MUST_EXIST);

    ddtaquiz_delete_all_attempts($ddtaquiz);
    ddtaquiz_delete_all_overrides($ddtaquiz);

    // Look for random questions that may no longer be used when this ddtaquiz is gone.
    $sql = "SELECT q.id
              FROM {ddtaquiz_slots} slot
              JOIN {question} q ON q.id = slot.questionid
             WHERE slot.ddtaquizid = ? AND q.qtype = ?";
    $questionids = $DB->get_fieldset_sql($sql, array($ddtaquiz->id, 'random'));

    // We need to do the following deletes before we try and delete randoms, otherwise they would still be 'in use'.
    $ddtaquizslots = $DB->get_fieldset_select('ddtaquiz_slots', 'id', 'ddtaquizid = ?', array($ddtaquiz->id));
    $DB->delete_records_list('ddtaquiz_slot_tags', 'slotid', $ddtaquizslots);
    $DB->delete_records('ddtaquiz_slots', array('ddtaquizid' => $ddtaquiz->id));
    $DB->delete_records('ddtaquiz_sections', array('ddtaquizid' => $ddtaquiz->id));

    foreach ($questionids as $questionid) {
        question_delete_question($questionid);
    }

    $DB->delete_records('ddtaquiz_feedback', array('ddtaquizid' => $ddtaquiz->id));

    ddtaquiz_access_manager::delete_settings($ddtaquiz);

    $events = $DB->get_records('event', array('modulename' => 'ddtaquiz', 'instance' => $ddtaquiz->id));
    foreach ($events as $event) {
        $event = calendar_event::load($event);
        $event->delete();
    }

    ddtaquiz_grade_item_delete($ddtaquiz);
    $DB->delete_records('ddtaquiz', array('id' => $ddtaquiz->id));

    return true;
}

/**
 * Deletes a ddtaquiz override from the database and clears any corresponding calendar events
 *
 * @param object $ddtaquiz The ddtaquiz object.
 * @param int $overrideid The id of the override being deleted
 * @param bool $log Whether to trigger logs.
 * @return bool true on success
 */
function ddtaquiz_delete_override($ddtaquiz, $overrideid, $log = true) {
    global $DB;

    if (!isset($ddtaquiz->cmid)) {
        $cm = get_coursemodule_from_instance('ddtaquiz', $ddtaquiz->id, $ddtaquiz->course);
        $ddtaquiz->cmid = $cm->id;
    }

    $override = $DB->get_record('ddtaquiz_overrides', array('id' => $overrideid), '*', MUST_EXIST);

    // Delete the events.
    if (isset($override->groupid)) {
        // Create the search array for a group override.
        $eventsearcharray = array('modulename' => 'ddtaquiz',
            'instance' => $ddtaquiz->id, 'groupid' => (int)$override->groupid);
    } else {
        // Create the search array for a user override.
        $eventsearcharray = array('modulename' => 'ddtaquiz',
            'instance' => $ddtaquiz->id, 'userid' => (int)$override->userid);
    }
    $events = $DB->get_records('event', $eventsearcharray);
    foreach ($events as $event) {
        $eventold = calendar_event::load($event);
        $eventold->delete();
    }

    $DB->delete_records('ddtaquiz_overrides', array('id' => $overrideid));

    if ($log) {
        // Set the common parameters for one of the events we will be triggering.
        $params = array(
            'objectid' => $override->id,
            'context' => context_module::instance($ddtaquiz->cmid),
            'other' => array(
                'ddtaquizid' => $override->ddtaquiz
            )
        );
        // Determine which override deleted event to fire.
        if (!empty($override->userid)) {
            $params['relateduserid'] = $override->userid;
            $event = \mod_ddtaquiz\event\user_override_deleted::create($params);
        } else {
            $params['other']['groupid'] = $override->groupid;
            $event = \mod_ddtaquiz\event\group_override_deleted::create($params);
        }

        // Trigger the override deleted event.
        $event->add_record_snapshot('ddtaquiz_overrides', $override);
        $event->trigger();
    }

    return true;
}

/**
 * Deletes all ddtaquiz overrides from the database and clears any corresponding calendar events
 *
 * @param object $ddtaquiz The ddtaquiz object.
 * @param bool $log Whether to trigger logs.
 */
function ddtaquiz_delete_all_overrides($ddtaquiz, $log = true) {
    global $DB;

    $overrides = $DB->get_records('ddtaquiz_overrides', array('ddtaquiz' => $ddtaquiz->id), 'id');
    foreach ($overrides as $override) {
        ddtaquiz_delete_override($ddtaquiz, $override->id, $log);
    }
}

/**
 * Updates a ddtaquiz object with override information for a user.
 *
 * Algorithm:  For each ddtaquiz setting, if there is a matching user-specific override,
 *   then use that otherwise, if there are group-specific overrides, return the most
 *   lenient combination of them.  If neither applies, leave the ddtaquiz setting unchanged.
 *
 *   Special case: if there is more than one password that applies to the user, then
 *   ddtaquiz->extrapasswords will contain an array of strings giving the remaining
 *   passwords.
 *
 * @param object $ddtaquiz The ddtaquiz object.
 * @param int $userid The userid.
 * @return object $ddtaquiz The updated ddtaquiz object.
 */
function ddtaquiz_update_effective_access($ddtaquiz, $userid) {
    global $DB;

    // Check for user override.
    $override = $DB->get_record('ddtaquiz_overrides', array('ddtaquiz' => $ddtaquiz->id, 'userid' => $userid));

    if (!$override) {
        $override = new stdClass();
        $override->timeopen = null;
        $override->timeclose = null;
        $override->timelimit = null;
        $override->attempts = null;
        $override->password = null;
    }

    // Check for group overrides.
    $groupings = groups_get_user_groups($ddtaquiz->course, $userid);

    if (!empty($groupings[0])) {
        // Select all overrides that apply to the User's groups.
        list($extra, $params) = $DB->get_in_or_equal(array_values($groupings[0]));
        $sql = "SELECT * FROM {ddtaquiz_overrides}
                WHERE groupid $extra AND ddtaquiz = ?";
        $params[] = $ddtaquiz->id;
        $records = $DB->get_records_sql($sql, $params);

        // Combine the overrides.
        $opens = array();
        $closes = array();
        $limits = array();
        $attempts = array();
        $passwords = array();

        foreach ($records as $gpoverride) {
            if (isset($gpoverride->timeopen)) {
                $opens[] = $gpoverride->timeopen;
            }
            if (isset($gpoverride->timeclose)) {
                $closes[] = $gpoverride->timeclose;
            }
            if (isset($gpoverride->timelimit)) {
                $limits[] = $gpoverride->timelimit;
            }
            if (isset($gpoverride->attempts)) {
                $attempts[] = $gpoverride->attempts;
            }
            if (isset($gpoverride->password)) {
                $passwords[] = $gpoverride->password;
            }
        }
        // If there is a user override for a setting, ignore the group override.
        if (is_null($override->timeopen) && count($opens)) {
            $override->timeopen = min($opens);
        }
        if (is_null($override->timeclose) && count($closes)) {
            if (in_array(0, $closes)) {
                $override->timeclose = 0;
            } else {
                $override->timeclose = max($closes);
            }
        }
        if (is_null($override->timelimit) && count($limits)) {
            if (in_array(0, $limits)) {
                $override->timelimit = 0;
            } else {
                $override->timelimit = max($limits);
            }
        }
        if (is_null($override->attempts) && count($attempts)) {
            if (in_array(0, $attempts)) {
                $override->attempts = 0;
            } else {
                $override->attempts = max($attempts);
            }
        }
        if (is_null($override->password) && count($passwords)) {
            $override->password = array_shift($passwords);
            if (count($passwords)) {
                $override->extrapasswords = $passwords;
            }
        }

    }

    // Merge with ddtaquiz defaults.
    $keys = array('timeopen', 'timeclose', 'timelimit', 'attempts', 'password', 'extrapasswords');
    foreach ($keys as $key) {
        if (isset($override->{$key})) {
            $ddtaquiz->{$key} = $override->{$key};
        }
    }

    return $ddtaquiz;
}

/**
 * Delete all the attempts belonging to a ddtaquiz.
 *
 * @param object $ddtaquiz The ddtaquiz object.
 */
function ddtaquiz_delete_all_attempts($ddtaquiz) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/mod/ddtaquiz/locallib.php');
    question_engine::delete_questions_usage_by_activities(new qubaids_for_ddtaquiz($ddtaquiz->id));
    $DB->delete_records('ddtaquiz_attempts', array('ddtaquiz' => $ddtaquiz->id));
    $DB->delete_records('ddtaquiz_grades', array('ddtaquiz' => $ddtaquiz->id));
}

/**
 * Delete all the attempts belonging to a user in a particular ddtaquiz.
 *
 * @param object $ddtaquiz The ddtaquiz object.
 * @param object $user The user object.
 */
function ddtaquiz_delete_user_attempts($ddtaquiz, $user) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/mod/ddtaquiz/locallib.php');
    question_engine::delete_questions_usage_by_activities(new qubaids_for_ddtaquiz_user($ddtaquiz->get_ddtaquizid(), $user->id));
    $params = [
        'ddtaquiz' => $ddtaquiz->get_ddtaquizid(),
        'userid' => $user->id,
    ];
    $DB->delete_records('ddtaquiz_attempts', $params);
    $DB->delete_records('ddtaquiz_grades', $params);
}

/**
 * Get the best current grade for a particular user in a ddtaquiz.
 *
 * @param object $ddtaquiz the ddtaquiz settings.
 * @param int $userid the id of the user.
 * @return float the user's current grade for this ddtaquiz, or null if this user does
 * not have a grade on this ddtaquiz.
 */
function ddtaquiz_get_best_grade($ddtaquiz, $userid) {
    global $DB;
    $grade = $DB->get_field('ddtaquiz_grades', 'grade',
            array('ddtaquiz' => $ddtaquiz->id, 'userid' => $userid));

    // Need to detect errors/no result, without catching 0 grades.
    if ($grade === false) {
        return null;
    }

    return $grade + 0; // Convert to number.
}

/**
 * Is this a graded ddtaquiz? If this method returns true, you can assume that
 * $ddtaquiz->grade and $ddtaquiz->sumgrades are non-zero (for example, if you want to
 * divide by them).
 *
 * @param object $ddtaquiz a row from the ddtaquiz table.
 * @return bool whether this is a graded ddtaquiz.
 */
function ddtaquiz_has_grades($ddtaquiz) {
    return $ddtaquiz->grade >= 0.000005 && $ddtaquiz->sumgrades >= 0.000005;
}

/**
 * Does this ddtaquiz allow multiple tries?
 *
 * @return bool
 */
function ddtaquiz_allows_multiple_tries($ddtaquiz) {
    $bt = question_engine::get_behaviour_type($ddtaquiz->preferredbehaviour);
    return $bt->allows_multiple_submitted_responses();
}

/**
 * Return a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @param object $course
 * @param object $user
 * @param object $mod
 * @param object $ddtaquiz
 * @return object|null
 */
function ddtaquiz_user_outline($course, $user, $mod, $ddtaquiz) {
    global $DB, $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    $grades = grade_get_grades($course->id, 'mod', 'ddtaquiz', $ddtaquiz->id, $user->id);

    if (empty($grades->items[0]->grades)) {
        return null;
    } else {
        $grade = reset($grades->items[0]->grades);
    }

    $result = new stdClass();
    // If the user can't see hidden grades, don't return that information.
    $gitem = grade_item::fetch(array('id' => $grades->items[0]->id));
    if (!$gitem->hidden || has_capability('moodle/grade:viewhidden', context_course::instance($course->id))) {
        $result->info = get_string('grade') . ': ' . $grade->str_long_grade;
    } else {
        $result->info = get_string('grade') . ': ' . get_string('hidden', 'grades');
    }

    // Datesubmitted == time created. dategraded == time modified or time overridden
    // if grade was last modified by the user themselves use date graded. Otherwise use
    // date submitted.
    // TODO: move this copied & pasted code somewhere in the grades API. See MDL-26704.
    if ($grade->usermodified == $user->id || empty($grade->datesubmitted)) {
        $result->time = $grade->dategraded;
    } else {
        $result->time = $grade->datesubmitted;
    }

    return $result;
}

/**
 * Print a detailed representation of what a  user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @param object $course
 * @param object $user
 * @param object $mod
 * @param object $ddtaquiz
 * @return bool
 */
function ddtaquiz_user_complete($course, $user, $mod, $ddtaquiz) {
    global $DB, $CFG, $OUTPUT;
    require_once($CFG->libdir . '/gradelib.php');
    require_once($CFG->dirroot . '/mod/ddtaquiz/locallib.php');

    $grades = grade_get_grades($course->id, 'mod', 'ddtaquiz', $ddtaquiz->id, $user->id);
    if (!empty($grades->items[0]->grades)) {
        $grade = reset($grades->items[0]->grades);
        // If the user can't see hidden grades, don't return that information.
        $gitem = grade_item::fetch(array('id' => $grades->items[0]->id));
        if (!$gitem->hidden || has_capability('moodle/grade:viewhidden', context_course::instance($course->id))) {
            echo $OUTPUT->container(get_string('grade').': '.$grade->str_long_grade);
            if ($grade->str_feedback) {
                echo $OUTPUT->container(get_string('feedback').': '.$grade->str_feedback);
            }
        } else {
            echo $OUTPUT->container(get_string('grade') . ': ' . get_string('hidden', 'grades'));
            if ($grade->str_feedback) {
                echo $OUTPUT->container(get_string('feedback').': '.get_string('hidden', 'grades'));
            }
        }
    }

    if ($attempts = $DB->get_records('ddtaquiz_attempts',
            array('userid' => $user->id, 'ddtaquiz' => $ddtaquiz->id), 'attempt')) {
        foreach ($attempts as $attempt) {
            echo get_string('attempt', 'ddtaquiz', $attempt->attempt) . ': ';
            if ($attempt->state != ddtaquiz_attempt::FINISHED) {
                echo ddtaquiz_attempt_state_name($attempt->state);
            } else {
                if (!isset($gitem)) {
                    if (!empty($grades->items[0]->grades)) {
                        $gitem = grade_item::fetch(array('id' => $grades->items[0]->id));
                    } else {
                        $gitem = new stdClass();
                        $gitem->hidden = true;
                    }
                }
                if (!$gitem->hidden || has_capability('moodle/grade:viewhidden', context_course::instance($course->id))) {
                    echo ddtaquiz_format_grade($ddtaquiz, $attempt->sumgrades) . '/' . ddtaquiz_format_grade($ddtaquiz, $ddtaquiz->sumgrades);
                } else {
                    echo get_string('hidden', 'grades');
                }
            }
            echo ' - '.userdate($attempt->timemodified).'<br />';
        }
    } else {
        print_string('noattempts', 'ddtaquiz');
    }

    return true;
}

/**
 * Ddtaquiz periodic clean-up tasks.
 */
function ddtaquiz_cron() {
    global $CFG;

    require_once($CFG->dirroot . '/mod/ddtaquiz/cronlib.php');
    mtrace('');

    $timenow = time();
    $overduehander = new mod_ddtaquiz_overdue_attempt_updater();

    $processto = $timenow - get_config('ddtaquiz', 'graceperiodmin');

    mtrace('  Looking for ddtaquiz overdue ddtaquiz attempts...');

    list($count, $ddtaquizcount) = $overduehander->update_overdue_attempts($timenow, $processto);

    mtrace('  Considered ' . $count . ' attempts in ' . $ddtaquizcount . ' ddtaquizzes.');

    // Run cron for our sub-plugin types.
    cron_execute_plugin_type('ddtaquiz', 'ddtaquiz reports');
    cron_execute_plugin_type('ddtaquizaccess', 'ddtaquiz access rules');

    return true;
}

/**
 * @param int|array $ddtaquizids A ddtaquiz ID, or an array of ddtaquiz IDs.
 * @param int $userid the userid.
 * @param string $status 'all', 'finished' or 'unfinished' to control
 * @param bool $includepreviews
 * @return an array of all the user's attempts at this ddtaquiz. Returns an empty
 *      array if there are none.
 */
function ddtaquiz_get_user_attempts($ddtaquizids, $userid, $status = 'finished', $includepreviews = false) {
    global $DB, $CFG;
    // TODO MDL-33071 it is very annoying to have to included all of locallib.php
    // just to get the ddtaquiz_attempt::FINISHED constants, but I will try to sort
    // that out properly for Moodle 2.4. For now, I will just do a quick fix for
    // MDL-33048.
    require_once($CFG->dirroot . '/mod/ddtaquiz/locallib.php');

    $params = array();
    switch ($status) {
        case 'all':
            $statuscondition = '';
            break;

        case 'finished':
            $statuscondition = ' AND state IN (:state1, :state2)';
            $params['state1'] = ddtaquiz_attempt::FINISHED;
            $params['state2'] = ddtaquiz_attempt::ABANDONED;
            break;

        case 'unfinished':
            $statuscondition = ' AND state IN (:state1, :state2)';
            $params['state1'] = ddtaquiz_attempt::IN_PROGRESS;
            $params['state2'] = ddtaquiz_attempt::OVERDUE;
            break;
    }

    $ddtaquizids = (array) $ddtaquizids;
    list($insql, $inparams) = $DB->get_in_or_equal($ddtaquizids, SQL_PARAMS_NAMED);
    $params += $inparams;
    $params['userid'] = $userid;

    $previewclause = '';
    if (!$includepreviews) {
        $previewclause = ' AND preview = 0';
    }

    return $DB->get_records_select('ddtaquiz_attempts',
            "ddtaquiz $insql AND userid = :userid" . $previewclause . $statuscondition,
            $params, 'ddtaquiz, attempt ASC');
}

/**
 * Return grade for given user or all users.
 *
 * @param int $ddtaquizid id of ddtaquiz
 * @param int $userid optional user id, 0 means all users
 * @return array array of grades, false if none. These are raw grades. They should
 * be processed with ddtaquiz_format_grade for display.
 */
function ddtaquiz_get_user_grades($ddtaquiz, $userid = 0) {
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
            JOIN {ddtaquiz_attempts} qa ON qa.ddtaquiz = qg.ddtaquiz AND qa.userid = u.id

            WHERE qg.ddtaquiz = ?
            $usertest
            GROUP BY u.id, qg.grade, qg.timemodified", $params);
}

/**
 * Round a grade to to the correct number of decimal places, and format it for display.
 *
 * @param object $ddtaquiz The ddtaquiz table row, only $ddtaquiz->decimalpoints is used.
 * @param float $grade The grade to round.
 * @return float
 */
function ddtaquiz_format_grade($ddtaquiz, $grade) {
    if (is_null($grade)) {
        return get_string('notyetgraded', 'ddtaquiz');
    }
    return format_float($grade, $ddtaquiz->decimalpoints);
}

/**
 * Determine the correct number of decimal places required to format a grade.
 *
 * @param object $ddtaquiz The ddtaquiz table row, only $ddtaquiz->decimalpoints is used.
 * @return integer
 */
function ddtaquiz_get_grade_format($ddtaquiz) {
    if (empty($ddtaquiz->questiondecimalpoints)) {
        $ddtaquiz->questiondecimalpoints = -1;
    }

    if ($ddtaquiz->questiondecimalpoints == -1) {
        return $ddtaquiz->decimalpoints;
    }

    return $ddtaquiz->questiondecimalpoints;
}

/**
 * Round a grade to the correct number of decimal places, and format it for display.
 *
 * @param object $ddtaquiz The ddtaquiz table row, only $ddtaquiz->decimalpoints is used.
 * @param float $grade The grade to round.
 * @return float
 */
function ddtaquiz_format_question_grade($ddtaquiz, $grade) {
    return format_float($grade, ddtaquiz_get_grade_format($ddtaquiz));
}

/**
 * Update grades in central gradebook
 *
 * @category grade
 * @param object $ddtaquiz the ddtaquiz settings.
 * @param int $userid specific user only, 0 means all users.
 * @param bool $nullifnone If a single user is specified and $nullifnone is true a grade item with a null rawgrade will be inserted
 */
function ddtaquiz_update_grades($ddtaquiz, $userid = 0, $nullifnone = true) {
    global $CFG, $DB;
    require_once($CFG->libdir . '/gradelib.php');

    if ($ddtaquiz->grade == 0) {
        ddtaquiz_grade_item_update($ddtaquiz);

    } else if ($grades = ddtaquiz_get_user_grades($ddtaquiz, $userid)) {
        ddtaquiz_grade_item_update($ddtaquiz, $grades);

    } else if ($userid && $nullifnone) {
        $grade = new stdClass();
        $grade->userid = $userid;
        $grade->rawgrade = null;
        ddtaquiz_grade_item_update($ddtaquiz, $grade);

    } else {
        ddtaquiz_grade_item_update($ddtaquiz);
    }
}

/**
 * Create or update the grade item for given ddtaquiz
 *
 * @category grade
 * @param object $ddtaquiz object with extra cmidnumber
 * @param mixed $grades optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok, error code otherwise
 */
function ddtaquiz_grade_item_update($ddtaquiz, $grades = null) {
    global $CFG, $OUTPUT;
    require_once($CFG->dirroot . '/mod/ddtaquiz/locallib.php');
    require_once($CFG->libdir . '/gradelib.php');

    if (array_key_exists('cmidnumber', $ddtaquiz)) { // May not be always present.
        $params = array('itemname' => $ddtaquiz->name, 'idnumber' => $ddtaquiz->cmidnumber);
    } else {
        $params = array('itemname' => $ddtaquiz->name);
    }

    if ($ddtaquiz->grade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = $ddtaquiz->grade;
        $params['grademin']  = 0;

    } else {
        $params['gradetype'] = GRADE_TYPE_NONE;
    }

    // What this is trying to do:
    // 1. If the ddtaquiz is set to not show grades while the ddtaquiz is still open,
    //    and is set to show grades after the ddtaquiz is closed, then create the
    //    grade_item with a show-after date that is the ddtaquiz close date.
    // 2. If the ddtaquiz is set to not show grades at either of those times,
    //    create the grade_item as hidden.
    // 3. If the ddtaquiz is set to show grades, create the grade_item visible.
    $openreviewoptions = mod_ddtaquiz_display_options::make_from_ddtaquiz($ddtaquiz,
            mod_ddtaquiz_display_options::LATER_WHILE_OPEN);
    $closedreviewoptions = mod_ddtaquiz_display_options::make_from_ddtaquiz($ddtaquiz,
            mod_ddtaquiz_display_options::AFTER_CLOSE);
    if ($openreviewoptions->marks < question_display_options::MARK_AND_MAX &&
            $closedreviewoptions->marks < question_display_options::MARK_AND_MAX) {
        $params['hidden'] = 1;

    } else if ($openreviewoptions->marks < question_display_options::MARK_AND_MAX &&
            $closedreviewoptions->marks >= question_display_options::MARK_AND_MAX) {
        if ($ddtaquiz->timeclose) {
            $params['hidden'] = $ddtaquiz->timeclose;
        } else {
            $params['hidden'] = 1;
        }

    } else {
        // Either
        // a) both open and closed enabled
        // b) open enabled, closed disabled - we can not "hide after",
        //    grades are kept visible even after closing.
        $params['hidden'] = 0;
    }

    if (!$params['hidden']) {
        // If the grade item is not hidden by the ddtaquiz logic, then we need to
        // hide it if the ddtaquiz is hidden from students.
        if (property_exists($ddtaquiz, 'visible')) {
            // Saving the ddtaquiz form, and cm not yet updated in the database.
            $params['hidden'] = !$ddtaquiz->visible;
        } else {
            $cm = get_coursemodule_from_instance('ddtaquiz', $ddtaquiz->id);
            $params['hidden'] = !$cm->visible;
        }
    }

    if ($grades  === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }

    $gradebook_grades = grade_get_grades($ddtaquiz->course, 'mod', 'ddtaquiz', $ddtaquiz->id);
    if (!empty($gradebook_grades->items)) {
        $grade_item = $gradebook_grades->items[0];
        if ($grade_item->locked) {
            // NOTE: this is an extremely nasty hack! It is not a bug if this confirmation fails badly. --skodak.
            $confirm_regrade = optional_param('confirm_regrade', 0, PARAM_INT);
            if (!$confirm_regrade) {
                if (!AJAX_SCRIPT) {
                    $message = get_string('gradeitemislocked', 'grades');
                    $back_link = $CFG->wwwroot . '/mod/ddtaquiz/report.php?q=' . $ddtaquiz->id .
                            '&amp;mode=overview';
                    $regrade_link = qualified_me() . '&amp;confirm_regrade=1';
                    echo $OUTPUT->box_start('generalbox', 'notice');
                    echo '<p>'. $message .'</p>';
                    echo $OUTPUT->container_start('buttons');
                    echo $OUTPUT->single_button($regrade_link, get_string('regradeanyway', 'grades'));
                    echo $OUTPUT->single_button($back_link,  get_string('cancel'));
                    echo $OUTPUT->container_end();
                    echo $OUTPUT->box_end();
                }
                return GRADE_UPDATE_ITEM_LOCKED;
            }
        }
    }

    return grade_update('mod/ddtaquiz', $ddtaquiz->course, 'mod', 'ddtaquiz', $ddtaquiz->id, 0, $grades, $params);
}

/**
 * Delete grade item for given ddtaquiz
 *
 * @category grade
 * @param object $ddtaquiz object
 * @return object ddtaquiz
 */
function ddtaquiz_grade_item_delete($ddtaquiz) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    return grade_update('mod/ddtaquiz', $ddtaquiz->course, 'mod', 'ddtaquiz', $ddtaquiz->id, 0,
            null, array('deleted' => 1));
}

/**
 * This standard function will check all instances of this module
 * and make sure there are up-to-date events created for each of them.
 * If courseid = 0, then every ddtaquiz event in the site is checked, else
 * only ddtaquiz events belonging to the course specified are checked.
 * This function is used, in its new format, by restore_refresh_events()
 *
 * @param int $courseid
 * @param int|stdClass $instance Ddtaquiz module instance or ID.
 * @param int|stdClass $cm Course module object or ID (not used in this module).
 * @return bool
 */
function ddtaquiz_refresh_events($courseid = 0, $instance = null, $cm = null) {
    global $DB;

    // If we have instance information then we can just update the one event instead of updating all events.
    if (isset($instance)) {
        if (!is_object($instance)) {
            $instance = $DB->get_record('ddtaquiz', array('id' => $instance), '*', MUST_EXIST);
        }
        ddtaquiz_update_events($instance);
        return true;
    }

    if ($courseid == 0) {
        if (!$ddtaquizzes = $DB->get_records('ddtaquiz')) {
            return true;
        }
    } else {
        if (!$ddtaquizzes = $DB->get_records('ddtaquiz', array('course' => $courseid))) {
            return true;
        }
    }

    foreach ($ddtaquizzes as $ddtaquiz) {
        ddtaquiz_update_events($ddtaquiz);
    }

    return true;
}

/**
 * Returns all ddtaquiz graded users since a given time for specified ddtaquiz
 */
function ddtaquiz_get_recent_mod_activity(&$activities, &$index, $timestart,
        $courseid, $cmid, $userid = 0, $groupid = 0) {
    global $CFG, $USER, $DB;
    require_once($CFG->dirroot . '/mod/ddtaquiz/locallib.php');

    $course = get_course($courseid);
    $modinfo = get_fast_modinfo($course);

    $cm = $modinfo->cms[$cmid];
    $ddtaquiz = $DB->get_record('ddtaquiz', array('id' => $cm->instance));

    if ($userid) {
        $userselect = "AND u.id = :userid";
        $params['userid'] = $userid;
    } else {
        $userselect = '';
    }

    if ($groupid) {
        $groupselect = 'AND gm.groupid = :groupid';
        $groupjoin   = 'JOIN {groups_members} gm ON  gm.userid=u.id';
        $params['groupid'] = $groupid;
    } else {
        $groupselect = '';
        $groupjoin   = '';
    }

    $params['timestart'] = $timestart;
    $params['ddtaquizid'] = $ddtaquiz->id;

    $ufields = user_picture::fields('u', null, 'useridagain');
    if (!$attempts = $DB->get_records_sql("
              SELECT qa.*,
                     {$ufields}
                FROM {ddtaquiz_attempts} qa
                     JOIN {user} u ON u.id = qa.userid
                     $groupjoin
               WHERE qa.timefinish > :timestart
                 AND qa.ddtaquiz = :ddtaquizid
                 AND qa.preview = 0
                     $userselect
                     $groupselect
            ORDER BY qa.timefinish ASC", $params)) {
        return;
    }

    $context         = context_module::instance($cm->id);
    $accessallgroups = has_capability('moodle/site:accessallgroups', $context);
    $viewfullnames   = has_capability('moodle/site:viewfullnames', $context);
    $grader          = has_capability('mod/ddtaquiz:viewreports', $context);
    $groupmode       = groups_get_activity_groupmode($cm, $course);

    $usersgroups = null;
    $aname = format_string($cm->name, true);
    foreach ($attempts as $attempt) {
        if ($attempt->userid != $USER->id) {
            if (!$grader) {
                // Grade permission required.
                continue;
            }

            if ($groupmode == SEPARATEGROUPS and !$accessallgroups) {
                $usersgroups = groups_get_all_groups($course->id,
                        $attempt->userid, $cm->groupingid);
                $usersgroups = array_keys($usersgroups);
                if (!array_intersect($usersgroups, $modinfo->get_groups($cm->groupingid))) {
                    continue;
                }
            }
        }

        $options = ddtaquiz_get_review_options($ddtaquiz, $attempt, $context);

        $tmpactivity = new stdClass();

        $tmpactivity->type       = 'ddtaquiz';
        $tmpactivity->cmid       = $cm->id;
        $tmpactivity->name       = $aname;
        $tmpactivity->sectionnum = $cm->sectionnum;
        $tmpactivity->timestamp  = $attempt->timefinish;

        $tmpactivity->content = new stdClass();
        $tmpactivity->content->attemptid = $attempt->id;
        $tmpactivity->content->attempt   = $attempt->attempt;
        if (ddtaquiz_has_grades($ddtaquiz) && $options->marks >= question_display_options::MARK_AND_MAX) {
            $tmpactivity->content->sumgrades = ddtaquiz_format_grade($ddtaquiz, $attempt->sumgrades);
            $tmpactivity->content->maxgrade  = ddtaquiz_format_grade($ddtaquiz, $ddtaquiz->sumgrades);
        } else {
            $tmpactivity->content->sumgrades = null;
            $tmpactivity->content->maxgrade  = null;
        }

        $tmpactivity->user = user_picture::unalias($attempt, null, 'useridagain');
        $tmpactivity->user->fullname  = fullname($tmpactivity->user, $viewfullnames);

        $activities[$index++] = $tmpactivity;
    }
}

function ddtaquiz_print_recent_mod_activity($activity, $courseid, $detail, $modnames) {
    global $CFG, $OUTPUT;

    echo '<table border="0" cellpadding="3" cellspacing="0" class="forum-recent">';

    echo '<tr><td class="userpicture" valign="top">';
    echo $OUTPUT->user_picture($activity->user, array('courseid' => $courseid));
    echo '</td><td>';

    if ($detail) {
        $modname = $modnames[$activity->type];
        echo '<div class="title">';
        echo $OUTPUT->image_icon('icon', $modname, $activity->type);
        echo '<a href="' . $CFG->wwwroot . '/mod/ddtaquiz/view.php?id=' .
                $activity->cmid . '">' . $activity->name . '</a>';
        echo '</div>';
    }

    echo '<div class="grade">';
    echo  get_string('attempt', 'ddtaquiz', $activity->content->attempt);
    if (isset($activity->content->maxgrade)) {
        $grades = $activity->content->sumgrades . ' / ' . $activity->content->maxgrade;
        echo ': (<a href="' . $CFG->wwwroot . '/mod/ddtaquiz/review.php?attempt=' .
                $activity->content->attemptid . '">' . $grades . '</a>)';
    }
    echo '</div>';

    echo '<div class="user">';
    echo '<a href="' . $CFG->wwwroot . '/user/view.php?id=' . $activity->user->id .
            '&amp;course=' . $courseid . '">' . $activity->user->fullname .
            '</a> - ' . userdate($activity->timestamp);
    echo '</div>';

    echo '</td></tr></table>';

    return;
}

/**
 * Pre-process the ddtaquiz options form data, making any necessary adjustments.
 * Called by add/update instance in this file.
 *
 * @param object $ddtaquiz The variables set on the form.
 */
function ddtaquiz_process_options($ddtaquiz) {
    global $CFG;
    require_once($CFG->dirroot . '/mod/ddtaquiz/locallib.php');
    require_once($CFG->libdir . '/questionlib.php');

    $ddtaquiz->timemodified = time();

    // Ddtaquiz name.
    if (!empty($ddtaquiz->name)) {
        $ddtaquiz->name = trim($ddtaquiz->name);
    }

    // Password field - different in form to stop browsers that remember passwords
    // getting confused.
    $ddtaquiz->password = $ddtaquiz->ddtaquizpassword;
    unset($ddtaquiz->ddtaquizpassword);

    // Ddtaquiz feedback.
    if (isset($ddtaquiz->feedbacktext)) {
        // Clean up the boundary text.
        for ($i = 0; $i < count($ddtaquiz->feedbacktext); $i += 1) {
            if (empty($ddtaquiz->feedbacktext[$i]['text'])) {
                $ddtaquiz->feedbacktext[$i]['text'] = '';
            } else {
                $ddtaquiz->feedbacktext[$i]['text'] = trim($ddtaquiz->feedbacktext[$i]['text']);
            }
        }

        // Check the boundary value is a number or a percentage, and in range.
        $i = 0;
        while (!empty($ddtaquiz->feedbackboundaries[$i])) {
            $boundary = trim($ddtaquiz->feedbackboundaries[$i]);
            if (!is_numeric($boundary)) {
                if (strlen($boundary) > 0 && $boundary[strlen($boundary) - 1] == '%') {
                    $boundary = trim(substr($boundary, 0, -1));
                    if (is_numeric($boundary)) {
                        $boundary = $boundary * $ddtaquiz->grade / 100.0;
                    } else {
                        return get_string('feedbackerrorboundaryformat', 'ddtaquiz', $i + 1);
                    }
                }
            }
            if ($boundary <= 0 || $boundary >= $ddtaquiz->grade) {
                return get_string('feedbackerrorboundaryoutofrange', 'ddtaquiz', $i + 1);
            }
            if ($i > 0 && $boundary >= $ddtaquiz->feedbackboundaries[$i - 1]) {
                return get_string('feedbackerrororder', 'ddtaquiz', $i + 1);
            }
            $ddtaquiz->feedbackboundaries[$i] = $boundary;
            $i += 1;
        }
        $numboundaries = $i;

        // Check there is nothing in the remaining unused fields.
        if (!empty($ddtaquiz->feedbackboundaries)) {
            for ($i = $numboundaries; $i < count($ddtaquiz->feedbackboundaries); $i += 1) {
                if (!empty($ddtaquiz->feedbackboundaries[$i]) &&
                        trim($ddtaquiz->feedbackboundaries[$i]) != '') {
                    return get_string('feedbackerrorjunkinboundary', 'ddtaquiz', $i + 1);
                }
            }
        }
        for ($i = $numboundaries + 1; $i < count($ddtaquiz->feedbacktext); $i += 1) {
            if (!empty($ddtaquiz->feedbacktext[$i]['text']) &&
                    trim($ddtaquiz->feedbacktext[$i]['text']) != '') {
                return get_string('feedbackerrorjunkinfeedback', 'ddtaquiz', $i + 1);
            }
        }
        // Needs to be bigger than $ddtaquiz->grade because of '<' test in ddtaquiz_feedback_for_grade().
        $ddtaquiz->feedbackboundaries[-1] = $ddtaquiz->grade + 1;
        $ddtaquiz->feedbackboundaries[$numboundaries] = 0;
        $ddtaquiz->feedbackboundarycount = $numboundaries;
    } else {
        $ddtaquiz->feedbackboundarycount = -1;
    }

    // Combing the individual settings into the review columns.
    $ddtaquiz->reviewattempt = ddtaquiz_review_option_form_to_db($ddtaquiz, 'attempt');
    $ddtaquiz->reviewcorrectness = ddtaquiz_review_option_form_to_db($ddtaquiz, 'correctness');
    $ddtaquiz->reviewmarks = ddtaquiz_review_option_form_to_db($ddtaquiz, 'marks');
    $ddtaquiz->reviewspecificfeedback = ddtaquiz_review_option_form_to_db($ddtaquiz, 'specificfeedback');
    $ddtaquiz->reviewgeneralfeedback = ddtaquiz_review_option_form_to_db($ddtaquiz, 'generalfeedback');
    $ddtaquiz->reviewrightanswer = ddtaquiz_review_option_form_to_db($ddtaquiz, 'rightanswer');
    $ddtaquiz->reviewoverallfeedback = ddtaquiz_review_option_form_to_db($ddtaquiz, 'overallfeedback');
    $ddtaquiz->reviewattempt |= mod_ddtaquiz_display_options::DURING;
    $ddtaquiz->reviewoverallfeedback &= ~mod_ddtaquiz_display_options::DURING;
}

/**
 * Helper function for {@link ddtaquiz_process_options()}.
 * @param object $fromform the sumbitted form date.
 * @param string $field one of the review option field names.
 */
function ddtaquiz_review_option_form_to_db($fromform, $field) {
    static $times = array(
        'during' => mod_ddtaquiz_display_options::DURING,
        'immediately' => mod_ddtaquiz_display_options::IMMEDIATELY_AFTER,
        'open' => mod_ddtaquiz_display_options::LATER_WHILE_OPEN,
        'closed' => mod_ddtaquiz_display_options::AFTER_CLOSE,
    );

    $review = 0;
    foreach ($times as $whenname => $when) {
        $fieldname = $field . $whenname;
        if (isset($fromform->$fieldname)) {
            $review |= $when;
            unset($fromform->$fieldname);
        }
    }

    return $review;
}

/**
 * This function is called at the end of ddtaquiz_add_instance
 * and ddtaquiz_update_instance, to do the common processing.
 *
 * @param object $ddtaquiz the ddtaquiz object.
 */
function ddtaquiz_after_add_or_update($ddtaquiz) {
    global $DB;
    $cmid = $ddtaquiz->coursemodule;

    // We need to use context now, so we need to make sure all needed info is already in db.
    $DB->set_field('course_modules', 'instance', $ddtaquiz->id, array('id'=>$cmid));
    $context = context_module::instance($cmid);

    // Save the feedback.
    $DB->delete_records('ddtaquiz_feedback', array('ddtaquizid' => $ddtaquiz->id));

    for ($i = 0; $i <= $ddtaquiz->feedbackboundarycount; $i++) {
        $feedback = new stdClass();
        $feedback->ddtaquizid = $ddtaquiz->id;
        $feedback->feedbacktext = $ddtaquiz->feedbacktext[$i]['text'];
        $feedback->feedbacktextformat = $ddtaquiz->feedbacktext[$i]['format'];
        $feedback->mingrade = $ddtaquiz->feedbackboundaries[$i];
        $feedback->maxgrade = $ddtaquiz->feedbackboundaries[$i - 1];
        $feedback->id = $DB->insert_record('ddtaquiz_feedback', $feedback);
        $feedbacktext = file_save_draft_area_files((int)$ddtaquiz->feedbacktext[$i]['itemid'],
                $context->id, 'mod_ddtaquiz', 'feedback', $feedback->id,
                array('subdirs' => false, 'maxfiles' => -1, 'maxbytes' => 0),
                $ddtaquiz->feedbacktext[$i]['text']);
        $DB->set_field('ddtaquiz_feedback', 'feedbacktext', $feedbacktext,
                array('id' => $feedback->id));
    }

    // Store any settings belonging to the access rules.
    ddtaquiz_access_manager::save_settings($ddtaquiz);

    // Update the events relating to this ddtaquiz.
    ddtaquiz_update_events($ddtaquiz);
    $completionexpected = (!empty($ddtaquiz->completionexpected)) ? $ddtaquiz->completionexpected : null;
    \core_completion\api::update_completion_date_event($ddtaquiz->coursemodule, 'ddtaquiz', $ddtaquiz->id, $completionexpected);

    // Update related grade item.
    ddtaquiz_grade_item_update($ddtaquiz);
}

/**
 * This function updates the events associated to the ddtaquiz.
 * If $override is non-zero, then it updates only the events
 * associated with the specified override.
 *
 * @uses DDTAQUIZ_MAX_EVENT_LENGTH
 * @param object $ddtaquiz the ddtaquiz object.
 * @param object optional $override limit to a specific override
 */
function ddtaquiz_update_events($ddtaquiz, $override = null) {
    global $DB;

    // Load the old events relating to this ddtaquiz.
    $conds = array('modulename'=>'ddtaquiz',
                   'instance'=>$ddtaquiz->id);
    if (!empty($override)) {
        // Only load events for this override.
        if (isset($override->userid)) {
            $conds['userid'] = $override->userid;
        } else {
            $conds['groupid'] = $override->groupid;
        }
    }
    $oldevents = $DB->get_records('event', $conds, 'id ASC');

    // Now make a to-do list of all that needs to be updated.
    if (empty($override)) {
        // We are updating the primary settings for the ddtaquiz, so we need to add all the overrides.
        $overrides = $DB->get_records('ddtaquiz_overrides', array('ddtaquiz' => $ddtaquiz->id), 'id ASC');
        // It is necessary to add an empty stdClass to the beginning of the array as the $oldevents
        // list contains the original (non-override) event for the module. If this is not included
        // the logic below will end up updating the wrong row when we try to reconcile this $overrides
        // list against the $oldevents list.
        array_unshift($overrides, new stdClass());
    } else {
        // Just do the one override.
        $overrides = array($override);
    }

    // Get group override priorities.
    $grouppriorities = ddtaquiz_get_group_override_priorities($ddtaquiz->id);

    foreach ($overrides as $current) {
        $groupid   = isset($current->groupid)?  $current->groupid : 0;
        $userid    = isset($current->userid)? $current->userid : 0;
        $timeopen  = isset($current->timeopen)?  $current->timeopen : $ddtaquiz->timeopen;
        $timeclose = isset($current->timeclose)? $current->timeclose : $ddtaquiz->timeclose;

        // Only add open/close events for an override if they differ from the ddtaquiz default.
        $addopen  = empty($current->id) || !empty($current->timeopen);
        $addclose = empty($current->id) || !empty($current->timeclose);

        if (!empty($ddtaquiz->coursemodule)) {
            $cmid = $ddtaquiz->coursemodule;
        } else {
            $cmid = get_coursemodule_from_instance('ddtaquiz', $ddtaquiz->id, $ddtaquiz->course)->id;
        }

        $event = new stdClass();
        $event->type = !$timeclose ? CALENDAR_EVENT_TYPE_ACTION : CALENDAR_EVENT_TYPE_STANDARD;
        $event->description = format_module_intro('ddtaquiz', $ddtaquiz, $cmid);
        // Events module won't show user events when the courseid is nonzero.
        $event->courseid    = ($userid) ? 0 : $ddtaquiz->course;
        $event->groupid     = $groupid;
        $event->userid      = $userid;
        $event->modulename  = 'ddtaquiz';
        $event->instance    = $ddtaquiz->id;
        $event->timestart   = $timeopen;
        $event->timeduration = max($timeclose - $timeopen, 0);
        $event->timesort    = $timeopen;
        $event->visible     = instance_is_visible('ddtaquiz', $ddtaquiz);
        $event->eventtype   = DDTAQUIZ_EVENT_TYPE_OPEN;
        $event->priority    = null;

        // Determine the event name and priority.
        if ($groupid) {
            // Group override event.
            $params = new stdClass();
            $params->ddtaquiz = $ddtaquiz->name;
            $params->group = groups_get_group_name($groupid);
            if ($params->group === false) {
                // Group doesn't exist, just skip it.
                continue;
            }
            $eventname = get_string('overridegroupeventname', 'ddtaquiz', $params);
            // Set group override priority.
            if ($grouppriorities !== null) {
                $openpriorities = $grouppriorities['open'];
                if (isset($openpriorities[$timeopen])) {
                    $event->priority = $openpriorities[$timeopen];
                }
            }
        } else if ($userid) {
            // User override event.
            $params = new stdClass();
            $params->ddtaquiz = $ddtaquiz->name;
            $eventname = get_string('overrideusereventname', 'ddtaquiz', $params);
            // Set user override priority.
            $event->priority = CALENDAR_EVENT_USER_OVERRIDE_PRIORITY;
        } else {
            // The parent event.
            $eventname = $ddtaquiz->name;
        }

        if ($addopen or $addclose) {
            // Separate start and end events.
            $event->timeduration  = 0;
            if ($timeopen && $addopen) {
                if ($oldevent = array_shift($oldevents)) {
                    $event->id = $oldevent->id;
                } else {
                    unset($event->id);
                }
                $event->name = get_string('ddtaquizeventopens', 'ddtaquiz', $eventname);
                // The method calendar_event::create will reuse a db record if the id field is set.
                calendar_event::create($event, false);
            }
            if ($timeclose && $addclose) {
                if ($oldevent = array_shift($oldevents)) {
                    $event->id = $oldevent->id;
                } else {
                    unset($event->id);
                }
                $event->type      = CALENDAR_EVENT_TYPE_ACTION;
                $event->name      = get_string('ddtaquizeventcloses', 'ddtaquiz', $eventname);
                $event->timestart = $timeclose;
                $event->timesort  = $timeclose;
                $event->eventtype = DDTAQUIZ_EVENT_TYPE_CLOSE;
                if ($groupid && $grouppriorities !== null) {
                    $closepriorities = $grouppriorities['close'];
                    if (isset($closepriorities[$timeclose])) {
                        $event->priority = $closepriorities[$timeclose];
                    }
                }
                calendar_event::create($event, false);
            }
        }
    }

    // Delete any leftover events.
    foreach ($oldevents as $badevent) {
        $badevent = calendar_event::load($badevent);
        $badevent->delete();
    }
}

/**
 * Calculates the priorities of timeopen and timeclose values for group overrides for a ddtaquiz.
 *
 * @param int $ddtaquizid The ddtaquiz ID.
 * @return array|null Array of group override priorities for open and close times. Null if there are no group overrides.
 */
function ddtaquiz_get_group_override_priorities($ddtaquizid) {
    global $DB;

    // Fetch group overrides.
    $where = 'ddtaquiz = :ddtaquiz AND groupid IS NOT NULL';
    $params = ['ddtaquiz' => $ddtaquizid];
    $overrides = $DB->get_records_select('ddtaquiz_overrides', $where, $params, '', 'id, timeopen, timeclose');
    if (!$overrides) {
        return null;
    }

    $grouptimeopen = [];
    $grouptimeclose = [];
    foreach ($overrides as $override) {
        if ($override->timeopen !== null && !in_array($override->timeopen, $grouptimeopen)) {
            $grouptimeopen[] = $override->timeopen;
        }
        if ($override->timeclose !== null && !in_array($override->timeclose, $grouptimeclose)) {
            $grouptimeclose[] = $override->timeclose;
        }
    }

    // Sort open times in ascending manner. The earlier open time gets higher priority.
    sort($grouptimeopen);
    // Set priorities.
    $opengrouppriorities = [];
    $openpriority = 1;
    foreach ($grouptimeopen as $timeopen) {
        $opengrouppriorities[$timeopen] = $openpriority++;
    }

    // Sort close times in descending manner. The later close time gets higher priority.
    rsort($grouptimeclose);
    // Set priorities.
    $closegrouppriorities = [];
    $closepriority = 1;
    foreach ($grouptimeclose as $timeclose) {
        $closegrouppriorities[$timeclose] = $closepriority++;
    }

    return [
        'open' => $opengrouppriorities,
        'close' => $closegrouppriorities
    ];
}

/**
 * List the actions that correspond to a view of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = 'r' and edulevel = LEVEL_PARTICIPATING will
 *       be considered as view action.
 *
 * @return array
 */
function ddtaquiz_get_view_actions() {
    return array('view', 'view all', 'report', 'review');
}

/**
 * List the actions that correspond to a post of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = ('c' || 'u' || 'd') and edulevel = LEVEL_PARTICIPATING
 *       will be considered as post action.
 *
 * @return array
 */
function ddtaquiz_get_post_actions() {
    return array('attempt', 'close attempt', 'preview', 'editquestions',
            'delete attempt', 'manualgrade');
}

/**
 * @param array $questionids of question ids.
 * @return bool whether any of these questions are used by any instance of this module.
 */
function ddtaquiz_questions_in_use($questionids) {
    global $DB, $CFG;
    require_once($CFG->libdir . '/questionlib.php');
    list($test, $params) = $DB->get_in_or_equal($questionids);
    return $DB->record_exists_select('ddtaquiz_slots',
            'questionid ' . $test, $params) || question_engine::questions_in_use(
            $questionids, new qubaid_join('{ddtaquiz_attempts} ddtaquiza',
            'ddtaquiza.uniqueid', 'ddtaquiza.preview = 0'));
}

/**
 * Implementation of the function for printing the form elements that control
 * whether the course reset functionality affects the ddtaquiz.
 *
 * @param $mform the course reset form that is being built.
 */
function ddtaquiz_reset_course_form_definition($mform) {
    $mform->addElement('header', 'ddtaquizheader', get_string('modulenameplural', 'ddtaquiz'));
    $mform->addElement('advcheckbox', 'reset_ddtaquiz_attempts',
            get_string('removeallddtaquizattempts', 'ddtaquiz'));
    $mform->addElement('advcheckbox', 'reset_ddtaquiz_user_overrides',
            get_string('removealluseroverrides', 'ddtaquiz'));
    $mform->addElement('advcheckbox', 'reset_ddtaquiz_group_overrides',
            get_string('removeallgroupoverrides', 'ddtaquiz'));
}

/**
 * Course reset form defaults.
 * @return array the defaults.
 */
function ddtaquiz_reset_course_form_defaults($course) {
    return array('reset_ddtaquiz_attempts' => 1,
                 'reset_ddtaquiz_group_overrides' => 1,
                 'reset_ddtaquiz_user_overrides' => 1);
}

/**
 * Removes all grades from gradebook
 *
 * @param int $courseid
 * @param string optional type
 */
function ddtaquiz_reset_gradebook($courseid, $type='') {
    global $CFG, $DB;

    $ddtaquizzes = $DB->get_records_sql("
            SELECT q.*, cm.idnumber as cmidnumber, q.course as courseid
            FROM {modules} m
            JOIN {course_modules} cm ON m.id = cm.module
            JOIN {ddtaquiz} q ON cm.instance = q.id
            WHERE m.name = 'ddtaquiz' AND cm.course = ?", array($courseid));

    foreach ($ddtaquizzes as $ddtaquiz) {
        ddtaquiz_grade_item_update($ddtaquiz, 'reset');
    }
}

/**
 * Actual implementation of the reset course functionality, delete all the
 * ddtaquiz attempts for course $data->courseid, if $data->reset_ddtaquiz_attempts is
 * set and true.
 *
 * Also, move the ddtaquiz open and close dates, if the course start date is changing.
 *
 * @param object $data the data submitted from the reset course.
 * @return array status array
 */
function ddtaquiz_reset_userdata($data) {
    global $CFG, $DB;
    require_once($CFG->libdir . '/questionlib.php');

    $componentstr = get_string('modulenameplural', 'ddtaquiz');
    $status = array();

    // Delete attempts.
    if (!empty($data->reset_ddtaquiz_attempts)) {
        question_engine::delete_questions_usage_by_activities(new qubaid_join(
                '{ddtaquiz_attempts} ddtaquiza JOIN {ddtaquiz} ddtaquiz ON ddtaquiza.ddtaquiz = ddtaquiz.id',
                'ddtaquiza.uniqueid', 'ddtaquiz.course = :ddtaquizcourseid',
                array('ddtaquizcourseid' => $data->courseid)));

        $DB->delete_records_select('ddtaquiz_attempts',
                'ddtaquiz IN (SELECT id FROM {ddtaquiz} WHERE course = ?)', array($data->courseid));
        $status[] = array(
            'component' => $componentstr,
            'item' => get_string('attemptsdeleted', 'ddtaquiz'),
            'error' => false);

        // Remove all grades from gradebook.
        $DB->delete_records_select('ddtaquiz_grades',
                'ddtaquiz IN (SELECT id FROM {ddtaquiz} WHERE course = ?)', array($data->courseid));
        if (empty($data->reset_gradebook_grades)) {
            ddtaquiz_reset_gradebook($data->courseid);
        }
        $status[] = array(
            'component' => $componentstr,
            'item' => get_string('gradesdeleted', 'ddtaquiz'),
            'error' => false);
    }

    // Remove user overrides.
    if (!empty($data->reset_ddtaquiz_user_overrides)) {
        $DB->delete_records_select('ddtaquiz_overrides',
                'ddtaquiz IN (SELECT id FROM {ddtaquiz} WHERE course = ?) AND userid IS NOT NULL', array($data->courseid));
        $status[] = array(
            'component' => $componentstr,
            'item' => get_string('useroverridesdeleted', 'ddtaquiz'),
            'error' => false);
    }
    // Remove group overrides.
    if (!empty($data->reset_ddtaquiz_group_overrides)) {
        $DB->delete_records_select('ddtaquiz_overrides',
                'ddtaquiz IN (SELECT id FROM {ddtaquiz} WHERE course = ?) AND groupid IS NOT NULL', array($data->courseid));
        $status[] = array(
            'component' => $componentstr,
            'item' => get_string('groupoverridesdeleted', 'ddtaquiz'),
            'error' => false);
    }

    // Updating dates - shift may be negative too.
    if ($data->timeshift) {
        $DB->execute("UPDATE {ddtaquiz_overrides}
                         SET timeopen = timeopen + ?
                       WHERE ddtaquiz IN (SELECT id FROM {ddtaquiz} WHERE course = ?)
                         AND timeopen <> 0", array($data->timeshift, $data->courseid));
        $DB->execute("UPDATE {ddtaquiz_overrides}
                         SET timeclose = timeclose + ?
                       WHERE ddtaquiz IN (SELECT id FROM {ddtaquiz} WHERE course = ?)
                         AND timeclose <> 0", array($data->timeshift, $data->courseid));

        // Any changes to the list of dates that needs to be rolled should be same during course restore and course reset.
        // See MDL-9367.
        shift_course_mod_dates('ddtaquiz', array('timeopen', 'timeclose'),
                $data->timeshift, $data->courseid);

        $status[] = array(
            'component' => $componentstr,
            'item' => get_string('openclosedatesupdated', 'ddtaquiz'),
            'error' => false);
    }

    return $status;
}

/**
 * Prints ddtaquiz summaries on MyMoodle Page
 *
 * @deprecated since 3.3
 * @todo The final deprecation of this function will take place in Moodle 3.7 - see MDL-57487.
 * @param array $courses
 * @param array $htmlarray
 */
function ddtaquiz_print_overview($courses, &$htmlarray) {
    global $USER, $CFG;

    debugging('The function ddtaquiz_print_overview() is now deprecated.', DEBUG_DEVELOPER);

    // These next 6 Lines are constant in all modules (just change module name).
    if (empty($courses) || !is_array($courses) || count($courses) == 0) {
        return array();
    }

    if (!$ddtaquizzes = get_all_instances_in_courses('ddtaquiz', $courses)) {
        return;
    }

    // Get the ddtaquizzes attempts.
    $attemptsinfo = [];
    $ddtaquizids = [];
    foreach ($ddtaquizzes as $ddtaquiz) {
        $ddtaquizids[] = $ddtaquiz->id;
        $attemptsinfo[$ddtaquiz->id] = ['count' => 0, 'hasfinished' => false];
    }
    $attempts = ddtaquiz_get_user_attempts($ddtaquizids, $USER->id);
    foreach ($attempts as $attempt) {
        $attemptsinfo[$attempt->ddtaquiz]['count']++;
        $attemptsinfo[$attempt->ddtaquiz]['hasfinished'] = true;
    }
    unset($attempts);

    // Fetch some language strings outside the main loop.
    $strddtaquiz = get_string('modulename', 'ddtaquiz');
    $strnoattempts = get_string('noattempts', 'ddtaquiz');

    // We want to list ddtaquizzes that are currently available, and which have a close date.
    // This is the same as what the lesson does, and the dabate is in MDL-10568.
    $now = time();
    foreach ($ddtaquizzes as $ddtaquiz) {
        if ($ddtaquiz->timeclose >= $now && $ddtaquiz->timeopen < $now) {
            $str = '';

            // Now provide more information depending on the uers's role.
            $context = context_module::instance($ddtaquiz->coursemodule);
            if (has_capability('mod/ddtaquiz:viewreports', $context)) {
                // For teacher-like people, show a summary of the number of student attempts.
                // The $ddtaquiz objects returned by get_all_instances_in_course have the necessary $cm
                // fields set to make the following call work.
                $str .= '<div class="info">' . ddtaquiz_num_attempt_summary($ddtaquiz, $ddtaquiz, true) . '</div>';

            } else if (has_any_capability(array('mod/ddtaquiz:reviewmyattempts', 'mod/ddtaquiz:attempt'), $context)) { // Student
                // For student-like people, tell them how many attempts they have made.

                if (isset($USER->id)) {
                    if ($attemptsinfo[$ddtaquiz->id]['hasfinished']) {
                        // The student's last attempt is finished.
                        continue;
                    }

                    if ($attemptsinfo[$ddtaquiz->id]['count'] > 0) {
                        $str .= '<div class="info">' .
                            get_string('numattemptsmade', 'ddtaquiz', $attemptsinfo[$ddtaquiz->id]['count']) . '</div>';
                    } else {
                        $str .= '<div class="info">' . $strnoattempts . '</div>';
                    }

                } else {
                    $str .= '<div class="info">' . $strnoattempts . '</div>';
                }

            } else {
                // For ayone else, there is no point listing this ddtaquiz, so stop processing.
                continue;
            }

            // Give a link to the ddtaquiz, and the deadline.
            $html = '<div class="ddtaquiz overview">' .
                    '<div class="name">' . $strddtaquiz . ': <a ' .
                    ($ddtaquiz->visible ? '' : ' class="dimmed"') .
                    ' href="' . $CFG->wwwroot . '/mod/ddtaquiz/view.php?id=' .
                    $ddtaquiz->coursemodule . '">' .
                    $ddtaquiz->name . '</a></div>';
            $html .= '<div class="info">' . get_string('ddtaquizcloseson', 'ddtaquiz',
                    userdate($ddtaquiz->timeclose)) . '</div>';
            $html .= $str;
            $html .= '</div>';
            if (empty($htmlarray[$ddtaquiz->course]['ddtaquiz'])) {
                $htmlarray[$ddtaquiz->course]['ddtaquiz'] = $html;
            } else {
                $htmlarray[$ddtaquiz->course]['ddtaquiz'] .= $html;
            }
        }
    }
}

/**
 * Return a textual summary of the number of attempts that have been made at a particular ddtaquiz,
 * returns '' if no attempts have been made yet, unless $returnzero is passed as true.
 *
 * @param object $ddtaquiz the ddtaquiz object. Only $ddtaquiz->id is used at the moment.
 * @param object $cm the cm object. Only $cm->course, $cm->groupmode and
 *      $cm->groupingid fields are used at the moment.
 * @param bool $returnzero if false (default), when no attempts have been
 *      made '' is returned instead of 'Attempts: 0'.
 * @param int $currentgroup if there is a concept of current group where this method is being called
 *         (e.g. a report) pass it in here. Default 0 which means no current group.
 * @return string a string like "Attempts: 123", "Attemtps 123 (45 from your groups)" or
 *          "Attemtps 123 (45 from this group)".
 */
function ddtaquiz_num_attempt_summary($ddtaquiz, $cm, $returnzero = false, $currentgroup = 0) {
    global $DB, $USER;
    $numattempts = $DB->count_records('ddtaquiz_attempts', array('ddtaquiz'=> $ddtaquiz->id, 'preview'=>0));
    if ($numattempts || $returnzero) {
        if (groups_get_activity_groupmode($cm)) {
            $a = new stdClass();
            $a->total = $numattempts;
            if ($currentgroup) {
                $a->group = $DB->count_records_sql('SELECT COUNT(DISTINCT qa.id) FROM ' .
                        '{ddtaquiz_attempts} qa JOIN ' .
                        '{groups_members} gm ON qa.userid = gm.userid ' .
                        'WHERE ddtaquiz = ? AND preview = 0 AND groupid = ?',
                        array($ddtaquiz->id, $currentgroup));
                return get_string('attemptsnumthisgroup', 'ddtaquiz', $a);
            } else if ($groups = groups_get_all_groups($cm->course, $USER->id, $cm->groupingid)) {
                list($usql, $params) = $DB->get_in_or_equal(array_keys($groups));
                $a->group = $DB->count_records_sql('SELECT COUNT(DISTINCT qa.id) FROM ' .
                        '{ddtaquiz_attempts} qa JOIN ' .
                        '{groups_members} gm ON qa.userid = gm.userid ' .
                        'WHERE ddtaquiz = ? AND preview = 0 AND ' .
                        "groupid $usql", array_merge(array($ddtaquiz->id), $params));
                return get_string('attemptsnumyourgroups', 'ddtaquiz', $a);
            }
        }
        return get_string('attemptsnum', 'ddtaquiz', $numattempts);
    }
    return '';
}

/**
 * Returns the same as {@link ddtaquiz_num_attempt_summary()} but wrapped in a link
 * to the ddtaquiz reports.
 *
 * @param object $ddtaquiz the ddtaquiz object. Only $ddtaquiz->id is used at the moment.
 * @param object $cm the cm object. Only $cm->course, $cm->groupmode and
 *      $cm->groupingid fields are used at the moment.
 * @param object $context the ddtaquiz context.
 * @param bool $returnzero if false (default), when no attempts have been made
 *      '' is returned instead of 'Attempts: 0'.
 * @param int $currentgroup if there is a concept of current group where this method is being called
 *         (e.g. a report) pass it in here. Default 0 which means no current group.
 * @return string HTML fragment for the link.
 */
function ddtaquiz_attempt_summary_link_to_reports($ddtaquiz, $cm, $context, $returnzero = false,
        $currentgroup = 0) {
    global $CFG;
    $summary = ddtaquiz_num_attempt_summary($ddtaquiz, $cm, $returnzero, $currentgroup);
    if (!$summary) {
        return '';
    }

    require_once($CFG->dirroot . '/mod/ddtaquiz/report/reportlib.php');
    $url = new moodle_url('/mod/ddtaquiz/report.php', array(
            'id' => $cm->id, 'mode' => ddtaquiz_report_default_report($context)));
    return html_writer::link($url, $summary);
}

/**
 * @param string $feature FEATURE_xx constant for requested feature
 * @return bool True if ddtaquiz supports feature
 */
function ddtaquiz_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:                    return true;
        case FEATURE_GROUPINGS:                 return true;
        case FEATURE_MOD_INTRO:                 return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:   return true;
        case FEATURE_COMPLETION_HAS_RULES:      return true;
        case FEATURE_GRADE_HAS_GRADE:           return true;
        case FEATURE_GRADE_OUTCOMES:            return true;
        case FEATURE_BACKUP_MOODLE2:            return true;
        case FEATURE_SHOW_DESCRIPTION:          return true;
        case FEATURE_CONTROLS_GRADE_VISIBILITY: return true;
        case FEATURE_USES_QUESTIONS:            return true;

        default: return null;
    }
}

/**
 * @return array all other caps used in module
 */
function ddtaquiz_get_extra_capabilities() {
    global $CFG;
    require_once($CFG->libdir . '/questionlib.php');
    $caps = question_get_all_capabilities();
    $caps[] = 'moodle/site:accessallgroups';
    return $caps;
}

/**
 * This function extends the settings navigation block for the site.
 *
 * It is safe to rely on PAGE here as we will only ever be within the module
 * context when this is called
 *
 * @param settings_navigation $settings
 * @param navigation_node $ddtaquiznode
 * @return void
 */
function ddtaquiz_extend_settings_navigation($settings, $ddtaquiznode) {
    global $PAGE, $CFG;

    // Require {@link questionlib.php}
    // Included here as we only ever want to include this file if we really need to.
    require_once($CFG->libdir . '/questionlib.php');

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

    if (has_capability('mod/ddtaquiz:manageoverrides', $PAGE->cm->context)) {
        $url = new moodle_url('/mod/ddtaquiz/overrides.php', array('cmid'=>$PAGE->cm->id));
        $node = navigation_node::create(get_string('groupoverrides', 'ddtaquiz'),
                new moodle_url($url, array('mode'=>'group')),
                navigation_node::TYPE_SETTING, null, 'mod_ddtaquiz_groupoverrides');
        $ddtaquiznode->add_node($node, $beforekey);

        $node = navigation_node::create(get_string('useroverrides', 'ddtaquiz'),
                new moodle_url($url, array('mode'=>'user')),
                navigation_node::TYPE_SETTING, null, 'mod_ddtaquiz_useroverrides');
        $ddtaquiznode->add_node($node, $beforekey);
    }

    if (has_capability('mod/ddtaquiz:manage', $PAGE->cm->context)) {
        $node = navigation_node::create(get_string('editddtaquiz', 'ddtaquiz'),
                new moodle_url('/mod/ddtaquiz/edit.php', array('cmid'=>$PAGE->cm->id)),
                navigation_node::TYPE_SETTING, null, 'mod_ddtaquiz_edit',
                new pix_icon('t/edit', ''));
        $ddtaquiznode->add_node($node, $beforekey);
    }

    if (has_capability('mod/ddtaquiz:preview', $PAGE->cm->context)) {
        $url = new moodle_url('/mod/ddtaquiz/startattempt.php',
                array('cmid'=>$PAGE->cm->id, 'sesskey'=>sesskey()));
        $node = navigation_node::create(get_string('preview', 'ddtaquiz'), $url,
                navigation_node::TYPE_SETTING, null, 'mod_ddtaquiz_preview',
                new pix_icon('i/preview', ''));
        $ddtaquiznode->add_node($node, $beforekey);
    }

    if (has_any_capability(array('mod/ddtaquiz:viewreports', 'mod/ddtaquiz:grade'), $PAGE->cm->context)) {
        require_once($CFG->dirroot . '/mod/ddtaquiz/report/reportlib.php');
        $reportlist = ddtaquiz_report_list($PAGE->cm->context);

        $url = new moodle_url('/mod/ddtaquiz/report.php',
                array('id' => $PAGE->cm->id, 'mode' => reset($reportlist)));
        $reportnode = $ddtaquiznode->add_node(navigation_node::create(get_string('results', 'ddtaquiz'), $url,
                navigation_node::TYPE_SETTING,
                null, null, new pix_icon('i/report', '')), $beforekey);

        foreach ($reportlist as $report) {
            $url = new moodle_url('/mod/ddtaquiz/report.php',
                    array('id' => $PAGE->cm->id, 'mode' => $report));
            $reportnode->add_node(navigation_node::create(get_string($report, 'ddtaquiz_'.$report), $url,
                    navigation_node::TYPE_SETTING,
                    null, 'ddtaquiz_report_' . $report, new pix_icon('i/item', '')));
        }
    }

    question_extend_settings_navigation($ddtaquiznode, $PAGE->cm->context)->trim_if_empty();
}

/**
 * Serves the ddtaquiz files.
 *
 * @package  mod_ddtaquiz
 * @category files
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - justsend the file
 */
function ddtaquiz_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {
    global $CFG, $DB;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_login($course, false, $cm);

    if (!$ddtaquiz = $DB->get_record('ddtaquiz', array('id'=>$cm->instance))) {
        return false;
    }

    // The 'intro' area is served by pluginfile.php.
    $fileareas = array('feedback');
    if (!in_array($filearea, $fileareas)) {
        return false;
    }

    $feedbackid = (int)array_shift($args);
    if (!$feedback = $DB->get_record('ddtaquiz_feedback', array('id'=>$feedbackid))) {
        return false;
    }

    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/mod_ddtaquiz/$filearea/$feedbackid/$relativepath";
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        return false;
    }
    send_stored_file($file, 0, 0, true, $options);
}

/**
 * Called via pluginfile.php -> question_pluginfile to serve files belonging to
 * a question in a question_attempt when that attempt is a ddtaquiz attempt.
 *
 * @package  mod_ddtaquiz
 * @category files
 * @param stdClass $course course settings object
 * @param stdClass $context context object
 * @param string $component the name of the component we are serving files for.
 * @param string $filearea the name of the file area.
 * @param int $qubaid the attempt usage id.
 * @param int $slot the id of a question in this ddtaquiz attempt.
 * @param array $args the remaining bits of the file path.
 * @param bool $forcedownload whether the user must be forced to download the file.
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - justsend the file
 */
function ddtaquiz_question_pluginfile($course, $context, $component,
        $filearea, $qubaid, $slot, $args, $forcedownload, array $options=array()) {
    global $CFG;
    require_once($CFG->dirroot . '/mod/ddtaquiz/locallib.php');

    $attemptobj = ddtaquiz_attempt::create_from_usage_id($qubaid);
    require_login($attemptobj->get_course(), false, $attemptobj->get_cm());

    if ($attemptobj->is_own_attempt() && !$attemptobj->is_finished()) {
        // In the middle of an attempt.
        if (!$attemptobj->is_preview_user()) {
            $attemptobj->require_capability('mod/ddtaquiz:attempt');
        }
        $isreviewing = false;

    } else {
        // Reviewing an attempt.
        $attemptobj->check_review_capability();
        $isreviewing = true;
    }

    if (!$attemptobj->check_file_access($slot, $isreviewing, $context->id,
            $component, $filearea, $args, $forcedownload)) {
        send_file_not_found();
    }

    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/$component/$filearea/$relativepath";
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        send_file_not_found();
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
}

/**
 * Return a list of page types
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 */
function ddtaquiz_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $module_pagetype = array(
        'mod-ddtaquiz-*'       => get_string('page-mod-ddtaquiz-x', 'ddtaquiz'),
        'mod-ddtaquiz-view'    => get_string('page-mod-ddtaquiz-view', 'ddtaquiz'),
        'mod-ddtaquiz-attempt' => get_string('page-mod-ddtaquiz-attempt', 'ddtaquiz'),
        'mod-ddtaquiz-summary' => get_string('page-mod-ddtaquiz-summary', 'ddtaquiz'),
        'mod-ddtaquiz-review'  => get_string('page-mod-ddtaquiz-review', 'ddtaquiz'),
        'mod-ddtaquiz-edit'    => get_string('page-mod-ddtaquiz-edit', 'ddtaquiz'),
        'mod-ddtaquiz-report'  => get_string('page-mod-ddtaquiz-report', 'ddtaquiz'),
    );
    return $module_pagetype;
}

/**
 * @return the options for ddtaquiz navigation.
 */
function ddtaquiz_get_navigation_options() {
    return array(
        DDTAQUIZ_NAVMETHOD_FREE => get_string('navmethod_free', 'ddtaquiz'),
        DDTAQUIZ_NAVMETHOD_SEQ  => get_string('navmethod_seq', 'ddtaquiz')
    );
}

/**
 * Obtains the automatic completion state for this ddtaquiz on any conditions
 * in ddtaquiz settings, such as if all attempts are used or a certain grade is achieved.
 *
 * @param object $course Course
 * @param object $cm Course-module
 * @param int $userid User ID
 * @param bool $type Type of comparison (or/and; can be used as return value if no conditions)
 * @return bool True if completed, false if not. (If no conditions, then return
 *   value depends on comparison type)
 */
function ddtaquiz_get_completion_state($course, $cm, $userid, $type) {
    global $DB;
    global $CFG;

    $ddtaquiz = $DB->get_record('ddtaquiz', array('id' => $cm->instance), '*', MUST_EXIST);
    if (!$ddtaquiz->completionattemptsexhausted && !$ddtaquiz->completionpass) {
        return $type;
    }

    // Check if the user has used up all attempts.
    if ($ddtaquiz->completionattemptsexhausted) {
        $attempts = ddtaquiz_get_user_attempts($ddtaquiz->id, $userid, 'finished', true);
        if ($attempts) {
            $lastfinishedattempt = end($attempts);
            $context = context_module::instance($cm->id);
            $ddtaquizobj = ddtaquiz::create($ddtaquiz->id, $userid);
            $accessmanager = new ddtaquiz_access_manager($ddtaquizobj, time(),
                    has_capability('mod/ddtaquiz:ignoretimelimits', $context, $userid, false));
            if ($accessmanager->is_finished(count($attempts), $lastfinishedattempt)) {
                return true;
            }
        }
    }

    // Check for passing grade.
    if ($ddtaquiz->completionpass) {
        require_once($CFG->libdir . '/gradelib.php');
        $item = grade_item::fetch(array('courseid' => $course->id, 'itemtype' => 'mod',
                'itemmodule' => 'ddtaquiz', 'iteminstance' => $cm->instance, 'outcomeid' => null));
        if ($item) {
            $grades = grade_grade::fetch_users_grades($item, array($userid), false);
            if (!empty($grades[$userid])) {
                return $grades[$userid]->is_passed($item);
            }
        }
    }
    return false;
}

/**
 * Check if the module has any update that affects the current user since a given time.
 *
 * @param  cm_info $cm course module data
 * @param  int $from the time to check updates from
 * @param  array $filter  if we need to check only specific updates
 * @return stdClass an object with the different type of areas indicating if they were updated or not
 * @since Moodle 3.2
 */
function ddtaquiz_check_updates_since(cm_info $cm, $from, $filter = array()) {
    global $DB, $USER, $CFG;
    require_once($CFG->dirroot . '/mod/ddtaquiz/locallib.php');

    $updates = course_check_module_updates_since($cm, $from, array(), $filter);

    // Check if questions were updated.
    $updates->questions = (object) array('updated' => false);
    $ddtaquizobj = ddtaquiz::create($cm->instance, $USER->id);
    $ddtaquizobj->preload_questions();
    $ddtaquizobj->load_questions();
    $questionids = array_keys($ddtaquizobj->get_questions());
    if (!empty($questionids)) {
        list($questionsql, $params) = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED);
        $select = 'id ' . $questionsql . ' AND (timemodified > :time1 OR timecreated > :time2)';
        $params['time1'] = $from;
        $params['time2'] = $from;
        $questions = $DB->get_records_select('question', $select, $params, '', 'id');
        if (!empty($questions)) {
            $updates->questions->updated = true;
            $updates->questions->itemids = array_keys($questions);
        }
    }

    // Check for new attempts or grades.
    $updates->attempts = (object) array('updated' => false);
    $updates->grades = (object) array('updated' => false);
    $select = 'ddtaquiz = ? AND userid = ? AND timemodified > ?';
    $params = array($cm->instance, $USER->id, $from);

    $attempts = $DB->get_records_select('ddtaquiz_attempts', $select, $params, '', 'id');
    if (!empty($attempts)) {
        $updates->attempts->updated = true;
        $updates->attempts->itemids = array_keys($attempts);
    }
    $grades = $DB->get_records_select('ddtaquiz_grades', $select, $params, '', 'id');
    if (!empty($grades)) {
        $updates->grades->updated = true;
        $updates->grades->itemids = array_keys($grades);
    }

    // Now, teachers should see other students updates.
    if (has_capability('mod/ddtaquiz:viewreports', $cm->context)) {
        $select = 'ddtaquiz = ? AND timemodified > ?';
        $params = array($cm->instance, $from);

        if (groups_get_activity_groupmode($cm) == SEPARATEGROUPS) {
            $groupusers = array_keys(groups_get_activity_shared_group_members($cm));
            if (empty($groupusers)) {
                return $updates;
            }
            list($insql, $inparams) = $DB->get_in_or_equal($groupusers);
            $select .= ' AND userid ' . $insql;
            $params = array_merge($params, $inparams);
        }

        $updates->userattempts = (object) array('updated' => false);
        $attempts = $DB->get_records_select('ddtaquiz_attempts', $select, $params, '', 'id');
        if (!empty($attempts)) {
            $updates->userattempts->updated = true;
            $updates->userattempts->itemids = array_keys($attempts);
        }

        $updates->usergrades = (object) array('updated' => false);
        $grades = $DB->get_records_select('ddtaquiz_grades', $select, $params, '', 'id');
        if (!empty($grades)) {
            $updates->usergrades->updated = true;
            $updates->usergrades->itemids = array_keys($grades);
        }
    }
    return $updates;
}

/**
 * Get icon mapping for font-awesome.
 */
function mod_ddtaquiz_get_fontawesome_icon_map() {
    return [
        'mod_ddtaquiz:navflagged' => 'fa-flag',
    ];
}

/**
 * This function receives a calendar event and returns the action associated with it, or null if there is none.
 *
 * This is used by block_myoverview in order to display the event appropriately. If null is returned then the event
 * is not displayed on the block.
 *
 * @param calendar_event $event
 * @param \core_calendar\action_factory $factory
 * @return \core_calendar\local\event\entities\action_interface|null
 */
function mod_ddtaquiz_core_calendar_provide_event_action(calendar_event $event,
                                                     \core_calendar\action_factory $factory) {
    global $CFG, $USER;

    require_once($CFG->dirroot . '/mod/ddtaquiz/locallib.php');

    $cm = get_fast_modinfo($event->courseid)->instances['ddtaquiz'][$event->instance];
    $ddtaquizobj = ddtaquiz::create($cm->instance, $USER->id);
    $ddtaquiz = $ddtaquizobj->get_ddtaquiz();

    // Check they have capabilities allowing them to view the ddtaquiz.
    if (!has_any_capability(array('mod/ddtaquiz:reviewmyattempts', 'mod/ddtaquiz:attempt'), $ddtaquizobj->get_context())) {
        return null;
    }

    ddtaquiz_update_effective_access($ddtaquiz, $USER->id);

    // Check if ddtaquiz is closed, if so don't display it.
    if (!empty($ddtaquiz->timeclose) && $ddtaquiz->timeclose <= time()) {
        return null;
    }

    $attempts = ddtaquiz_get_user_attempts($ddtaquizobj->get_ddtaquizid(), $USER->id);
    if (!empty($attempts)) {
        // The student's last attempt is finished.
        return null;
    }

    $name = get_string('attemptddtaquiznow', 'ddtaquiz');
    $url = new \moodle_url('/mod/ddtaquiz/view.php', [
        'id' => $cm->id
    ]);
    $itemcount = 1;
    $actionable = true;

    // Check if the ddtaquiz is not currently actionable.
    if (!empty($ddtaquiz->timeopen) && $ddtaquiz->timeopen > time()) {
        $actionable = false;
    }

    return $factory->create_instance(
        $name,
        $url,
        $itemcount,
        $actionable
    );
}

/**
 * Add a get_coursemodule_info function in case any ddtaquiz type wants to add 'extra' information
 * for the course (see resource).
 *
 * Given a course_module object, this function returns any "extra" information that may be needed
 * when printing this activity in a course listing.  See get_array_of_activities() in course/lib.php.
 *
 * @param stdClass $coursemodule The coursemodule object (record).
 * @return cached_cm_info An object on information that the courses
 *                        will know about (most noticeably, an icon).
 */
function ddtaquiz_get_coursemodule_info($coursemodule) {
    global $DB;

    $dbparams = ['id' => $coursemodule->instance];
    $fields = 'id, name, intro, introformat, completionattemptsexhausted, completionpass';
    if (!$ddtaquiz = $DB->get_record('ddtaquiz', $dbparams, $fields)) {
        return false;
    }

    $result = new cached_cm_info();
    $result->name = $ddtaquiz->name;

    if ($coursemodule->showdescription) {
        // Convert intro to html. Do not filter cached version, filters run at display time.
        $result->content = format_module_intro('ddtaquiz', $ddtaquiz, $coursemodule->id, false);
    }

    // Populate the custom completion rules as key => value pairs, but only if the completion mode is 'automatic'.
    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $result->customdata['customcompletionrules']['completionattemptsexhausted'] = $ddtaquiz->completionattemptsexhausted;
        $result->customdata['customcompletionrules']['completionpass'] = $ddtaquiz->completionpass;
    }

    return $result;
}

/**
 * Callback which returns human-readable strings describing the active completion custom rules for the module instance.
 *
 * @param cm_info|stdClass $cm object with fields ->completion and ->customdata['customcompletionrules']
 * @return array $descriptions the array of descriptions for the custom rules.
 */
function mod_ddtaquiz_get_completion_active_rule_descriptions($cm) {
    // Values will be present in cm_info, and we assume these are up to date.
    if (empty($cm->customdata['customcompletionrules'])
        || $cm->completion != COMPLETION_TRACKING_AUTOMATIC) {
        return [];
    }

    $descriptions = [];
    foreach ($cm->customdata['customcompletionrules'] as $key => $val) {
        switch ($key) {
            case 'completionattemptsexhausted':
                if (empty($val)) {
                    continue;
                }
                $descriptions[] = get_string('completionattemptsexhausteddesc', 'ddtaquiz');
                break;
            case 'completionpass':
                if (empty($val)) {
                    continue;
                }
                $descriptions[] = get_string('completionpassdesc', 'ddtaquiz', format_time($val));
                break;
            default:
                break;
        }
    }
    return $descriptions;
}

/**
 * Returns the min and max values for the timestart property of a ddtaquiz
 * activity event.
 *
 * The min and max values will be the timeopen and timeclose properties
 * of the ddtaquiz, respectively, if they are set.
 *
 * If either value isn't set then null will be returned instead to
 * indicate that there is no cutoff for that value.
 *
 * If the vent has no valid timestart range then [false, false] will
 * be returned. This is the case for overriden events.
 *
 * A minimum and maximum cutoff return value will look like:
 * [
 *     [1505704373, 'The date must be after this date'],
 *     [1506741172, 'The date must be before this date']
 * ]
 *
 * @throws \moodle_exception
 * @param \calendar_event $event The calendar event to get the time range for
 * @param stdClass $ddtaquiz The module instance to get the range from
 * @return array
 */
function mod_ddtaquiz_core_calendar_get_valid_event_timestart_range(\calendar_event $event, \stdClass $ddtaquiz) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/mod/ddtaquiz/locallib.php');

    // Overrides do not have a valid timestart range.
    if (ddtaquiz_is_overriden_calendar_event($event)) {
        return [false, false];
    }

    $mindate = null;
    $maxdate = null;

    if ($event->eventtype == DDTAQUIZ_EVENT_TYPE_OPEN) {
        if (!empty($ddtaquiz->timeclose)) {
            $maxdate = [
                $ddtaquiz->timeclose,
                get_string('openafterclose', 'ddtaquiz')
            ];
        }
    } else if ($event->eventtype == DDTAQUIZ_EVENT_TYPE_CLOSE) {
        if (!empty($ddtaquiz->timeopen)) {
            $mindate = [
                $ddtaquiz->timeopen,
                get_string('closebeforeopen', 'ddtaquiz')
            ];
        }
    }

    return [$mindate, $maxdate];
}

/**
 * This function will update the ddtaquiz module according to the
 * event that has been modified.
 *
 * It will set the timeopen or timeclose value of the ddtaquiz instance
 * according to the type of event provided.
 *
 * @throws \moodle_exception
 * @param \calendar_event $event A ddtaquiz activity calendar event
 * @param \stdClass $ddtaquiz A ddtaquiz activity instance
 */
function mod_ddtaquiz_core_calendar_event_timestart_updated(\calendar_event $event, \stdClass $ddtaquiz) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/mod/ddtaquiz/locallib.php');

    if (!in_array($event->eventtype, [DDTAQUIZ_EVENT_TYPE_OPEN, DDTAQUIZ_EVENT_TYPE_CLOSE])) {
        // This isn't an event that we care about so we can ignore it.
        return;
    }

    $courseid = $event->courseid;
    $modulename = $event->modulename;
    $instanceid = $event->instance;
    $modified = false;
    $closedatechanged = false;

    // Something weird going on. The event is for a different module so
    // we should ignore it.
    if ($modulename != 'ddtaquiz') {
        return;
    }

    if ($ddtaquiz->id != $instanceid) {
        // The provided ddtaquiz instance doesn't match the event so
        // there is nothing to do here.
        return;
    }

    // We don't update the activity if it's an override event that has
    // been modified.
    if (ddtaquiz_is_overriden_calendar_event($event)) {
        return;
    }

    $coursemodule = get_fast_modinfo($courseid)->instances[$modulename][$instanceid];
    $context = context_module::instance($coursemodule->id);

    // The user does not have the capability to modify this activity.
    if (!has_capability('moodle/course:manageactivities', $context)) {
        return;
    }

    if ($event->eventtype == DDTAQUIZ_EVENT_TYPE_OPEN) {
        // If the event is for the ddtaquiz activity opening then we should
        // set the start time of the ddtaquiz activity to be the new start
        // time of the event.
        if ($ddtaquiz->timeopen != $event->timestart) {
            $ddtaquiz->timeopen = $event->timestart;
            $modified = true;
        }
    } else if ($event->eventtype == DDTAQUIZ_EVENT_TYPE_CLOSE) {
        // If the event is for the ddtaquiz activity closing then we should
        // set the end time of the ddtaquiz activity to be the new start
        // time of the event.
        if ($ddtaquiz->timeclose != $event->timestart) {
            $ddtaquiz->timeclose = $event->timestart;
            $modified = true;
            $closedatechanged = true;
        }
    }

    if ($modified) {
        $ddtaquiz->timemodified = time();
        $DB->update_record('ddtaquiz', $ddtaquiz);

        if ($closedatechanged) {
            ddtaquiz_update_open_attempts(array('ddtaquizid' => $ddtaquiz->id));
        }

        // Delete any previous preview attempts.
        ddtaquiz_delete_previews($ddtaquiz);
        ddtaquiz_update_events($ddtaquiz);
        $event = \core\event\course_module_updated::create_from_cm($coursemodule, $context);
        $event->trigger();
    }
}

/**
 * Generates the question bank in a fragment output. This allows
 * the question bank to be displayed in a modal.
 *
 * The only expected argument provided in the $args array is
 * 'querystring'. The value should be the list of parameters
 * URL encoded and used to build the question bank page.
 *
 * The individual list of parameters expected can be found in
 * question_build_edit_resources.
 *
 * @param array $args The fragment arguments.
 * @return string The rendered mform fragment.
 */
function mod_ddtaquiz_output_fragment_ddtaquiz_question_bank($args) {
    global $CFG, $DB, $PAGE;
    require_once($CFG->dirroot . '/mod/ddtaquiz/locallib.php');
    require_once($CFG->dirroot . '/question/editlib.php');

    $querystring = preg_replace('/^\?/', '', $args['querystring']);
    $params = [];
    parse_str($querystring, $params);

    // Build the required resources. The $params are all cleaned as
    // part of this process.
    list($thispageurl, $contexts, $cmid, $cm, $ddtaquiz, $pagevars) =
            question_build_edit_resources('editq', '/mod/ddtaquiz/edit.php', $params);

    // Get the course object and related bits.
    $course = $DB->get_record('course', array('id' => $ddtaquiz->course), '*', MUST_EXIST);
    require_capability('mod/ddtaquiz:manage', $contexts->lowest());

    // Create ddtaquiz question bank view.
    $questionbank = new mod_ddtaquiz\question\bank\custom_view($contexts, $thispageurl, $course, $cm, $ddtaquiz);
    $questionbank->set_ddtaquiz_has_attempts(ddtaquiz_has_attempts($ddtaquiz->id));

    // Output.
    $renderer = $PAGE->get_renderer('mod_ddtaquiz', 'edit');
    return $renderer->question_bank_contents($questionbank, $pagevars);
}

/**
 * Generates the add random question in a fragment output. This allows the
 * form to be rendered in javascript, for example inside a modal.
 *
 * The required arguments as keys in the $args array are:
 *      cat {string} The category and category context ids comma separated.
 *      addonpage {int} The page id to add this question to.
 *      returnurl {string} URL to return to after form submission.
 *      cmid {int} The course module id the questions are being added to.
 *
 * @param array $args The fragment arguments.
 * @return string The rendered mform fragment.
 */
function mod_ddtaquiz_output_fragment_add_random_question_form($args) {
    global $CFG;
    require_once($CFG->dirroot . '/mod/ddtaquiz/addrandomform.php');

    $contexts = new \question_edit_contexts($args['context']);
    $formoptions = [
        'contexts' => $contexts,
        'cat' => $args['cat']
    ];
    $formdata = [
        'category' => $args['cat'],
        'addonpage' => $args['addonpage'],
        'returnurl' => $args['returnurl'],
        'cmid' => $args['cmid']
    ];

    $form = new ddtaquiz_add_random_form(
        new \moodle_url('/mod/ddtaquiz/addrandom.php'),
        $formoptions,
        'post',
        '',
        null,
        true,
        $formdata
    );
    $form->set_data($formdata);

    return $form->render();
}
