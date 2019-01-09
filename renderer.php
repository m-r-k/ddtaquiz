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
 * Defines the renderer for the ddtaquiz module.
 *
 * @package    mod_ddtaquiz
 * @copyright  2017 Jana Vatter <jana.vatter@stud.tu-darmstadt.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/mod/ddtaquiz/locallib.php');

use mod_ddtaquiz\output\ddtaquiz_bootstrap_render;
/**
 * The renderer for the ddtaquiz module.
 *
 * @copyright  2017 Jana Vatter <jana.vatter@stud.tu-darmstadt.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_ddtaquiz_renderer extends plugin_renderer_base {
    /**
     * Generates the view page.
     *
     * @param array $quiz Array containing quiz data.
     * @param mod_ddtaquiz_view_object $viewobj the information required to display the view page.
     * @return $output html data.
     */
    public function view_page($quiz, $viewobj) {
        $output = '';
        $output .= $this->heading($quiz->get_name());
        $output .= $this->view_table($quiz, $viewobj);
        $output .= $this->view_page_buttons($viewobj);
        return $output;
    }

    /**
     * Renders the review question pop-up.
     *
     * @param attempt $attempt an instance of attempt.
     * @param int $slot which question to display.
     * @param question_display_options $options the display options.
     * @param array $summarydata contains all table data.
     * @return $output containing html data.
     */
    public function review_question_page(attempt $attempt, $slot, question_display_options $options, $summarydata) {
        $output = '';
        $output .= $this->heading($attempt->get_quiz()->get_name());
        $output .= $this->review_summary_table($summarydata);
        $output .= $attempt->get_quba()->render_question($slot, $options);
        $output .= $this->close_window_button();
        return $output;
    }

    /**
     * Renders the grade and comment question pop-up.
     *
     * @param attempt $attempt an instance of attempt.
     * @param int $slot which question to display.
     * @param question_display_options $options the display options.
     * @param array $summarydata contains all table data.
     * @return $output containing html data.
     */
    public function grade_question_page(attempt $attempt, $slot, question_display_options $options, $summarydata) {
        $output = '';
        $output .= $this->heading($attempt->get_quiz()->get_name());
        $output .= $this->review_summary_table($summarydata);

        $url = new moodle_url('/mod/ddtaquiz/comment.php');
        $output .= \html_writer::start_tag('form', array('method' => 'post', 'action' => $url->out()));
        $output .= html_writer::tag('input', '', array('type' => 'hidden', 'name' => 'attempt', 'value' => $attempt->get_id()));
        $output .= html_writer::tag('input', '', array('type' => 'hidden', 'name' => 'slot', 'value' => $slot));
        $output .= html_writer::tag('input', '', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
        $output .= $attempt->get_quba()->render_question($slot, $options);
        $output .= \html_writer::tag('input', '', array('type' => 'submit', 'id' => 'id_submitbutton',
                'name' => 'submit', 'value' => get_string('save', 'ddtaquiz')));
        $output .= \html_writer::end_tag('form');
        return $output;
    }

    /**
     * Generates the table of data
     *
     * @param array $quiz Array contining quiz data.
     * @param mod_ddtaquiz_view_object $viewobj the information required to display the view page.
     * @return $output html data.
     */
    public function view_table($quiz, $viewobj) {
        if (!$viewobj->attempts) {
            return '';
        }

        // Prepare table header.
        $table = new html_table();
        $table->attributes['class'] = 'generaltable quizattemptsummary';
        $table->head = array();
        $table->align = array();
        $table->size = array();

        $table->head[] = get_string('attemptnumber', 'ddtaquiz');
        $table->align[] = 'left';
        $table->size[] = '';

        $table->head[] = get_string('attemptstate', 'ddtaquiz');
        $table->align[] = 'left';
        $table->size[] = '';

        $table->head[] = get_string('marks', 'ddtaquiz') . ' / ' . $quiz->get_maxgrade();
        $table->align[] = 'center';
        $table->size[] = '';

        $table->head[] = get_string('review', 'ddtaquiz');
        $table->align[] = 'center';
        $table->size[] = '';

        // One row for each attempt.
        foreach ($viewobj->attempts as $attempt) {
            $row = array();

            if ($attempt->is_preview()) {
                $row[] = get_string('preview', 'ddtaquiz');
            } else {
                $row[] = $attempt->get_attempt_number();
            }
            $row[] = $this->attempt_state($attempt);

            if ($attempt->get_state() == attempt::IN_PROGRESS) {
                $row[] = '';
                $row[] = '';
            } else {
                $row[] = round($attempt->get_quba()->get_total_mark(), 2);
                $row[] = html_writer::link($attempt->review_url(), get_string('review', 'ddtaquiz'),
                    array('title' => get_string('reviewthisattempt', 'ddtaquiz')));
            }

            if ($attempt->is_preview()) {
                $table->data['preview'] = $row;
            } else {
                $table->data[$attempt->get_attempt_number()] = $row;
            }
        }

        $output = '';
        $output .= $this->view_table_heading();
        $output .= html_writer::table($table);
        return $output;
    }

    /**
     * Generates the table heading.
     *
     * @return string the table heading.
     */
    public function view_table_heading() {
        return $this->heading(get_string('summaryofattempts', 'ddtaquiz'), 3);
    }

    /**
     * Generate a brief textual desciption of the current state of an attempt.
     *
     * @param attempt $attempt the attempt.
     * @return string the appropriate lang string to describe the state.
     */
    public function attempt_state(attempt $attempt) {
        switch ($attempt->get_state()) {
            case attempt::IN_PROGRESS:
                return get_string('stateinprogress', 'ddtaquiz');

            case attempt::FINISHED:
                return get_string('statefinished', 'ddtaquiz') . html_writer::tag('span',
                get_string('statefinisheddetails', 'ddtaquiz',
                userdate($attempt->get_finish_time())),
                array('class' => 'statedetails'));
        }
    }

    /**
     * Work out, and render, whatever buttons, and surrounding info, should appear.
     * at the end of the review page.
     *
     * @param mod_ddtaquiz_view_object $viewobj the information required to display the view page.
     * @return string HTML to output.
     */
    public function view_page_buttons($viewobj) {
        global $CFG;
        $output = '';
        $url = new \moodle_url('/mod/ddtaquiz/startattempt.php', array('cmid' => $viewobj->cmid));
        if ($viewobj->buttontext) {
            if ($viewobj->unfinishedattempt) {
                $attempturl = new moodle_url('/mod/ddtaquiz/attempt.php', array('attempt' => $viewobj->unfinishedattempt));
                $output .= $this->start_attempt_button($viewobj->buttontext, $attempturl);
            } else {
                $output .= $this->start_attempt_button($viewobj->buttontext, $url);
            }
            if ($viewobj->canmanage) {
                $output .= $this->edit_quiz_button($viewobj);
            }
        }
        if (!$viewobj->buttontext && $viewobj->canmanage) {
            $output .= get_string('noquestions', 'ddtaquiz');
            $output .= $this->edit_quiz_button($viewobj);
        }
        return $output;
    }

    /**
     * Generates the view attempt button.
     *
     * @param string $buttontext the label to display on the button.
     * @param moodle_url $url The URL to POST to in order to start the attempt.
     * @return string HTML fragment.
     */
    public function start_attempt_button($buttontext, $url) {
        $button = new single_button($url, $buttontext);
        $button->class .= ' quizstartbuttondiv';
        return $this->render($button);
    }

    /**
     * Generates the edit quiz button.
     *
     * @param mod_ddtaquiz_view_object $viewobj the information required to display the view page.
     * @return string HTML fragment.
     */
    public function edit_quiz_button($viewobj) {
        $url = new \moodle_url('/mod/ddtaquiz/edit.php', array('cmid' => $viewobj->cmid));
        $buttontext = get_string('editquiz', 'ddtaquiz');
        $button = new single_button($url, $buttontext);
        $button->class .= ' quizstartbuttondiv';
        return $this->render($button);
    }

    /**
     * TODO: done
     * Generates the page of the attempt.
     *
     * @param attempt $attempt the attempt.
     * @param int $slot the current slot.
     * @param question_display_options $options options that control how a question is displayed.
     * @param int $cmid the course module id.
     * @return string HTML fragment.
     * @throws
     *
     */
    public function attempt_page(attempt $attempt, $slot, $options, $cmid) {

        $processurl = new \moodle_url('/mod/ddtaquiz/processslot.php');
        // The progress bar.
        $progress = floor(($slot - 1) * 100 / $attempt->get_quiz()->get_slotcount());
        $progressbar = \html_writer::div('', 'bar',
            array('role' => 'progressbar', 'style' => 'width: ' . $progress . '%;', 'class'=>'bg-primary'));
        $header = \html_writer::div($progressbar, 'progress');

        $body = html_writer::start_tag('form',
           array('action' => $processurl, 'method' => 'post',
               'enctype' => 'multipart/form-data', 'accept-charset' => 'utf-8',
               'id' => 'responseform'));



        $body .= html_writer::start_tag('div');

        $body .=
            $attempt->get_quba()->render_question($slot, $options);

        // Some hidden fields to track what is going on.
        $body .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'attempt',
           'value' => $attempt->get_id()));
        $body .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'slot',
           'value' => $slot));
        $body .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'cmid',
           'value' => $cmid));

        $body .= html_writer::end_tag('div');
        $body .= html_writer::end_tag('form');


        $footer = $this->attempt_navigation_buttons();

        $this->page->requires->js_call_amd('mod_ddtaquiz/attempt', 'init');

        return ddtaquiz_bootstrap_render::createCard(
            $body,
            $header,
            $footer
        );
    }

    /**
     * TODO:
     * Generates the attempt navigation buttons.
     *
     * @return string HTML fragment.
     *
     * @throws
     */
    public function attempt_navigation_buttons() {
        $output = '';

        $output .= html_writer::start_div('text-right');

        $nextlabel = get_string('nextpage', 'ddtaquiz');
        $output .= html_writer::empty_tag('input', array('type' => 'submit', 'name' => 'next',
            'value' => $nextlabel, 'class'=>'btn btn-primary', 'id'=>'attemptNextBtn'));
        $output .= html_writer::end_div();

        return $output;
    }

    /**
     * Builds the review page.
     *
     * @param attempt $attempt the attempt this review belongs to.
     * @param question_display_options $options the display options.
     * @param array $summarydata contains all table data
     * @param feedback $feedback the feedback for the quiz.
     * @return $output containing HTML data.
     */
    public function review_page(attempt $attempt, $options, $summarydata, $feedback) {
        $output = '';
        $output .= $this->heading(get_string('quizfinished', 'ddtaquiz'));
        $output .= $this->review_summary_table($summarydata);
        $output .= $this->review_block($attempt->get_quiz()->get_main_block(), $attempt, $options, $feedback);
        $output .= $this->finish_review_button($attempt->get_quiz()->get_cmid());

        return $output;
    }

    /**
     * Outputs the table containing data from summary data array.
     *
     * @param array $summarydata contains row data for table.
     * @return $output containing HTML data.
     */
    public function review_summary_table($summarydata) {
        if (empty($summarydata)) {
            return '';
        }

        $output = '';
        $output .= html_writer::start_tag('table', array(
            'class' => 'generaltable generalbox quizreviewsummary'));
        $output .= html_writer::start_tag('tbody');
        foreach ($summarydata as $rowdata) {
            if ($rowdata['title'] instanceof renderable) {
                $title = $this->render($rowdata['title']);
            } else {
                $title = $rowdata['title'];
            }

            if ($rowdata['content'] instanceof renderable) {
                $content = $this->render($rowdata['content']);
            } else {
                $content = $rowdata['content'];
            }

            $output .= html_writer::tag('tr',
                html_writer::tag('th', $title, array('class' => 'cell', 'scope' => 'row')) .
                html_writer::tag('td', $content, array('class' => 'cell'))
                );
        }

        $output .= html_writer::end_tag('tbody');
        $output .= html_writer::end_tag('table');
        return $output;
    }

    /**
     * Renders the feedback for a block.
     *
     * @param block $block the block to generate the feedback for.
     * @param attempt $attempt the attempt this review belongs to.
     * @param question_display_options $options the display options.
     * @param feedback $feedback the specialized feedback.
     * @return string HTML to output.
     */
    protected function review_block(block $block, attempt $attempt, $options, $feedback) {
        $output = '';
        foreach ($block->get_children() as $child) {
            $output .= $this->review_block_element($block, $child, $attempt, $options, $feedback);
        }
        return $output;
    }

    /**
     *
     * Renders the feedback for an element of the block.
     *
     * @param block $block the block to generate the feedback for.
     * @param block_element $blockelem the element of the block.
     * @param attempt $attempt the attempt this review belongs to.
     * @param question_display_options $options the display options.
     * @param feedback $feedback the specialized feedback.
     * @return string HTML to output.
     */
    protected function review_block_element($block, $blockelem, $attempt, $options, $feedback) {
        $output = '';
        if ($feedback->has_specialized_feedback($blockelem, $attempt)) {
            $specialfeedback = $feedback->get_specialized_feedback_at_element($blockelem, $attempt);
            foreach ($specialfeedback as $sf) {
                $parts = $sf->get_parts();
                $review = $this->review_parts($parts, $block, $attempt, $options, $feedback);
                $output .= html_writer::div($review, 'reviewblock');
            }
        } else {
            $output .= $this->review_block_element_render($block, $blockelem, $attempt, $options, $feedback);
        }
        return $output;
    }

    /**
     * Used to surpass the has_specialized_feedback check.
     *
     * @param block $block the block to generate the feedback for.
     * @param block_element $blockelem the element of the block.
     * @param attempt $attempt the attempt this review belongs to.
     * @param question_display_options $options the display options.
     * @param feedback $feedback the specialized feedback.
     * @return string HTML to output.
     */
    protected function review_block_element_render($block, $blockelem, $attempt, $options, $feedback) {
        $output = '';
        if ($blockelem->is_block()) {
            $childblock = $blockelem->get_element();
            $condition = $childblock->get_condition();
            if ($condition->is_fullfilled($attempt)) {
                $output .= $this->review_block($childblock, $attempt, $options, $feedback);
            }
        } else if ($blockelem->is_question()) {
            $slot = $block->get_quiz()->get_slot_for_element($blockelem->get_id());
            $feedbackblock = $feedback->search_uses($blockelem, $attempt);
            if (is_null($feedbackblock)) {
                $output .= $attempt->get_quba()->render_question($slot, $options);
            } else {
                $adaptedgrade = $feedbackblock->get_adapted_grade();
                $oldmaxmark = $attempt->get_quba()->get_question_attempt($slot)->get_max_mark();
                $attempt->get_quba()->get_question_attempt($slot)->set_max_mark($adaptedgrade);
                $output .= $attempt->get_quba()->render_question($slot, $options);
                $attempt->get_quba()->get_question_attempt($slot)->set_max_mark($oldmaxmark);
            }
        }
        return $output;
    }

    /**
     * Renders the parts of the specialized feedback.
     *
     * @param array $parts the parts of the specialized feedback.
     * @param block $block the block to get the feedback for.
     * @param attempt $attempt the current attempt.
     * @param question_display_options $options the display options.
     * @param feedback $feedback the specialized feedback.
     * @return string HTML to output.
     */
    protected function review_parts($parts, $block, $attempt, $options, $feedback) {
        $output = '';
        foreach ($parts as $part) {
            if (is_string($part)) {
                $output .= html_writer::div($part, 'specialfeedbacktext');
            } else if ($part instanceof block_element) {
                $output .= $this->review_block_element_render($block, $part, $attempt, $options, $feedback);
            }
        }
        return $output;
    }

    /**
     * Generates the finish review button.
     *
     * @param int $cmid the course module id.
     * @return string HTML fragment.
     */
    public function finish_review_button($cmid) {
        $url = new moodle_url('/mod/ddtaquiz/view.php', array('id' => $cmid));
        $buttontext = get_string('finishreview', 'ddtaquiz');
        $button = new single_button($url, $buttontext);
        return $this->render($button);
    }
}

/**
 * Collects data for display by view.php.
 *
 * @copyright  2017 Jana Vatter <jana.vatter@stud.tu-darmstadt.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_ddtaquiz_view_object {
    /** @var bool $quizhasquestions whether the quiz has any questions. */
    public $quizhasquestions;
    /** @var string $buttontext caption for the start attempt button. If this is null, show no
     *      button, or if it is '' show a back to the course button. */
    public $buttontext;
    /** @var bool $unfinished contains 1 if an attempt is unfinished. */
    public $unfinished;
    /** @var array $preventmessages of messages telling the user why they can't
     *       attempt the quiz now. */
    public $preventmessages;
    /** @var int $numattempts contains the total number of attempts. */
    public $numattempts;
    /** @var object $lastfinishedattempt the last attempt from the attempts array. */
    public $lastfinishedattempt;
    /** @var int $cmid the course module id. */
    public $cmid;
    /** @var bool $canmanage whether the user is authorized to manage the quiz. */
    public $canmanage;
    /** @var int $unfinishedattempt the id of the unfinished attempt. */
    public $unfinishedattempt;
    /** @var array $attempts contains all the user's attempts at this quiz. */
    public $attempts;
}
