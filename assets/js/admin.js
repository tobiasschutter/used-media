(function($) {

	/**
	 *	Custom Delete Notice for Media Library Attachment that are attached to a post
	 *
	 */
	showCustomDeleteNotice = {
		warn : function( by_editor, as_featured, is_attached, in_custom_field ) {
			var msg = '';
			
			// used by editor message
			if ( by_editor ) {
				msg += cpumL10n.usedByEditor || '';
			}
			
			// used as featured image message
			if ( as_featured ) {
				msg += cpumL10n.usedAsFeatured || '';
			}
			
			// attached message
			if ( is_attached ) {
				msg += cpumL10n.isAttached || '';
			}
			
			// attached message
			if ( in_custom_field ) {
				msg += cpumL10n.usedInCustomField || '';
			}
			
			
			// add original
			msg += commonL10n.warnDelete || '';
			
			if ( confirm(msg) ) {
				return true;
			}

			return false;
		}
	};
	
})(jQuery);