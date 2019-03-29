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
 * Javascript for the edit feedback page. Enables a user to add questions whose feedback is to be replaced.
 *
 * @package    mod_ddtaquiz
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery'], function ($) {
    var condition = {
        singleConditionIndex: 0,
        mqConditionIndex: 0,
        addcondition: function (e) {
            e.preventDefault();
            e.stopImmediatePropagation();
            var newcondition = $('.pointsconditioncontainer').find('.conditionpart').clone(true);
            this.singleConditionIndex++;
            newcondition.find('.conditionpoints').attr('name', 'conditionparts[newparts' + this.singleConditionIndex + '][points]');
            newcondition.find('.conditiontype').attr('name', 'conditionparts[newparts' + this.singleConditionIndex + '][type]');
            newcondition.find('.conditionquestion').attr(
                'name', 'conditionparts[newparts' +
                this.singleConditionIndex + '][question]'
            );
            newcondition.appendTo('.conditionpartslist');
        },
        addMQCondition: function (e) {
            e.preventDefault();
            e.stopImmediatePropagation();
            var newcondition = $('.mq-pointsconditioncontainer').find('.mq-conditionpart').clone(true);
            var index = ++this.mqConditionIndex;
            var headingId = 'mq-heading-newpart' + index + '-' + $.now();
            var collapseId = 'mq-collapse-newpart' + index + '-' + $.now();
            newcondition.find('.card-header').first().attr('id', headingId);
            newcondition.find('.collapse').first().attr({
                'id': collapseId,
                'aria-labelledby': headingId,
            });
            newcondition.find('span[data-toggle="collapse"]').first().attr({
                'data-target': "#" + collapseId,
                'aria-controls': collapseId
            });
            newcondition.find('.conditionpoints').attr('name', 'conditionMQParts[newparts' + index + '][points]');
            newcondition.find('.conditiontype').attr('name', 'conditionMQParts[newparts' + index + '][type]');
            newcondition.find('.custom-control-input').each(function () {
                var id = 'mq-checkbox-' + $(this).val() + '-' + $.now();
                newcondition.find("label[for='" + $(this).attr('id') + "']").attr('for', id);
                $(this).attr('name', 'conditionMQParts[newparts' + index + '][questions][' + $(this).val() + ']');
                $(this).attr('id', id);
            });

            newcondition.appendTo('.conditionpartslist');
        }
    };
    return {


        init: function () {
            $(document).ready(function () {
                var tmp=$('[data-toggle="tooltip"]');
                if(tmp.tooltip) {
                    tmp.tooltip();
                }
                $('#addPointsConditionBtn').click(function (e) {
                    condition.addcondition(e);
                });
                $('#addMQPointsConditionBtn').click(function (e) {
                    condition.addMQCondition(e);
                });

                $('.conditionpartdelete').click(function (e) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    $(this).parents('.conditionpart').remove();
                    $(this).parents('.mq-conditionpart').remove();
                });

            });

        },
        cleanAlerts: function () {
            $('.ddtaquiz-alerts:not(:first)').remove();
        }
    };
});