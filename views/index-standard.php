<?php
require_once('site_search/views/search_form.php');
if (!empty($search_summary)) {
	?>
<h3 id="search-summary"><?php echo $search_summary ?></h3>
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
		?><div class="search-result <?php echo $Navigation->tiger_stripe('search_results') ?>"><?php
		$fcache = new FragmentCache('ContentIndex',$search_result->id());
		if ($fcache->start('regular-search-result')) {
			$synopsis = $search_result->synopsis($stemmed_keywords);
			?>
		<h4><a href="<?php echo $search_result->url() ?>"><?php echo $search_result->highlight_text($search_result->title(), $stemmed_keywords) ?></a></h4>
		<?php
			if (!empty($synopsis)) {
				?><p><?php echo $synopsis ?></p><?php
			}
		?>
		<p class="small"><a href="<?php echo $search_result->url() ?>"><?php echo STANDARD_URL.$search_result->url() ?></a></p>
		<?php
			$fcache->end('regular-search-result');
		}
		?></div><?php
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
