<?php
/**
 * Model the content index table which stores the plain text content of pages
 *
 * @package Modules
 * @author Peter Epp
 */
class ContentIndex extends AbstractModel {
	/**
	 * Output text snippets from $full_text_content with keywords highlighted
	 *
	 * @param string $keywords 
	 * @return string
	 * @author Peter Epp
	 */
	public function synopsis($keywords) {
		Console::log("Building synopsis for search result for ".$this->url());
		$source_content = $this->full_text_content();
		// Remove excess whitespace:
		$source_content = str_replace('&nbsp;',' ',$source_content);
		$source_content = preg_replace('/([\r\n\t\s]+)/',' ',$source_content);
		$content_words = explode(" ", strtolower($source_content));
		$content_words = array_filter($content_words);
		$last_end_offset = 0;
		$snippets = array();
		foreach ($content_words as $index => $content_word) {
			// Remove all punctuation (except hyphens):
			$content_word = preg_replace('/([^A-Za-z-\s]+)/','',$content_word);
			// Grab the word stem:
			$word_stem = PorterStemmer::stem(strtolower($content_word));
			if (!empty($word_stem) && in_array($word_stem, $keywords)) {
				$snippet_offset = $index-5;
				if ($snippet_offset < 0) {
					$snippet_offset = 0;
				}
				if ($snippet_offset > $last_end_offset || $last_end_offset == 0) {
					$snippet = implode(' ',array_slice($content_words,$snippet_offset,10));
					$last_end_offset = $snippet_offset+10;
					$snippets[] = $snippet;
				}
			}
			if (count($snippets) == 3) {
				break;
			}
		}
		$snippet_str = '';
		if (!empty($snippets)) {
			$snippet_str = implode(' ... ',$snippets).' ...';
			if (substr($snippet_str,0,5) != substr($source_content,0,5)) {
				$snippet_str = '... '.$snippet_str;
			}
			$snippet_str = $this->highlight_text($snippet_str, $keywords);
		}
		return $snippet_str;
	}

	public function highlight_text($text, $keywords) {
		$words = explode(" ",$text);
		$words = array_filter($words);
		$new_words = array();
		foreach ($words as $word) {
			// Remove all punctuation (except hyphens):
			$check_word = preg_replace('/([^A-Za-z-\s]+)/','',$word);
			$word_stem = PorterStemmer::stem(strtolower($check_word));
			if (!empty($word_stem) && in_array($word_stem,$keywords)) {
				$new_words[] = preg_replace('/('.$check_word.')/i','<strong><em>$1</em></strong>',$word);
			} else {
				$new_words[] = $word;
			}
		}
		$new_text = implode(" ",$new_words);
		return $new_text;
	}

	public static function db_tablename() {
		return "content_index";
	}
}
?>