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
 * This file defines the combined view of grades and responses in reports
 *
 * @package    mod_ddtaquiz
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_ddtaquiz\report;

defined('MOODLE_INTERNAL') || die();


/**
 * Quiz report subclass for the combined report.
 *
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class combined extends attempts {

    public function display($cm, $course, \ddtaquiz $quiz) {
        global $CFG, $DB, $OUTPUT, $PAGE;

        list($currentgroup, $students, $groupstudents, $allowed) = $this->init('\mod_ddtaquiz\report\combined_form',
            $quiz, $cm, $course);
        $options = new combined_options($quiz, $cm, $course);
        if ($fromform = $this->form->get_data()) {
            $options->process_settings_from_form($fromform);

        } else {
            $options->process_settings_from_params();
        }

        $this->form->set_data($options->get_initial_form_data());

        if ($options->attempts == self::ALL_WITH) {
            // This option is only available to users who can access all groups in
            // groups mode, so setting allowed to empty (which means all quiz attempts
            // are accessible, is not a security porblem.
            $allowed = array();
        }

        $questions = $this->get_significant_questions();

        // Prepare for downloading, if applicable.
        $courseshortname = format_string($course->shortname, true,
                array('context' => \context_course::instance($course->id)));
         $table = new combined_table($quiz, $this->context, $this->qmsubselect,
                $options, $groupstudents, $students, $questions, $options->get_url());
        $filename = $this->download_filename(get_string('combinedfilename', 'ddtaquiz'),
                $courseshortname, $quiz->get_name());
        $table->is_downloading($options->download, $filename,
                $courseshortname . ' ' . format_string($quiz->get_name(), true));
        if ($table->is_downloading()) {
            raise_memory_limit(MEMORY_EXTRA);
        }

        $this->course = $course; // Hack to make this available in process_actions.
        $this->process_actions($quiz, $cm, $currentgroup, $groupstudents, $allowed, $options->get_url());

        // Start output.
        if (!$table->is_downloading()) {
            // Only print headers if not asked to download data.
            $this->print_header_and_tabs($cm, $course, $quiz);
        }

        if ($groupmode = groups_get_activity_groupmode($cm)) {
            // Groups are being used, so output the group selector if we are not downloading.
            if (!$table->is_downloading()) {
                groups_print_activity_menu($cm, $options->get_url());
            }
        }

        // Print information on the number of existing attempts.
        if (!$table->is_downloading()) {
            // Do not print notices when downloading.
            $numattempts = $quiz->get_num_attempts();
            $strattemptsnum = get_string('attemptsnum', 'ddtaquiz', $numattempts);
            echo '<div class="quizattemptcounts">' . $strattemptsnum . '</div>';
        }

        if (!$table->is_downloading()) {
            if (!$students) {
                echo $OUTPUT->notification(get_string('nostudentsyet'));
            } else if ($currentgroup && !$groupstudents) {
                echo $OUTPUT->notification(get_string('nostudentsingroup'));
            }

            // Print the display options.
            $this->form->display();
        }

        $hasstudents = $students && (!$currentgroup || $groupstudents);
        if ($hasstudents || $options->attempts == self::ALL_WITH) {
            // Construct the SQL.
            $fields = $DB->sql_concat('u.id', "'#'", 'COALESCE(quiza.attempt, 0)') .
                    ' AS uniqueid, ';

            list($fields, $from, $where, $params) = $table->base_sql($allowed);

            $table->set_count_sql("SELECT COUNT(1) FROM $from WHERE $where", $params);
            $table->set_sql($fields, $from, $where, $params);

            // Define table columns.
            $columns = array();
            $headers = array();

            $this->add_user_columns($table, $columns, $headers);
            $this->add_state_column($columns, $headers);
            $this->add_time_columns($columns, $headers);

            $this->add_grade_columns($quiz, $columns, $headers, false);

            //if ($options->slotmarks) {
                foreach ($questions as $slot => $question) {
                    if($options->displayQuestionName) {
                        $columns[] = 'question' . $slot;
                        //$headers[] = get_string('questionx', 'question', $question->number);
                        $headers[] = $question->name . PHP_EOL . 'Question';
                    }
                    if($options->displayResponses) {
                        $columns[] = 'response' . $slot;
                        //$headers[] = $question->name;
                        $headers[] = $question->name . PHP_EOL . 'Response';
                    }
                    if($options->displayCorrectAnswers) {
                        $columns[] = 'right' . $slot;
                        //$headers[] = get_string('rightanswerx', 'ddtaquiz', $question->number);
                        $headers[] = $question->name . PHP_EOL . 'Expected';
                    }
                    if($options->displayAchievedPoints) {
                        // Ignore questions of zero length.
                        $columns[] = 'qsgrade' . $slot;
                        //$headers[] = $question->name;
                        $headers[] = $question->name . PHP_EOL . 'Points';
                    }
                }
           // }

            $this->set_up_table_columns($table, $columns, $headers, $this->get_base_url(), $options, true);
            $table->set_attribute('class', 'generaltable generalbox grades');

            $table->out($options->pagesize, true);
        }
        return true;
    }

    protected function get_base_url() {
        return new \moodle_url('/mod/ddtaquiz/report.php',
            array('id' => $this->context->instanceid, 'mode' => 'combined'));
    }
}
