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
 * Class to store the options for an combined report.
 *
 * @package    mod_ddtaquiz
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_ddtaquiz\report;

defined('MOODLE_INTERNAL') || die();


/**
 * Class to store the options for an combined report.
 *
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class combined_options extends attempts_options
{

    /** @var bool whether to show only attempt that need regrading. */
    public $onlyregraded = false;

    /** @var bool whether to show marks for each question (slot). */
    public $slotmarks = true;

    /** @var bool whether to show marks for each question (slot). */

    public $displayCorrectAnswers = true;
    /** @var bool whether to show marks for each question (slot). */
    public $displayResponses = true;

    /** @var bool whether to show marks for each question (slot). */
    public $displayAchievedPoints = true;

    /** @var bool whether to show marks for each question (slot). */
    public $displayQuestionName = true;

    /**
     * @inheritdoc
     */
    protected function get_url_params() {
        $params = parent::get_url_params();
        $params['mode'] = 'combined';
        $params['onlyregraded'] = $this->onlyregraded;
        $params['slotmarks'] = $this->slotmarks;
        $params['displayCorrectAnswers'] = $this->displayCorrectAnswers;
        $params['displayResponses'] = $this->displayResponses;
        $params['displayAchievedPoints'] = $this->displayAchievedPoints;
        $params['displayQuestionName'] = $this->displayQuestionName;
        return $params;
    }

    /**
     * @inheritdoc
     */
    public function get_initial_form_data() {
        $toform = parent::get_initial_form_data();
        $toform->onlyregraded = $this->onlyregraded;
        $toform->slotmarks = $this->slotmarks;
        $toform->displayCorrectAnswers = $this->displayCorrectAnswers;
        $toform->displayResponses = $this->displayResponses;
        $toform->displayAchievedPoints = $this->displayAchievedPoints;
        $toform->displayQuestionName = $this->displayQuestionName;

        return $toform;
    }

    /**
     * @inheritdoc
     */
    public function setup_from_form_data($fromform) {
        parent::setup_from_form_data($fromform);

        $this->onlyregraded = !empty($fromform->onlyregraded);
        $this->slotmarks = $fromform->slotmarks;
        $this->displayCorrectAnswers = $fromform->displayCorrectAnswers;
        $this->displayResponses = $fromform->displayResponses;
        $this->displayAchievedPoints = $fromform->displayAchievedPoints;
        $this->displayQuestionName = $fromform->displayQuestionName;
    }

    /**
     * @inheritdoc
     */
    public function setup_from_params() {
        parent::setup_from_params();

        $this->onlyregraded = optional_param('onlyregraded', $this->onlyregraded, PARAM_BOOL);
        $this->slotmarks = optional_param('slotmarks', $this->slotmarks, PARAM_BOOL);
        $this->displayCorrectAnswers = optional_param('displayCorrectAnswers', $this->displayCorrectAnswers, PARAM_BOOL);
        $this->displayResponses = optional_param('displayResponses', $this->displayResponses, PARAM_BOOL);
        $this->displayAchievedPoints = optional_param('displayAchievedPoints', $this->displayAchievedPoints, PARAM_BOOL);
        $this->displayQuestionName = optional_param('displayQuestionName', $this->displayQuestionName, PARAM_BOOL);
    }

    /**
     * @inheritdoc
     */
    public function setup_from_user_preferences() {
        parent::setup_from_user_preferences();

        $this->slotmarks = get_user_preferences('ddtaquiz_combined_slotmarks', $this->slotmarks);
        $this->displayCorrectAnswers = get_user_preferences('ddtaquiz_combined_displayCorrectAnswers', $this->displayCorrectAnswers);
        $this->displayResponses = get_user_preferences('ddtaquiz_combined_displayResponses', $this->displayResponses);
        $this->displayAchievedPoints = get_user_preferences('ddtaquiz_combined_displayAchievedPoints', $this->displayAchievedPoints);
        $this->displayQuestionName = get_user_preferences('ddtaquiz_combined_displayQuestionName', $this->displayQuestionName);
    }

    /**
     * @inheritdoc
     */
    public function update_user_preferences() {
        parent::update_user_preferences();

        set_user_preference('ddtaquiz_combined_slotmarks', $this->slotmarks);
        get_user_preferences('ddtaquiz_combined_displayCorrectAnswers', $this->displayCorrectAnswers);
        get_user_preferences('ddtaquiz_combined_displayResponses', $this->displayResponses);
        get_user_preferences('ddtaquiz_combined_displayAchievedPoints', $this->displayAchievedPoints);
        get_user_preferences('ddtaquiz_combined_displayQuestionName', $this->displayQuestionName);
    }

    /**
     * @inheritdoc
     */
    public function resolve_dependencies() {
        parent::resolve_dependencies();

        if ($this->attempts == attempts::ENROLLED_WITHOUT) {
            $this->onlyregraded = false;
        }
    }
}