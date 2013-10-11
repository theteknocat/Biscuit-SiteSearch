<form id="site-search-form" name="search_form" action="/search" method="GET">
	<?php
	if (!empty($search_root)) {
		?><input id="search-root-field" type="hidden" name="search_root" value="<?php echo $search_root ?>"><?php
	}
	?>
	<p style="text-align: center"><input id="search-keywords-field" type="text" class="text search-field" name="search" value="<?php echo $original_keywords ?>"> <input type="submit" class="SubmitButton" value="<?php echo __('Search'); ?>"></p>
	<div id="ajax-search-result-container">
		<div id="ajax-search-results">
			<h4><img id="ajax-search-throbber" src="/modules/site_search/images/top-hits-throbber.gif"><?php echo sprintf(__('Top %d Hits'), (int)$top_hit_count) ?></h4>
			<div id="ajax-search-top-hits-content"></div>
		</div>
	</div>
</form>
