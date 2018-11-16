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
 * This file defines the ddtaquiz responses report class.
 *
 * @package   ddtaquiz_responses
 * @copyright 2006 Jean-Michel Vedrine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/ddtaquiz/report/attemptsreport.php');
require_once($CFG->dirroot . '/mod/ddtaquiz/report/responses/responses_options.php');
require_once($CFG->dirroot . '/mod/ddtaquiz/report/responses/responses_form.php');
require_once($CFG->dirroot . '/mod/ddtaquiz/report/responses/last_responses_table.php');
require_once($CFG->dirroot . '/mod/ddtaquiz/report/responses/first_or_all_responses_table.php');


/**
 * Ddtaquiz report subclass for the responses report.
 *
 * This report lists some combination of
 *  * what question each student saw (this makes sense if random questions were used).
 *  * the response they gave,
 *  * and what the right answer is.
 *
 * Like the overview report, there are options for showing students with/without
 * attempts, and for deleting selected attempts.
 *
 * @copyright 1999 onwards Martin Dougiamas and others {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ddtaquiz_responses_report extends ddtaquiz_attempts_report {

    public function display($ddtaquiz, $cm, $course) {
        global $OUTPUT, $DB;

        list($currentgroup, $studentsjoins, $groupstudentsjoins, $allowedjoins) = $this->init(
                'responses', 'ddtaquiz_responses_settings_form', $ddtaquiz, $cm, $course);

        $options = new ddtaquiz_responses_options('responses', $ddtaquiz, $cm, $course);

        if ($fromform = $this->form->get_data()) {
            $options->process_settings_from_form($fromform);

        } else {
            $options->process_settings_from_params();
        }

        $this->form->set_data($options->get_initial_form_data());

        // Load the required questions.
        $questions = ddtaquiz_report_get_significant_questions($ddtaquiz);

        // Prepare for downloading, if applicable.
        $courseshortname = format_string($course->shortname, true,
                array('context' => context_course::instance($course->id)));
        if ($options->whichtries === question_attempt::LAST_TRY) {
            $tableclassname = 'ddtaquiz_last_responses_table';
        } else {
            $tableclassname = 'ddtaquiz_first_or_all_responses_table';
        }
        $table = new $tableclassname($ddtaquiz, $this->context, $this->qmsubselect,
                $options, $groupstudentsjoins, $studentsjoins, $questions, $options->get_url());
        $filename = ddtaquiz_report_download_filename(get_string('responsesfilename', 'ddtaquiz_responses'),
                $courseshortname, $ddtaquiz->name);
        $table->is_downloading($options->download, $filename,
                $courseshortname . ' ' . format_string($ddtaquiz->name, true));
        if ($table->is_downloading()) {
            raise_memory_limit(MEMORY_EXTRA);
        }

        $this->hasgroupstudents = false;
        if (!empty($groupstudentsjoins->joins)) {
            $sql = "SELECT DISTINCT u.id
                      FROM {user} u
                    $groupstudentsjoins->joins
                     WHERE $groupstudentsjoins->wheres";
            $this->hasgroupstudents = $DB->record_exists_sql($sql, $groupstudentsjoins->params);
        }
        $hasstudents = false;
        if (!empty($studentsjoins->joins)) {
            $sql = "SELECT DISTINCT u.id
                    FROM {user} u
                    $studentsjoins->joins
                    WHERE $studentsjoins->wheres";
            $hasstudents = $DB->record_exists_sql($sql, $studentsjoins->params);
        }
        if ($options->attempts == self::ALL_WITH) {
            // This option is only available to users who can access all groups in
            // groups mode, so setting allowed to empty (which means all ddtaquiz attempts
            // are accessible, is not a security problem.
            $allowedjoins = new \core\dml\sql_join();
        }

        $this->process_actions($ddtaquiz, $cm, $currentgroup, $groupstudentsjoins, $allowedjoins, $options->get_url());

        $hasquestions = ddtaquiz_has_questions($ddtaquiz->id);

        // Start output.
        if (!$table->is_downloading()) {
            // Only print headers if not asked to download data.
            $this->print_standard_header_and_messages($cm, $course, $ddtaquiz,
                    $options, $currentgroup, $hasquestions, $hasstudents);

            // Print the display options.
            $this->form->display();
        }

        $hasstudents = $hasstudents && (!$currentgroup || $this->hasgroupstudents);
        if ($hasquestions && ($hasstudents || $options->attempts == self::ALL_WITH)) {

            $table->setup_sql_queries($allowedjoins);

            if (!$table->is_downloading()) {
                // Print information on the grading method.
                if ($strattempthighlight = ddtaquiz_report_highlighting_grading_method(
                        $ddtaquiz, $this->qmsubselect, $options->onlygraded)) {
                    echo '<div class="ddtaquizattemptcounts">' . $strattempthighlight . '</div>';
                }
            }

            // Define table columns.
            $columns = array();
            $headers = array();

            if (!$table->is_downloading() && $options->checkboxcolumn) {
                $columns[] = 'checkbox';
                $headers[] = null;
            }

            $this->add_user_columns($table, $columns, $headers);
            $this->add_state_column($columns, $headers);

            if ($table->is_downloading()) {
                $this->add_time_columns($columns, $headers);
            }

            $this->add_grade_columns($ddtaquiz, $options->usercanseegrades, $columns, $headers);

            foreach ($questions as $id => $question) {
                if ($options->showqtext) {
                    $columns[] = 'question' . $id;
                    $headers[] = get_string('questionx', 'question', $question->number);
                }
                if ($options->showresponses) {
                    $columns[] = 'response' . $id;
                    $headers[] = get_string('responsex', 'ddtaquiz_responses', $question->number);
                }
                if ($options->showright) {
                    $columns[] = 'right' . $id;
                    $headers[] = get_string('rightanswerx', 'ddtaquiz_responses', $question->number);
                }
            }

            $table->define_columns($columns);
            $table->define_headers($headers);
            $table->sortable(true, 'uniqueid');

            // Set up the table.
            $table->define_baseurl($options->get_url());

            $this->configure_user_columns($table);

            $table->no_sorting('feedbacktext');
            $table->column_class('sumgrades', 'bold');

            $table->set_attribute('id', 'responses');

            $table->collapsible(true);

            $table->out($options->pagesize, true);
        }
        return true;
    }
}
