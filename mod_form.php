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
 * Defines the ddtaquiz module ettings form.
 *
 * @package    mod_ddtaquiz
 * @copyright  2006 Jamie Pratt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/ddtaquiz/locallib.php');


/**
 * Settings form for the ddtaquiz module.
 *
 * @copyright  2006 Jamie Pratt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_ddtaquiz_mod_form extends moodleform_mod {
    /** @var array options to be used with date_time_selector fields in the ddtaquiz. */
    public static $datefieldoptions = array('optional' => true);

    protected $_feedbacks;
    protected static $reviewfields = array(); // Initialised in the constructor.

    /** @var int the max number of attempts allowed in any user or group override on this ddtaquiz. */
    protected $maxattemptsanyoverride = null;

    public function __construct($current, $section, $cm, $course) {
        self::$reviewfields = array(
            'attempt'          => array('theattempt', 'ddtaquiz'),
            'correctness'      => array('whethercorrect', 'question'),
            'marks'            => array('marks', 'ddtaquiz'),
            'specificfeedback' => array('specificfeedback', 'question'),
            'generalfeedback'  => array('generalfeedback', 'question'),
            'rightanswer'      => array('rightanswer', 'question'),
            'overallfeedback'  => array('reviewoverallfeedback', 'ddtaquiz'),
        );
        parent::__construct($current, $section, $cm, $course);
    }

    protected function definition() {
        global $COURSE, $CFG, $DB, $PAGE;
        $ddtaquizconfig = get_config('ddtaquiz');
        $mform = $this->_form;

        // -------------------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Name.
        $mform->addElement('text', 'name', get_string('name'), array('size'=>'64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        // Introduction.
        $this->standard_intro_elements(get_string('introduction', 'ddtaquiz'));

        // -------------------------------------------------------------------------------
        $mform->addElement('header', 'timing', get_string('timing', 'ddtaquiz'));

        // Open and close dates.
        $mform->addElement('date_time_selector', 'timeopen', get_string('ddtaquizopen', 'ddtaquiz'),
                self::$datefieldoptions);
        $mform->addHelpButton('timeopen', 'ddtaquizopenclose', 'ddtaquiz');

        $mform->addElement('date_time_selector', 'timeclose', get_string('ddtaquizclose', 'ddtaquiz'),
                self::$datefieldoptions);

        // Time limit.
        $mform->addElement('duration', 'timelimit', get_string('timelimit', 'ddtaquiz'),
                array('optional' => true));
        $mform->addHelpButton('timelimit', 'timelimit', 'ddtaquiz');
        $mform->setAdvanced('timelimit', $ddtaquizconfig->timelimit_adv);
        $mform->setDefault('timelimit', $ddtaquizconfig->timelimit);

        // What to do with overdue attempts.
        $mform->addElement('select', 'overduehandling', get_string('overduehandling', 'ddtaquiz'),
                ddtaquiz_get_overdue_handling_options());
        $mform->addHelpButton('overduehandling', 'overduehandling', 'ddtaquiz');
        $mform->setAdvanced('overduehandling', $ddtaquizconfig->overduehandling_adv);
        $mform->setDefault('overduehandling', $ddtaquizconfig->overduehandling);
        // TODO Formslib does OR logic on disableif, and we need AND logic here.
        // $mform->disabledIf('overduehandling', 'timelimit', 'eq', 0);
        // $mform->disabledIf('overduehandling', 'timeclose', 'eq', 0);

        // Grace period time.
        $mform->addElement('duration', 'graceperiod', get_string('graceperiod', 'ddtaquiz'),
                array('optional' => true));
        $mform->addHelpButton('graceperiod', 'graceperiod', 'ddtaquiz');
        $mform->setAdvanced('graceperiod', $ddtaquizconfig->graceperiod_adv);
        $mform->setDefault('graceperiod', $ddtaquizconfig->graceperiod);
        $mform->disabledIf('graceperiod', 'overduehandling', 'neq', 'graceperiod');

        // -------------------------------------------------------------------------------
        // Grade settings.
        $this->standard_grading_coursemodule_elements();

        $mform->removeElement('grade');
        if (property_exists($this->current, 'grade')) {
            $currentgrade = $this->current->grade;
        } else {
            $currentgrade = $ddtaquizconfig->maximumgrade;
        }
        $mform->addElement('hidden', 'grade', $currentgrade);
        $mform->setType('grade', PARAM_FLOAT);

        // Number of attempts.
        $attemptoptions = array('0' => get_string('unlimited'));
        for ($i = 1; $i <= DDTAQUIZ_MAX_ATTEMPT_OPTION; $i++) {
            $attemptoptions[$i] = $i;
        }
        $mform->addElement('select', 'attempts', get_string('attemptsallowed', 'ddtaquiz'),
                $attemptoptions);
        $mform->setAdvanced('attempts', $ddtaquizconfig->attempts_adv);
        $mform->setDefault('attempts', $ddtaquizconfig->attempts);

        // Grading method.
        $mform->addElement('select', 'grademethod', get_string('grademethod', 'ddtaquiz'),
                ddtaquiz_get_grading_options());
        $mform->addHelpButton('grademethod', 'grademethod', 'ddtaquiz');
        $mform->setAdvanced('grademethod', $ddtaquizconfig->grademethod_adv);
        $mform->setDefault('grademethod', $ddtaquizconfig->grademethod);
        if ($this->get_max_attempts_for_any_override() < 2) {
            $mform->disabledIf('grademethod', 'attempts', 'eq', 1);
        }

        // -------------------------------------------------------------------------------
        $mform->addElement('header', 'layouthdr', get_string('layout', 'ddtaquiz'));

        $pagegroup = array();
        $pagegroup[] = $mform->createElement('select', 'questionsperpage',
                get_string('newpage', 'ddtaquiz'), ddtaquiz_questions_per_page_options(), array('id' => 'id_questionsperpage'));
        $mform->setDefault('questionsperpage', $ddtaquizconfig->questionsperpage);

        if (!empty($this->_cm)) {
            $pagegroup[] = $mform->createElement('checkbox', 'repaginatenow', '',
                    get_string('repaginatenow', 'ddtaquiz'), array('id' => 'id_repaginatenow'));
        }

        $mform->addGroup($pagegroup, 'questionsperpagegrp',
                get_string('newpage', 'ddtaquiz'), null, false);
        $mform->addHelpButton('questionsperpagegrp', 'newpage', 'ddtaquiz');
        $mform->setAdvanced('questionsperpagegrp', $ddtaquizconfig->questionsperpage_adv);

        // Navigation method.
        $mform->addElement('select', 'navmethod', get_string('navmethod', 'ddtaquiz'),
                ddtaquiz_get_navigation_options());
        $mform->addHelpButton('navmethod', 'navmethod', 'ddtaquiz');
        $mform->setAdvanced('navmethod', $ddtaquizconfig->navmethod_adv);
        $mform->setDefault('navmethod', $ddtaquizconfig->navmethod);

        // -------------------------------------------------------------------------------
        $mform->addElement('header', 'interactionhdr', get_string('questionbehaviour', 'ddtaquiz'));

        // Shuffle within questions.
        $mform->addElement('selectyesno', 'shuffleanswers', get_string('shufflewithin', 'ddtaquiz'));
        $mform->addHelpButton('shuffleanswers', 'shufflewithin', 'ddtaquiz');
        $mform->setAdvanced('shuffleanswers', $ddtaquizconfig->shuffleanswers_adv);
        $mform->setDefault('shuffleanswers', $ddtaquizconfig->shuffleanswers);

        // How questions behave (question behaviour).
        if (!empty($this->current->preferredbehaviour)) {
            $currentbehaviour = $this->current->preferredbehaviour;
        } else {
            $currentbehaviour = '';
        }
        $behaviours = question_engine::get_behaviour_options($currentbehaviour);
        $mform->addElement('select', 'preferredbehaviour',
                get_string('howquestionsbehave', 'question'), $behaviours);
        $mform->addHelpButton('preferredbehaviour', 'howquestionsbehave', 'question');
        $mform->setDefault('preferredbehaviour', $ddtaquizconfig->preferredbehaviour);

        // Can redo completed questions.
        $redochoices = array(0 => get_string('no'), 1 => get_string('canredoquestionsyes', 'ddtaquiz'));
        $mform->addElement('select', 'canredoquestions', get_string('canredoquestions', 'ddtaquiz'), $redochoices);
        $mform->addHelpButton('canredoquestions', 'canredoquestions', 'ddtaquiz');
        $mform->setAdvanced('canredoquestions', $ddtaquizconfig->canredoquestions_adv);
        $mform->setDefault('canredoquestions', $ddtaquizconfig->canredoquestions);
        foreach ($behaviours as $behaviour => $notused) {
            if (!question_engine::can_questions_finish_during_the_attempt($behaviour)) {
                $mform->disabledIf('canredoquestions', 'preferredbehaviour', 'eq', $behaviour);
            }
        }

        // Each attempt builds on last.
        $mform->addElement('selectyesno', 'attemptonlast',
                get_string('eachattemptbuildsonthelast', 'ddtaquiz'));
        $mform->addHelpButton('attemptonlast', 'eachattemptbuildsonthelast', 'ddtaquiz');
        $mform->setAdvanced('attemptonlast', $ddtaquizconfig->attemptonlast_adv);
        $mform->setDefault('attemptonlast', $ddtaquizconfig->attemptonlast);
        if ($this->get_max_attempts_for_any_override() < 2) {
            $mform->disabledIf('attemptonlast', 'attempts', 'eq', 1);
        }

        // -------------------------------------------------------------------------------
        $mform->addElement('header', 'reviewoptionshdr',
                get_string('reviewoptionsheading', 'ddtaquiz'));
        $mform->addHelpButton('reviewoptionshdr', 'reviewoptionsheading', 'ddtaquiz');

        // Review options.
        $this->add_review_options_group($mform, $ddtaquizconfig, 'during',
                mod_ddtaquiz_display_options::DURING, true);
        $this->add_review_options_group($mform, $ddtaquizconfig, 'immediately',
                mod_ddtaquiz_display_options::IMMEDIATELY_AFTER);
        $this->add_review_options_group($mform, $ddtaquizconfig, 'open',
                mod_ddtaquiz_display_options::LATER_WHILE_OPEN);
        $this->add_review_options_group($mform, $ddtaquizconfig, 'closed',
                mod_ddtaquiz_display_options::AFTER_CLOSE);

        foreach ($behaviours as $behaviour => $notused) {
            $unusedoptions = question_engine::get_behaviour_unused_display_options($behaviour);
            foreach ($unusedoptions as $unusedoption) {
                $mform->disabledIf($unusedoption . 'during', 'preferredbehaviour',
                        'eq', $behaviour);
            }
        }
        $mform->disabledIf('attemptduring', 'preferredbehaviour',
                'neq', 'wontmatch');
        $mform->disabledIf('overallfeedbackduring', 'preferredbehaviour',
                'neq', 'wontmatch');
        foreach (self::$reviewfields as $field => $notused) {
            $mform->disabledIf($field . 'closed', 'timeclose[enabled]');
        }

        // -------------------------------------------------------------------------------
        $mform->addElement('header', 'display', get_string('appearance'));

        // Show user picture.
        $mform->addElement('select', 'showuserpicture', get_string('showuserpicture', 'ddtaquiz'),
                ddtaquiz_get_user_image_options());
        $mform->addHelpButton('showuserpicture', 'showuserpicture', 'ddtaquiz');
        $mform->setAdvanced('showuserpicture', $ddtaquizconfig->showuserpicture_adv);
        $mform->setDefault('showuserpicture', $ddtaquizconfig->showuserpicture);

        // Overall decimal points.
        $options = array();
        for ($i = 0; $i <= DDTAQUIZ_MAX_DECIMAL_OPTION; $i++) {
            $options[$i] = $i;
        }
        $mform->addElement('select', 'decimalpoints', get_string('decimalplaces', 'ddtaquiz'),
                $options);
        $mform->addHelpButton('decimalpoints', 'decimalplaces', 'ddtaquiz');
        $mform->setAdvanced('decimalpoints', $ddtaquizconfig->decimalpoints_adv);
        $mform->setDefault('decimalpoints', $ddtaquizconfig->decimalpoints);

        // Question decimal points.
        $options = array(-1 => get_string('sameasoverall', 'ddtaquiz'));
        for ($i = 0; $i <= DDTAQUIZ_MAX_Q_DECIMAL_OPTION; $i++) {
            $options[$i] = $i;
        }
        $mform->addElement('select', 'questiondecimalpoints',
                get_string('decimalplacesquestion', 'ddtaquiz'), $options);
        $mform->addHelpButton('questiondecimalpoints', 'decimalplacesquestion', 'ddtaquiz');
        $mform->setAdvanced('questiondecimalpoints', $ddtaquizconfig->questiondecimalpoints_adv);
        $mform->setDefault('questiondecimalpoints', $ddtaquizconfig->questiondecimalpoints);

        // Show blocks during ddtaquiz attempt.
        $mform->addElement('selectyesno', 'showblocks', get_string('showblocks', 'ddtaquiz'));
        $mform->addHelpButton('showblocks', 'showblocks', 'ddtaquiz');
        $mform->setAdvanced('showblocks', $ddtaquizconfig->showblocks_adv);
        $mform->setDefault('showblocks', $ddtaquizconfig->showblocks);

        // -------------------------------------------------------------------------------
        $mform->addElement('header', 'security', get_string('extraattemptrestrictions', 'ddtaquiz'));

        // Require password to begin ddtaquiz attempt.
        $mform->addElement('passwordunmask', 'ddtaquizpassword', get_string('requirepassword', 'ddtaquiz'));
        $mform->setType('ddtaquizpassword', PARAM_TEXT);
        $mform->addHelpButton('ddtaquizpassword', 'requirepassword', 'ddtaquiz');
        $mform->setAdvanced('ddtaquizpassword', $ddtaquizconfig->password_adv);
        $mform->setDefault('ddtaquizpassword', $ddtaquizconfig->password);

        // IP address.
        $mform->addElement('text', 'subnet', get_string('requiresubnet', 'ddtaquiz'));
        $mform->setType('subnet', PARAM_TEXT);
        $mform->addHelpButton('subnet', 'requiresubnet', 'ddtaquiz');
        $mform->setAdvanced('subnet', $ddtaquizconfig->subnet_adv);
        $mform->setDefault('subnet', $ddtaquizconfig->subnet);

        // Enforced time delay between ddtaquiz attempts.
        $mform->addElement('duration', 'delay1', get_string('delay1st2nd', 'ddtaquiz'),
                array('optional' => true));
        $mform->addHelpButton('delay1', 'delay1st2nd', 'ddtaquiz');
        $mform->setAdvanced('delay1', $ddtaquizconfig->delay1_adv);
        $mform->setDefault('delay1', $ddtaquizconfig->delay1);
        if ($this->get_max_attempts_for_any_override() < 2) {
            $mform->disabledIf('delay1', 'attempts', 'eq', 1);
        }

        $mform->addElement('duration', 'delay2', get_string('delaylater', 'ddtaquiz'),
                array('optional' => true));
        $mform->addHelpButton('delay2', 'delaylater', 'ddtaquiz');
        $mform->setAdvanced('delay2', $ddtaquizconfig->delay2_adv);
        $mform->setDefault('delay2', $ddtaquizconfig->delay2);
        if ($this->get_max_attempts_for_any_override() < 3) {
            $mform->disabledIf('delay2', 'attempts', 'eq', 1);
            $mform->disabledIf('delay2', 'attempts', 'eq', 2);
        }

        // Browser security choices.
        $mform->addElement('select', 'browsersecurity', get_string('browsersecurity', 'ddtaquiz'),
                ddtaquiz_access_manager::get_browser_security_choices());
        $mform->addHelpButton('browsersecurity', 'browsersecurity', 'ddtaquiz');
        $mform->setAdvanced('browsersecurity', $ddtaquizconfig->browsersecurity_adv);
        $mform->setDefault('browsersecurity', $ddtaquizconfig->browsersecurity);

        // Any other rule plugins.
        ddtaquiz_access_manager::add_settings_form_fields($this, $mform);

        // -------------------------------------------------------------------------------
        $mform->addElement('header', 'overallfeedbackhdr', get_string('overallfeedback', 'ddtaquiz'));
        $mform->addHelpButton('overallfeedbackhdr', 'overallfeedback', 'ddtaquiz');

        if (isset($this->current->grade)) {
            $needwarning = $this->current->grade === 0;
        } else {
            $needwarning = $ddtaquizconfig->maximumgrade == 0;
        }
        if ($needwarning) {
            $mform->addElement('static', 'nogradewarning', '',
                    get_string('nogradewarning', 'ddtaquiz'));
        }

        $mform->addElement('static', 'gradeboundarystatic1',
                get_string('gradeboundary', 'ddtaquiz'), '100%');

        $repeatarray = array();
        $repeatedoptions = array();
        $repeatarray[] = $mform->createElement('editor', 'feedbacktext',
                get_string('feedback', 'ddtaquiz'), array('rows' => 3), array('maxfiles' => EDITOR_UNLIMITED_FILES,
                        'noclean' => true, 'context' => $this->context));
        $repeatarray[] = $mform->createElement('text', 'feedbackboundaries',
                get_string('gradeboundary', 'ddtaquiz'), array('size' => 10));
        $repeatedoptions['feedbacktext']['type'] = PARAM_RAW;
        $repeatedoptions['feedbackboundaries']['type'] = PARAM_RAW;

        if (!empty($this->_instance)) {
            $this->_feedbacks = $DB->get_records('ddtaquiz_feedback',
                    array('ddtaquizid' => $this->_instance), 'mingrade DESC');
            $numfeedbacks = count($this->_feedbacks);
        } else {
            $this->_feedbacks = array();
            $numfeedbacks = $ddtaquizconfig->initialnumfeedbacks;
        }
        $numfeedbacks = max($numfeedbacks, 1);

        $nextel = $this->repeat_elements($repeatarray, $numfeedbacks - 1,
                $repeatedoptions, 'boundary_repeats', 'boundary_add_fields', 3,
                get_string('addmoreoverallfeedbacks', 'ddtaquiz'), true);

        // Put some extra elements in before the button.
        $mform->insertElementBefore($mform->createElement('editor',
                "feedbacktext[$nextel]", get_string('feedback', 'ddtaquiz'), array('rows' => 3),
                array('maxfiles' => EDITOR_UNLIMITED_FILES, 'noclean' => true,
                      'context' => $this->context)),
                'boundary_add_fields');
        $mform->insertElementBefore($mform->createElement('static',
                'gradeboundarystatic2', get_string('gradeboundary', 'ddtaquiz'), '0%'),
                'boundary_add_fields');

        // Add the disabledif rules. We cannot do this using the $repeatoptions parameter to
        // repeat_elements because we don't want to dissable the first feedbacktext.
        for ($i = 0; $i < $nextel; $i++) {
            $mform->disabledIf('feedbackboundaries[' . $i . ']', 'grade', 'eq', 0);
            $mform->disabledIf('feedbacktext[' . ($i + 1) . ']', 'grade', 'eq', 0);
        }

        // -------------------------------------------------------------------------------
        $this->standard_coursemodule_elements();

        // Check and act on whether setting outcomes is considered an advanced setting.
        $mform->setAdvanced('modoutcomes', !empty($ddtaquizconfig->outcomes_adv));

        // The standard_coursemodule_elements method sets this to 100, but the
        // ddtaquiz has its own setting, so use that.
        $mform->setDefault('grade', $ddtaquizconfig->maximumgrade);

        // -------------------------------------------------------------------------------
        $this->add_action_buttons();

        $PAGE->requires->yui_module('moodle-mod_ddtaquiz-modform', 'M.mod_ddtaquiz.modform.init');
    }

    protected function add_review_options_group($mform, $ddtaquizconfig, $whenname,
            $when, $withhelp = false) {
        global $OUTPUT;

        $group = array();
        foreach (self::$reviewfields as $field => $string) {
            list($identifier, $component) = $string;

            $label = get_string($identifier, $component);
            if ($withhelp) {
                $label .= ' ' . $OUTPUT->help_icon($identifier, $component);
            }

            $group[] = $mform->createElement('checkbox', $field . $whenname, '', $label);
        }
        $mform->addGroup($group, $whenname . 'optionsgrp',
                get_string('review' . $whenname, 'ddtaquiz'), null, false);

        foreach (self::$reviewfields as $field => $notused) {
            $cfgfield = 'review' . $field;
            if ($ddtaquizconfig->$cfgfield & $when) {
                $mform->setDefault($field . $whenname, 1);
            } else {
                $mform->setDefault($field . $whenname, 0);
            }
        }

        if ($whenname != 'during') {
            $mform->disabledIf('correctness' . $whenname, 'attempt' . $whenname);
            $mform->disabledIf('specificfeedback' . $whenname, 'attempt' . $whenname);
            $mform->disabledIf('generalfeedback' . $whenname, 'attempt' . $whenname);
            $mform->disabledIf('rightanswer' . $whenname, 'attempt' . $whenname);
        }
    }

    protected function preprocessing_review_settings(&$toform, $whenname, $when) {
        foreach (self::$reviewfields as $field => $notused) {
            $fieldname = 'review' . $field;
            if (array_key_exists($fieldname, $toform)) {
                $toform[$field . $whenname] = $toform[$fieldname] & $when;
            }
        }
    }

    public function data_preprocessing(&$toform) {
        if (isset($toform['grade'])) {
            // Convert to a real number, so we don't get 0.0000.
            $toform['grade'] = $toform['grade'] + 0;
        }

        if (count($this->_feedbacks)) {
            $key = 0;
            foreach ($this->_feedbacks as $feedback) {
                $draftid = file_get_submitted_draft_itemid('feedbacktext['.$key.']');
                $toform['feedbacktext['.$key.']']['text'] = file_prepare_draft_area(
                    $draftid,               // Draftid.
                    $this->context->id,     // Context.
                    'mod_ddtaquiz',             // Component.
                    'feedback',             // Filarea.
                    !empty($feedback->id) ? (int) $feedback->id : null, // Itemid.
                    null,
                    $feedback->feedbacktext // Text.
                );
                $toform['feedbacktext['.$key.']']['format'] = $feedback->feedbacktextformat;
                $toform['feedbacktext['.$key.']']['itemid'] = $draftid;

                if ($toform['grade'] == 0) {
                    // When a ddtaquiz is un-graded, there can only be one lot of
                    // feedback. If the ddtaquiz previously had a maximum grade and
                    // several lots of feedback, we must now avoid putting text
                    // into input boxes that are disabled, but which the
                    // validation will insist are blank.
                    break;
                }

                if ($feedback->mingrade > 0) {
                    $toform['feedbackboundaries['.$key.']'] =
                            round(100.0 * $feedback->mingrade / $toform['grade'], 6) . '%';
                }
                $key++;
            }
        }

        if (isset($toform['timelimit'])) {
            $toform['timelimitenable'] = $toform['timelimit'] > 0;
        }

        $this->preprocessing_review_settings($toform, 'during',
                mod_ddtaquiz_display_options::DURING);
        $this->preprocessing_review_settings($toform, 'immediately',
                mod_ddtaquiz_display_options::IMMEDIATELY_AFTER);
        $this->preprocessing_review_settings($toform, 'open',
                mod_ddtaquiz_display_options::LATER_WHILE_OPEN);
        $this->preprocessing_review_settings($toform, 'closed',
                mod_ddtaquiz_display_options::AFTER_CLOSE);
        $toform['attemptduring'] = true;
        $toform['overallfeedbackduring'] = false;

        // Password field - different in form to stop browsers that remember
        // passwords from getting confused.
        if (isset($toform['password'])) {
            $toform['ddtaquizpassword'] = $toform['password'];
            unset($toform['password']);
        }

        // Load any settings belonging to the access rules.
        if (!empty($toform['instance'])) {
            $accesssettings = ddtaquiz_access_manager::load_settings($toform['instance']);
            foreach ($accesssettings as $name => $value) {
                $toform[$name] = $value;
            }
        }

        // Completion settings check.
        if (empty($toform['completionusegrade'])) {
            $toform['completionpass'] = 0; // Forced unchecked.
        }
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Check open and close times are consistent.
        if ($data['timeopen'] != 0 && $data['timeclose'] != 0 &&
                $data['timeclose'] < $data['timeopen']) {
            $errors['timeclose'] = get_string('closebeforeopen', 'ddtaquiz');
        }

        // Check that the grace period is not too short.
        if ($data['overduehandling'] == 'graceperiod') {
            $graceperiodmin = get_config('ddtaquiz', 'graceperiodmin');
            if ($data['graceperiod'] <= $graceperiodmin) {
                $errors['graceperiod'] = get_string('graceperiodtoosmall', 'ddtaquiz', format_time($graceperiodmin));
            }
        }

        if (array_key_exists('completion', $data) && $data['completion'] == COMPLETION_TRACKING_AUTOMATIC) {
            $completionpass = isset($data['completionpass']) ? $data['completionpass'] : $this->current->completionpass;

            // Show an error if require passing grade was selected and the grade to pass was set to 0.
            if ($completionpass && (empty($data['gradepass']) || grade_floatval($data['gradepass']) == 0)) {
                if (isset($data['completionpass'])) {
                    $errors['completionpassgroup'] = get_string('gradetopassnotset', 'ddtaquiz');
                } else {
                    $errors['gradepass'] = get_string('gradetopassmustbeset', 'ddtaquiz');
                }
            }
        }

        // Check the boundary value is a number or a percentage, and in range.
        $i = 0;
        while (!empty($data['feedbackboundaries'][$i] )) {
            $boundary = trim($data['feedbackboundaries'][$i]);
            if (strlen($boundary) > 0) {
                if ($boundary[strlen($boundary) - 1] == '%') {
                    $boundary = trim(substr($boundary, 0, -1));
                    if (is_numeric($boundary)) {
                        $boundary = $boundary * $data['grade'] / 100.0;
                    } else {
                        $errors["feedbackboundaries[$i]"] =
                                get_string('feedbackerrorboundaryformat', 'ddtaquiz', $i + 1);
                    }
                } else if (!is_numeric($boundary)) {
                    $errors["feedbackboundaries[$i]"] =
                            get_string('feedbackerrorboundaryformat', 'ddtaquiz', $i + 1);
                }
            }
            if (is_numeric($boundary) && $boundary <= 0 || $boundary >= $data['grade'] ) {
                $errors["feedbackboundaries[$i]"] =
                        get_string('feedbackerrorboundaryoutofrange', 'ddtaquiz', $i + 1);
            }
            if (is_numeric($boundary) && $i > 0 &&
                    $boundary >= $data['feedbackboundaries'][$i - 1]) {
                $errors["feedbackboundaries[$i]"] =
                        get_string('feedbackerrororder', 'ddtaquiz', $i + 1);
            }
            $data['feedbackboundaries'][$i] = $boundary;
            $i += 1;
        }
        $numboundaries = $i;

        // Check there is nothing in the remaining unused fields.
        if (!empty($data['feedbackboundaries'])) {
            for ($i = $numboundaries; $i < count($data['feedbackboundaries']); $i += 1) {
                if (!empty($data['feedbackboundaries'][$i] ) &&
                        trim($data['feedbackboundaries'][$i] ) != '') {
                    $errors["feedbackboundaries[$i]"] =
                            get_string('feedbackerrorjunkinboundary', 'ddtaquiz', $i + 1);
                }
            }
        }
        for ($i = $numboundaries + 1; $i < count($data['feedbacktext']); $i += 1) {
            if (!empty($data['feedbacktext'][$i]['text']) &&
                    trim($data['feedbacktext'][$i]['text'] ) != '') {
                $errors["feedbacktext[$i]"] =
                        get_string('feedbackerrorjunkinfeedback', 'ddtaquiz', $i + 1);
            }
        }

        // If CBM is involved, don't show the warning for grade to pass being larger than the maximum grade.
        if (($data['preferredbehaviour'] == 'deferredcbm') OR ($data['preferredbehaviour'] == 'immediatecbm')) {
            unset($errors['gradepass']);
        }
        // Any other rule plugins.
        $errors = ddtaquiz_access_manager::validate_settings_form_fields($errors, $data, $files, $this);

        return $errors;
    }

    /**
     * Display module-specific activity completion rules.
     * Part of the API defined by moodleform_mod
     * @return array Array of string IDs of added items, empty array if none
     */
    public function add_completion_rules() {
        $mform = $this->_form;
        $items = array();

        $group = array();
        $group[] = $mform->createElement('advcheckbox', 'completionpass', null, get_string('completionpass', 'ddtaquiz'),
                array('group' => 'cpass'));
        $mform->disabledIf('completionpass', 'completionusegrade', 'notchecked');
        $group[] = $mform->createElement('advcheckbox', 'completionattemptsexhausted', null,
                get_string('completionattemptsexhausted', 'ddtaquiz'),
                array('group' => 'cattempts'));
        $mform->disabledIf('completionattemptsexhausted', 'completionpass', 'notchecked');
        $mform->addGroup($group, 'completionpassgroup', get_string('completionpass', 'ddtaquiz'), ' &nbsp; ', false);
        $mform->addHelpButton('completionpassgroup', 'completionpass', 'ddtaquiz');
        $items[] = 'completionpassgroup';
        return $items;
    }

    /**
     * Called during validation. Indicates whether a module-specific completion rule is selected.
     *
     * @param array $data Input data (not yet validated)
     * @return bool True if one or more rules is enabled, false if none are.
     */
    public function completion_rule_enabled($data) {
        return !empty($data['completionattemptsexhausted']) || !empty($data['completionpass']);
    }

    /**
     * Get the maximum number of attempts that anyone might have due to a user
     * or group override. Used to decide whether disabledIf rules should be applied.
     * @return int the number of attempts allowed. For the purpose of this method,
     * unlimited is returned as 1000, not 0.
     */
    public function get_max_attempts_for_any_override() {
        global $DB;

        if (empty($this->_instance)) {
            // Ddtaquiz not created yet, so no overrides.
            return 1;
        }

        if ($this->maxattemptsanyoverride === null) {
            $this->maxattemptsanyoverride = $DB->get_field_sql("
                    SELECT MAX(CASE WHEN attempts = 0 THEN 1000 ELSE attempts END)
                      FROM {ddtaquiz_overrides}
                     WHERE ddtaquiz = ?",
                    array($this->_instance));
            if ($this->maxattemptsanyoverride < 1) {
                // This happens when no override alters the number of attempts.
                $this->maxattemptsanyoverride = 1;
            }
        }

        return $this->maxattemptsanyoverride;
    }
}
