/* eslint-disable no-mixed-spaces-and-tabs */
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
 * Javascript for the question type chooser, when adding a new question to a block.
 *
 * @package    mod_ddtaquiz
 * @copyright  2017 Luca Gladiator <lucamarius.gladiator@stud.tu-darmstadt.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/modal_factory'], function($, ModalFactory) {
	$(document.body).addClass('jschooser');

    return {
        init: function() {
            var trigger = $('.addquestion');
            trigger.click(function(e) {
            	e.preventDefault();
            });

            ModalFactory.create({
                title: $('div.createnewquestion div.choosertitle').html(),
                body: $('div.createnewquestion div.chooserdialoguebody').html(),
                footer: 'test footer content'
            }, trigger)
                .done(function(modal) {
                    modal.getRoot().addClass('ddtaquiz-questionchooserdialog');
                });
        }
    };
});