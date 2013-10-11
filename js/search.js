AjaxSearch = {
	do_search_timer: null,
	start_popup_timer: null,
	close_popup_timer: null,
	last_search_term: null,
	popup_left_pos: null,
	init: function() {
		Biscuit.Console.log("Ajax search initializing...");
		jQuery('#search-keywords-field').keyup(function() {
			clearTimeout(AjaxSearch.do_search_timer);
			var my_val = jQuery(this).val();
			if (my_val != AjaxSearch.last_search_term) {
				Biscuit.Crumbs.ShowThrobber('ajax-search-throbber');
				AjaxSearch.start_popup_timer = setTimeout("AjaxSearch.show_popup('<p class=\"none-found\">Searching...</p>');",250);
				AjaxSearch.do_search_timer = setTimeout("AjaxSearch.do_search('"+my_val+"');",1500);
			}
		});
		jQuery('#search-keywords-field').blur(function() {
			AjaxSearch.close_popup_timer = setTimeout('AjaxSearch.hide_popup();',250);
		});
		jQuery('#search-keywords-field').focus(function() {
			var top_hits_html = jQuery('#ajax-search-result-container #ajax-search-top-hits-content').html();
			if (top_hits_html != '') {
				AjaxSearch.start_popup_timer = setTimeout("AjaxSearch.show_popup();",250);
			} else {
				var my_val = jQuery(this).val();
				if (my_val != '') {
					// If the popup content is empty but there's a search term, do a search so the popup opens with results on the first
					// focus of the field.
					Biscuit.Crumbs.ShowThrobber('ajax-search-throbber');
					AjaxSearch.start_popup_timer = setTimeout("AjaxSearch.show_popup('<p class=\"none-found\">Searching...</p>');",250);
					AjaxSearch.do_search_timer = setTimeout("AjaxSearch.do_search('"+my_val+"');",1500);
				}
			}
		});
		jQuery('#ajax-search-result-container').click(function() {
			clearTimeout(AjaxSearch.close_popup_timer);
			jQuery('#search-keywords-field').focus();
		});
	},
	do_search: function(keywords) {
		var search_root_field = jQuery('#search-root-field');
		if (search_root_field.length > 0) {
			var search_root = search_root_field.val();
		} else {
			var search_root = '/';
		}
		Biscuit.Ajax.Request('/search?search='+escape(keywords)+'&search_root='+search_root,'update',{
			success: function(html) {
				Biscuit.Crumbs.HideThrobber('ajax-search-throbber');
				jQuery('#ajax-search-result-container #ajax-search-top-hits-content').html(html);
				AjaxSearch.last_search_term = keywords;
			}
		});
	},
	show_popup: function(html) {
		if (this.popup_left_offset == null) {
			Biscuit.Console.log("Calculate left pos for ajax result popup");
			// Set the position of the popup to line up with the left of the search form field, unless that causes it to stick out over
			// the edge of the top-most container div in the body, in which case we'll pull it in by the difference between right offsets.
			var popup_box_left_pos = null;
			var keywords_field_left_pos = parseInt(jQuery('#search-keywords-field').position().left);
			Biscuit.Console.log("Keywords field left pos: "+keywords_field_left_pos);
			var keywords_field_left_offset = parseInt(jQuery('#search-keywords-field').offset().left);
			Biscuit.Console.log("Keywords field left offset: "+keywords_field_left_offset);
			var main_container_right = parseInt(jQuery('body > div:first').offset().left)+parseInt(jQuery('body > div').css('width'));
			var popup_box_right = keywords_field_left_offset+300;
			if (popup_box_right > main_container_right) {
				var offset_difference = popup_box_right-main_container_right;
				Biscuit.Console.log("Right edge of ajax popup ("+popup_box_right+") is greater than the right edge ("+main_container_right+")");
				popup_box_left_pos = keywords_field_left_pos-offset_difference-5;
			} else {
				Biscuit.Console.log("Right edge of ajax popup is within page container");
				popup_box_left_pos = keywords_field_left_pos;
			}
			Biscuit.Console.log('Popup box left position: '+popup_box_left_pos);
			this.popup_left_pos = popup_box_left_pos;
		}
		jQuery('#ajax-search-result-container').css({'left': this.popup_left_pos+'px'});
		if (html != undefined) {
			jQuery('#ajax-search-result-container #ajax-search-top-hits-content').html(html);
		}
		jQuery('#ajax-search-result-container').slideDown('fast');
	},
	hide_popup: function(html) {
		if (html != undefined) {
			jQuery('#ajax-search-result-container #ajax-search-top-hits-content').html(html);
		}
		jQuery('#ajax-search-result-container').slideUp('fast');
	}
}

jQuery(document).ready(function() {
	AjaxSearch.init();
});