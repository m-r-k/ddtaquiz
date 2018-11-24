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

define(['jquery'], function($) {
	$(document.body).addClass('jschooser');
	var question_chooser = {
		 // The panel widget
	    panel: null,

	    // The submit button - we disable this until an element is set
	    submitbutton : null,

	    // The chooserdialogue container
	    container : null,

	    // Any event listeners we may need to cancel later
	    listenevents : [],

	    // The hidden field storing the disabled element values for submission.
	    hiddenRadioValue: null,
	    
		prepare_chooser : function() {
	        if (this.panel) {
	            return;
	        }
	        
			var params = {
					bodyContent : $('div.createnewquestion div.chooserdialogue').html(),
					headerContent : $('div.createnewquestion div.choosertitle').html(),
					width : '540px',
					draggable : true,
					visible : false, // Hide by default
	                zindex : 100, // Display in front of other items
	                modal: true, // This dialogue should be modal.
	                shim : true,
	                closeButtonTitle : 'Close',
	                focusOnPreviousTargetAfterHide: true,
	                render : false,
	                extraClasses: this._getClassNames()
			};
			// Create the panel
	        this.panel = new M.core.dialogue(params);
	
	        // Hide and then render the panel
	        this.panel.hide();
	        this.panel.render();

	        // Set useful links.
	        this.container = this.panel.get('boundingBox').one('.choosercontainer');
	        this.options = this.container.all('.option input[type=radio]');

	        // The hidden form element we use when submitting.
	        this.hiddenRadioValue = Y.Node.create('<input type="hidden" value="" />');
	        this.container.one('form').appendChild(this.hiddenRadioValue);
	        
	        // Add the chooserdialogue class to the container for styling
	        this.panel.get('boundingBox').addClass('chooserdialogue');
		},
		
		display_chooserdialogue : function(e) {
			this.prepare_chooser();
			e.preventDefault();
			var bb = this.panel.get('boundingBox');
			var dialogue = this.container.one('.alloptions');

			// Detect window resizes (most browsers)
	        var thisevent = $(window).on('resize', function() {
	            this.center_dialogue(dialogue);
	        }, this);
	        this.listenevents.push(thisevent);

	        // These will trigger a check_options call to display the correct help
	        thisevent = this.container.on('click', this.check_options, this);
	        this.listenevents.push(thisevent);
	        thisevent = this.container.on('key_up', this.check_options, this);
	        this.listenevents.push(thisevent);
	        thisevent = this.container.on('dblclick', function(e) {
	            if (e.target.ancestor('div.option')) {
	                this.check_options();

	                // Prevent duplicate submissions
	                this.submitbutton.setAttribute('disabled', 'disabled');
	                this.options.setAttribute('disabled', 'disabled');
	                this.cancel_listenevents();

	                e.preventDefault();
	                var input = $('<input>')
	                    .attr('type', 'hidden')
	                    .attr('name', 'qtype').val($('input[name=qtype]:checked').val());
	                $('#blockeditingform').append($(input));
	                $('#blockeditingform').submit(); 
	            }
	        }, this);
	        this.listenevents.push(thisevent);

	        this.container.one('form').on('submit', function(e) {
	            // Prevent duplicate submissions on submit
	            this.submitbutton.setAttribute('disabled', 'disabled');
	            this.options.setAttribute('disabled', 'disabled');
	            this.cancel_listenevents();
	            e.preventDefault();
	        }, this);

	        // Hook onto the cancel button to hide the form
	        thisevent = this.container.one('.addcancel').on('click', this.cancel_popup, this);
	        this.listenevents.push(thisevent);

	        // Hide will be managed by cancel_popup after restoring the body overflow
	        thisevent = bb.one('button.closebutton').on('click', this.cancel_popup, this);
	        this.listenevents.push(thisevent);

	        // Grab global keyup events and handle them
	        thisevent = $(document).on('keydown', this.handle_key_press, this);
	        this.listenevents.push(thisevent);

	        // Add references to various elements we adjust
	        this.submitbutton = this.container.one('.submitbutton');
            
            this.submitbutton.on('click', function(e) {
                e.preventDefault();
                var input = $('<input>')
                    .attr('type', 'hidden')
                    .attr('name', 'qtype').val($('input[name=qtype]:checked').val());
                $('#blockeditingform').append($(input));
                $('#blockeditingform').submit();
            }, this);

	        // Disable the submit element until the user makes a selection
	        this.submitbutton.set('disabled', 'true');

	        // Ensure that the options are shown
	        this.options.removeAttribute('disabled');

	        // Display the panel
	        this.panel.show(e);

	        // Re-centre the dialogue after we've shown it.
	        this.center_dialogue(dialogue);

	        // Finally, focus the first radio element - this enables form selection via the keyboard
	        this.container.one('.option input[type=radio]').focus();

	        // Trigger check_options to set the initial jumpurl
	        this.check_options();
		},

	    /**
	     * Cancel any listen events in the listenevents queue
	     *
	     * Several locations add event handlers which should only be called before the form is submitted. This provides
	     * a way of cancelling those events.
	     *
	     * @method cancel_listenevents
	     */
	    cancel_listenevents : function () {
	        // Detach all listen events to prevent duplicate triggers
	        var thisevent;
	        while (this.listenevents.length) {
	            thisevent = this.listenevents.shift();
	            thisevent.detach();
	        }
	    },

	    /**
	      * Calculate the optimum height of the chooser dialogue
	      *
	      * This tries to set a sensible maximum and minimum to ensure that some options are always shown, and preferably
	      * all, whilst fitting the box within the current viewport.
	      *
	      * @method center_dialogue
	      * @param Node {dialogue} Y.Node The dialogue
	      */
	    center_dialogue : function(dialogue) {
	        var bb = this.panel.get('boundingBox'),
	            winheight = bb.get('winHeight'),
	            newheight, totalheight;

	        if (this.panel.shouldResizeFullscreen()) {
	            // No custom sizing required for a fullscreen dialog.
	            return;
	        }

	        // Try and set a sensible max-height -- this must be done before setting the top
	        // Set a default height of 640px
	        newheight = 660;
	        if (winheight <= newheight) {
	            // Deal with smaller window sizes
	            if (winheight <= 300) {
	                newheight = 300;
	            } else {
	                newheight = winheight;
	            }
	        }

	        // If the dialogue is larger than a reasonable minimum height, we
	        // disable the page scrollbars.
	        if (newheight > 300) {
	            // Disable the page scrollbars.
	            if (this.panel.lockScroll && !this.panel.lockScroll.isActive()) {
	                this.panel.lockScroll.enableScrollLock(true);
	            }
	        } else {
	            // Re-enable the page scrollbars.
	            if (this.panel.lockScroll && this.panel.lockScroll.isActive()) {
	                this.panel.lockScroll.disableScrollLock();
	            }
	        }

	        // Take off 15px top and bottom for borders, plus 40px each for the title and button area before setting the
	        // new max-height
	        totalheight = newheight;
	        newheight = newheight - (15 + 15 + 40 + 40);
	        dialogue.setStyle('maxHeight', newheight + 'px');

	        var dialogueheight = bb.getStyle('height');
	        if (dialogueheight.match(/.*px$/)) {
	            dialogueheight = dialogueheight.replace(/px$/, '');
	        } else {
	            dialogueheight = totalheight;
	        }

	        if (dialogueheight < 400) {
	            dialogueheight = 400;
	            dialogue.setStyle('height', dialogueheight + 'px');
	        }

	        this.panel.centerDialogue();
	    },

	    handle_key_press : function(e) {
	        if (e.keyCode === 27) {
	            this.cancel_popup(e);
	        }
	    },

	    cancel_popup : function (e) {
	        // Prevent normal form submission before hiding
	        e.preventDefault();
	        this.hide();
	    },

	    hide : function() {
	        // Cancel all listen events
	        this.cancel_listenevents();

	        this.container.detachAll();
	        this.panel.hide();
	    },

	    check_options : function() {
	        // Check which options are set, and change the parent class
	        // to show/hide help as required
	        this.options.each(function(thisoption) {
	            var optiondiv = thisoption.get('parentNode').get('parentNode');
	            if (thisoption.get('checked')) {
	                optiondiv.addClass('selected');

	                // Trigger any events for this option
	                this.option_selected(thisoption);

	                // Ensure that the form may be submitted
	                this.submitbutton.removeAttribute('disabled');

	                // Ensure that the radio remains focus so that keyboard navigation is still possible
	                thisoption.focus();
	            } else {
	                optiondiv.removeClass('selected');
	            }
	        }, this);
	    },

	    option_selected : function(e) {
	        // Set a hidden input field with the value and name of the radio button.  When we submit the form, we
	        // disable the radios to prevent duplicate submission. This has the result however that the value is never
	        // submitted so we set this value to a hidden field instead
	        this.hiddenRadioValue.setAttrs({
	            value: e.get('value'),
	            name: e.get('name')
	        });
	    },

	    /**
	     * Return an array of class names prefixed with 'chooserdialogue-' and
	     * the name of the type of dialogue.
	     *
	     * Note: Class name are converted to lower-case.
	     *
	     * If an array of arguments is supplied, each of these is prefixed and
	     * lower-cased also.
	     *
	     * If no arguments are supplied, then the prefix is returned on it's
	     * own.
	     *
	     * @method _getClassNames
	     * @param {Array} [args] Any additional names to prefix and lower-case.
	     * @return {Array}
	     * @private
	     */
	    _getClassNames: function(args) {
	        var prefix = 'chooserdialogue-' + this.name,
	            results = [];

	        results.push(prefix.toLowerCase());
	        if (args) {
	            var arg;
	            for (arg in args) {
	                results.push((prefix + '-' + arg).toLowerCase());
	            }
	        }

	        return results;
	    }
	};
    return {
        init: function() {
        	
        	$('.addquestion').click(function(e) {
        		question_chooser.display_chooserdialogue(e);
        	});
        }
    };
});