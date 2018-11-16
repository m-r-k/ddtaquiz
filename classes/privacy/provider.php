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
 * Privacy Subsystem implementation for mod_ddtaquiz.
 *
 * @package    mod_ddtaquiz
 * @copyright  2018 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_ddtaquiz\privacy;

use \core_privacy\local\request\writer;
use \core_privacy\local\request\transform;
use \core_privacy\local\request\contextlist;
use \core_privacy\local\request\approved_contextlist;
use \core_privacy\local\request\deletion_criteria;
use \core_privacy\local\metadata\collection;
use \core_privacy\manager;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/ddtaquiz/lib.php');
require_once($CFG->dirroot . '/mod/ddtaquiz/locallib.php');

/**
 * Privacy Subsystem implementation for mod_ddtaquiz.
 *
 * @copyright  2018 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    // This plugin has data.
    \core_privacy\local\metadata\provider,

    // This plugin currently implements the original plugin_provider interface.
    \core_privacy\local\request\plugin\provider {

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param   collection  $items  The collection to add metadata to.
     * @return  collection  The array of metadata
     */
    public static function get_metadata(collection $items) : collection {
        // The table 'ddtaquiz' stores a record for each ddtaquiz.
        // It does not contain user personal data, but data is returned from it for contextual requirements.

        // The table 'ddtaquiz_attempts' stores a record of each ddtaquiz attempt.
        // It contains a userid which links to the user making the attempt and contains information about that attempt.
        $items->add_database_table('ddtaquiz_attempts', [
                'attempt'               => 'privacy:metadata:ddtaquiz_attempts:attempt',
                'currentpage'           => 'privacy:metadata:ddtaquiz_attempts:currentpage',
                'preview'               => 'privacy:metadata:ddtaquiz_attempts:preview',
                'state'                 => 'privacy:metadata:ddtaquiz_attempts:state',
                'timestart'             => 'privacy:metadata:ddtaquiz_attempts:timestart',
                'timefinish'            => 'privacy:metadata:ddtaquiz_attempts:timefinish',
                'timemodified'          => 'privacy:metadata:ddtaquiz_attempts:timemodified',
                'timemodifiedoffline'   => 'privacy:metadata:ddtaquiz_attempts:timemodifiedoffline',
                'timecheckstate'        => 'privacy:metadata:ddtaquiz_attempts:timecheckstate',
                'sumgrades'             => 'privacy:metadata:ddtaquiz_attempts:sumgrades',
            ], 'privacy:metadata:ddtaquiz_attempts');

        // The table 'ddtaquiz_feedback' contains the feedback responses which will be shown to users depending upon the
        // grade they achieve in the ddtaquiz.
        // It does not identify the user who wrote the feedback item so cannot be returned directly and is not
        // described, but relevant feedback items will be included with the ddtaquiz export for a user who has a grade.

        // The table 'ddtaquiz_grades' contains the current grade for each ddtaquiz/user combination.
        $items->add_database_table('ddtaquiz_grades', [
                'ddtaquiz'                  => 'privacy:metadata:ddtaquiz_grades:ddtaquiz',
                'userid'                => 'privacy:metadata:ddtaquiz_grades:userid',
                'grade'                 => 'privacy:metadata:ddtaquiz_grades:grade',
                'timemodified'          => 'privacy:metadata:ddtaquiz_grades:timemodified',
            ], 'privacy:metadata:ddtaquiz_grades');

        // The table 'ddtaquiz_overrides' contains any user or group overrides for users.
        // It should be included where data exists for a user.
        $items->add_database_table('ddtaquiz_overrides', [
                'ddtaquiz'                  => 'privacy:metadata:ddtaquiz_overrides:ddtaquiz',
                'userid'                => 'privacy:metadata:ddtaquiz_overrides:userid',
                'timeopen'              => 'privacy:metadata:ddtaquiz_overrides:timeopen',
                'timeclose'             => 'privacy:metadata:ddtaquiz_overrides:timeclose',
                'timelimit'             => 'privacy:metadata:ddtaquiz_overrides:timelimit',
            ], 'privacy:metadata:ddtaquiz_overrides');

        // These define the structure of the ddtaquiz.

        // The table 'ddtaquiz_sections' contains data about the structure of a ddtaquiz.
        // It does not contain any user identifying data and does not need a mapping.

        // The table 'ddtaquiz_slots' contains data about the structure of a ddtaquiz.
        // It does not contain any user identifying data and does not need a mapping.

        // The table 'ddtaquiz_reports' does not contain any user identifying data and does not need a mapping.

        // The table 'ddtaquiz_statistics' contains abstract statistics about question usage and cannot be mapped to any
        // specific user.
        // It does not contain any user identifying data and does not need a mapping.

        // The ddtaquiz links to the 'core_question' subsystem for all question functionality.
        $items->add_subsystem_link('core_question', [], 'privacy:metadata:core_question');

        // The ddtaquiz has two subplugins..
        $items->add_plugintype_link('ddtaquiz', [], 'privacy:metadata:ddtaquiz');
        $items->add_plugintype_link('ddtaquizaccess', [], 'privacy:metadata:ddtaquizaccess');

        // Although the ddtaquiz supports the core_completion API and defines custom completion items, these will be
        // noted by the manager as all activity modules are capable of supporting this functionality.

        return $items;
    }

    /**
     * Get the list of contexts where the specified user has attempted a ddtaquiz, or been involved with manual marking
     * and/or grading of a ddtaquiz.
     *
     * @param   int             $userid The user to search.
     * @return  contextlist     $contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid) : contextlist {
        // Get the SQL used to link indirect question usages for the user.
        // This includes where a user is the manual marker on a question attempt.
        $qubaid = \core_question\privacy\provider::get_related_question_usages_for_user('rel', 'mod_ddtaquiz', 'qa.uniqueid', $userid);

        // Select the context of any ddtaquiz attempt where a user has an attempt, plus the related usages.
        $sql = "SELECT c.id
                  FROM {context} c
                  JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {ddtaquiz} q ON q.id = cm.instance
                  JOIN {ddtaquiz_attempts} qa ON qa.ddtaquiz = q.id
             LEFT JOIN {ddtaquiz_overrides} qo ON qo.ddtaquiz = q.id AND qo.userid = :qouserid
            " . $qubaid->from . "
            WHERE (
                qa.userid = :qauserid OR
                " . $qubaid->where() . " OR
                qo.id IS NOT NULL
            ) AND qa.preview = 0
        ";

        $params = array_merge(
                [
                    'contextlevel'      => CONTEXT_MODULE,
                    'modname'           => 'ddtaquiz',
                    'qauserid'          => $userid,
                    'qouserid'          => $userid,
                ],
                $qubaid->from_where_params()
            );

        $resultset = new contextlist();
        $resultset->add_from_sql($sql, $params);

        return $resultset;
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param   approved_contextlist    $contextlist    The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (!count($contextlist)) {
            return;
        }

        $user = $contextlist->get_user();
        $userid = $user->id;
        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);

        $sql = "SELECT
                    q.*,
                    qg.id AS hasgrade,
                    qg.grade AS bestgrade,
                    qg.timemodified AS grademodified,
                    qo.id AS hasoverride,
                    qo.timeopen AS override_timeopen,
                    qo.timeclose AS override_timeclose,
                    qo.timelimit AS override_timelimit,
                    c.id AS contextid,
                    cm.id AS cmid
                  FROM {context} c
            INNER JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
            INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
            INNER JOIN {ddtaquiz} q ON q.id = cm.instance
             LEFT JOIN {ddtaquiz_overrides} qo ON qo.ddtaquiz = q.id AND qo.userid = :qouserid
             LEFT JOIN {ddtaquiz_grades} qg ON qg.ddtaquiz = q.id AND qg.userid = :qguserid
                 WHERE c.id {$contextsql}";

        $params = [
            'contextlevel'      => CONTEXT_MODULE,
            'modname'           => 'ddtaquiz',
            'qguserid'          => $userid,
            'qouserid'          => $userid,
        ];
        $params += $contextparams;

        // Fetch the individual ddtaquizzes.
        $ddtaquizzes = $DB->get_recordset_sql($sql, $params);
        foreach ($ddtaquizzes as $ddtaquiz) {
            list($course, $cm) = get_course_and_cm_from_cmid($ddtaquiz->cmid, 'ddtaquiz');
            $ddtaquizobj = new \ddtaquiz($ddtaquiz, $cm, $course);
            $context = $ddtaquizobj->get_context();

            $ddtaquizdata = \core_privacy\local\request\helper::get_context_data($context, $contextlist->get_user());
            \core_privacy\local\request\helper::export_context_files($context, $contextlist->get_user());

            if (!empty($ddtaquizdata->timeopen)) {
                $ddtaquizdata->timeopen = transform::datetime($ddtaquiz->timeopen);
            }
            if (!empty($ddtaquizdata->timeclose)) {
                $ddtaquizdata->timeclose = transform::datetime($ddtaquiz->timeclose);
            }
            if (!empty($ddtaquizdata->timelimit)) {
                $ddtaquizdata->timelimit = $ddtaquiz->timelimit;
            }

            if (!empty($ddtaquiz->hasoverride)) {
                $ddtaquizdata->override = (object) [];

                if (!empty($ddtaquizdata->override_override_timeopen)) {
                    $ddtaquizdata->override->timeopen = transform::datetime($ddtaquiz->override_timeopen);
                }
                if (!empty($ddtaquizdata->override_timeclose)) {
                    $ddtaquizdata->override->timeclose = transform::datetime($ddtaquiz->override_timeclose);
                }
                if (!empty($ddtaquizdata->override_timelimit)) {
                    $ddtaquizdata->override->timelimit = $ddtaquiz->override_timelimit;
                }
            }

            $ddtaquizdata->accessdata = (object) [];

            $components = \core_component::get_plugin_list('ddtaquizaccess');
            $exportparams = [
                    $ddtaquizobj,
                    $user,
                ];
            foreach (array_keys($components) as $component) {
                $classname = manager::get_provider_classname_for_component("ddtaquizaccess_$component");
                if (class_exists($classname) && is_subclass_of($classname, ddtaquizaccess_provider::class)) {
                    $result = component_class_callback($classname, 'export_ddtaquizaccess_user_data', $exportparams);
                    if (count((array) $result)) {
                        $ddtaquizdata->accessdata->$component = $result;
                    }
                }
            }

            if (empty((array) $ddtaquizdata->accessdata)) {
                unset($ddtaquizdata->accessdata);
            }

            writer::with_context($context)
                ->export_data([], $ddtaquizdata);
        }
        $ddtaquizzes->close();

        // Store all ddtaquiz attempt data.
        static::export_ddtaquiz_attempts($contextlist);
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param   context                 $context   The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        if ($context->contextlevel != CONTEXT_MODULE) {
            // Only ddtaquiz module will be handled.
            return;
        }

        $cm = get_coursemodule_from_id('ddtaquiz', $context->instanceid);
        if (!$cm) {
            // Only ddtaquiz module will be handled.
            return;
        }

        $ddtaquizobj = \ddtaquiz::create($cm->instance);
        $ddtaquiz = $ddtaquizobj->get_ddtaquiz();

        // Handle the 'ddtaquizaccess' subplugin.
        manager::plugintype_class_callback(
                'ddtaquizaccess',
                ddtaquizaccess_provider::class,
                'delete_subplugin_data_for_all_users_in_context',
                [$ddtaquizobj]
            );

        // Delete all overrides - do not log.
        ddtaquiz_delete_all_overrides($ddtaquiz, false);

        // This will delete all question attempts, ddtaquiz attempts, and ddtaquiz grades for this ddtaquiz.
        ddtaquiz_delete_all_attempts($ddtaquiz);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param   approved_contextlist    $contextlist    The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        foreach ($contextlist as $context) {
            if ($context->contextlevel != CONTEXT_MODULE) {
            // Only ddtaquiz module will be handled.
                continue;
            }

            $cm = get_coursemodule_from_id('ddtaquiz', $context->instanceid);
            if (!$cm) {
                // Only ddtaquiz module will be handled.
                continue;
            }

            // Fetch the details of the data to be removed.
            $ddtaquizobj = \ddtaquiz::create($cm->instance);
            $ddtaquiz = $ddtaquizobj->get_ddtaquiz();
            $user = $contextlist->get_user();

            // Handle the 'ddtaquizaccess' ddtaquizaccess.
            manager::plugintype_class_callback(
                    'ddtaquizaccess',
                    ddtaquizaccess_provider::class,
                    'delete_ddtaquizaccess_data_for_user',
                    [$ddtaquizobj, $user]
                );

            // Remove overrides for this user.
            $overrides = $DB->get_records('ddtaquiz_overrides' , [
                'ddtaquiz' => $ddtaquizobj->get_ddtaquizid(),
                'userid' => $user->id,
            ]);

            foreach ($overrides as $override) {
                ddtaquiz_delete_override($ddtaquiz, $override->id, false);
            }

            // This will delete all question attempts, ddtaquiz attempts, and ddtaquiz grades for this ddtaquiz.
            ddtaquiz_delete_user_attempts($ddtaquizobj, $user);
        }
    }

    /**
     * Store all ddtaquiz attempts for the contextlist.
     *
     * @param   approved_contextlist    $contextlist
     */
    protected static function export_ddtaquiz_attempts(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;
        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);
        $qubaid = \core_question\privacy\provider::get_related_question_usages_for_user('rel', 'mod_ddtaquiz', 'qa.uniqueid', $userid);

        $sql = "SELECT
                    c.id AS contextid,
                    cm.id AS cmid,
                    qa.*
                  FROM {context} c
                  JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'ddtaquiz'
                  JOIN {ddtaquiz} q ON q.id = cm.instance
                  JOIN {ddtaquiz_attempts} qa ON qa.ddtaquiz = q.id
            " . $qubaid->from. "
            WHERE (
                qa.userid = :qauserid OR
                " . $qubaid->where() . "
            ) AND qa.preview = 0
        ";

        $params = array_merge(
                [
                    'contextlevel'      => CONTEXT_MODULE,
                    'qauserid'          => $userid,
                ],
                $qubaid->from_where_params()
            );

        $attempts = $DB->get_recordset_sql($sql, $params);
        foreach ($attempts as $attempt) {
            $ddtaquiz = $DB->get_record('ddtaquiz', ['id' => $attempt->ddtaquiz]);
            $context = \context_module::instance($attempt->cmid);
            $attemptsubcontext = helper::get_ddtaquiz_attempt_subcontext($attempt, $contextlist->get_user());
            $options = ddtaquiz_get_review_options($ddtaquiz, $attempt, $context);

            if ($attempt->userid == $userid) {
                // This attempt was made by the user.
                // They 'own' all data on it.
                // Store the question usage data.
                \core_question\privacy\provider::export_question_usage($userid,
                        $context,
                        $attemptsubcontext,
                        $attempt->uniqueid,
                        $options,
                        true
                    );

                // Store the ddtaquiz attempt data.
                $data = (object) [
                    'state' => \ddtaquiz_attempt::state_name($attempt->state),
                ];

                if (!empty($attempt->timestart)) {
                    $data->timestart = transform::datetime($attempt->timestart);
                }
                if (!empty($attempt->timefinish)) {
                    $data->timefinish = transform::datetime($attempt->timefinish);
                }
                if (!empty($attempt->timemodified)) {
                    $data->timemodified = transform::datetime($attempt->timemodified);
                }
                if (!empty($attempt->timemodifiedoffline)) {
                    $data->timemodifiedoffline = transform::datetime($attempt->timemodifiedoffline);
                }
                if (!empty($attempt->timecheckstate)) {
                    $data->timecheckstate = transform::datetime($attempt->timecheckstate);
                }

                if ($options->marks == \question_display_options::MARK_AND_MAX) {
                    $grade = ddtaquiz_rescale_grade($attempt->sumgrades, $ddtaquiz, false);
                    $data->grade = (object) [
                            'grade' => ddtaquiz_format_grade($ddtaquiz, $grade),
                            'feedback' => ddtaquiz_feedback_for_grade($grade, $ddtaquiz, $context),
                        ];
                }

                writer::with_context($context)
                    ->export_data($attemptsubcontext, $data);
            } else {
                // This attempt was made by another user.
                // The current user may have marked part of the ddtaquiz attempt.
                \core_question\privacy\provider::export_question_usage(
                        $userid,
                        $context,
                        $attemptsubcontext,
                        $attempt->uniqueid,
                        $options,
                        false
                    );
            }
        }
        $attempts->close();
    }
}
