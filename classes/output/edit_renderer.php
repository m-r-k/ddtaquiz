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
 * Defines the edit renderer for the ddta quiz module.
 *
 * @package    mod_ddtaquiz
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_ddtaquiz\output;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/mod/ddtaquiz/locallib.php');
require_once($CFG->dirroot . '/lib/editorlib.php');

use \html_writer;

/**
 * The renderer for the ddta quiz module.
 *
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_renderer extends \plugin_renderer_base {
    /**
     * Render the edit page
     *
     * @param \block $block object containing all the block information.
     * @param \moodle_url $pageurl The URL of the page.
     * @param array $pagevars the variables from {@link question_edit_setup()}.
     * @param \feedback $feedback object containing all the feedback information.
     * @return string HTML to output.
     * @throws
     */
    public function edit_page($errorOutput, \block $block, \moodle_url $pageurl, array $pagevars, $feedback) {
        $output = '';

        /********************** Initializing  *****************************************/
        $output .= html_writer::start_tag('form',
            array('method' => 'POST', 'id' => 'blockeditingform', 'action' => $pageurl->out()));
        $output .= html_writer::tag('input', '',
            array('type' => 'hidden', 'name' => 'cmid', 'value' => $pageurl->get_param('cmid')));
        $output .= html_writer::tag('input', '', array('type' => 'hidden', 'name' => 'bid', 'value' => $block->get_id()));
        $output .= html_writer::tag('input', '', array('type' => 'hidden', 'name' => 'save', 'value' => 1));
        if ($block->is_main_block()) {
            $headingContent = get_string('editingquizx', 'ddtaquiz', format_string($block->get_name()));
            $headingContent .= html_writer::tag('input', '', array('type' => 'text',
                'name' => 'blockname', 'value' => $block->get_name(), 'class'=>'col-3 form-control inline rounded ml-3 '));
            $headingIcon = ''; //TODO: add icons
            $output .= $this->heading(
                ddtaquiz_bootstrap_render::createHeading(
                    $headingIcon,
                    $headingContent
                )
            );
        } else {
            $headingContent = get_string('editingblock', 'ddtaquiz');
            $headingContent .= html_writer::tag('input', '', array('type' => 'text',
                'name' => 'blockname', 'value' => $block->get_name(), 'class'=>'col-3 form-control inline rounded ml-3 '));
            $headingIcon = ''; //TODO: add icons
            $output .= $this->heading(
                ddtaquiz_bootstrap_render::createHeading(
                    $headingIcon,
                    $headingContent
                )
            );
        }
        /************************ Output error ******************/
        $output .= html_writer::div($errorOutput,'errors');



        if (!$block->is_main_block()) {
            if (!$block->get_quiz()->has_attempts()) {
                $output .= $this->condition_block($block->get_condition(), $block->get_condition_candidates());
            } else {
                $output .= $this->show_condition_block($block->get_condition(), $block->get_condition_candidates());
            }
        }


        /********************** Questions Card  *****************************************/
        $questionCardHeader = \html_writer::tag('h3', get_string('questions', 'ddtaquiz'), array('class' => 'questionheader'));

        // children of Accordion
        $accordionChildren = '';
        $children = $block->get_children();
        $counter = 1;
        foreach ($children as $child) {
            $accordionChildren .= html_writer::start_div('card');
            $accordionChildren .= $this->block_elem(
                $child,
                $pageurl,
                'block-children-list',
                null,
                $counter,
                true
            );
            $accordionChildren .= html_writer::end_div();
            $counter++;
        }
        // build questionCard body
        $questionCardBody = ddtaquiz_bootstrap_render::createAccordion('block-children-list',$accordionChildren);

        $questionCardFooter = null;
        $category = question_get_category_id_from_pagevars($pagevars);
        if (!$block->get_quiz()->has_attempts()) {
            $addmenu = $this->add_menu($block, $pageurl, $category);
            $questionCardFooter = html_writer::tag('div', $addmenu,['class'=>'float-right btn btn-dark card-btn', 'id' => 'addQuestionBtnContainer']);
        }

        $output .= ddtaquiz_bootstrap_render::createCard($questionCardBody,$questionCardHeader, $questionCardFooter);

        /********************** FeedBack Card  *****************************************/
        if ($block->is_main_block()) {
            $output .= $this->feedback_block($feedback, $pageurl);
        }

        /********************** close tags, load scripts *****************************************/
        $output .=
            html_writer::start_div('card-footer text-right').
            html_writer::tag('button', get_string('done', 'ddtaquiz'),
                array('class'=>'btn btn-primary card-btn','type' => 'submit', 'name' => 'done', 'value' => 1)).
            html_writer::end_div();

        $output .= html_writer::end_tag('form');

        $output .= $this->question_chooser($pageurl, $category);
        $this->page->requires->js_call_amd('mod_ddtaquiz/questionChooserInitializer', 'init');

        $output .= $this->question_bank_modal();
        $output .= $this->questionbank_loading();
        $this->page->requires->js_call_amd('mod_ddtaquiz/questionbank', 'init',[
            'panelId'=>'qbankChoiceModal',
            'addButtonId'=> 'qbankAddButton',
            'loadingId' => 'qbankLoading'
        ]);

        $this->page->requires->js_call_amd('mod_ddtaquiz/addnewblock', 'init');

        if (!$block->is_main_block()) {
            $output .= $this->condition_type_chooser($block->get_condition_candidates());
        }

        $this->page->requires->js_call_amd('mod_ddtaquiz/dragdrop', 'init');
        $this->page->requires->js_call_amd('mod_ddtaquiz/main', 'init');

        return $output;
    }

    /**
     * TODO: done
     *
     *
     * @param \block_element $blockelem
     * @param $pageurl
     * @param $accordionId
     * @param $parentCounter
     * @param $counter
     * @param bool $isMain
     * @return string
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public function block_elem(\block_element $blockelem, $pageurl,$accordionId,$parentCounter , $counter, $isMain = false) {
        // constants
        $headerId = 'block-element-'. $blockelem->get_id(). '-'.microtime();
        $nr = \html_writer::tag('b',($parentCounter)?$parentCounter.'.'.$counter:$counter);
        $collapseId = null;

        // elements
        $preContent = '';
        $content = '';
        $postContent = '';
        $collapseContent = '';

        // add only if main block
        if ($isMain){
            //mover
            if(!$blockelem->get_quiz()->has_attempts()) {
                $preContent .= $this->question_move_icon();
            }
            //number to count questions/blocks
            $preContent .=
                html_writer::tag('input', '',
                    array('type' => 'hidden', 'name' => 'elementsorder[]', 'value' => $blockelem->get_id()));

            // edit/delete buttons
            $edithtml = '';
            $removehtml = '';
            if (!$blockelem->get_quiz()->has_attempts()) {
                $edithtml .= $this->element_edit_button($blockelem, $pageurl);
                $removehtml = $this->element_remove_button($blockelem, $pageurl);
            } else if ($blockelem->is_block()) {
                $edithtml .= $this->element_edit_button($blockelem, $pageurl);
            }

            $postContent .= \html_writer::div($edithtml . $removehtml, 'blockelementbuttons');
        }

        $blockClass='';
        // render collapsible body if block, with block elements
        if($blockelem->is_block()){
            $content .= html_writer::span($nr.' '. $blockelem->get_name(), 'blockelementblock');
            /** @var \block $blockelem */
            $blockClass = 'blockAccordionHeader';
            $collapseId = 'collapse-'. $blockelem->get_id();
            $newParentCounter = ($parentCounter)?$parentCounter.'.'.$counter:$counter;
            $collapseContent = $this->block_elem_desc($blockelem,$pageurl,$collapseId,$headerId,$accordionId,$newParentCounter);
        }else{
            // otherwise just show body
            $content .= html_writer::span($nr.' '. $blockelem->get_name(), 'blockelementdescriptionname');
        }
        // return as accordion header + accordion body , expected to be part of an accordion
        $container = ddtaquiz_bootstrap_render::createAccordionHeader(
            $preContent,
            $content,
            $postContent,
            ['id'=>$headerId,'class'=>$blockClass],
            $collapseId
        ).
        $collapseContent;


        return $container;
    }

    /**
     * TODO: done
     *
     * Renders the icon to move questions and blocks.
     *
     * @return string the HTML of the move icon.
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public function question_move_icon() {
        return html_writer::link(new \moodle_url('#'),
            $this->pix_icon('i/dragdrop', get_string('move'), 'moodle', array('class' => 'iconsmall', 'title' => '')),
            array('class' => 'editing_move', 'data-action' => 'move')
            );
    }

    /**
     * TODO: done
     *
     * Render the description.
     *
     * @param \block_element $blockelem
     * @param $pageurl
     * @param $id
     * @param $triggerId
     * @param $parentAccordionId
     * @return string
     * @throws
     */
    protected function block_elem_desc(\block_element $blockelem,$pageurl,$id,$triggerId,$parentAccordionId,$parentCounter) {
        $accordionId = 'block-element-accordion-'. $blockelem->get_id();
        $accordionChildren = '';
        $children = $blockelem->get_element()->get_children();
        $counter = 1;
        foreach ($children as $child) {
            $accordionChildren .= html_writer::start_div('card');
            $accordionChildren .= $this->block_elem($child, $pageurl,$accordionId, $parentCounter,$counter,false);
            $accordionChildren .= html_writer::end_div();
            $counter++;
        }
        $elementDescrp = ddtaquiz_bootstrap_render::createAccordionCollapsible(
            $id,
            $triggerId,
            $parentAccordionId,
            ddtaquiz_bootstrap_render::createAccordion($accordionId,$accordionChildren)
        );


        return $elementDescrp;
    }

    /**
     * Outputs the edit button HTML for an element.
     *
     * @param \block_element $element the element to get the button for.
     * @param \moodle_url $returnurl the URL of the page.
     * @return string HTML to output.
     * @throws
     */
    public function element_edit_button($element, $returnurl) {
        global $OUTPUT, $CFG;
        // Minor efficiency saving. Only get strings once, even if there are a lot of icons on one page.
        static $stredit = null;
        static $strview = null;
        if ($stredit === null) {
            $stredit = get_string('edit');
            $strview = get_string('view');
        }

        // What sort of icon should we show?
        $action = '';
        if ($element->may_edit()) {
            $action = $stredit;
            $icon = '/t/edit';
        } else if ($element->may_view()) {
            $action = $strview;
            $icon = '/i/info';
        }

        // Build the icon.
        if ($action) {
            return html_writer::tag('button',
                '<img src="' . $OUTPUT->image_url($icon) . '" alt="' . $action . '" />',
                array('class'=>'btn btn-warning','type' => 'submit', 'name' => 'edit', 'value' => $element->get_id()));
        } else {
            return '';
        }
    }

    /**
     * TODO: done
     *
     * Outputs the edit button HTML for a feedbackelement.
     *
     * @param \feedback_block $element the element to get the button for.
     * @param \moodle_url $returnurl the URL of the page.
     * @return string HTML to output.
     *
     * @throws \coding_exception
     */
    public function feedback_edit_button($element, $returnurl) {
        global $OUTPUT, $CFG;
        // Minor efficiency saving. Only get strings once, even if there are a lot of icons on one page.
        static $stredit = null;
        if ($stredit === null) {
            $stredit = get_string('edit');
        }

        $icon = '/t/edit';

        // Build the icon.
        return html_writer::tag('button',
            '<img src="' . $OUTPUT->image_url($icon) . '" alt="' . $stredit . '" />',
            array('class'=>'btn btn-warning','type' => 'submit', 'name' => 'feedbackedit', 'value' => $element->get_id()));
    }

    /**
     * TODO: done
     *
     * Outputs the remove button HTML for an element.
     *
     * @param \block_element $element the element to get the button for.
     * @param \moodle_url $pageurl The URL of the page.
     * @return string HTML to output.
     * @throws
     */
    public function element_remove_button($element, $pageurl) {
        $image = $this->pix_icon('t/delete', get_string('delete'));
        return html_writer::tag('button', $image,
            array('class'=>'btn btn-danger','type' => 'submit', 'name' => 'delete', 'value' => $element->get_id()));
    }

    /**
     * TODO: done
     *
     * Outputs the remove button HTML for a feedbackelement.
     *
     * @param \feedback_block $element the element to get the button for.
     * @param \moodle_url $pageurl The URL of the page.
     * @return string HTML to output.
     * @throws
     */
    public function feedback_element_remove_button($element, $pageurl) {
        $image = $this->pix_icon('t/delete', get_string('delete'));
        return html_writer::tag('button', $image,
            array('class'=>'btn btn-danger','type' => 'submit', 'name' => 'feedbackdelete', 'value' => $element->get_id()));
    }

    /**
     * TODO: done
     *
     * Outputs the add menu HTML.
     *
     * @param \block $block object containing all the block information.
     * @param \moodle_url $pageurl The URL of the page.
     * @param int $category the id of the category for new questions.
     * @return string HTML to output.
     * @throws
     */
    protected function add_menu(\block $block, \moodle_url $pageurl, $category) {
        $menu = new \action_menu();
        $menu->set_alignment(\action_menu::TL, \action_menu::TL);
        $trigger = html_writer::tag('span', get_string('add', 'ddtaquiz'));
        $menu->set_menu_trigger($trigger);
        // The menu appears within an absolutely positioned element causing width problems.
        // Make sure no-wrap is set so that we don't get a squashed menu.
        $menu->set_nowrap_on_items(true);
        $params = array('returnurl' => $pageurl->out_as_local_url(false),
            'category' => $category,
            'appendqnumstring' => 'addquestion');

        // Button to add a question.
        $addaquestion = new \action_menu_link_secondary(
            new \moodle_url('/question/addquestion.php', $params),
            new \pix_icon('t/add', get_string('addaquestion', 'ddtaquiz'),
                'moodle', array('class' => 'iconsmall', 'title' => '')), get_string('addaquestion', 'ddtaquiz'),
            array(
                'class' => 'cm-edit-action',
                'data-toggle'=>"modal",
                'data-target'=>"#qtypeChoiceModal"
            )
        );
        $menu->add($addaquestion);

        // Button to add question from question bank.
        $questionbank = new \action_menu_link_secondary($pageurl,
            new \pix_icon('t/add', get_string('questionbank', 'ddtaquiz'), 'moodle',
                array('class' => 'iconsmall', 'title' => '')), get_string('questionbank', 'ddtaquiz'),
            array(
                'class' => 'cm-edit-action questionbank',
                'data-action' => 'questionbank',
                'data-cmid' => $block->get_quiz()->get_cmid(),
                'data-bid' => $block->get_id(),
                'data-toggle'=>"modal",
                'data-target'=>"#qbankChoiceModal"
            )
        );
        $menu->add($questionbank);
        $menu->prioritise = true;

        // Button to add a block.
        $addblockurl = new \moodle_url($pageurl, array('addblock' => 1));
        $addablock = new \action_menu_link_secondary($addblockurl,
            new \pix_icon('t/add', get_string('addablock', 'ddtaquiz'), 'moodle', array('class' => 'iconsmall', 'title' => '')),
            get_string('addablock', 'ddtaquiz'),
            array('class' => 'cm-edit-action addnewblock'));
        $menu->add($addablock);

        return html_writer::tag('span', $this->render($menu),
            array('class' => 'add-menu-outer'));
    }

    /**
     * TODO:
     * Renders the HTML for the condition block.
     *
     * @param \condition $condition the condition to be rendered.
     * @param array $candidates the block_elements the condition can depend on.
     * @return string the HTML of the condition block.
     * @throws
     */
    public function condition_block(\condition $condition, $candidates) {
        $conditionCardHeader = \html_writer::tag('h3', get_string('conditions', 'ddtaquiz'), array('class' => 'conditionblockheader'));

        $conjunctionchooser = $this->conjunction_chooser($condition);

        $conditionlist = \html_writer::div($this->condition($condition, $candidates), 'conditionpartslist');

        $container =  $conjunctionchooser . $conditionlist  ;

        // build questionCard body
        $conditionCardBody = ddtaquiz_bootstrap_render::createAccordion('condition-list',$container);

        //footer part to add condition for single question
        $pointsConditionBtn = \html_writer::tag('button', get_string('addpointscondition', 'ddtaquiz'),
            array('type' => 'submit','class' => 'btn btn-primary', 'id'=>'addPointsConditionBtn'));
        //TODO: string ?
        $mqPointsConditionBtn = \html_writer::tag('button', 'Add MQ Condition',
            array('type' => 'submit','class' => 'btn btn-primary mr-3', 'id'=>'addMQPointsConditionBtn'));

        $conditionCardFooter =
            html_writer::tag('div', $mqPointsConditionBtn.$pointsConditionBtn,[
                'class'=>'text-right'
                ]
            );


        return  ddtaquiz_bootstrap_render::createCard($conditionCardBody,$conditionCardHeader, $conditionCardFooter);
    }

    /**
     * TODO: done
     * Renders the HTML for the conjunction type chooser.
     *
     * @param \condition $condition the condition to render this chooser for.
     * @return string the HTML of the chooser.
     * @throws
     */
    protected function conjunction_chooser(\condition $condition) {

        if ($condition->get_useand()) {
            $options = \html_writer::tag('option', get_string('all', 'ddtaquiz'), array('value' => 1, 'selected' => ''));
            $options .= \html_writer::tag('option', get_string('atleastone', 'ddtaquiz'), array('value' => 0));
        } else {
            $options = \html_writer::tag('option', get_string('all', 'ddtaquiz'), array('value' => 1));
            $options .= \html_writer::tag('option', get_string('atleastone', 'ddtaquiz'),
                array('value' => 0, 'selected' => ''));
        }

        $chooser = \html_writer::tag('select', $options, array('name' => 'use_and','class'=>'custom-select'));
        $content = \html_writer::tag('label', get_string('mustfullfill', 'ddtaquiz') . ' ' .
            $chooser . ' ' . get_string('oftheconditions', 'ddtaquiz'),['class'=>'mustFullFillLabel']);
        return ddtaquiz_bootstrap_render::createAccordionHeader(
            '', $content, '',
            ['class'=>'conditionHead']
        );
    }

    /**
     * TODO:done
     * Renders the HTML for the condition type chooser.
     *
     * @param array $candidates the block_elements the condition can depend on.
     * @return string the HTML of the condtion type chooser
     * @throws
     */
    protected function condition_type_chooser($candidates) {
        $body =
            \html_writer::div(\html_writer::div($this->points_condition($candidates), 'conditionpart'), 'pointsconditioncontainer').
            \html_writer::div(\html_writer::div($this->mq_points_condition($candidates,'condition-list'), 'mq-conditionpart'), 'mq-pointsconditioncontainer').

        $header = html_writer::div(get_string('choosecondtiontypetoadd', 'ddtaquiz'), 'chooserheader hd');

        //this modal will not be opened, it will just save the conditions templates
        return ddtaquiz_bootstrap_render::createModal(
            $header,
            $body,
            '',
            ['id'=>'conditiontypechoicecontainer']
        );
    }

    /**
     * TODO: done
     * @param \condition $condition
     * @param $candidates
     * @return string
     * @throws \coding_exception
     */
    protected function condition(\condition $condition, $candidates) {
        $output = '';
        foreach ($condition->get_parts() as $part) {
            $output .= $this->condition_part($candidates, $part);
        }

        foreach ($condition->get_mqParts() as $part) {
            $output .= $this->condition_mq_part($candidates, $part);
        }
        return $output;
    }

    /**
     * TODO: done
     * @param $candidates
     * @param $part
     * @return string
     */
    protected function condition_part($candidates, $part) {
        static $index = 0;
        $index += 1;
        $conditionpart = '';

        switch ($part->get_type()) {
            case \condition_part::WAS_DISPLAYED:
                break;
            default:
                $conditionpart = $this->points_condition($candidates, 'part' . $index, $part);
        }

        $conditionpart .= \html_writer::tag('input', '',
            array('class' => 'conditionid', 'name' => 'conditionparts[part' . $index . '][id]', 'value' => $part->get_id()));

        return \html_writer::div($conditionpart, 'conditionpart');
    }

    /**
     * TODO: done
     * @param $candidates
     * @param $part
     * @return string
     * @throws \coding_exception
     */
    protected function condition_mq_part($candidates, $part) {
        static $mqIndex = 0;
        $mqIndex += 1;
        $conditionpart = '';
        switch ($part->get_type()) {
            case \condition_part::WAS_DISPLAYED:
                break;
            default:
                $conditionpart = $this->mq_points_condition($candidates, 'condition-list','part' . $mqIndex, $part);
        }

        $conditionpart .= \html_writer::tag('input', '',
            array('class' => 'conditionid', 'name' => 'conditionMQParts[part' . $mqIndex . '][id]', 'value' => $part->get_id()));

        return \html_writer::div($conditionpart, 'mq-conditionpart');
    }

    /**
     * TODO: done
     * Renders the HTML for the condition over question points.
     *
     * @param array $candidates the block_elements the condition can depend on.
     * @param string $index the index into the conditionparts array for this condition.
     * @param \condition_part|null $part hte condtion part to fill in or null.
     * @return string the HTML of the points condition.
     * @throws
     */
    protected function points_condition($candidates, $index = '', $part = null) {
        //If no candidate is available display a warning
        if(count($candidates)==0)
        {

            $this->page->requires->js_call_amd('mod_ddtaquiz/main', 'cleanAlerts');
            $content = \html_writer::tag('label', get_string('noCandidatesForCondition', 'ddtaquiz'));
            return ddtaquiz_bootstrap_render::createAlert('danger',$content);
        }

        $preContent = \html_writer::tag('label', get_string('gradeat', 'ddtaquiz'),
            array('class' => 'conditionelement'));

        $content = \html_writer::tag('span', $this->question_selector($candidates, $index, $part));
        $content .= ' ' . \html_writer::tag('label', get_string('mustbe', 'ddtaquiz'),
            array('class' => 'conditionelement'));


        $postContent = \html_writer::tag('span', $this->comparator_selector($index, $part));
        $value = 0;
        if ($part) {
            $value = $part->get_grade();
        }
        $postContent .= ' ' . \html_writer::tag('input', '',
            array('class' => 'conditionelement conditionpoints form-control inline', 'name' => 'conditionparts[' . $index . '][points]',
                'type' => 'number', 'value' => $value));


        $strdelete = get_string('delete');
        $image = $this->pix_icon('t/delete', $strdelete);
        $postContent .= $this->action_link('#', $image, null, array('title' => $strdelete,
            'class' => 'cm-edit-action editing_delete element-remove-button conditionpartdelete btn btn-danger float-right', 'data-action' => 'delete'));

        return ddtaquiz_bootstrap_render::createAccordionHeader(
            $preContent,
            $content,
            $postContent
        );
    }

    /**
     * TODO: done
     * @param $candidates
     * @param $accordionId
     * @param string $mqIndex
     * @param \multiquestions_condition_part|null $part
     * @return string
     * @throws \coding_exception
     */
    protected function mq_points_condition($candidates, $accordionId, $mqIndex = '', ?\multiquestions_condition_part $part = null) {
        //If no candidate is available display a warning
        if(count($candidates)==0)
        {

            $this->page->requires->js_call_amd('mod_ddtaquiz/main', 'cleanAlerts');
            $content = \html_writer::tag('label', get_string('noCandidatesForCondition', 'ddtaquiz'));
            return ddtaquiz_bootstrap_render::createAlert('danger',$content);
        }

        //TODO: string add
        $preContent = \html_writer::tag('label', 'Grade of all ',
            array('class' => 'conditionelement'));

        $content = \html_writer::link('#', 'Selected Questions',
            array('class' => 'conditionelement', ));

        $postContent = ' ' . \html_writer::tag('label', get_string('mustbe', 'ddtaquiz'),
                array('class' => 'conditionelement'));


        $postContent .= \html_writer::tag('span', $this->comparator_mq_selector($mqIndex, $part));
        $value = 0;
        if ($part) {
            $value = $part->get_grade();
        }
        $postContent .= ' ' . \html_writer::tag('input', '',
                array('class' => 'conditionelement conditionpoints form-control inline', 'name' => 'conditionMQParts[' . $mqIndex . '][points]',
                    'type' => 'number', 'value' => $value));


        $strdelete = get_string('delete');
        $image = $this->pix_icon('t/delete', $strdelete);
        $postContent .= $this->action_link('#', $image, null, array('title' => $strdelete,
            'class' => 'cm-edit-action editing_delete element-remove-button conditionpartdelete btn btn-danger float-right', 'data-action' => 'delete'));

        $collapseId = 'mq-collapse-'. $mqIndex.'-'.microtime();
        $headingId = 'mq-heading-'.$mqIndex.'-'.microtime();
        return
            ddtaquiz_bootstrap_render::createAccordionHeader(
                $preContent,
                $content,
                $postContent,
                ['id'=>$headingId],
                $collapseId
            ).
            ddtaquiz_bootstrap_render::createAccordionCollapsible($collapseId,$headingId,$accordionId,
                $this->mq_question_checkboxes($candidates,$mqIndex,$part)
            );
    }
    /**
     * Renders the HTML for a dropdownbox of all questions, that this block can have conditions on.
     *
     * @param array $candidates the block_elements the condition can depend on.
     * @param string $index the index into the conditionparts array for this condition.
     * @param \condition_part|null $part the condition part used to fill in a value or null.
     * @return string the HTML of the dropdownbox.
     */
    protected function question_selector($candidates, $index, $part) {
        $options = '';
        foreach ($candidates as $element) {
            $attributes = array('value' => $element->get_id());
            if ($part && $part->get_elementid() == $element->get_id()) {
                $attributes['selected'] = '';
            }
            $options .= \html_writer::tag('option', $element->get_name(), $attributes);
        }
        return \html_writer::tag('select', $options,
            array('class' => 'conditionquestion custom-select', 'name' => 'conditionparts[' . $index . '][question]'));
    }

    /**
     * TODO: done
     * @param $candidates
     * @param $mqIndex
     * @param $part
     * @return string
     */
    protected function mq_question_checkboxes($candidates, $mqIndex, \multiquestions_condition_part $part = null) {
        $elements = [];
        foreach ($candidates as $question) {
            $element['id'] =  $question->get_id();
            if ($part && in_array($question->get_id(),$part->get_elements())) {
                $element['checked'] = '';
            }else{
                unset($element['checked']);
            }
            $element['name']  = $question->get_name();
            $elements[] = $element;
        }
        return ddtaquiz_bootstrap_render::createMQCheckBoxes($elements, ['name' => 'conditionMQParts[' . $mqIndex . '][questions]']);
    }


    /**
     * Renders the HTML for a dropdownbox of all comparators that can be used in conditions.
     *
     * @param string $index the index into the conditionparts array for this condition.
     * @param \condition_part|null $part the condition part used to fill in a value or null.
     * @return string the HTML of the dropdownbox.
     */
    protected function comparator_selector($index, $part = null) {
        $options = $this->comparator_attributes($part);
        return \html_writer::tag('select', $options,
            array('class' => 'conditiontype custom-select', 'name' => 'conditionparts[' . $index . '][type]'));
    }

    /**
     * TODO:done
     * @param $mqIndex
     * @param null $part
     * @return string
     */
    protected function comparator_mq_selector($mqIndex, $part = null) {
        $options = $this->comparator_attributes($part);
        return \html_writer::tag('select', $options,
            array('class' => 'conditiontype custom-select', 'name' => 'conditionMQParts[' . $mqIndex . '][type]'));
    }

    /**
     * TODO:done
     * @param null $part
     * @return string
     */
    private function comparator_attributes($part = null){
        if ($part) {
            $attributes = array();
            $attributes[\condition_part::LESS] = array('value' => \condition_part::LESS);
            $attributes[\condition_part::LESS_OR_EQUAL] = array('value' => \condition_part::LESS_OR_EQUAL);
            $attributes[\condition_part::GREATER] = array('value' => \condition_part::GREATER);
            $attributes[\condition_part::GREATER_OR_EQUAL] = array('value' => \condition_part::GREATER_OR_EQUAL);
            $attributes[\condition_part::EQUAL] = array('value' => \condition_part::EQUAL);
            $attributes[\condition_part::NOT_EQUAL] = array('value' => \condition_part::NOT_EQUAL);

            $attributes[$part->get_type()]['selected'] = '';

            $options = \html_writer::tag('option', '<', $attributes[\condition_part::LESS]);
            $options .= \html_writer::tag('option', '&le;', $attributes[\condition_part::LESS_OR_EQUAL]);
            $options .= \html_writer::tag('option', '>', $attributes[\condition_part::GREATER]);
            $options .= \html_writer::tag('option', '&ge;', $attributes[\condition_part::GREATER_OR_EQUAL]);
            $options .= \html_writer::tag('option', '=', $attributes[\condition_part::EQUAL]);
            $options .= \html_writer::tag('option', '&ne;', $attributes[\condition_part::NOT_EQUAL]);
        } else {
            $options = \html_writer::tag('option', '<', array('value' => \condition_part::LESS));
            $options .= \html_writer::tag('option', '&le;', array('value' => \condition_part::LESS_OR_EQUAL));
            $options .= \html_writer::tag('option', '>', array('value' => \condition_part::GREATER));
            $options .= \html_writer::tag('option', '&ge;', array('value' => \condition_part::GREATER_OR_EQUAL));
            $options .= \html_writer::tag('option', '=', array('value' => \condition_part::EQUAL));
            $options .= \html_writer::tag('option', '&ne;', array('value' => \condition_part::NOT_EQUAL));
        }

        return $options;
    }

    /**
     * Renders the HTML for the question type chooser dialogue.
     *
     * @param \moodle_url $returnurl the url to return to after creating the question.
     * @param int $category the id of the category for the question.
     * @return string the HTML of the dialogue.
     * @throws
     */
    public function question_chooser(\moodle_url $returnurl, $category) {
        $body = html_writer::div(print_choose_qtype_to_add_form(array('returnurl' => $returnurl->out_as_local_url(false),
            'cmid' => $returnurl->get_param('cmid'), 'appendqnumstring' => 'addquestion', 'category' => $category),
            null, false), '', array('id' => 'qtypeChoiceBody'));
        $addButton =
            html_writer::start_tag('button',['class'=> 'btn btn-primary', 'id'=>'addQuestionBtn']).
            'Add'.
            html_writer::end_tag('button');

        return  ddtaquiz_bootstrap_render::createModal(
            '',
            $body,
            $addButton,
            ['id'=>'qtypeChoiceModal']
        );
    }

    /**
     * Renders the HTML for the question bank loading icon.
     *
     * @return string the HTML div of the icon.
     */
    public function questionbank_loading() {
        return html_writer::div(html_writer::empty_tag('img',
            array('alt' => 'loading', 'class' => 'loading-icon', 'src' => $this->image_url('i/loading'))),
            'questionbankloading', ['id'=>'qbankLoading']);
    }

    public function question_bank_modal(){
        $buttons =
            html_writer::tag('button','Add Selected Questions',
                ['class'=> 'btn btn-primary', 'id'=>'qbankAddButton']);

        return  ddtaquiz_bootstrap_render::createModal(
            get_string('addfromquestionbank', 'ddtaquiz'),
            '',
            $buttons,
            ['id'=>'qbankChoiceModal']
        );
    }

    /**
     * Return the contents of the question bank, to be displayed in the question-bank pop-up.
     *
     * @param \mod_ddtaquiz\question\bank\custom_view $questionbank the question bank view object.
     * @param array $pagevars the variables from {@link \question_edit_setup()}.
     * @return string HTML to output / send back in response to an AJAX request.
     */
    public function question_bank_contents(\mod_ddtaquiz\question\bank\custom_view $questionbank, array $pagevars) {
        return $questionbank->render('editq', $pagevars['page'], $pagevars['qperpage'], $pagevars['cat'], true, false, false);
    }

    /**
     * Renders the HTML for the condition block where no editing is possible.
     *
     * @param \condition $condition the condition to be rendered.
     * @param array $candidates the block_elements the condition can depend on.
     * @return string the HTML of the condition block.
     */
    public function show_condition_block($condition, $candidates) {
        $header = \html_writer::tag('h3', get_string('conditions', 'ddtaquiz'), array('class' => 'conditionblockheader'));
        $start = \html_writer::start_tag('ul', array('id' => 'condition-list'));
        if ($condition->get_useand()) {
            $option = \html_writer::tag('b', get_string('all', 'ddtaquiz'));
        } else {
            $option = \html_writer::tag('b', get_string('atleastone', 'ddtaquiz'));
        }
        $conjunction = \html_writer::div(get_string('mustfullfill', 'ddtaquiz') . ' ' . $option . ' ' . get_string('oftheconditions', 'ddtaquiz'), 'conjunction');
        $conditionlist = \html_writer::div($this->show_condition($condition, $candidates));
        $end = \html_writer::end_tag('ul');

        $container = $header . $start . $conjunction . $conditionlist . $end;
        return html_writer::div($container, 'conditionblock');
    }

    /**
     * Renders the HTML for a condition.
     *
     * @param \condition $condition the condition to render.
     * @param array $candidates the block_elements the condition can depend on.
     * @return string the HTML of the condition.
     */
    protected function show_condition($condition, $candidates) {
        $output = '';
        foreach ($condition->get_parts() as $part) {
            $output .= $this->show_condition_part($part, $candidates);
        }
        return $output;
    }

    /**
     * Renders the HTML for a condition part.
     *
     * @param \condition_part $part the part of the condition to render.
     * @param array $candidates the block_elements the condition can depend on.
     * @return string the HTML of the condition part.
     */
    protected function show_condition_part($part, $candidates) {
        $condition = '';
        $condition .= get_string('gradeat', 'ddtaquiz') . ' ';
        foreach ($candidates as $element) {
            if ($part->get_elementid() == $element->get_id()) {
                $condition .= \html_writer::tag('b', $element->get_name() . ' ');
            }
        }
        $condition .= get_string('mustbe', 'ddtaquiz'). ' ';
        switch ($part->get_type()) {
            case \condition_part::EQUAL:
                $condition .= \html_writer::tag('b', '=');
                break;
            case \condition_part::GREATER:
                $condition .= \html_writer::tag('b', '>');
                break;
            case \condition_part::GREATER_OR_EQUAL:
                $condition .= \html_writer::tag('b', '&ge;');
                break;
            case \condition_part::LESS:
                $condition .= \html_writer::tag('b', '<');
                break;
            case \condition_part::LESS_OR_EQUAL:
                $condition .= \html_writer::tag('b', '&le;');
                break;
            case \condition_part::NOT_EQUAL:
                $condition .= \html_writer::tag('b', '&ne;');
                break;
        }
        $condition .= \html_writer::tag('b', ' ' . $part->get_grade());
        return \html_writer::div($condition, 'part');
    }

    /**
     * TODO: done
     *
     * Render the feedback block.
     *
     * @param \feedback $feedback the feedback for which to render the block.
     * @param \moodle_url $pageurl the url of this page.
     * @return string the HTML of the feedback block.
     *
     * @throws \coding_exception
     */
    public function feedback_block($feedback, $pageurl) {
        $questionCardHeader = html_writer::tag('h3', get_string('feedback', 'ddtaquiz'), array('class' => 'feedbackheader'));
        $output = '';

        // children of Accordion
        $accordionChildren = '';
        $blocks = $feedback->get_blocks();
        $counter = 1;
        foreach ($blocks as $block) {
            $accordionChildren .= html_writer::start_div('card');
            $accordionChildren .= $this->feedback_block_elem($block, $pageurl,$counter);
            $accordionChildren .= html_writer::end_div();
            $counter++;
        }
        // build questionCard body
        $questionCardBody = ddtaquiz_bootstrap_render::createAccordion('feedbackblock-children-list',$accordionChildren);

        $questionCardFooter = html_writer::tag('button', get_string('addfeedback', 'ddtaquiz'),
            array('type' => 'submit', 'name' => 'addfeedback', 'value' => 1,'class'=>'btn btn-dark card-btn float-right'));

        return ddtaquiz_bootstrap_render::createCard($questionCardBody,$questionCardHeader, $questionCardFooter);
        //return html_writer::div($output, 'feedbackblock');
    }

    /**
     * TODO: done
     *
     * Render one element of a feedbackbblock.
     *
     * @param \feedback_block $feedbackelem An element of a block.
     * @param \moodle_url $pageurl The URL of the page.
     * @return string HTML to display this element.
     * @throws
     */
    public function feedback_block_elem(\feedback_block $feedbackelem, $pageurl,$counter) {
        // constants
        $headerId = 'feedback-element-'. $feedbackelem->get_id(). '-'.microtime();
        $nr = \html_writer::tag('b',$counter);

        // elements
        $preContent = '';
        $content = '';
        $postContent = '';

        // Description of the element.
        $content = \html_writer::div($nr.' '.$feedbackelem->get_name(), 'blockelement');
        $edithtml = $this->feedback_edit_button($feedbackelem, $pageurl);
        $removehtml = $this->feedback_element_remove_button($feedbackelem, $pageurl);
        $postContent = \html_writer::div($edithtml . $removehtml, 'blockelementbuttons');


        // return as accordion header + accordion body , expected to be part of an accordion
        $container = ddtaquiz_bootstrap_render::createAccordionHeader(
                $preContent,
                $content,
                $postContent,
                ['id'=>$headerId]
        );


        return $container;
    }

    /**
     * @param $errorOutput
     * @param \feedback_block $block
     * @param \moodle_url $pageurl
     * @param array $pagevars
     * @return string
     * @throws \coding_exception
     */
    public function edit_feedback_page($errorOutput, \feedback_block $block, \moodle_url $pageurl, array $pagevars) {
        $candidates = $block->get_quiz()->get_elements();
        $output = '';

        //hidden inputs
        $output .= html_writer::start_tag('form',
            array('method' => 'POST', 'id' => 'blockeditingform', 'action' => $pageurl->out()));
        $output .= html_writer::tag('input', '',
            array('type' => 'hidden', 'name' => 'cmid', 'value' => $pageurl->get_param('cmid')));
        $output .= html_writer::tag('input', '', array('type' => 'hidden', 'name' => 'bid', 'value' => $block->get_id()));
        $output .= html_writer::tag('input', '', array('type' => 'hidden', 'name' => 'save', 'value' => 1));

        //header
        $headingContent = get_string('editingfeedback', 'ddtaquiz');
        $headingContent .= html_writer::tag('input', '', array('class'=>' col-3 form-control inline rounded ml-3 ','type' => 'text', 'name' => 'blockname', 'value' => $block->get_name()));
        $headingIcon = ''; //TODO: add icons
        $output .= $this->heading(
            ddtaquiz_bootstrap_render::createHeading(
                $headingIcon,
                $headingContent
            )
        );

        $output .= html_writer::div($errorOutput,'errors');

        //
        $output .= $this->uses_block($block);

        $output .= $this->condition_block($block->get_condition(), $candidates);

        $output .= $this->feedback_editor($block->get_feedback_text());

        $output .=
            html_writer::start_div('card-footer text-right').
            html_writer::tag('button', get_string('done', 'ddtaquiz'),
                array('class'=>'btn btn-primary card-btn','type' => 'submit', 'name' => 'done', 'value' => 1)).
            html_writer::end_div();

        $output .= html_writer::end_tag('form');

        $output .= $this->condition_type_chooser($candidates);

        $this->page->requires->js_call_amd('mod_ddtaquiz/main', 'init');

        return $output;
    }

    /**
     * Outputs the HTML to choose which questions feedback is replaced by the feedback block.
     *
     * @param \feedback_block $block the block for which to generate the HTML.
     * @return string HTML to output.
     */
    public function uses_block(\feedback_block $block) {
        $header = \html_writer::tag('h3', get_string('usesquestions', 'ddtaquiz'));

        $body = \html_writer::start_div('usedquestions');
        foreach ($block->get_used_question_instances() as $instance) {
            $body .= $this->uses_element($block, $instance);
        }
        $body .= html_writer::end_div();
        $footer = \html_writer::tag('button', get_string('addusedquestion', 'ddtaquiz'), array('class' => 'addusedquestion btn btn-dark float-right card-btn'));
        $footer .=
            \html_writer::div($this->uses_element($block), 'usesquestioncontainer',['hidden'=>'true']);

        $this->page->requires->js_call_amd('mod_ddtaquiz/feedback', 'init');
        return ddtaquiz_bootstrap_render::createCard(
            $body,
            $header,
            $footer
        );
    }

    /**
     * Outputs the HTML for a element whose feedback is replaced by the feedback block.
     *
     * @param \feedback_block $block the block for which to generate the HTML.
     * @param null|\block_element $question the question for which to generate the HTML.
     * @return string HTML to output.
     */
    public function uses_element(\feedback_block $block, \block_element $question = null) {
        static $index = 64; // ... 'A' - 1.
        $index += 1;

        $preContent = \html_writer::span(html_writer::tag('b',chr($index)), 'usesquestionletter');
        $content = $this->uses_selector($block, $question);

        $strdelete = get_string('delete');
        $image = $this->pix_icon('t/delete', $strdelete);
        $postContent = $this->action_link('#', $image, null, array('title' => $strdelete,
            'class' => 'cm-edit-action editing_delete element-remove-button usesdelete btn btn-danger float-right', 'data-action' => 'delete'));

        return ddtaquiz_bootstrap_render::createAccordionHeader(
            $preContent,
            $content,
            $postContent,
            [
                'class' => 'usesquestion'
            ]
        );
    }

    /**
     * Outputs the HTML to choose a question whose feedback is replaced by the feedback block.
     *
     * @param \feedback_block $block the block for which to generate the HTML.
     * @param null|\block_element $selected the selected option.
     * @return string HTML to output.
     */
    public function uses_selector(\feedback_block $block, \block_element $selected = null) {
        $options = '';

        static $index = 0;
        $index += 1;

        foreach ($block->get_quiz()->get_elements() as $element) {
            $attributes = array('value' => $element->get_id());
            if ($selected && $selected->get_id() == $element->get_id()) {
                $attributes['selected'] = '';
            }
            $options .=
                \html_writer::tag('option', $element->get_name(), $attributes);
        }
        if ($selected) {
            return \html_writer::tag('select', $options,
                array('class' => 'usesquestionselector custom-select', 'name' => 'usesquestions[' . $index . ']'));
        } else {
            return \html_writer::tag('select', $options,
                array('class' => 'usesquestionselector custom-select'));
        }
    }

    /**
     * TODO: done
     * Generates the HTML for the feeback text editor.
     *
     * @param string $feedbacktext the feedback text to put in at the start.
     * @return string the HTML output.
     * @throws
     */
    public function feedback_editor($feedbacktext = '') {
        $heading = $this->heading_with_help(get_string('feedbacktext', 'ddtaquiz'), 'feedbackquestion', 'ddtaquiz');

        $editor = editors_get_preferred_editor();
        $editor->set_text($feedbacktext);
        $editor->use_editor('feedbacktext',[]);
        $editorhtml = \html_writer::tag('textarea', s($feedbacktext),
            array('id' => 'feedbacktext', 'name' => 'feedbacktext', 'rows' => 15, 'cols' => 80));
        $body = \html_writer::div($editorhtml);

        return ddtaquiz_bootstrap_render::createCard(
            $body,
            $heading
        );
    }
}
