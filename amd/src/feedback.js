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
    var index = 0;
    var checkButtons = function () {
        var container = $('.editorQuestionsContainer');
        $(container).html('');
        var letters = $('.card-body  .usesquestionletter');

        for (var i = 0; i < letters.length; i++) {
            var letter = letters[i];
            var value = '[[' + $(letter).text() + ']]';
            var selector = $(letter).parent().find('.usesquestionselector');
            var questionName = $(selector).find('option[value="'+$(selector).val()+'"]').text();
            console.log(questionName);
            $(container).append(
                '<div class="d-inline mr-2 mb-2 p-2 bg-secondary questionButtons" data-value="' + value + '">'+value+' => ' + questionName + '</div>'
            );
        }

        $('select').change(function (){
            checkButtons();
        });
    };
	var update_chars = function() {
		var letter = 'A'.charCodeAt(0);
		var children = $('.usedquestions').children();
		for (var i = 0; i < children.length; i++) {
			$(children[i]).find('.usesquestionletter').html(String.fromCharCode(letter));
            $(children[i]).find('.question-letter').attr('value',letter);
			letter++;
		}
		checkButtons();
	};

    
    return {

        init: function () {
            $('.addusedquestion').click(function (e) {
                e.preventDefault();
                var newusespart = $('.usesquestioncontainer').find('.usesquestion').clone(true);
                $('.usedquestions').append(newusespart);
                // Upcounting letters
                //var lastletter = $('.usesquestioncontainer').find('.usesquestionletter').html();
                //$('.usesquestioncontainer').find('.usesquestionletter').html(String.fromCharCode(lastletter.charCodeAt(0) + 1));
                newusespart.find('.usesquestionselector').attr('name', 'usesquestions[newparts' + index + '][questionId]');
                newusespart.find('.shift-checkbox').attr('name', 'usesquestions[newparts' + index + '][shift]');
                newusespart.find('.question-letter').attr('name', 'usesquestions[newparts' + index + '][letter]');

                update_chars();
                // Increase submit index
                index++;
            });
            $('.usesdelete').click(function (e) {
                e.preventDefault();
                e.stopImmediatePropagation();
                $(this).parents('.usesquestion').remove();
                update_chars();
            });
        },
        'editorQuestions': function () {
            $(document).ready(function(){
                checkButtons();
            });
        }
    };
});