<?php
require_once('site_search/views/search_form.php');
if (!empty($search_summary)) {
	?>
<h2 id="search-summary"><?php echo $search_summary ?></h2>
	<?php
}
if ($result_count > 0) {
	?>
<div id="site-search-results">
	<?php
	if ($paginator->GetPageCount() > 1) {
		?>
	<div class="paging"><div class="paging-title">Results <?php echo $paginator->GetPageItemNumbers($result_count) ?></div><div class="page-links-right"><?php echo $paginator->GetPageLinks(); ?></div></div>
		<?php
	}
	foreach ($search_results as $search_result) {
		$synopsis = $search_result->synopsis($stemmed_keywords);
		?>
	<div class="search-result <?php echo $Navigation->tiger_stripe('search_results') ?>">
		<?php
		$item_render_start_time = microtime(true);
		?>
		<h2><a href="<?php echo $search_result->url() ?>"><?php echo $search_result->highlight_text($search_result->title(), $stemmed_keywords) ?></a></h2>
		<?php
		if (!empty($synopsis)) {
			?><p><?php echo $synopsis ?></p><?php
		}
		?>
		<p class="small"><a href="<?php echo $search_result->url() ?>"><?php echo STANDARD_URL.$search_result->url() ?></a></p>
	</div>
		<?php
	}
	if ($paginator->GetPageCount() > 1) {
		?>
	<div class="paging"><div class="paging-title">Results <?php echo $paginator->GetPageItemNumbers($result_count) ?></div><div class="page-links-right"><?php echo $paginator->GetPageLinks(); ?></div></div>
		<?php
	}
	?>
	<p class="small" style="text-align: center">Search performed in <?php echo round((microtime(true) - $search_start_time),4) ?> seconds.</p>
</div>
	<?php
}
?>