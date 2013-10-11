<?php
/**
 * Special factory for the ContentIndex model with find_by_keywords search method
 *
 * @package Modules
 * @subpackage SiteSearch
 * @author Peter Epp
 * @version $Id: content_index_factory.php 13843 2011-07-27 19:45:49Z teknocat $
 */
class ContentIndexFactory extends ModelFactory {
	/**
	 * Find rows in the content_index table by keywords that match content in the cleaned_text_content column
	 *
	 * @param string $keywords 
	 * @return array ContentIndex models
	 * @author Peter Epp
	 */
	public function find_by_keywords($keywords, $pagination_limit, $search_root = '/') {
		if ($search_root == '/') {
			$search_root_subs = '';
		} else {
			$search_root_subs = $search_root;
		}
		$in_sql = $this->in_sql($keywords);
		$query = "SELECT `ci`.*, COUNT(`ci`.`id`) AS `num_words_matched`, SUM(`wi`.`occurrences`) AS `total_matches` FROM `content_index` `ci` LEFT JOIN `word_index` `wi` ON (`wi`.`content_index_id` = `ci`.`id`) WHERE `wi`.`word` IN ({$in_sql}) AND (`ci`.`url` = '{$search_root}' OR `ci`.`url` LIKE '{$search_root_subs}/%') GROUP BY `ci`.`id` ORDER BY `num_words_matched` DESC, `total_matches` DESC LIMIT ".$pagination_limit;
		return $this->models_from_query($query,$keywords);
	}
	/**
	 * Return the count of rows for a search
	 *
	 * @param string $keywords 
	 * @return int
	 * @author Peter Epp
	 */
	public function search_row_count($keywords,$search_root = '/') {
		if ($search_root == '/') {
			$search_root_subs = '';
		} else {
			$search_root_subs = $search_root;
		}
		$in_sql = $this->in_sql($keywords);
		$query = "SELECT COUNT(DISTINCT `wi`.`content_index_id`) FROM `word_index` `wi` LEFT JOIN `content_index` `ci` ON (`ci`.`id` = `wi`.`content_index_id`) WHERE `wi`.`word` IN ({$in_sql}) AND (`ci`.`url` = '{$search_root}' OR `ci`.`url` LIKE '{$search_root_subs}/%')";
		$rowcount = DB::fetch_one($query,$keywords);
		if ($rowcount) {
			return $rowcount;
		}
		return 0;
	}
	/**
	 * Get the SQL for the "IN" function for the search and row-count queries
	 *
	 * @param string $keywords 
	 * @return void
	 * @author Peter Epp
	 */
	private function in_sql($keywords) {
		return implode(',',array_fill(0,count($keywords),'?'));
	}
	/**
	 * Delete rows by URL that matches the supplied argument
	 *
	 * @param string $url 
	 * @return void
	 * @author Peter Epp
	 */
	public function delete_by_url($url) {
		DB::query("DELETE FROM `content_index` WHERE `url` LIKE '%{$url}'");
	}
	/**
	 * Update an existing record with a new URL (if the record exists, if not nothing will be affected)
	 *
	 * @param string $old_url 
	 * @param string $new_url 
	 * @return void
	 * @author Peter Epp
	 */
	public function update_url($old_url, $new_url) {
		DB::query("UPDATE `content_index` SET `url` = ? WHERE `url` = ?",array($new_url,$old_url));
	}
	/**
	 * Store words in the word index table
	 *
	 * @param string $words 
	 * @param string $content_index_id 
	 * @return void
	 * @author Peter Epp
	 */
	public function store_words($words, $content_index_id) {
		DB::query("DELETE FROM `word_index` WHERE `content_index_id` = ?",$content_index_id);
		// Format a new array of words with the word as the key and the number of occurences as the value:
		foreach ($words as $word) {
			if (!isset($occurrences_by_word[$word])) {
				$occurrences_by_word[$word] = 0;
			}
			$occurrences_by_word[$word] += 1;
		}
		// Reformat the array into an indexed array alternating between word and occurrences for PDO:
		foreach ($occurrences_by_word as $word => $occurrences) {
			$pdo_array[] = $word;
			$pdo_array[] = $occurrences;
		}
		$word_values_sql = str_repeat("(".$content_index_id.",?,?), ",count($occurrences_by_word));
		$word_values_sql = rtrim($word_values_sql,", ");
		DB::query("INSERT INTO `word_index` (`content_index_id`, `word`, `occurrences`) VALUES ".$word_values_sql,$pdo_array);
	}
}
?>