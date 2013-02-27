<?php
if ( !defined('AVH_FRAMEWORK') )
	die('You are not allowed to call this page directly.');

class AVH_RPS_CompetitionList extends WP_List_Table
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

		$this->screen = 'avh_rps_page_avh_rps_competition_';
		$default_status = get_user_option('avhrps_competition_list_last_view');
		if ( empty($default_status) )
			$default_status = 'all';
		$status = isset($_REQUEST['avhrps_competition_list_status']) ? $_REQUEST['avhrps_competition_list_status'] : $default_status;
		if ( !in_array($status, array('all','open','closed','search')) ) {
			$status = 'all';
		}
		if ( $status != $default_status && 'search' != $status ) {
			update_user_meta(get_current_user_id(), 'avhrps_competition_list_last_view', $status);
		}

		$page = $this->get_pagenum();

		parent::__construct(array('plural' => 'competitions','singular' => 'competition','ajax' => false));
	}

	function ajax_user_can ()
	{
		return TRUE;
	}

	function prepare_items ()
	{
		global $post_id, $competition_status, $search, $comment_type;

		$competition_status = isset($_REQUEST['competition_status']) ? $_REQUEST['competition_status'] : 'open';
		if ( !in_array($competition_status, array('all','open','closed')) ) {
			$competition_status = 'open';
		}

		$search = ( isset($_REQUEST['s']) ) ? $_REQUEST['s'] : '';

		$orderby = ( isset($_REQUEST['orderby']) ) ? $_REQUEST['orderby'] : 'Competition_Date DESC, Class_Code ASC, Medium ASC';
		$order = ( isset($_REQUEST['order']) ) ? $_REQUEST['order'] : '';

		$competitions_per_page = $this->get_per_page($competition_status);

		$doing_ajax = defined('DOING_AJAX') && DOING_AJAX;

		if ( isset($_REQUEST['number']) ) {
			$number = (int) $_REQUEST['number'];
		} else {
			$number = $competitions_per_page + min(8, $competitions_per_page); // Grab a few extra, when changing the 8 changes are need in avh-fdas.ipcachelist.js
		}

		$page = $this->get_pagenum();

		if ( isset($_REQUEST['start']) ) {
			$start = $_REQUEST['start'];
		} else {
			$start = ( $page - 1 ) * $competitions_per_page;
		}

		if ( $doing_ajax && isset($_REQUEST['offset']) ) {
			$start += $_REQUEST['offset'];
		}

		$args = array('status' => $competition_status,'search' => $search,'offset' => $start,'number' => $number,'orderby' => $orderby,'order' => $order);

		$_competitions = $this->_rpsdb->getCompetitions($args);
		$this->items = array_slice($_competitions, 0, $competitions_per_page);
		$this->extra_items = array_slice($_competitions, $competitions_per_page);

		$total_competitions = $this->_rpsdb->getCompetitions(array_merge($args, array('count' => true,'offset' => 0,'number' => 0)));

		$this->set_pagination_args(array('total_items' => $total_competitions,'per_page' => $competitions_per_page));

		$s = isset($_REQUEST['s']) ? $_REQUEST['s'] : '';
	}

	function get_per_page ($competition_status = 'open')
	{
		$competitions_per_page = $this->get_items_per_page('competitions_per_page');
		$competitions_per_page = apply_filters('competitions_per_page', $competitions_per_page, $competition_status);
		return $competitions_per_page;
	}

	function no_items ()
	{
		_e('No competitions.');
	}

	function get_columns ()
	{
		global $status;

		return array('cb' => '<input type="checkbox" />','date' => 'Date','theme' => 'Theme','classification' => 'Classification','medium' => 'Medium','status' => 'Closed','scored' => 'Scored');
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
		global $totals, $competition_status;

		// $total_ips = $this->_ipcachedb->getIpCache(array ( 'count' => true, 'offset' => 0, 'number' => 0 ));
		$num_competitions = $this->_rpsdb->countCompetitions();
		$status_links = array();
		$stati = array('all' => _nx_noop('All', 'All', 'competitions'),'open' => _n_noop('Open <span class="count">(<span class="open-count">%s</span>)</span>', 'Open <span class="count">(<span class="open-count">%s</span>)</span>'),'closed' => _n_noop('Closed <span class="count">(<span class="closed-count">%s</span>)</span>', 'Closed <span class="count">(<span class="closed-count">%s</span>)</span>'));

		$link = 'admin.php?page=' . AVH_RPS_Define::MENU_SLUG_COMPETITION;

		foreach ( $stati as $status => $label ) {
			$class = ( $status == $competition_status ) ? ' class="current"' : '';

			if ( !isset($num_competitions->$status) ) {
				$num_competitions->$status = 10;
			}
			$link = add_query_arg('competition_status', $status, $link);
			// I toyed with this, but decided against it. Leaving it in here in case anyone thinks it is a good idea. ~ Mark if ( !empty( $_REQUEST['s'] ) ) $link = add_query_arg( 's', esc_attr( stripslashes( $_REQUEST['s'] ) ), $link );
			$status_links[$status] = "<a href='$link'$class>" . sprintf(translate_nooped_plural($label, $num_competitions->$status), number_format_i18n($num_competitions->$status)) . '</a>';
		}

		return $status_links;
	}

	function get_bulk_actions ()
	{
		global $competition_status;

		$actions = array();

		// if (in_array($competition_status, array ( 'all', 'ham' ))) {
		// $actions['spam'] = __('Mark as spam');
		// }
		// if (in_array($competition_status, array ( 'all', 'spam' ))) {
		// $actions['ham'] = __('Mark as ham');
		// }
		$actions['delete'] = __('Delete');
		// $actions['blacklist'] = __('Blacklist');

		return $actions;
	}

	function extra_tablenav ($which)
	{
		global $status;

		if ( 'recently_activated' == $status ) {
			?>
<div class="alignleft actions">
				<?php
			submit_button(__('Clear List'), 'secondary', 'clear-recent-list', false);
			?>
			</div>
<?php
		}
	}

	function current_action ()
	{
		if ( isset($_POST['clear-recent-list']) )
			return 'clear-recent-list';

		return parent::current_action();
	}

	function display ()
	{
		extract($this->_args);

		wp_nonce_field("fetch-list-" . get_class($this), '_ajax_fetch_list_nonce', FALSE);

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

		echo '<tbody id="the-competition-list" class="list:competition">';
		$this->display_rows_or_placeholder();
		echo '</tbody>';

		echo '<tbody id="the-extra-competition-list" class="list:competition" style="display: none;">';
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
		echo "<input type='checkbox' name='competitions[]' value='$competition->ID' />";
	}

	function column_date ($competition)
	{
		global $competition_status;

		$date_text = mysql2date(get_option('date_format'), $competition->Competition_Date);
		echo $date_text;

		$user_ID = get_current_user_id();
		$url = admin_url('admin.php') . '?';

		$queryReferer = array ('page' => AVH_RPS_Define::MENU_SLUG_COMPETITION);
		$wp_http_referer='/admin.php?' . http_build_query($queryReferer, '', '&');

		$nonceDelete = wp_create_nonce('bulk-competitions');
		$queryDelete = array('page' => AVH_RPS_Define::MENU_SLUG_COMPETITION,'competition' => $competition->ID,'action' => 'delete','_wpnonce' => $nonceDelete);
		$urlDelete = $url . http_build_query($queryDelete, '', '&');

		$queryEdit = array('page' => AVH_RPS_Define::MENU_SLUG_COMPETITION,'competition' => $competition->ID,'action' => 'edit','wp_http_referer' =>$wp_http_referer);
		$urlEdit = $url . http_build_query($queryEdit, '', '&');

		$actions = array();
		$actions['delete'] = '<a ' . AVH_Common::attributes(array('href' => $urlDelete,'class' => 'delete','title' => 'Delete this competition')) . '>' . 'Delete' . '</a>';
		$actions['edit'] = '<a ' . AVH_Common::attributes(array('href' => $urlEdit,'title' => 'Edit this competition')) . '>' . 'Edit' . '</a>';

		echo '<div class="row-actions">';
		$sep = '';
		foreach ( $actions as $action => $link ) {
			echo "<span class='set_$action'>$sep$link</span>";
			$sep = ' | ';
		}
		echo '</div>';
	}

	function column_theme ($competition)
	{
		echo $competition->Theme;
	}

	function column_classification ($competition)
	{
		echo $competition->Classification;
	}

	function column_medium ($competition)
	{
		echo $competition->Medium;
	}

	function column_status ($competition)
	{
		echo $competition->Closed;
	}

	function column_scored ($competition)
	{
		echo $competition->Scored;
	}
}