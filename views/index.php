<?php
if (Request::is_ajax()) {
	include 'site_search/views/index-ajax.php';
} else {
	include 'site_search/views/index-standard.php';
}
?>