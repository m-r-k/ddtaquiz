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
    var index = 0;
	var update_chars = function() {
		var letter = 'A'.charCodeAt(0);
		var children = $('.usedquestions').children();
		for (var i = 0; i < children.length; i++) {
			$(children[i]).find('.usesquestionletter').html(String.fromCharCode(letter));
			letter++;
		}
	};
    
    return {
    
        init: function() {
            $('.addusedquestion').click(function (e) {
                e.preventDefault();
                var newusespart = $('.usesquestioncontainer').find('.usesquestion').clone(true);
                $('.usedquestions').append(newusespart);
                // Upcounting letters
                //var lastletter = $('.usesquestioncontainer').find('.usesquestionletter').html();
                //$('.usesquestioncontainer').find('.usesquestionletter').html(String.fromCharCode(lastletter.charCodeAt(0) + 1));
                update_chars();
                // Increase submit index
                index++;
                newusespart.find('.usesquestionselector').attr('name', 'usesquestions[newparts' + index + ']');
            });
            $('.usesdelete').click(function(e) {
                e.preventDefault();
                e.stopImmediatePropagation();
                $(this).parents('.usesquestion').remove();
                update_chars();
            });
        }
    };
});