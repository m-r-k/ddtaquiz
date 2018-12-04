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
 * @copyright  2017 Luca Gladiator <lucamarius.gladiator@stud.tu-darmstadt.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery'], function($) {
    var condition = {
        index : 0,
        addcondition : function(e) {
            e.preventDefault();
            e.stopImmediatePropagation();
            var newcondition = $('.pointsconditioncontainer').find('.conditionpart').clone(true);
            this.index++;
            newcondition.find('.conditionpoints').attr('name', 'conditionparts[newparts' + this.index + '][points]');
            newcondition.find('.conditiontype').attr('name', 'conditionparts[newparts' + this.index + '][type]');
            newcondition.find('.conditionquestion').attr('name', 'conditionparts[newparts' + this.index + '][question]');
            newcondition.appendTo('.conditionpartslist');
        }
    };
    return {

    
        init: function() {
            $(document).ready(function(){
                $('#addPointsConditionBtn').click(function(e){
                    condition.addcondition(e);
                });

                $('.conditionpartdelete').click(function(e) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    $(this).parents('.conditionpart').remove();
                });

            });

        },
        cleanAlerts: function () {
            $('.ddtaquiz-alerts:not(:first)').remove();
        }
    };
});