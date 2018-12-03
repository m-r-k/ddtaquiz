define(['jquery'], function($) {

    return {
        init: function(){
            $(document).ready(function(){
                var title = $('#qtypeChoiceBody .hd.choosertitle').html();
                $('#qtypeChoiceModal .modal-header .modal-title').html(title);
                $('#qtypeChoiceBody .hd.choosertitle').html('');

                $('#addQuestionBtn').click(function(){
                    $('#qtypeChoiceBody #chooserform').submit();
                });
                $('#qtypeChoiceBody .submitbuttons').html('');

            });
        }
    };
});