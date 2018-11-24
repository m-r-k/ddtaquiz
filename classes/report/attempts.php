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
 * The file defines a base class that can be used to build a report like the
 * overview or responses report, that has one row per attempt.
 *
 * @package    mod_ddtaquiz
 * @copyright  2017 Luca Gladiator <lucamarius.gladiator@stud.tu-darmstadt.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_ddtaquiz\report;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/tablelib.php');


/**
 * Base class for reports that are basically a table with one row for each attempt.
 *
 * @copyright  2017 Luca Gladiator <lucamarius.gladiator@stud.tu-darmstadt.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class attempts {
    const NO_GROUPS_ALLOWED = -2;

    /** @var int default page size for reports. */
    const DEFAULT_PAGE_SIZE = 30;

    /** @var string constant used for the options, means all users with attempts. */
    const ALL_WITH = 'all_with';
    /** @var string constant used for the options, means only enrolled users with attempts. */
    const ENROLLED_WITH = 'enrolled_with';
    /** @var string constant used for the options, means only enrolled users without attempts. */
    const ENROLLED_WITHOUT = 'enrolled_without';
    /** @var string constant used for the options, means all enrolled users. */
    const ENROLLED_ALL = 'enrolled_any';

    /** @var object the quiz context. */
    protected $context;

    /** @var \ddtaquiz $quiz the quiz. */
    protected $quiz = null;

    /** @var attempts_form The settings form to use. */
    protected $form;

    /** @var string SQL fragment for selecting the attempt that gave the final grade,
     * if applicable. */
    protected $qmsubselect;

    /** @var boolean caches the results of {@link should_show_grades()}. */
    protected $showgrades = null;

    /**
     * Override this function to displays the report.
     * @param object $cm the course-module for this quiz.
     * @param object $course the coures we are in.
     * @param \ddtaquiz $quiz this quiz.
     */
    public abstract function display($cm, $course, \ddtaquiz $quiz);

    /**
     *  Initialise various aspects of this report.
     *
     * @param string $formclass
     * @param object $quiz
     * @param object $cm
     * @param object $course
     * @return array with four elements:
     * 0 => integer the current group id (0 for none).
     * 1 => array ids of all the students in this course.
     * 2 => array ids of all the students in the current group.
     * 3 => array ids of all the students to show in the report.
     */
    protected function init($formclass, $quiz, $cm, $course) {
        $this->context = \context_module::instance($cm->id);
        $this->quiz = $quiz;

        list($currentgroup, $students, $groupstudents, $allowed) = $this->load_relevant_students($cm, $course);

        $this->qmsubselect = ''; // TODO: quiz_report_qm_filter_select($quiz);

        $this->form = new $formclass($this->get_base_url(),
            array('quiz' => $quiz, 'currentgroup' => $currentgroup, 'context' => $this->context));

        return array($currentgroup, $students, $groupstudents, $allowed);
    }

    /**
     * Initialise some parts of $PAGE and start output.
     *
     * @param object $cm the course_module information.
     * @param object $coures the course settings.
     * @param \ddtaquiz $quiz the quiz.
     */
    public function print_header_and_tabs($cm, $course, $quiz) {
        global $PAGE, $OUTPUT;

        // Print the page header.
        $PAGE->set_title($quiz->get_name());
        $PAGE->set_heading($course->fullname);
        echo $OUTPUT->header();
        $context = \context_module::instance($cm->id);
        echo $OUTPUT->heading(format_string($quiz->get_name(), true, array('context' => $context)));
    }

    /**
     * Get the current group for the user user looking at the report.
     *
     * @param object $cm the course_module information.
     * @param object $coures the course settings.
     * @param \context $context the quiz context.
     * @return int the current group id, if applicable. 0 for all users,
     *      NO_GROUPS_ALLOWED if the user cannot see any group.
     */
    public function get_current_group($cm, $course, $context) {
        $groupmode = groups_get_activity_groupmode($cm, $course);
        $currentgroup = groups_get_activity_group($cm, true);

        if ($groupmode == SEPARATEGROUPS && !$currentgroup && !has_capability('moodle/site:accessallgroups', $context)) {
            $currentgroup = self::NO_GROUPS_ALLOWED;
        }

        return $currentgroup;
    }

    /**
     * Get the base URL for this report.
     * @return \moodle_url the URL.
     */
    abstract protected function get_base_url();

    /**
     * Get information about which students to show in the report.
     * @param object $cm the coures module.
     * @param object $course the course settings.
     * @return array with four elements:
     *      0 => integer the current group id (0 for none).
     *      1 => array ids of all the students in this course.
     *      2 => array ids of all the students in the current group.
     *      3 => array ids of all the students to show in the report. Will be the
     *              same as either element 1 or 2.
     */
    protected function load_relevant_students($cm, $course = null) {
        $currentgroup = $this->get_current_group($cm, $course, $this->context);

        if ($currentgroup == self::NO_GROUPS_ALLOWED) {
            return array($currentgroup, array(), array(), array());
        }

        if (!$students = get_users_by_capability($this->context,
            array('mod/ddtaquiz:attempt'),
            'u.id, 1', '', '', '', '', '', false)) {
            $students = array();
        } else {
            $students = array_keys($students);
        }

        if (empty($currentgroup)) {
            return array($currentgroup, $students, array(), $students);
        }

        // We have a currently selected group.
        if (!$groupstudents = get_users_by_capability($this->context,
            array('mod/ddtaquiz:attempt'),
            'u.id, 1', '', '', '', $currentgroup, '', false)) {
            $groupstudents = array();
        } else {
            $groupstudents = array_keys($groupstudents);
        }

        return array($currentgroup, $students, $groupstudents, $groupstudents);
    }

    /**
     * Add all the user-related columns to the $columns and $headers arrays.
     * @param \table_sql $table the table being constructed.
     * @param array $columns the list of columns. Added to.
     * @param array $headers the columns headings. Added to.
     */
    protected function add_user_columns($table, &$columns, &$headers) {
        global $CFG;
        if (!$table->is_downloading() && $CFG->grade_report_showuserimage) {
            $columns[] = 'picture';
            $headers[] = '';
        }
        if (!$table->is_downloading()) {
            $columns[] = 'fullname';
            $headers[] = get_string('name');
        } else {
            $columns[] = 'lastname';
            $headers[] = get_string('lastname');
            $columns[] = 'firstname';
            $headers[] = get_string('firstname');
        }

        // When downloading, some extra fields are always displayed (because
        // there's no space constraint) so do not include in extra-field list.
        $extrafields = get_extra_user_fields($this->context,
            $table->is_downloading() ? array('institution', 'department', 'email') : array());
        foreach ($extrafields as $field) {
            $columns[] = $field;
            $headers[] = get_user_field_name($field);
        }

        if ($table->is_downloading()) {
            $columns[] = 'institution';
            $headers[] = get_string('institution');

            $columns[] = 'department';
            $headers[] = get_string('department');

            $columns[] = 'email';
            $headers[] = get_string('email');
        }
    }

    /**
     * Set the display options for the user-related columns in the table.
     * @param \table_sql $table the table being constructed.
     */
    protected function configure_user_columns($table) {
        $table->column_suppress('picture');
        $table->column_suppress('fullname');
        $extrafields = get_extra_user_fields($this->context);
        foreach ($extrafields as $field) {
            $table->column_suppress($field);
        }

        $table->column_class('picture', 'picture');
        $table->column_class('lastname', 'bold');
        $table->column_class('firstname', 'bold');
        $table->column_class('fullname', 'bold');
    }

    /**
     * Add the state column to the $columns and $headers arrays.
     * @param array $columns the list of columns. Added to.
     * @param array $headers the columns headings. Added to.
     */
    protected function add_state_column(&$columns, &$headers) {
        $columns[] = 'state';
        $headers[] = get_string('attemptstate', 'ddtaquiz');
    }

    /**
     * Add all the time-related columns to the $columns and $headers arrays.
     * @param array $columns the list of columns. Added to.
     * @param array $headers the columns headings. Added to.
     */
    protected function add_time_columns(&$columns, &$headers) {
        $columns[] = 'timestart';
        $headers[] = get_string('startedon', 'ddtaquiz');

        $columns[] = 'timefinish';
        $headers[] = get_string('timecompleted', 'ddtaquiz');

        $columns[] = 'duration';
        $headers[] = get_string('attemptduration', 'ddtaquiz');
    }

    /**
     * Add all the grade and feedback columns, if applicable, to the $columns
     * and $headers arrays.
     * @param \ddtaquiz $quiz the quiz.
     * @param array $columns the list of columns. Added to.
     * @param array $headers the columns headings. Added to.
     * @param bool $includefeedback whether to include the feedbacktext columns
     */
    protected function add_grade_columns(\ddtaquiz $quiz, &$columns, &$headers, $includefeedback = true) {
        $columns[] = 'sumgrades';
        $headers[] = get_string('grade', 'ddtaquiz') . '/' .
            $quiz->format_grade($quiz->get_maxgrade());
    }

    /**
     * Set up the table.
     * @param \table_sql $table the table being constructed.
     * @param array $columns the list of columns.
     * @param array $headers the columns headings.
     * @param \moodle_url $reporturl the URL of this report.
     * @param attempts_options $options the display options.
     * @param bool $collapsible whether to allow columns in the report to be collapsed.
     */
    protected function set_up_table_columns($table, $columns, $headers, $reporturl,
        attempts_options $options, $collapsible) {
            $table->define_columns($columns);
            $table->define_headers($headers);
            $table->sortable(true, 'uniqueid');

            $table->define_baseurl($options->get_url());

            $this->configure_user_columns($table);

            $table->no_sorting('feedbacktext');
            $table->column_class('sumgrades', 'bold');

            $table->set_attribute('id', 'attempts');

            $table->collapsible($collapsible);
    }

    /**
     * Process any submitted actions.
     */
    protected function process_actions($quiz, $cm, $currentgroup, $groupstudents, $allowed, $redirecturl) {
        // Nothing to do.
    }

    /**
     * Create a filename for use when downloading data from a quiz report. It is
     * expected that this will be passed to flexible_table::is_downloading, which
     * cleans the filename of bad characters and adds the file extension.
     * @param string $mode the type of report.
     * @param string $courseshortname the course shortname.
     * @param string $quizname the quiz name.
     * @return string the filename.
     */
    protected function download_filename($mode, $courseshortname, $quizname) {
        return $courseshortname . '-' . format_string($quizname, true) . '-' . $mode;
    }

    /**
     * Get the slots of real questions (not descriptions) in this quiz, in order.
     *
     * @return array of slot => $question object with fields
     *      ->slot, ->id, ->maxmark, ->number, ->length.
     */
    protected function get_significant_questions() {
        global $DB;

        $questionsraw = $this->quiz->get_questions();
        $questions = array();
        for ($i = 0; $i < count($questionsraw); $i++) {
            $question = clone $questionsraw[$i]->get_element();
            $question->slot = $i + 1;
            $question->number = $i + 1;
            $questions[$i + 1] = $question;
        }
        return $questions;
    }
}