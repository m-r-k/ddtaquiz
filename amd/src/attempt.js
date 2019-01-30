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

function timingFromSeconds(textBefore, timestamp){

    var minutes = parseInt(timestamp/60);
    var hours = parseInt(minutes/60);
    var seconds = parseInt(timestamp - (minutes * 60));
    minutes = parseInt(minutes - (hours * 60));

    // add a 0 infront if smaller than 10
    if(minutes < 10)
        minutes = '0' + minutes;
    if(seconds < 10)
        seconds = '0' + seconds;
    if(hours < 10)
        hours = '0' + hours;

    return textBefore + ' <b>' +hours+':'+minutes + ':' + seconds +'</b>';
}

define(['jquery'], function ($) {
    return {
        init: function () {
            $(document).ready(function () {
                $('#attemptNextBtn').click(function (e) {
                    $('#responseform').submit();
                });
                $('#directFeedbackBtn').click(function (e) {
                    var feedBack=$('#directFeedbackID');
                    feedBack.css("display", feedBack.css("display") === 'none' ? 'block' : 'none');
                });

            });

        },
        'startTime': function(abandon, timestamp,graceperiod,url){
            // set interval for timing
            var interval = setInterval(function(){
                timestamp -= 1; // minus one second
                $('.timeDiv').html(timingFromSeconds('Time left:' ,timestamp));

            }, 1000);// repeat every second

            if(graceperiod> 0){
                setTimeout(function(){
                    //TODO what to display when time over
                    clearInterval(interval);
                    setInterval(function(){
                        graceperiod -= 1;

                        $('.timeDiv').html(timingFromSeconds('Auto submit in :' , graceperiod));

                    }, 1000);
                    setTimeout(function(){ $('#attemptNextBtn').click(); }, graceperiod * 1000);
                }, timestamp  * 1000);
            }else{
                if(abandon){
                    setTimeout(function(){ window.location = url; }, timestamp * 1000);
                }else{
                    setTimeout(function(){ $('#attemptNextBtn').click(); }, timestamp * 1000);
                }
            }
        }
    };
});

