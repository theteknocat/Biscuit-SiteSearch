<?php
if ($result_count > 0) {
	if ($result_count > $popup_top_hit_count) {
		?>
	<div id="ajax-search-see-all-link"><a class="small" href="<?php echo $SiteSearch->url(); ?>?search=<?php echo urlencode($original_keywords) ?>">See all <?php echo $result_count ?> results</a></div>
		<?php
	}
	foreach ($search_results as $search_result) {
	?>
	<div class="search-result <?php echo $Navigation->tiger_stripe('search_results') ?>">
		<a href="<?php echo $search_result->url() ?>" title="<?php echo STANDARD_URL.$search_result->url() ?>"><?php echo $search_result->title() ?></a>
	</div>
	<?php
	}
} else {
	?><p class="none-found">No Results</p><?php
}
