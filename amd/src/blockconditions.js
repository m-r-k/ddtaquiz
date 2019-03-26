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
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery'], function ($) {
    var conditiontypechooser = {
        //the panel widget
        panel: null,

        // The chooserdialogue container
        container: null,

        // the index of the current conditionpart
        index: 0,

        addcondition: function (e) {
            e.preventDefault();
            e.stopImmediatePropagation();
            this.panel.hide();
            var newcondition = $('.pointsconditioncontainer').find('.conditionpart').clone(true);
            this.index++;
            newcondition.find('.conditionpoints').attr('name', 'conditionparts[newparts' + this.index + '][points]');
            newcondition.find('.conditiontype').attr('name', 'conditionparts[newparts' + this.index + '][type]');
            newcondition.find('.conditionquestion').attr('name', 'conditionparts[newparts' + this.index + '][question]');
            newcondition.appendTo('.conditionpartslist');
        },

        prepare_chooser: function () {
            if (this.panel) {
                return;
            }

            var params = {
                bodyContent: $('div.addcondition div.chooserdialogue').html(),
                headerContent: $('div.addcondition div.chooserheader').html(),
                width: '540px',
                draggable: true,
                visible: false,
                zindex: 100,
                modal: true,
                shim: true,
                closeButtonTitle: 'Close',
                focusOnPreviousTargetAfterHide: true,
                render: false
            };

            this.panel = new M.core.dialogue(params);

            this.panel.hide();
            this.panel.render();

            this.container = this.panel.get('boundingBox').one('.choosercontainer');

            this.panel.get('boundingBox').addClass('chooserdialogue');
        },

        display_chooserdialogue: function (e) {
            this.prepare_chooser();
            e.preventDefault();
            this.container.one('form').on('submit', function (e) {
                this.addcondition(e);
            }, this);
            this.panel.show(e);
            this.panel.centerDialogue();
        }
    };
    return {
        init: function () {
            //make the add condition button open a dialogue
            $('.addblockcondition').click(function (e) {
                conditiontypechooser.display_chooserdialogue(e);
            });
            //make the delete buttons on conditionparts work
            $('.conditionpartdelete').click(function (e) {
                e.preventDefault();
                e.stopImmediatePropagation();
                $(this).parents('.conditionpart').remove();
            });
        }
    };
});