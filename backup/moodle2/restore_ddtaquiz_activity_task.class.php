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
 * @package    mod_ddtaquiz
 * @subpackage backup-moodle2
 * @copyright  2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/ddtaquiz/backup/moodle2/restore_ddtaquiz_stepslib.php');


/**
 * ddtaquiz restore task that provides all the settings and steps to perform one
 * complete restore of the activity
 *
 * @copyright  2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_ddtaquiz_activity_task extends restore_activity_task {

    /**
     * Define (add) particular settings this activity can have
     */
    protected function define_my_settings() {
        // No particular settings for this activity.
    }

    /**
     * Define (add) particular steps this activity can have
     */
    protected function define_my_steps() {
        // Ddtaquiz only has one structure step.
        $this->add_step(new restore_ddtaquiz_activity_structure_step('ddtaquiz_structure', 'ddtaquiz.xml'));
    }

    /**
     * Define the contents in the activity that must be
     * processed by the link decoder
     */
    public static function define_decode_contents() {
        $contents = array();

        $contents[] = new restore_decode_content('ddtaquiz', array('intro'), 'ddtaquiz');
        $contents[] = new restore_decode_content('ddtaquiz_feedback',
                array('feedbacktext'), 'ddtaquiz_feedback');

        return $contents;
    }

    /**
     * Define the decoding rules for links belonging
     * to the activity to be executed by the link decoder
     */
    public static function define_decode_rules() {
        $rules = array();

        $rules[] = new restore_decode_rule('DDTAQUIZVIEWBYID',
                '/mod/ddtaquiz/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('DDTAQUIZVIEWBYQ',
                '/mod/ddtaquiz/view.php?q=$1', 'ddtaquiz');
        $rules[] = new restore_decode_rule('DDTAQUIZINDEX',
                '/mod/ddtaquiz/index.php?id=$1', 'course');

        return $rules;

    }

    /**
     * Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * ddtaquiz logs. It must return one array
     * of {@link restore_log_rule} objects
     */
    public static function define_restore_log_rules() {
        $rules = array();

        $rules[] = new restore_log_rule('ddtaquiz', 'add',
                'view.php?id={course_module}', '{ddtaquiz}');
        $rules[] = new restore_log_rule('ddtaquiz', 'update',
                'view.php?id={course_module}', '{ddtaquiz}');
        $rules[] = new restore_log_rule('ddtaquiz', 'view',
                'view.php?id={course_module}', '{ddtaquiz}');
        $rules[] = new restore_log_rule('ddtaquiz', 'preview',
                'view.php?id={course_module}', '{ddtaquiz}');
        $rules[] = new restore_log_rule('ddtaquiz', 'report',
                'report.php?id={course_module}', '{ddtaquiz}');
        $rules[] = new restore_log_rule('ddtaquiz', 'editquestions',
                'view.php?id={course_module}', '{ddtaquiz}');
        $rules[] = new restore_log_rule('ddtaquiz', 'delete attempt',
                'report.php?id={course_module}', '[oldattempt]');
        $rules[] = new restore_log_rule('ddtaquiz', 'edit override',
                'overrideedit.php?id={ddtaquiz_override}', '{ddtaquiz}');
        $rules[] = new restore_log_rule('ddtaquiz', 'delete override',
                'overrides.php.php?cmid={course_module}', '{ddtaquiz}');
        $rules[] = new restore_log_rule('ddtaquiz', 'addcategory',
                'view.php?id={course_module}', '{question_category}');
        $rules[] = new restore_log_rule('ddtaquiz', 'view summary',
                'summary.php?attempt={ddtaquiz_attempt}', '{ddtaquiz}');
        $rules[] = new restore_log_rule('ddtaquiz', 'manualgrade',
                'comment.php?attempt={ddtaquiz_attempt}&question={question}', '{ddtaquiz}');
        $rules[] = new restore_log_rule('ddtaquiz', 'manualgrading',
                'report.php?mode=grading&q={ddtaquiz}', '{ddtaquiz}');
        // All the ones calling to review.php have two rules to handle both old and new urls
        // in any case they are always converted to new urls on restore.
        // TODO: In Moodle 2.x (x >= 5) kill the old rules.
        // Note we are using the 'ddtaquiz_attempt' mapping because that is the
        // one containing the ddtaquiz_attempt->ids old an new for ddtaquiz-attempt.
        $rules[] = new restore_log_rule('ddtaquiz', 'attempt',
                'review.php?id={course_module}&attempt={ddtaquiz_attempt}', '{ddtaquiz}',
                null, null, 'review.php?attempt={ddtaquiz_attempt}');
        $rules[] = new restore_log_rule('ddtaquiz', 'attempt',
                'review.php?attempt={ddtaquiz_attempt}', '{ddtaquiz}',
                null, null, 'review.php?attempt={ddtaquiz_attempt}');
        // Old an new for ddtaquiz-submit.
        $rules[] = new restore_log_rule('ddtaquiz', 'submit',
                'review.php?id={course_module}&attempt={ddtaquiz_attempt}', '{ddtaquiz}',
                null, null, 'review.php?attempt={ddtaquiz_attempt}');
        $rules[] = new restore_log_rule('ddtaquiz', 'submit',
                'review.php?attempt={ddtaquiz_attempt}', '{ddtaquiz}');
        // Old an new for ddtaquiz-review.
        $rules[] = new restore_log_rule('ddtaquiz', 'review',
                'review.php?id={course_module}&attempt={ddtaquiz_attempt}', '{ddtaquiz}',
                null, null, 'review.php?attempt={ddtaquiz_attempt}');
        $rules[] = new restore_log_rule('ddtaquiz', 'review',
                'review.php?attempt={ddtaquiz_attempt}', '{ddtaquiz}');
        // Old an new for ddtaquiz-start attemp.
        $rules[] = new restore_log_rule('ddtaquiz', 'start attempt',
                'review.php?id={course_module}&attempt={ddtaquiz_attempt}', '{ddtaquiz}',
                null, null, 'review.php?attempt={ddtaquiz_attempt}');
        $rules[] = new restore_log_rule('ddtaquiz', 'start attempt',
                'review.php?attempt={ddtaquiz_attempt}', '{ddtaquiz}');
        // Old an new for ddtaquiz-close attemp.
        $rules[] = new restore_log_rule('ddtaquiz', 'close attempt',
                'review.php?id={course_module}&attempt={ddtaquiz_attempt}', '{ddtaquiz}',
                null, null, 'review.php?attempt={ddtaquiz_attempt}');
        $rules[] = new restore_log_rule('ddtaquiz', 'close attempt',
                'review.php?attempt={ddtaquiz_attempt}', '{ddtaquiz}');
        // Old an new for ddtaquiz-continue attempt.
        $rules[] = new restore_log_rule('ddtaquiz', 'continue attempt',
                'review.php?id={course_module}&attempt={ddtaquiz_attempt}', '{ddtaquiz}',
                null, null, 'review.php?attempt={ddtaquiz_attempt}');
        $rules[] = new restore_log_rule('ddtaquiz', 'continue attempt',
                'review.php?attempt={ddtaquiz_attempt}', '{ddtaquiz}');
        // Old an new for ddtaquiz-continue attemp.
        $rules[] = new restore_log_rule('ddtaquiz', 'continue attemp',
                'review.php?id={course_module}&attempt={ddtaquiz_attempt}', '{ddtaquiz}',
                null, 'continue attempt', 'review.php?attempt={ddtaquiz_attempt}');
        $rules[] = new restore_log_rule('ddtaquiz', 'continue attemp',
                'review.php?attempt={ddtaquiz_attempt}', '{ddtaquiz}',
                null, 'continue attempt');

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * course logs. It must return one array
     * of {@link restore_log_rule} objects
     *
     * Note this rules are applied when restoring course logs
     * by the restore final task, but are defined here at
     * activity level. All them are rules not linked to any module instance (cmid = 0)
     */
    public static function define_restore_log_rules_for_course() {
        $rules = array();

        $rules[] = new restore_log_rule('ddtaquiz', 'view all', 'index.php?id={course}', null);

        return $rules;
    }
}
