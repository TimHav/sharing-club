(function( $ ) {
	'use strict';
    $(function() {
        $('.datepicker').datepicker({
            minDate: '0',
            dateFormat : 'dd-mm-yy'
        });

        var $objectInput = $('#object_autocomplete');
        var $objectId = $('#comment_post_ID');
        var $objectResults = $('#object_autocomplete_results');

        if ($objectInput.length && $objectId.length && $objectResults.length) {
            var objectSource = $('#scwp-object-options').length ? JSON.parse($('#scwp-object-options').text()) : [];

            function renderMatches(term) {
                var needle = term.toLowerCase();
                var matches = objectSource.filter(function(item) {
                    return item.title.toLowerCase().indexOf(needle) !== -1;
                });

                if (!matches.length) {
                    $objectResults.hide().empty();
                    return;
                }

                var html = '';
                $.each(matches, function(index, item) {
                    var disabledClass = item.lent ? ' is-disabled' : '';
                    html += '<div class="scwp-object-result' + disabledClass + '" data-object-id="' + item.id + '" data-object-title="' + item.title.replace('"', '&quot;') + '" data-disabled="' + (item.lent ? '1' : '0') + '">' + item.title + '</div>';
                });

                $objectResults.html(html).show();
            }

            $objectInput.on('focus', function() {
                var input = this;
                setTimeout(function() {
                    input.scrollIntoView({ block: 'start', behavior: 'smooth' });
                }, 250);
                renderMatches($(this).val());
            });

            $objectInput.on('input', function() {
                renderMatches($(this).val());
            });

            $objectResults.on('click', '.scwp-object-result', function() {
                if ($(this).attr('data-disabled') === '1') {
                    return;
                }
                var chosenId = $(this).data('object-id');
                var chosenTitle = $(this).data('object-title');
                $objectInput.val(chosenTitle);
                $objectId.val(chosenId);
                $objectResults.hide().empty();
            });

            $(document).on('click', function(event) {
                if (!$(event.target).closest('#object_autocomplete').length && !$(event.target).closest('#object_autocomplete_results').length) {
                    $objectResults.hide();
                }
            });

            $objectInput.on('change', function() {
                var title = $(this).val();
                var exact = objectSource.find(function(item) {
                    return item.title === title;
                });

                if (exact) {
                    $objectId.val(exact.id);
                } else {
                    $objectId.val('');
                }
            });
        }
    });
	/**
	 * All of the code for your admin-facing JavaScript source
	 * should reside in this file.
	 *
	 * Note: It has been assumed you will write jQuery code here, so the
	 * $ function reference has been prepared for usage within the scope
	 * of this file.
	 *
	 * This enables you to define handlers, for when the DOM is ready:
	 *
	 * $(function() {
	 *
	 * });
	 *
	 * When the window is loaded:
	 *
	 * $( window ).load(function() {
	 *
	 * });
	 *
	 * ...and/or other possibilities.
	 *
	 * Ideally, it is not considered best practise to attach more than a
	 * single DOM-ready or window-load handler for a particular page.
	 * Although scripts in the WordPress core, Plugins and Themes may be
	 * practising this, we should strive to set a better example in our own work.
	 */

})( jQuery );
