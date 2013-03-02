<?php
if ( !defined('AVH_FRAMEWORK') )
	die('You are not allowed to call this page directly.');

class AVH_RPS_EntriesList extends WP_List_Table
{
	/**
	 *
	 * @var AVH_RPS_Core
	 */
	private $_core;
	/**
	 *
	 * @var AVH_Settings_Registry
	 */
	private $_settings;
	/**
	 *
	 * @var AVH_Class_registry
	 */
	private $_classes;
	/**
	 *
	 * @var AVH_RPS_OldRpsDb
	 */
	private $_rpsdb;
	public $messages;
	public $screen;

	function __construct ()
	{

		// Get The Registry
		$this->_settings = AVH_RPS_Settings::getInstance();
		$this->_classes = AVH_RPS_Classes::getInstance();
		// Initialize the plugin
		$this->_core = $this->_classes->load_class('Core', 'plugin', true);
		$this->_rpsdb = $this->_classes->load_class('OldRpsDb', 'plugin', true);

		$this->screen = 'avh_rps_page_avh_rps_entries_';
		$default_status = get_user_option('avhrps_entries_list_last_view');
		if ( empty($default_status) )
			$default_status = 'all';
		$status = isset($_REQUEST['avhrps_entries_list_status']) ? $_REQUEST['avhrps_entries_list_status'] : $default_status;
		if ( !in_array($status, array('all','open','closed','search')) ) {
			$status = 'all';
		}
		if ( $status != $default_status && 'search' != $status ) {
			update_user_meta(get_current_user_id(), 'avhrps_entries_list_last_view', $status);
		}

		$page = $this->get_pagenum();

		parent::__construct(array('plural' => 'entries','singular' => 'entry','ajax' => false));
	}

	function ajax_user_can ()
	{
		return TRUE;
	}

	function prepare_items ()
	{
		global $post_id, $entry_status, $search, $comment_type;

		$entry_status = isset($_REQUEST['entry_status']) ? $_REQUEST['entry_status'] : 'open';
		if ( !in_array($entry_status, array('all','open','closed')) ) {
			$entry_status = 'all';
		}

		$search = ( isset($_REQUEST['s']) ) ? $_REQUEST['s'] : '';

		$orderby = ( isset($_REQUEST['orderby']) ) ? $_REQUEST['orderby'] : 'Competition_ID';
		$order = ( isset($_REQUEST['order']) ) ? $_REQUEST['order'] : '';

		$entries_per_page = $this->get_per_page($entry_status);

		$doing_ajax = defined('DOING_AJAX') && DOING_AJAX;

		if ( isset($_REQUEST['number']) ) {
			$number = (int) $_REQUEST['number'];
		} else {
			$number = $entries_per_page + min(8, $entries_per_page); // Grab a few extra, when changing the 8 changes are need in avh-fdas.ipcachelist.js
		}

		$page = $this->get_pagenum();

		if ( isset($_REQUEST['start']) ) {
			$start = $_REQUEST['start'];
		} else {
			$start = ( $page - 1 ) * $entries_per_page;
		}

		if ( $doing_ajax && isset($_REQUEST['offset']) ) {
			$start += $_REQUEST['offset'];
		}

		$args = array('search' => $search,'offset' => $start,'number' => $number,'orderby' => $orderby,'order' => $order);

		$_entries = $this->_rpsdb->getEntries($args);
		$this->items = array_slice($_entries, 0, $entries_per_page);
		$this->extra_items = array_slice($_entries, $entries_per_page);

		$total_entries = $this->_rpsdb->getEntries(array_merge($args, array('count' => true,'offset' => 0,'number' => 0)));

		$this->set_pagination_args(array('total_items' => $total_entries,'per_page' => $entries_per_page));

		$s = isset($_REQUEST['s']) ? $_REQUEST['s'] : '';
	}

	function get_per_page ($entry_status = 'all')
	{
		$entries_per_page = $this->get_items_per_page('entries_per_page');
		$entries_per_page = apply_filters('entries_per_page', $entries_per_page, $entry_status);
		return $entries_per_page;
	}

	function no_items ()
	{
		_e('No entries.');
	}

	function get_columns ()
	{
		global $status;

		return array('cb' => '<input type="checkbox" />','season' => 'Season','competition' => 'Competition','name' => 'Photographer','title' => 'Title','score' => 'Score','award' => 'Award');
	}

	function get_sortable_columns ()
	{
		return array('');
	}

	function display_tablenav ($which)
	{
		global $status;

		parent::display_tablenav($which);
	}

	function get_views ()
	{
		// global $totals, $entry_status;

		// // $total_ips = $this->_ipcachedb->getIpCache(array ( 'count' => true, 'offset' => 0, 'number' => 0 ));
		// $num_competitions = $this->_rpsdb->countCompetitions();
		// $status_links = array();
		// $stati = array('all' => _nx_noop('All', 'All', 'competitions'),'open' => _n_noop('Open <span class="count">(<span class="open-count">%s</span>)</span>', 'Open <span class="count">(<span class="open-count">%s</span>)</span>'),'closed' => _n_noop('Closed <span class="count">(<span class="closed-count">%s</span>)</span>', 'Closed <span class="count">(<span class="closed-count">%s</span>)</span>'));

		// $link = 'admin.php?page=' . AVH_RPS_Define::MENU_SLUG_COMPETITION;

		// foreach ( $stati as $status => $label ) {
		// $class = ( $status == $competition_status ) ? ' class="current"' : '';

		// if ( !isset($num_competitions->$status) ) {
		// $num_competitions->$status = 10;
		// }
		// $link = add_query_arg('competition_status', $status, $link);
		// // I toyed with this, but decided against it. Leaving it in here in case anyone thinks it is a good idea. ~ Mark if ( !empty( $_REQUEST['s'] ) ) $link = add_query_arg( 's', esc_attr( stripslashes( $_REQUEST['s'] ) ), $link );
		// $status_links[$status] = "<a href='$link'$class>" . sprintf(translate_nooped_plural($label, $num_competitions->$status), number_format_i18n($num_competitions->$status)) . '</a>';
		// }

		// return $status_links;
	}

	function get_bulk_actions ()
	{
		global $competition_status;

		$actions = array();
		$actions['delete'] = __('Delete');

		return $actions;
	}

	function extra_tablenav ($which)
	{
		global $status;

		echo '<div class="alignleft actions">';
		if ( 'top' == $which ) {
			$_seasons = $this->_rpsdb->getSeasonList('DESC');
			$season = isset($_GET['filter-season']) ? $_GET['filter-season'] : 0;
			echo '<select name="filter-season">';
			echo '<option' . selected($season, 0, false) . ' value="0">' . __('Show all seasons') . '</option>';
			foreach ( $_seasons as $_season ) {
				echo '<option' . selected($season, $_season, false) . ' value="' . esc_attr($_season) . '">' . $_season . '</option>';
			}
			submit_button(__('Filter'), 'button', false, false, array('id' => 'entries-query-submit'));
		}
		echo '</div>';
	}

	function current_action ()
	{
		if ( isset($_POST['clear-recent-list']) )
			return 'clear-recent-list';
		if ( isset($_POST['filter-season']) )
			return 'filter-season';

		return parent::current_action();
	}

	function display ()
	{
		extract($this->_args);

		$this->display_tablenav('top');

		echo '<table class="' . implode(' ', $this->get_table_classes()) . '" cellspacing="0">';
		echo '<thead>';
		echo '<tr>';
		$this->print_column_headers();
		echo '</tr>';
		echo '</thead>';

		echo '<tfoot>';
		echo '<tr>';
		$this->print_column_headers(false);
		echo '</tr>';
		echo '</tfoot>';

		echo '<tbody id="the-entries-list" class="list:entry">';
		$this->display_rows_or_placeholder();
		echo '</tbody>';

		echo '<tbody id="the-extra-entries-list" class="list:entry" style="display: none;">';
		$this->items = $this->extra_items;
		$this->display_rows();
		echo '</tbody>';
		echo '</table>';

		$this->display_tablenav('bottom');
	}

	function single_row ($a_competition)
	{
		$competition = $a_competition;
		$status = ( $competition->Closed == "Y" ? '' : 'closed' );
		echo '<tr id="competition-' . $competition->ID . '" class="' . $status . '">';
		echo $this->single_row_columns($competition);
		echo "</tr>";
	}

	function column_cb ($competition)
	{
		echo "<input type='checkbox' name='entries[]' value='$entry->ID' />";
	}

	function column_season ($entry)
	{
		$_competition = $this->_rpsdb->getCompetitionByID2($entry->Competition_ID);
		$unix_date = mysql2date('U', $_competition->Competition_Date);
		$_competition_month = date('n', $unix_date);
		if ( $_competition_month >= $this->_settings->club_season_start_month_num && $_competition_month <= $this->_settings->club_season_end_month_num ) {
			$_season_text = date('Y', $unix_date) . ' - ' . date('Y', strtotime('+1 year', $unix_date));
		} else {
			$_season_text = date('Y', strtotime('-1 year', $unix_date)) . ' - ' . date('Y', $unix_date);
		}
		echo $_season_text;
	}

	function column_competition ($entry)
	{
		global $competition_status;
		$_competition = $this->_rpsdb->getCompetitionByID2($entry->Competition_ID);
		$competition_text = $_competition->Theme . ' - ' . $_competition->Medium . ' - ' . $_competition->Classification;
		echo $competition_text;
	}

	function column_name ($entry)
	{
		$_user = get_user_by('id', $entry->Member_ID);
		echo $_user->first_name . ' ' . $_user->last_name;
	}

	function column_title ($entry)
	{
		echo $entry->Title;
	}

	function column_score ($entry)
	{
		echo $entry->Score;
	}

	function column_award ($entry)
	{
		echo $entry->Award;
	}
}