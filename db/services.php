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
 * Quiz external functions and service definitions.
 *
 * @package    mod_ddtaquiz
 * @category   external
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.1
 */

defined('MOODLE_INTERNAL') || die;

$functions = array(
    'mod_ddtaquiz_get_questionbank' => array(
        'classname' => 'mod_ddtaquiz_external',
        'methodname' => 'get_questionbank',
        'classpath' => 'mod/ddtaquiz/externallib.php',
        'description' => 'Returns the HTML for the questionbank.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'mod/ddtaquiz:manage'
    )
);

$services = array(
  'DDTA quiz service' => array(
      'functions' => array('mod_ddtaquiz_get_questionbank'),
      'restrictedusers' => 0,
      'enabled' => 1
  )
);