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
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/ddtaquiz/locallib.php');

use mod_ddtaquiz\output\ddtaquiz_bootstrap_render;

/**
 * The renderer for the ddtaquiz module.
 *
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_ddtaquiz_renderer extends plugin_renderer_base
{

    #region view index section
    /**
     * Generates the view page.
     *
     * @param ddtaquiz $quiz ddtaquiz object containing quiz data.
     * @param mod_ddtaquiz_view_object $viewobj the information required to display the view page.
     * @return string $output html data.
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function view_page($quiz, $viewobj)
    {
        $output = '';
        $output .= $this->heading($quiz->get_name());
        $output .= $this->view_table($quiz, $viewobj);
        $output .= $this->view_page_buttons($viewobj);
        return $output;

    }

    /**
     * Generates the table of data
     *
     * @param ddtaquiz $quiz ddtaquiz object containing quiz data.
     * @param mod_ddtaquiz_view_object $viewobj the information required to display the view page.
     * @return string $output html data.
     * @throws coding_exception
     */
    public function view_table($quiz, $viewobj)
    {
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
     * @throws coding_exception
     */
    public function view_table_heading()
    {
        return $this->heading(get_string('summaryofattempts', 'ddtaquiz'), 3);
    }

    /**
     * Generate a brief textual desciption of the current state of an attempt.
     *
     * @param attempt $attempt the attempt.
     * @return string the appropriate lang string to describe the state.
     * @throws coding_exception
     */
    public function attempt_state(attempt $attempt)
    {
        switch ($attempt->get_state()) {
            case attempt::IN_PROGRESS:
                return get_string('stateinprogress', 'ddtaquiz');

            case attempt::FINISHED:
                return get_string('statefinished', 'ddtaquiz') . html_writer::tag('span',
                        get_string('statefinisheddetails', 'ddtaquiz',
                            userdate($attempt->get_finish_time())),
                        array('class' => 'statedetails'));
            default:
                return "";
        }
    }

    /**
     * Work out, and render, whatever buttons, and surrounding info, should appear.
     * at the end of the review page.
     *
     * @param mod_ddtaquiz_view_object $viewobj the information required to display the view page.
     * @return string HTML to output.
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function view_page_buttons($viewobj)
    {
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
    public function start_attempt_button($buttontext, $url)
    {
        $button = new single_button($url, $buttontext);
        $button->class .= ' quizstartbuttondiv';
        return $this->render($button);
    }

    /**
     * Generates the edit quiz button.
     *
     * @param mod_ddtaquiz_view_object $viewobj the information required to display the view page.
     * @return string HTML fragment.
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function edit_quiz_button($viewobj)
    {
        $url = new \moodle_url('/mod/ddtaquiz/edit.php', array('cmid' => $viewobj->cmid));
        $buttontext = get_string('editquiz', 'ddtaquiz');
        $button = new single_button($url, $buttontext);
        $button->class .= ' quizstartbuttondiv';
        return $this->render($button);
    }
#endregion

    #region never used?
    /**
     * Renders the review question pop-up.
     *
     * @param attempt $attempt an instance of attempt.
     * @param int $slot which question to display.
     * @param question_display_options $options the display options.
     * @param array $summarydata contains all table data.
     * @return string $output containing html data.
     */
    public function review_question_page(attempt $attempt, $slot, question_display_options $options, $summarydata)
    {
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
     * @return string $output containing html data.
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function grade_question_page(attempt $attempt, $slot, question_display_options $options, $summarydata)
    {
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

#endregion

    #region Attempt Section
    /**
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
    public function attempt_page(attempt $attempt, $slot, $options, $cmid)
    {
        $processurl = new \moodle_url('/mod/ddtaquiz/processslot.php');
        // The progress bar.
        $progress = floor(($slot - 1) * 100 / $attempt->get_quiz()->get_slotcount());
        $progressbar = \html_writer::div('', 'bar',
            array('role' => 'progressbar', 'style' => 'width: ' . $progress . '%;', 'class' => 'bg-primary'));
        $progressBarContainer = html_writer::div($progressbar, 'progress');
        $time = html_writer::div(html_writer::div('', 'timeDiv'), 'text-right');
        $header = \html_writer::div($time . $progressBarContainer, '');

        $body = html_writer::start_tag('form',
            array('action' => $processurl, 'method' => 'post',
                'enctype' => 'multipart/form-data', 'accept-charset' => 'utf-8',
                'id' => 'responseform'));

        $body .= html_writer::start_tag('div');
        $body .= $attempt->get_quba()->render_question($slot, $options);
        $attempt->setSeenQuestions($slot);

        // Some hidden fields to track what is going on.
        $body .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'attempt',
            'value' => $attempt->get_id()));
        $body .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'slot',
            'value' => $slot));
        $body .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'cmid',
            'value' => $cmid));

        if (($attempt->get_quba()->get_question_attempt($slot)->get_state() == \question_state::$todo) && $attempt->get_quiz()->showDirectFeedback())
            $body .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'directFeedback',
                'value' => true));
        else
            $body .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'directFeedback',
                'value' => false));


        $body .= html_writer::end_tag('div');
        $body .= html_writer::end_tag('form');


        $footer = $this->attempt_navigation_buttons();
        $this->page->requires->js_call_amd('mod_ddtaquiz/attempt', 'init');
        if ($attempt->get_quiz()->timing_activated()) {
            $this->page->requires->js_call_amd('mod_ddtaquiz/attempt', 'startTime', [
                'abandon' => $attempt->get_quiz()->to_abandon(),
                'timestamp' => $attempt->get_timeleft(),
                'graceperiod' => $attempt->get_graceperiod(),
                'url' => $attempt->attempt_url()->raw_out()
            ]);
        }


        return ddtaquiz_bootstrap_render::createCard(
            $body,
            $header,
            $footer
        );
    }

    public function bin_dif_page(attempt $attempt, $options, $cmid)
    {
        $processurl = new \moodle_url('/mod/ddtaquiz/processslot.php');
        $slots = $attempt->get_quiz()->get_slotcount();

        $time = html_writer::div(html_writer::div('', 'timeDiv'), 'text-right');
        $header = \html_writer::div($time, '');

        $body = html_writer::start_tag('form',
            array('action' => $processurl, 'method' => 'post',
                'enctype' => 'multipart/form-data', 'accept-charset' => 'utf-8',
                'id' => 'responseform'));

        $body .= html_writer::start_tag('div');
        for ($count = 1; $count <= $slots; $count++) {
            $body .= html_writer::div($attempt->get_quba()->render_question($count, $options), 'binDifContainer', array('max-points' => $attempt->get_quba()->get_question_max_mark($count)));
            $attempt->setSeenQuestions($count);
        }

        // Some hidden fields to track what is going on.
        $body .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'attempt',
            'value' => $attempt->get_id()));
        $body .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'cmid',
            'value' => $cmid));
        $body .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'directFeedback',
            'value' => false));

        $body .= html_writer::end_tag('div');
        $body .= html_writer::end_tag('form');


        $doublecheckbutton = html_writer::empty_tag('input', array('type' => 'submit', 'name' => 'finish',
            'value' => get_string('finishbindifquiz', 'ddtaquiz'), 'class' => 'btn btn-primary text-right', 'id' => 'attemptNextBtn'));

        $minPoints = $attempt->get_quiz()->getMinpointsforbindif();
        $footer = ddtaquiz_bootstrap_render::createModal('Are you sure?', 'Attempt will be finished! You need at least ' . $minPoints . ' points to succeed.<br> The maximum of points possible with the questions you have answered is: ' . html_writer::div('', 'points-overview-bindif', array('minPoints' => $minPoints)), $doublecheckbutton, array('id' => 'confirm-finish-attempt'));
        $footer .= ddtaquiz_bootstrap_render::createModalTrigger('confirm-finish-attempt', "submit", get_string('finishbindifquiz', 'ddtaquiz'), array('class' => 'btn btn-danger', 'id' => 'confirmFinishBtn'));


        $this->page->requires->js_call_amd('mod_ddtaquiz/attempt', 'init');
        if ($attempt->get_quiz()->timing_activated()) {
            $this->page->requires->js_call_amd('mod_ddtaquiz/attempt', 'startTime', [
                'abandon' => $attempt->get_quiz()->to_abandon(),
                'timestamp' => $attempt->get_timeleft(),
                'graceperiod' => $attempt->get_graceperiod(),
                'url' => $attempt->attempt_url()->raw_out()
            ]);
        }


        return ddtaquiz_bootstrap_render::createCard(
            $body,
            $header,
            $footer
        );
    }


    /**
     * Generates the attempt navigation buttons.
     *
     * @return string HTML fragment.
     *
     * @throws coding_exception
     */
    public function attempt_navigation_buttons()
    {
        $output = '';
        $nextlabel = get_string('nextpage', 'ddtaquiz');
        $output .= html_writer::empty_tag('input', array('type' => 'submit', 'name' => 'next',
            'value' => $nextlabel, 'class' => 'btn btn-primary text-right', 'id' => 'attemptNextBtn'));
        return $output;
    }



    #endregion

    #region review section
    /**
     * Builds the review page.
     *
     * @param attempt $attempt the attempt this review belongs to.
     * @param question_display_options $options the display options.
     * @param array $summarydata contains all table data
     * @param feedback $feedback the feedback for the quiz.
     * @return string $output containing HTML data.
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function review_page(attempt $attempt, $options, $summarydata, $feedback)
    {
        $output = '';
        $output .= $this->heading(get_string('quizfinished', 'ddtaquiz'));
        $output .= $this->review_summary_table($summarydata, $attempt);
        $output .= $this->review_domain($attempt);
        $output .= $this->review_block($attempt->get_quiz()->get_main_block(), $attempt, $options, $feedback);
        $output .= $this->finish_review_button($attempt->get_quiz()->get_cmid());

        return $output;
    }

    /**
     * Outputs the table containing data from summary data array.
     *
     * @param array $summarydata contains row data for table.
     * @param attempt $attempt
     * @return string $output containing HTML data.
     */
    public function review_summary_table($summarydata, $attempt = Null)
    {
        $mode = $attempt->get_quiz()->getQuizmodes();
        $slots = $attempt->get_quba()->get_slots();
        $correctanswers = 0;
        $usedQuestions = 0;

        foreach ($slots as $slot) {
                $grade = $attempt->get_grade_at_slot($slot);
                $maxgrade = $attempt->get_quba()->get_question_max_mark($slot);

            $seenQuestions=explode(";", $attempt->getSeenQuestions());
            if (in_array($slot, $seenQuestions)) {
                if ($grade == $maxgrade)
                    $correctanswers++;
                $usedQuestions++;

            }
        }


        if (empty($summarydata)) {
            return '';
        }

        $output = '';
        $output .= html_writer::start_tag('table', array(
            'class' => 'generaltable generalbox quizreviewsummary'));
        $output .= html_writer::start_tag('tbody');
        foreach ($summarydata as $rowdata) {

            if ($rowdata['title'] == 'Marks') {
                if ($mode == 0) {
                    $rowdata['title'] = 'Correct answers';
                    $rowdata['content'] = $correctanswers . "/" . $usedQuestions;
                }
                else if($mode==1) {
                    $max = 0;
                    $used = 0;

                    foreach (explode(';', $attempt->getseenquestions()) as $seenQuestion) {

                        $response = $attempt->get_quba()->get_question_attempt($seenQuestion)->get_response_summary();

                        if (!empty($response)) {
                            if (strpos('#', $response) === false) {
                                $max += $attempt->get_quba()->get_question_attempt($seenQuestion)->get_max_mark();
                                $used += $attempt->get_grade_at_slot($seenQuestion);
                                $rowdata['content'] = $used . "/" . $max;
                            }
                        }

                    }

                }


            }

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
     * Renders the feedback for the domain.
     *
     * @param attempt $attempt the attempt this review belongs to.
     * @return string HTML to output.
     * @throws coding_exception
     * @throws dml_exception
     */
    protected function review_domain(attempt $attempt)
    {
        $mode = $attempt->get_quiz()->getQuizmodes();
        $output = "";
        $quiz = $attempt->get_quiz();
        $feedback = \domain_feedback::get_feedback($quiz);
        $output = "";
        /** @var \feedback_block $block */
        foreach ($feedback->get_blocks() as $block) {
            $condition = $block->get_condition();
            if ($condition->is_fullfilled($attempt)) {
                $grades = $condition->get_grading($attempt);

                $title = $condition->get_replace() ? $condition->get_replace() : $condition->get_name();


                $diff = $grades[1]  - $grades[0];
                if($mode==0) {

                    if ($diff ==  $grades[1])
                        $content = " " . get_string('questionFeedbackAccordionHeaderPostLabelIncorrect', 'ddtaquiz');
                    else if ($diff == 0)
                        $content = " " . get_string('questionFeedbackAccordionHeaderPostLabelCorrect', 'ddtaquiz');
                    else
                        $content = " " . get_string('questionFeedbackAccordionHeaderPostLabelPartialCorrect', 'ddtaquiz');
                    $conditionCardBody=$content;
                }
                else{
                    $content = $grades[0] . "/" . $grades[1];
                    $conditionCardBody = \html_writer::div("Result: " . $grades[0] . " / " . $grades[1], 'result');
                }




                $conditionCardBody .= \html_writer::div($block->get_feedback_text(), 'conditionpartslist');

                /**
                 * Collapsible accordions for domainfeedback
                 */
                $collapseId = 'collapse-id'.$block->get_id();
                $accordionId = 'feedback-accordion';
                $headerId = 'accordion-header';
                $blockClass = 'blockAccordionHeader';
                $collapseContent = ddtaquiz_bootstrap_render::createAccordionCollapsible(
                    $collapseId,
                    $headerId,
                    $accordionId,
                    $conditionCardBody
                );

                if ($grades[1] != 0)
                    $progress = floor(($grades[0]  /  $grades[1]) * 100);
                else
                    $progress = 0;

                $progressbar = \html_writer::div($progress . '%', 'progress-bar bg-success',
                    array('role' => 'progressbar', 'style' => 'width:' . $progress . '%;color:black;', 'class' => 'bg-primary', 'aria-valuenow' => $progress, 'aria-valuemin' => "0", 'aria-valuemax' => "100"));
                $progressbar .= \html_writer::div((100 - $progress) . '%', 'progress-bar bg-danger',
                    array('role' => 'progressbar', 'style' => 'width:' . (100 - $progress) . '%;color:black;', 'class' => 'bg-primary', 'aria-valuenow' => (100 - $progress), 'aria-valuemin' => "0", 'aria-valuemax' => "100"));
                $progressBarContainer = html_writer::div($progressbar, 'progress feedback ml-auto', array());


                $postContent = \html_writer::tag('label', $content, []);
                $postContent .= $progressBarContainer;

                if ($grades[0] != $grades[0])
                    $blockClass .= " incorrectColor";
                else
                    $blockClass .= " correctColor";
                $container = ddtaquiz_bootstrap_render::createAccordionHeader(
                        \html_writer::tag('label', get_string('domainFeedbackAccordionHeaderPre', 'ddtaquiz'),
                            array('class' => 'conditionelement precontent')),
                        \html_writer::tag('label', $title, ['class' => 'collapsible-highlight']),
                        $postContent,
                        ['id' => $headerId, 'class' => $blockClass],
                        $collapseId
                    ) .
                    $collapseContent;

                $output .= ddtaquiz_bootstrap_render::createAccordion($accordionId, $container);
            }
        }
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
     * @throws coding_exception
     * @throws dml_exception
     */
    protected function review_block(block $block, attempt $attempt, $options, $feedback)
    {
        $modes=$block->get_quiz()->getQuizmodes();
        $output = '';
        /**@var block_element $child*/
        foreach ($block->get_children() as $child) {
            $slot=$attempt->get_quiz()->get_slot_for_element($child->get_id());
            $response=$attempt->get_quba()->get_question_attempt($slot)->get_response_summary();
            if($modes!=1){
                $output .= $this->review_block_element($block, $child, $attempt, $options, $feedback);
            }
            else{
                if(!empty($response)) {
                    if (strpos('#', $response)===false) {
                        $output .= $this->review_block_element($block, $child, $attempt, $options, $feedback);
                    }
                }
            }
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
     * @throws coding_exception
     * @throws dml_exception
     */
    protected function review_block_element($block, $blockelem, $attempt, $options, $feedback)
    {
        $mode = $attempt->get_quiz()->getQuizmodes();
        $collapseId = 'collabse-id-review' . $blockelem->get_id();
        $accordionId = 'review-accordion' . $blockelem->get_id();
        $headerId = 'review-accordion-header' . $blockelem->get_id();

        $output = '';

        // review only if specilaized feedback
        if ($feedback->has_specialized_feedback($blockelem, $attempt)) {


            $specialfeedback = $feedback->get_specialized_feedback_at_element($blockelem, $attempt);
            /** @var specialized_feedback $sf */
            foreach ($specialfeedback as $sf) {
                $grade = 0;
                $maxgrade = 0;

                foreach ( $sf->getFeedbackBlock()->get_used_question_instances() as $usedInstance){
                    $element=$usedInstance->getBlockElement();
                    $maxgrade+=$element->get_maxgrade();
                    $grade+=$element->get_grade($attempt);

                }


                $diff = $maxgrade - $grade;


                $parts = $sf->get_parts();
                $output .= $this->review_parts($parts, $block, $attempt, $options, $feedback);
                $collapseContent = ddtaquiz_bootstrap_render::createAccordionCollapsible(
                    $collapseId,
                    $headerId,
                    $accordionId,
                    $output
                );

                $label = get_string('specialFeedbackAccordionHeaderPre', 'ddtaquiz');

                if($mode==0) {
                    if ($diff == $maxgrade)
                        $content = " " . get_string('questionFeedbackAccordionHeaderPostLabelIncorrect', 'ddtaquiz');
                    else if ($diff == 0)
                        $content = " " . get_string('questionFeedbackAccordionHeaderPostLabelCorrect', 'ddtaquiz');
                    else
                        $content = " " . get_string('questionFeedbackAccordionHeaderPostLabelPartialCorrect', 'ddtaquiz');
                }
                else{
                    $content = $grade . "/" . $maxgrade;
                }

                 $progress = floor(($grade / $maxgrade) * 100);

                $progressbar = \html_writer::div($progress . '%', 'progress-bar bg-success',
                    array('role' => 'progressbar', 'style' => 'width:' . $progress . '%;color:black;', 'class' => 'bg-primary', 'aria-valuenow' => $progress, 'aria-valuemin' => "0", 'aria-valuemax' => "100"));
                $progressbar .= \html_writer::div((100 - $progress) . '%', 'progress-bar bg-danger',
                    array('role' => 'progressbar', 'style' => 'width:' . (100 - $progress) . '%;color:black;', 'class' => 'bg-primary', 'aria-valuenow' => (100 - $progress), 'aria-valuemin' => "0", 'aria-valuemax' => "100"));
                $progressBarContainer = html_writer::div($progressbar, 'progress feedback ml-auto', array());

                $postContent = \html_writer::tag('label', $content, []);
                $postContent .= $progressBarContainer;
                $backgroundColor = "blockBackgroundHeader";
                $container = ddtaquiz_bootstrap_render::createAccordionHeader(
                        \html_writer::tag('label', $label,
                            array('class' => 'conditionelement precontent')),
                        \html_writer::tag('label', $blockelem->get_name(), ['class' => 'collapsible-highlight']),
                        $postContent,
                        ['id' => $headerId, 'class' => 'blockAccordionHeader' . ' ' . $backgroundColor, 'style' => 'margin-bottom:5px;'],
                        $collapseId
                    ) .
                    $collapseContent;

                $output = ddtaquiz_bootstrap_render::createAccordion($accordionId, $container);




            }

        } // review as normal block if not specialized feedback
        else {

            $output .= $this->review_block_element_render($block, $blockelem, $attempt, $options, $feedback);
            //If no feedback is to render than return
            if ($output == "") {
                return "";
            }
            //Collabsible color
            $slot = $block->get_slot_for_element($blockelem->get_id());

            /**
             * Collapsible accordions for each feedbackblock
             */

            $collapseContent = ddtaquiz_bootstrap_render::createAccordionCollapsible(
                $collapseId,
                $headerId,
                $accordionId,
                $output
            );

            if ($blockelem->is_block()) {
                /** @var block $childblock */
                $childblock = $blockelem->get_element();
                $maxgrade = $childblock->get_maxgrade();
                $grade = $blockelem->get_grade($attempt);

                $label = get_string('blockFeedbackAccordionHeaderPre', 'ddtaquiz');

                $progress = floor(($grade / $maxgrade) * 100);

                $progressbar = \html_writer::div($progress . '%', 'progress-bar bg-success',
                    array('role' => 'progressbar', 'style' => 'width:' . $progress . '%;color:black;', 'class' => 'bg-primary', 'aria-valuenow' => $progress, 'aria-valuemin' => "0", 'aria-valuemax' => "100"));
                $progressbar .= \html_writer::div((100 - $progress) . '%', 'progress-bar bg-danger',
                    array('role' => 'progressbar', 'style' => 'width:' . (100 - $progress) . '%;color:black;', 'class' => 'bg-primary', 'aria-valuenow' => (100 - $progress), 'aria-valuemin' => "0", 'aria-valuemax' => "100"));
                $progressBarContainer = html_writer::div($progressbar, 'progress feedback ml-auto', array());

                $backgroundColor = "blockBackgroundHeader";
            } else {
                $grade = $attempt->get_grade_at_slot($slot);
                $maxgrade = $attempt->get_quba()->get_question_max_mark($slot);
                $label = get_string('questionFeedbackAccordionHeaderPre', 'ddtaquiz');
                $progressBarContainer = "";
                $backgroundColor = "correctBackgroundHeader";
                if ($grade != $maxgrade)
                    $backgroundColor = "incorrectBackgroundHeader";
            }

            //Set Postcontent for Header
            $diff = $maxgrade - $grade;
            if($mode==0) {
                if ($diff == $maxgrade)
                    $content = " " . get_string('questionFeedbackAccordionHeaderPostLabelIncorrect', 'ddtaquiz');
                else if ($diff == 0)
                    $content = " " . get_string('questionFeedbackAccordionHeaderPostLabelCorrect', 'ddtaquiz');
                else
                    $content = " " . get_string('questionFeedbackAccordionHeaderPostLabelPartialCorrect', 'ddtaquiz');
            }
            else{
                $content = $grade . "/" . $maxgrade;
            }

            $postContent = \html_writer::tag('label', $content, []);
            $postContent .= $progressBarContainer;

            $container = ddtaquiz_bootstrap_render::createAccordionHeader(
                    \html_writer::tag('label', $label,
                        array('class' => 'conditionelement precontent')),
                    \html_writer::tag('label', $blockelem->get_name(), ['class' => 'collapsible-highlight']),
                    $postContent,
                    ['id' => $headerId, 'class' => 'blockAccordionHeader' . ' ' . $backgroundColor, 'style' => 'margin-bottom:5px;'],
                    $collapseId
                ) .
                $collapseContent;

            $output = ddtaquiz_bootstrap_render::createAccordion($accordionId, $container);

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
     * @throws coding_exception
     * @throws dml_exception
     */
    protected function review_block_element_render($block, $blockelem, $attempt, $options, $feedback)
    {
        $output = '';
        if ($blockelem->is_block()) {
            /** @var block $childblock */
            $childblock = $blockelem->get_element();
            /** @var condition $condition */
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
     * @throws coding_exception
     * @throws dml_exception
     */
    protected function review_parts($parts, $block, $attempt, $options, $feedback)
    {
        $output = '';
        $index = 0;
        foreach ($parts as $part) {
            if (is_string($part)) {
                $index = 0;
                $output .= html_writer::div($part, 'specialfeedbacktext');
            } else if ($part instanceof feedback_used_question) {
                $index++;
                if ($part->isShifted()) {
                    $output .=
                        \html_writer::start_tag('div', ['class' => 'shiftedFeedback']) .
                        $this->review_block_element_render($block, $part->getBlockElement(), $attempt, $options, $feedback) .
                        html_writer::end_div();
                } else {
                    $output .= $this->review_block_element_render($block, $part->getBlockElement(), $attempt, $options, $feedback);
                }
            }
        }
        return $output;
    }

    /**
     * Generates the finish review button.
     *
     * @param int $cmid the course module id.
     * @return string HTML fragment.
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function finish_review_button($cmid)
    {
        $url = new moodle_url('/mod/ddtaquiz/view.php', array('id' => $cmid));
        $buttontext = get_string('finishreview', 'ddtaquiz');
        $button = new single_button($url, $buttontext);
        return $this->render($button);
    }
    #endregion
}

/**
 * Collects data for display by view.php.
 *
 * @copyright  2017 Jana Vatter <jana.vatter@stud.tu-darmstadt.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_ddtaquiz_view_object
{
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
