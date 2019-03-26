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

define(['jquery', 'core/ajax'], function ($, ajax) {
    $(document.body).addClass('jschooser');
    var questionbank_chooser = {
        // The panel widget
        panel: null,
        addButton: null,
        loading: null,


        prepare_chooser: function (panelId, addButtonId, loadingId) {
            this.panel = $('#' + panelId);
            this.addButton = $('#' + addButtonId);
            this.loading = $('#' + loadingId);


            $(this.panel).find('.modal-body').html(this.loading);
        },

        init_question_bank_modal: function (panelId, addButtonId, loadingId) {
            this.prepare_chooser(panelId, addButtonId, loadingId);
            this.load();
        },

        get_href_param: function (href, paramname) {
            var paramstr = href.split('?')[1];
            var paramsarr = paramstr.split('&');
            for (var i = 0; i < paramsarr.length; i++) {
                var attr = paramsarr[i].split('=');
                if (attr[0] == paramname) {
                    return attr[1];
                }
            }
            return null;
        },

        questionbank_loaded: function (response) {
            $(this.panel).find('.modal-body').html(response);

            $(this.panel).find('.modulespecificbuttonscontainer').html('');
            $(this.panel).find('.tag-condition-container').html('');

            $('.addfromquestionbank').click(function (e) {
                e.preventDefault();
                var input = $('<input>')
                    .attr('type', 'hidden')
                    .attr('name', 'addfromquestionbank').val($(this).data('id'));
                $('#blockeditingform').append($(input));
                $('#blockeditingform').submit();
            });
            $(this.addButton).click(function (e) {
                e.preventDefault();
                var selected = $('input:checkbox:checked[name^="q"]');
                for (var i = 0; i < selected.length; i++) {
                    var name = $(selected[i]).attr('name');
                    var input = $('<input>')
                        .attr('type', 'hidden')
                        .attr('name', name).val('1');
                    $('#blockeditingform').append($(input));
                }
                var button = $('<input>')
                    .attr('type', 'hidden')
                    .attr('name', 'add').val('1');
                $('#blockeditingform').append($(button));
                $('#blockeditingform').submit();
            });
            $('.questionname').click(this.link_clicked);
            $('.questionbankcontent').find('a').click($.proxy(this.link_clicked, this));
            $('#id_selectacategory').change($.proxy(this.category_changed, this));


        },

        category_changed: function (e) {
            e.preventDefault();
            this.load();
        },

        link_clicked: function (e) {
            e.preventDefault();
            var page = this.get_href_param(e.target.href, 'qpage');
            var perpage = this.get_href_param(e.target.href, 'qperpage');
            var qbs1 = this.get_href_param(e.target.href, 'qbs1');
            if (page !== null || perpage !== null || qbs1 !== null) {
                this.load(page, perpage, qbs1);
            }
        },

        load: function (page, perpage, qbs1) {
            var args = {};
            args.cmid = $('.questionbank').data('cmid');
            args.bid = $('.questionbank').data('bid');
            if (page) {
                args.page = page;
            }
            if (perpage) {
                args.qperpage = perpage;
            }
            if (qbs1) {
                args.qbs1 = qbs1;
            }
            args.category = $('#id_selectacategory').val();
            //this.panel.bodyNode.setHTML($('div.questionbankloading').parent().html());
            var promises = ajax.call([{
                methodname: 'mod_ddtaquiz_get_questionbank',
                args: args
            }]);
            promises[0].done($.proxy(this.questionbank_loaded, this)).fail($.proxy(this.questionbank_load_failed, this));
        },

        questionbank_load_failed: function () {
            $(this.panel).find('.modal-body').html('Error fetching from question bank');
        }
    };
    return {
        init: function (panelId, addButtonId, loadingId) {
            questionbank_chooser.init_question_bank_modal(panelId, addButtonId, loadingId);
        }
    };
});