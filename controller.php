<?php
require_once('vendor/porter_stemmer/porter_stemmer.php');
require_once('vendor/simple_html_dom/simple_html_dom.php');
/**
 * Module for performing internal indexing and searching of the website
 *
 * @package Modules
 * @author Peter Epp
 * @copyright Copyright (c) 2009 Peter Epp (http://teknocat.org)
 * @license GNU Lesser General Public License (http://www.gnu.org/licenses/lgpl.html)
 * @version 2.0
 */
class SiteSearch extends AbstractModuleController {
	protected $_models = array(
		"ContentIndex" => "ContentIndex"
	);
	protected $_items_per_page = 20;
	protected $_pagination_prev_marker = '&laquo;';
	protected $_pagination_separator = '';
	protected $_pagination_next_marker = '&raquo;';
	protected $_pagination_links_to_display = 10;
	protected $_popup_top_hit_count = 5;

	protected $_dependencies = array('Jquery');
	/**
	 * Root site section for searching within
	 *
	 * @var string
	 */
	private $_search_root = '/';
	/**
	 * Whether or not the current request should not be indexed. This can be set using the set_no_index() method by another module
	 * acting on the "search_indexing_page" event
	 *
	 * @var bool
	 */
	private $_do_not_index = false;
	/**
	 * Register common JS and CSS files and then run
	 *
	 * @return void
	 * @author Peter Epp
	 */
	public function run() {
		$this->register_css(array('filename' => 'search.css', 'media' => 'screen'));
		$this->register_js('footer','search.js');
		parent::run();
	}
	/**
	 * Run search on post requests and render
	 *
	 * @return void
	 * @author Peter Epp
	 */
	protected function action_index() {
		$this->Biscuit->set_never_cache();
		if (!empty($this->params['search'])) {
			if (defined('SEARCH_ITEMS_PER_PAGE')) {
				$this->_items_per_page = SEARCH_ITEMS_PER_PAGE;
			}
			if (defined('PAGINATION_PREV_MARKER')) {
				$this->_pagination_prev_marker = PAGINATION_PREV_MARKER;
			}
			if (defined('PAGINATION_SEPARATOR')) {
				$this->_pagination_separator = PAGINATION_SEPARATOR;
			}
			if (defined('PAGINATION_NEXT_MARKER')) {
				$this->_pagination_next_marker = PAGINATION_NEXT_MARKER;
			}
			if (defined('SEARCH_POPUP_TOP_HIT_COUNT') && SEARCH_POPUP_TOP_HIT_COUNT > 0) {
				$this->_popup_top_hit_count = SEARCH_POPUP_TOP_HIT_COUNT;
			}
			if (defined('SEARCH_PAGINATION_LINKS_TO_DISPLAY')) {
				$this->_pagination_links_to_display = SEARCH_PAGINATION_LINKS_TO_DISPLAY;
			}
			$search_results = $this->do_search();
			$this->set_view_var("search_results", $search_results);
		} else if (!Request::is_ajax()) {
			$this->set_view_var("search_summary","Please enter some keywords to search for.");
			$this->set_view_var('original_keywords','');
		}
		if (Request::is_ajax()) {
			$this->set_view_var('popup_top_hit_count',$this->_popup_top_hit_count);
		}
		$this->render();
	}
	/**
	 * Attempt to use wget to spider the entire site, thereby causing page's to be cached if they aren't already and thereby indexed. This allows use of cron job
	 * to spider and index the site by simply hitting /search/spider. If it doesn't work on your server you can setup a cron job to spider the site by some other means.
	 *
	 * Note that this method requires a global constant named "WGET_PATH". This should be defined in the config settings for the specific server. Note that you can include
	 * "wget" on the end of the path if you want.  If you omit "wget" it will be added on.  Trailing slashes will be removed.
	 *
	 * @return void
	 * @author Peter Epp
	 */
	protected function action_spider() {
		if (defined('WGET_PATH')) {
			ob_implicit_flush();
			$wget_path = WGET_PATH;
			if (substr($wget_path,-4) != "wget") {
				if (substr($wget_path,-1) == '/') {
					$wget_path = substr($wget_path,0,-1);
				}
				$wget_path .= '/wget';
			}
			@exec($wget_path.' --spider --recursive --force-html --directory-prefix='.SITE_ROOT.'/tmp --no-directories --quiet '.STANDARD_URL);
		} else {
			Console::log("Path to wget not specified, spidering will not be performed.");
		}
		Bootstrap::end_program(true);
	}
	/**
	 * Set a view var containing search form HTML snippet whenever this module is secondary.
	 *
	 * @return void
	 * @author Peter Epp
	 */
	protected function action_secondary() {
		if (defined('SEARCH_POPUP_TOP_HIT_COUNT') && SEARCH_POPUP_TOP_HIT_COUNT > 0) {
			$this->_popup_top_hit_count = SEARCH_POPUP_TOP_HIT_COUNT;
		}
		Event::fire('render_search_form',$this);
		$search_form_params = array(
			'original_keywords' => '',
			'top_hit_count' => $this->_popup_top_hit_count,
			'search_root' => $this->_search_root
		);
		$search_form = Crumbs::capture_include('site_search/views/search_form.php',$search_form_params);
		$this->set_view_var('search_form',$search_form);
	}
	/**
	 * Set the root section of the site to search within
	 *
	 * @param string $search_root 
	 * @return void
	 * @author Peter Epp
	 */
	public function set_search_root($search_root) {
		$this->_search_root = $search_root;
	}
	/**
	 * Perform search and set vars for the view
	 *
	 * @return void
	 * @author Peter Epp
	 */
	protected function do_search() {
		if (!empty($this->params['search_root'])) {
			$search_root = $this->params['search_root'];
		} else {
			$search_root = '/';
		}
		$this->set_view_var('search_root',$search_root);
		$search = $this->cleanup_text($this->params['search']);
		if (empty($search)) {
			if (!empty($this->params['search'])) {
				$this->set_view_var("search_summary","No results found searching for '".$this->params['search']."'.");
				$this->set_view_var('original_keywords',$this->params['search']);
			} else {
				$this->set_view_var("search_summary","Please enter some keywords to search for.");
			}
			return;
		}
		$search_words = explode(' ',$search);
		$search_words = array_filter($search_words);
		$stems = array_map(array('PorterStemmer','stem'),$search_words);
		$search_start_time = microtime(true);
		$result_count = $this->ContentIndex->search_row_count($stems, $search_root);
		$this->set_view_var("result_count",$result_count);
		$this->set_view_var("original_keywords", $this->params['search']);
		$this->set_view_var('top_hit_count',$this->_popup_top_hit_count);
		if ($result_count > 0) {
			if (Request::is_ajax()) {
				$sql_limit = $this->_popup_top_hit_count;
			} else {
				$paginator = new PaginateIt();
				$paginator->SetLinksFormat($this->_pagination_prev_marker.' Prev',$this->_pagination_separator,'Next '.$this->_pagination_next_marker);
				$paginator->SetItemsPerPage($this->_items_per_page);
				$paginator->SetLinksToDisplay($this->_pagination_links_to_display);
				$paginator->SetLinksHref($this->url());
				$paginator->SetItemCount($result_count);
				$sql_limit = $paginator->GetSqlLimit();
				$this->set_view_var("paginator", $paginator);
			}
			$search_results = $this->ContentIndex->find_by_keywords($stems, $sql_limit, $search_root);
			if (!empty($this->params['page'])) {
				$page = $this->params['page'];
			} else {
				$page = 1;
			}
			$this->set_view_var("stemmed_keywords", $stems);
			$this->set_view_var("page", $page);
			$this->set_view_var('result_count',$result_count);
			$this->set_view_var("search_summary", $result_count." result".(($result_count > 1) ? "s" : "")." found in search for '".$this->params['search']."'");
			$this->set_view_var("search_start_time",$search_start_time);
			return $search_results;
		}
		$this->set_view_var("search_summary","No results found searching for '".$this->params['search']."'.");
		return null;
	}
	/**
	 * Index the page content
	 *
	 * @param Page $page
	 * @return void
	 * @author Peter Epp
	 */
	private function index_page() {
		$page = $this->Biscuit->Page;
		if ($this->can_index_request($page)) {
			Console::log("Site Search is indexing current request: ".Request::uri());
			$html = file_get_html($page->cache_file());
			$indexable_content = $html->find('div.indexable-content',0);
			if (empty($indexable_content)) {
				trigger_error("Unable to index content for ".Request::uri().": Page does not contain an element with a class of 'indexable-content'", E_USER_WARNING);
				return;
			}
			$raw_text_content = $this->extract_text_content($indexable_content);
			$raw_text_content = preg_replace('/[\r\n\t]+/',' ',trim($raw_text_content));
			$alt_text         = $this->extract_alt_text($html);
			$cleaned_content  = $this->cleanup_text($raw_text_content);
			$title            = $this->cleanup_text($page->title());
			$keywords         = $page->keywords();
			if (!empty($keywords)) {
				$keywords = str_replace(',',' ',$keywords);
				$keywords = $this->cleanup_text($keywords);
			}
			$description  = $this->cleanup_text($page->description());
			$all_text_content = trim($title.' '.$description.' '.$keywords.' '.$cleaned_content.' '.$alt_text);
			$all_text_content = preg_replace('/\s\s+/',' ',$all_text_content);	// Strip extra spaces
			$words = explode(" ",$all_text_content);	// Explode into an array
			// Stem the words:
			$words = array_map(array('PorterStemmer','stem'),$words);
			$request_uri = Request::uri();
			if (substr($request_uri,-1) == '?') {
				$request_uri = substr($request_uri,0,-1);
			}
			if ($request_uri == '/index') {
				$request_uri = '/';
			}
			$content_index_data = array(
				'url'                  => $request_uri,
				'title'                => $page->title(),
				'full_text_content'    => $raw_text_content
			);
			$content_index = $this->ContentIndex->find_by('url',$request_uri);
			if ($content_index) {
				Console::log("Updating existing indexed content");
				$content_index->set_attributes($content_index_data);
			} else {
				Console::log("Creating new content index");
				$content_index = $this->ContentIndex->create($content_index_data);
			}
			if ($content_index->save()) {
				$this->ContentIndex->store_words($words,$content_index->id());
				Console::log("Content index successfully saved");
			} else {
				trigger_error("Unable to index content for ".Request::uri().': '.implode(', ',$content_index->errors()), E_USER_WARNING);
			}
		}
	}
	/**
	 * Recursively walk through all nodes of an HTML DOM object and return a concatenated plain text string
	 *
	 * @param string $html_dom_object 
	 * @return void
	 * @author Peter Epp
	 */
	private function extract_text_content($html_dom_object) {
		$elements = $html_dom_object->find('h1, h2, h3, h4, p, td, li, dd');
		$plain_text_array = array();
		foreach ($elements as $element) {
			$plain_text_array[] = trim($element->plaintext);
		}
		return implode(' ',$plain_text_array);
	}
	/**
	 * Whether or not the current request can be indexed
	 *
	 * @param Page $page 
	 * @return bool
	 * @author Peter Epp
	 */
	private function can_index_request($page) {
		if ($this->_do_not_index) {
			return false;
		}
		if (!Response::is_html()) {
			Console::log("Site Search: request is not HTML output, content will not be indexed.");
			return false;
		}
		if ($page->parent() == HIDDEN_ORPHAN_PAGE) {
			Console::log("Site Search: request is for a hidden orphan page, content will not be indexed.");
			return false;
		}
		if ($page->access_level() != PUBLIC_USER) {
			Console::log("Site Search: request is for a non-public page, content will not be indexed.");
			return false;
		}
		$primary_module = $this->Biscuit->primary_module();
		if (is_object($primary_module)) {
			if (!Permissions::user_can($primary_module,$primary_module->action(),PUBLIC_USER)) {
				Console::log("Site Search: request is for an action not publicly accessible, content will not be indexed.");
				return false;
			}
		}
		return true;
	}
	/**
	 * Set the current request to not be indexed
	 *
	 * @return void
	 * @author Peter Epp
	 */
	public function set_no_index() {
		$this->_do_not_index = true;
	}
	/**
	 * Extract alt text from image tags and title text from anchor tags
	 *
	 * @param object $html Simple HTML DOM object 
	 * @return string
	 * @author Peter Epp
	 */
	private function extract_alt_text($html) {
		$alt_text = '';
		$text_bits = array();
		$elements = $html->find('div.indexable-content a[title], div.indexable-content img[alt]');
		foreach ($elements as $element) {
			if (!empty($element->alt)) {
				$text_bits[] = $element->alt;
			} else if (!empty($element->title)) {
				$text_bits[] = $element->title;
			}
		}
		if (!empty($text_bits)) {
			$alt_text = implode(' ',$text_bits);
		}
		return $this->cleanup_text($alt_text);
	}
	/**
	 * Cleanup text by trimming extra whitespace, reducing extra whitespace within the text to single spaces and removing prepositions and punctuation
	 *
	 * @param string $text Text to trim
	 * @return string Trimmed text
	 * @author Peter Epp
	 */
	private function cleanup_text($text) {
		if (empty($text)) {
			return '';
		}
		$text = H::purify_text(trim(strtolower(html_entity_decode($text))));
		// Remove all punctuation (except hyphens):
		$text = preg_replace('/[^a-z-\s]/','',$text);
		// Remove excess whitespace
		$text = preg_replace('/[\r\n\t ]+/',' ',$text);
		// Remove prepositions:
		$words = explode(' ',$text);
		$new_words = array();
		$common_words = array("a","am","an","and","amp","as","at","but","by","for","i","in","is","it","me","my","of","off","on","per","so","the","to", "about", "above", "across", "after", "against", "along", "among", "around", "before", "behind", "below", "beneath", "beside", "between", "beyond", "despite", "down", "during", "except", "from", "inside", "into", "like", "near", "onto", "out", "outside", "over", "past", "since", "through", "throughout", "till", "toward", "under", "underneath", "until", "up", "upon", "with", "within", "without");
		foreach ($words as $index => $word) {
			if (!in_array(strtolower($word),$common_words) && preg_match('/[a-z]+/i',$word) && strlen($word) > 1) {
				$new_words[] = $word;
			}
		}
		$text = implode(' ',$new_words);
		$text = preg_replace('/\s\s+/',' ',$text);	// Strip extra spaces
		return trim($text);
	}
	/**
	 * Run migrations required for module to be installed properly
	 *
	 * @return void
	 * @author Peter Epp
	 */
	public function install_migration() {
		// Create tables:
		DB::query("CREATE TABLE IF NOT EXISTS `content_index` (
		  `id` int(11) NOT NULL auto_increment,
		  `url` text NOT NULL,
		  `title` varchar(255) NOT NULL default '',
		  `full_text_content` text NOT NULL,
		  PRIMARY KEY  (`id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8");
		DB::query("CREATE TABLE IF NOT EXISTS `word_index` (
		  `id` int(11) NOT NULL auto_increment,
		  `content_index_id` int(11) NOT NULL,
		  `word` varchar(255) NOT NULL,
		  `occurrences` int(5) NOT NULL default '0',
		  PRIMARY KEY  (`id`),
		  KEY `content_index_id` (`content_index_id`),
		  KEY `word` (`word`),
		  CONSTRAINT `word_index_ibfk_1` FOREIGN KEY (`content_index_id`) REFERENCES `content_index` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
		) ENGINE=InnoDB DEFAULT CHARSET=utf8");
		// Create search page, if needed:
		$search_page = DB::fetch_one("SELECT `id` FROM `page_index` WHERE `slug` = 'search'");
		if (!$search_page) {
			// Add search page:
			DB::insert("INSERT INTO `page_index` SET `parent` = 9999999, `slug` = 'search', `title` = 'Site Search'");
			// Get module row ID:
			$module_id = DB::fetch_one("SELECT `id` FROM `modules` WHERE `name` = 'SiteSearch'");
			// Remove SiteSearch from module pages first to ensure clean install:
			DB::query("DELETE FROM `module_pages` WHERE `module_id` = {$module_id}");
			// Add SiteSearch to search page as primary:
			DB::insert("INSERT INTO `module_pages` SET `module_id` = {$module_id}, `page_name` = 'search', `is_primary` = 1");
			// Add SiteSearch to all pages as secondary:
			DB::insert("INSERT INTO `module_pages` SET `module_id` = {$module_id}, `page_name` = '*', `is_primary` = 0");
		}
	}
	/**
	 * Run migrations to properly uninstall the module
	 *
	 * @return void
	 * @author Peter Epp
	 */
	public function uninstall_migration() {
		$module_id = DB::fetch_one("SELECT `id` FROM `modules` WHERE `name` = 'SiteSearch'");
		DB::query("DELETE FROM `page_index` WHERE `slug` = 'search'");
		DB::query("DELETE FROM `module_pages` WHERE `module_id` = ".$module_id);
		DB::query("DROP TABLE IF EXISTS `word_index`");
		DB::query("DROP TABLE IF EXISTS `content_index`");
	}
	/**
	 * When caching of the page occurs, index the content
	 *
	 * @return void
	 * @author Peter Epp
	 */
	protected function act_on_page_cached() {
		if (!$this->is_primary()) {
			Event::fire('search_indexing_page');
			$this->index_page();
		}
	}
	/**
	 * Upon successful save of a model, check if it's URL has changed and if so update the content index accordingly
	 *
	 * @param string $model 
	 * @param string $old_url 
	 * @param string $new_url 
	 * @return void
	 * @author Peter Epp
	 */
	protected function act_on_successful_save($model, $old_url, $new_url) {
		if ($new_url != $old_url) {
			$this->ContentIndex->update_url($old_url,$new_url);
		}
	}
	/**
	 * When a model is successfully deleted delete the associated row from the content index
	 *
	 * @param object $model 
	 * @param string $url 
	 * @return void
	 * @author Peter Epp
	 */
	protected function act_on_successful_delete($model, $url) {
		$this->ContentIndex->delete_by_url($url);
	}
	/**
	 * Provide special rewrite rule for the spider action
	 *
	 * @return array
	 * @author Peter Epp
	 */
	public static function rewrite_rules() {
		return array(
			array(
				'pattern' => '/^search\/spider$/',
				'replacement' => 'page_slug=search&action=spider'
			)
		);
	}
}
?>