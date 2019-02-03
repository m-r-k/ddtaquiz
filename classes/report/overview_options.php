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
 * Class to store the options for an overview report.
 *
 * @package    mod_ddtaquiz
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_ddtaquiz\report;

defined('MOODLE_INTERNAL') || die();


/**
 * Class to store the options for an overview report.
 *
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class overview_options extends attempts_options {

    /** @var bool whether to show only attempt that need regrading. */
    public $onlyregraded = false;

    /** @var bool whether to show marks for each question (slot). */
    public $slotmarks = true;

    /**
     * @inheritdoc
     */
    protected function get_url_params() {
        $params = parent::get_url_params();
        $params['mode'] = 'overview';
        $params['onlyregraded'] = $this->onlyregraded;
        $params['slotmarks']    = $this->slotmarks;
        return $params;
    }

    /**
     * @inheritdoc
     */
    public function get_initial_form_data() {
        $toform = parent::get_initial_form_data();
        $toform->onlyregraded = $this->onlyregraded;
        $toform->slotmarks    = $this->slotmarks;

        return $toform;
    }

    /**
     * @inheritdoc
     */
    public function setup_from_form_data($fromform) {
        parent::setup_from_form_data($fromform);

        $this->onlyregraded = !empty($fromform->onlyregraded);
        $this->slotmarks    = $fromform->slotmarks;
    }

    /**
     * @inheritdoc
     */
    public function setup_from_params() {
        parent::setup_from_params();

        $this->onlyregraded = optional_param('onlyregraded', $this->onlyregraded, PARAM_BOOL);
        $this->slotmarks    = optional_param('slotmarks', $this->slotmarks, PARAM_BOOL);
    }

    /**
     * @inheritdoc
     */
    public function setup_from_user_preferences() {
        parent::setup_from_user_preferences();

        $this->slotmarks = get_user_preferences('ddtaquiz_overview_slotmarks', $this->slotmarks);
    }

    /**
     * @inheritdoc
     */
    public function update_user_preferences() {
        parent::update_user_preferences();

        set_user_preference('ddtaquiz_overview_slotmarks', $this->slotmarks);
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