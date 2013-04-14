<?php

final class AVH_RPS_Admin
{
	/* @var $classForm AVH_Form */
	/**
	 * Message management
	 */
	private $_message = '';
	private $_status = '';
	/**
	 *
	 * @var AVH_RPS_Core
	 */
	private $_core;
	/**
	 *
	 * @var AVH_RPS_Settings
	 */
	private $_settings;
	/**
	 *
	 * @var AVH_RPS_Classes
	 */
	private $_classes;
	/**
	 *
	 * @var AVH_RPS_OldRPSDb
	 */
	private $_rpsdb;
	/**
	 *
	 * @var AVH_RPS_CompetitionList
	 */
	private $_competition_list;
	/**
	 *
	 * @var AVH_RPS_EntriesList
	 */
	private $_entries_list;
	private $_add_disabled_notice = false;
	private $_hooks = array();
	private $_referer;

	/**
	 * PHP5 Constructor
	 *
	 * @return unknown_type
	 */
	public function __construct ()
	{
		// The Settings Registery
		$this->_settings = AVH_RPS_Settings::getInstance();

		// The Classes Registery
		$this->_classes = AVH_RPS_Classes::getInstance();
		add_action('init', array($this,'handleActionInit'));
	}

	public function handleActionInit ()
	{
		// Loads the CORE class
		$this->_core = $this->_classes->load_class('Core', 'plugin', true);
		$this->_rpsdb = $this->_classes->load_class('OldRpsDb', 'plugin', true);

		// Admin URL and Pagination
		$this->_core->admin_base_url = $this->_settings->siteurl . '/wp-admin/admin.php?page=';
		if ( isset($_GET['pagination']) ) {
			$this->_core->actual_page = (int) $_GET['pagination'];
		}

		$this->actionInit_Roles();
		$this->actionInit_UserFields();

		// Admin menu
		add_action('admin_menu', array($this,'actionAdminMenu'));

		return;
	}

	/**
	 * Setup Roles
	 *
	 * @WordPress Action init
	 */
	public function actionInit_Roles ()
	{
		// Get the administrator role.
		$role = get_role('administrator');

		// If the administrator role exists, add required capabilities for the plugin.
		if ( !empty($role) ) {

			// Role management capabilities.
			$role->add_cap('rps_edit_competition_classification');
			$role->add_cap('rps_edit_competitions');
			$role->add_cap('rps_edit_entries');
		}
	}

	public function actionInit_UserFields ()
	{
		add_action('edit_user_profile', array($this,'actionUser_Profile'));
		add_action('show_user_profile', array($this,'actionUser_Profile'));
		add_action('personal_options_update', array($this,'actionProfile_Update_Save'));
		add_action('edit_user_profile_update', array($this,'actionProfile_Update_Save'));
	}

	/**
	 * Add the Tools and Options to the Management and Options page repectively
	 *
	 * @WordPress Action admin_menu
	 */
	public function actionAdminMenu ()
	{
		wp_register_style('avhrps-admin-css', $this->_settings->getSetting('plugin_url') . '/css/avh-rps.admin.css', array('wp-admin'), AVH_RPS_Define::PLUGIN_VERSION, 'screen');
		wp_register_style('avhrps-jquery-css', $this->_settings->getSetting('plugin_url') . '/css/smoothness/jquery-ui-1.8.22.custom.css', array('wp-admin'), '1.8.22', 'screen');

		add_menu_page('All Competitions', 'Competitions', 'rps_edit_competitions', AVH_RPS_Define::MENU_SLUG_COMPETITION, array($this,'menuCompetition'), '', AVH_RPS_Define::MENU_POSITION_COMPETITION);

		$this->_hooks['avhrps_menu_competition'] = add_submenu_page(AVH_RPS_Define::MENU_SLUG_COMPETITION, 'All Competitions', 'All Competitions', 'rps_edit_competitions', AVH_RPS_Define::MENU_SLUG_COMPETITION, array($this,'menuCompetition'));
		$this->_hooks['avhrps_menu_competition_add'] = add_submenu_page(AVH_RPS_Define::MENU_SLUG_COMPETITION, 'Add Competition', 'Add Competition', 'rps_edit_competitions', AVH_RPS_Define::MENU_SLUG_COMPETITION_ADD, array($this,'menuCompetitionAdd'));

		add_action('load-' . $this->_hooks['avhrps_menu_competition'], array($this,'actionLoadPagehookCompetition'));
		add_action('load-' . $this->_hooks['avhrps_menu_competition_add'], array($this,'actionLoadPagehookCompetitionAdd'));

		add_menu_page('All Entries', 'Entries', 'rps_edit_entries', AVH_RPS_Define::MENU_SLUG_ENTRIES, array($this,'menuEntries'), '', AVH_RPS_Define::MENU_POSITION_ENTRIES);
		$this->_hooks['avhrps_menu_entries'] = add_submenu_page(AVH_RPS_Define::MENU_SLUG_ENTRIES, 'All Entries', 'All Entries', 'rps_edit_entries', AVH_RPS_Define::MENU_SLUG_ENTRIES, array($this,'menuEntries'));
		add_action('load-' . $this->_hooks['avhrps_menu_entries'], array($this,'actionLoadPagehookEntries'));
	}

	public function actionLoadPagehookCompetition ()
	{
		global $current_screen;

		$this->_competition_list = $this->_classes->load_class('CompetitionList', 'plugin', true);
		$this->_handleRequestCompetition();

		add_filter('screen_layout_columns', array($this,'filterScreenLayoutColumns'), 10, 2);
		// WordPress core Styles and Scripts
		wp_enqueue_script('jquery-ui-datepicker');
		wp_enqueue_script('common');
		wp_enqueue_script('wp-lists');
		wp_enqueue_script('postbox');
		wp_enqueue_style('css/dashboard');
		// Plugin Style and Scripts
		// wp_enqueue_script('avhrps-competition-js');

		wp_enqueue_style('avhrps-admin-css');
		wp_enqueue_style('avhrps-jquery-css');

		// add_screen_option('per_page', array ( 'label' => _x('IP\'s', 'ip\'s per page (screen options)'), 'default' => 20, 'option' => 'ipcachelog_per_page' ));
		// add_contextual_help($current_screen, '<p>' . __('You can manage IP\'s added to the IP cache Log. This screen is customizable in the same ways as other management screens, and you can act on IP\'s using the on-hover action links or the Bulk Actions.') . '</p>');
	}

	/**
	 * Handle the HTTP Request before the page of the menu Competition is displayed.
	 * This is needed for the redirects.
	 */
	private function _handleRequestCompetition ()
	{
		if ( isset($_REQUEST['wp_http_referer']) ) {
			$redirect = remove_query_arg(array('wp_http_referer','updated','delete_count'), stripslashes($_REQUEST['wp_http_referer']));
		} else {
			$redirect = admin_url('admin.php') . '?page=' . AVH_RPS_Define::MENU_SLUG_COMPETITION;
		}

		$doAction = $this->_competition_list->current_action();
		switch ( $doAction )
		{
			case 'delete':
			case 'open':
			case 'close':
				check_admin_referer('bulk-competitions');
				if ( empty($_REQUEST['competitions']) && empty($_REQUEST['competition']) ) {
					wp_redirect($redirect);
					exit();
				}
				break;

			case 'edit':
				if ( empty($_REQUEST['competition']) ) {
					wp_redirect($redirect);
					exit();
				}
				break;

			case 'dodelete':
				check_admin_referer('delete-competitions');
				if ( empty($_REQUEST['competitions']) ) {
					wp_redirect($redirect);
					exit();
				}
				$competitionIds = $_REQUEST['competitions'];

				$deleteCount = 0;

				foreach ( (array) $competitionIds as $id ) {
					$id = (int) $id;
					$this->_rpsdb->deleteCompetition($id);
					++$deleteCount;
				}
				$redirect = add_query_arg(array('deleteCount' => $deleteCount,'update' => 'del_many'), $redirect);
				wp_redirect($redirect);
				break;

			case 'doopen':
				check_admin_referer('open-competitions');
				if ( empty($_REQUEST['competitions']) ) {
					wp_redirect($redirect);
					exit();
				}
				$competitionIds = $_REQUEST['competitions'];
				$count = 0;

				foreach ( (array) $competitionIds as $id ) {
					$data['ID'] = (int) $id;
					$data['Closed'] = 'N';
					$this->_rpsdb->insertCompetition($data);
					++$count;
				}
				$redirect = add_query_arg(array('count' => $count,'update' => 'open_many'), $redirect);
				wp_redirect($redirect);
				break;

			case 'doclose':
				check_admin_referer('close-competitions');
				if ( empty($_REQUEST['competitions']) ) {
					wp_redirect($redirect);
					exit();
				}
				$competitionIds = $_REQUEST['competitions'];
				$count = 0;

				foreach ( (array) $competitionIds as $id ) {
					$data['ID'] = (int) $id;
					$data['Closed'] = 'Y';
					$this->_rpsdb->insertCompetition($data);
					++$count;
				}
				$redirect = add_query_arg(array('count' => $count,'update' => 'close_many'), $redirect);
				wp_redirect($redirect);
				break;

			default:
				if ( !empty($_GET['_wp_http_referer']) ) {
					wp_redirect(remove_query_arg(array('_wp_http_referer','_wpnonce'), stripslashes($_SERVER['REQUEST_URI'])));
					exit();
				}
				$pagenum = $this->_competition_list->get_pagenum();
				$this->_competition_list->prepare_items();
				$total_pages = $this->_competition_list->get_pagination_arg('total_pages');
				if ( $pagenum > $total_pages && $total_pages > 0 ) {
					wp_redirect(add_query_arg('paged', $total_pages));
					exit();
				}
				break;
		}
	}

	/**
	 * Display the page for the menu Competition
	 */
	public function menuCompetition ()
	{
		$doAction = $this->_competition_list->current_action();
		switch ( $doAction )
		{
			case 'delete':
				$this->_displayPageCompetitionDelete();
				break;

			case 'edit':
				$this->_displayPageCompetitionEdit();
				break;

			case 'open':
				$this->_displayPageCompetitionOpenClose('open');
				break;

			case 'close':
				$this->_displayPageCompetitionOpenClose('close');
				break;

			default:
				$this->_displayPageCompetitionList();
				break;
		}
	}

	/**
	 * Display the page to confirm the deletion of the selected competitions.
	 *
	 * @param string $redirect
	 * @param string $referer
	 *
	 */
	private function _displayPageCompetitionDelete ()
	{
		global $wpdb;

		if ( empty($_REQUEST['competitions']) ) {
			$competitionIdsArray = array(intval($_REQUEST['competition']));
		} else {
			$competitionIdsArray = (array) $_REQUEST['competitions'];
		}

		$classForm = $this->_classes->load_class('Form', 'system', false);

		$this->admin_header('Delete Competitions');
		echo $classForm->open('', array('method' => 'post','id' => 'updatecompetitions','name' => 'updatecompetitions'));
		wp_nonce_field('delete-competitions');
		echo $this->_referer;

		echo '<p>' . _n('You have specified this competition for deletion:', 'You have specified these competitions for deletion:', count($competitionIdsArray)) . '</p>';

		$goDelete = 0;
		foreach ( $competitionIdsArray as $competitionID ) {

			$sqlWhere = $wpdb->prepare('Competition_ID=%d', $competitionID);
			$entries = $this->_rpsdb->getEntries(array('where' => $sqlWhere,'count' => TRUE));
			$sqlWhere = $wpdb->prepare('ID=%d', $competitionID);
			$competition = $this->_rpsdb->getCompetitions(array('where' => $sqlWhere));
			$competition = $competition[0];
			if ( $entries !== "0" ) {
				echo "<li>" . sprintf(__('ID #%1s: %2s - %3s - %4s -%5s <strong>This competition will not be deleted. It still has %6s entries.</strong>'), $competitionID, mysql2date(get_option('date_format'), $competition->Competition_Date), $competition->Theme, $competition->Classification, $competition->Medium, $entries) . "</li>\n";
			} else {
				echo "<li><input type=\"hidden\" name=\"competitions[]\" value=\"" . esc_attr($competitionID) . "\" />" . sprintf(__('ID #%1s: %2s - %3s - %4s - %5s'), $competitionID, mysql2date(get_option('date_format'), $competition->Competition_Date), $competition->Theme, $competition->Classification, $competition->Medium) . "</li>\n";
				$goDelete++;
			}
		}
		if ( $goDelete ) {
			echo $classForm->hidden('action', 'dodelete');
			echo $classForm->submit('delete', 'Confirm Deletion', array('class' => 'button-secondary delete'));
		} else {
			echo '<p>There are no valid competitions to delete</p>';
		}
		echo $classForm->close();
		$this->admin_footer();
	}

	private function _displayPageCompetitionEdit ()
	{
		global $wpdb;

		// @var $classForm AVH_Form
		$classForm = $this->_classes->load_class('Form', 'system', false);
		$classForm->setOption_name('competition-edit');

		if ( isset($_POST['update']) ) {
			$this->_updateCompetition();
		}
		$vars = ( array('action','redirect','competition','wp_http_referer') );
		for ( $i = 0; $i < count($vars); $i += 1 ) {
			$var = $vars[$i];
			if ( empty($_POST[$var]) ) {
				if ( empty($_GET[$var]) )
					$$var = '';
				else
					$$var = $_GET[$var];
			} else {
				$$var = $_POST[$var];
			}
		}

		$wp_http_referer = remove_query_arg(array('update'), stripslashes($wp_http_referer));

		$competition = $this->_rpsdb->getCompetitionByID2($_REQUEST['competition']);

		$formOptions['date'] = mysql2date('Y-m-d', $competition->Competition_Date);
		$formOptions['close-date'] = mysql2date('Y-m-d', $competition->Close_Date);
		$formOptions['close-time'] = mysql2date('H:i:II', $competition->Close_Date);

		$this->admin_header('Edit Competition');

		if ( isset($_POST['update']) ) {
			echo '<div id="message" class="updated">';
			echo '<p><strong>Competition updated.</strong></p>';
			if ( $wp_http_referer ) {
				echo '<p><a href="' . esc_url($wp_http_referer) . '">&larr; Back to Competitions</a></p>';
			}
			echo '</div>';
		}

		$queryEdit = array('page' => AVH_RPS_Define::MENU_SLUG_COMPETITION);
		echo $classForm->open(admin_url('admin.php') . '?' . http_build_query($queryEdit, '', '&'), array('method' => 'post','id' => 'rps-competitionedit'));
		echo $classForm->open_table();
		echo $classForm->text('Date', '', 'date', $formOptions['date']);
		echo $classForm->text('Theme', '', 'theme', $competition->Theme, array('maxlength' => '32'));
		echo $classForm->text('Closing Date', '', 'close-date', $formOptions['close-date']);

		for ( $hour = 0; $hour <= 23; $hour++ ) {
			$time_val = sprintf("%02d:00:00", $hour);
			$time_text = date("g:i a", strtotime($time_val));
			$time[$time_val] = $time_text;
		}
		echo $classForm->select('Closing Time', '', 'close-time', $time, $formOptions['close-time']);

		// @format_off
		$_medium = array ( 'medium_bwd'		=> 'B&W Digital',
							'medium_cd'		=> 'Color Digital',
							'medium_bwp'	=> 'B&W Print',
							'medium_cp'		=> 'Color Print'
					);
		$selectedMedium=array_search($competition->Medium, $_medium);
		// @format_on
		echo $classForm->select('Medium', '', 'medium', $_medium, $selectedMedium);

		// @format_off
		$_classification = array ( 'class_b' => 'Beginner',
									'class_a' => 'Advanced',
									'class_s' => 'Salon',
			);
		// @format_on
		$selectedClassification = array_search($competition->Classification, $_classification);
		echo $classForm->select('Classification', '', 'classification', $_classification, $selectedClassification);

		$_max_entries = array('1' => '1','2' => '2','3' => '3','4' => '4','5' => '5','6' => '6','7' => '7','8' => '8','9' => '9','10' => '10');
		echo $classForm->select('Max Entries', '', 'max_entries', $_max_entries, $competition->Max_Entries);

		$_judges = array('1' => '1','2' => '2','3' => '3','4' => '4','5' => '5');
		echo $classForm->select('No. Judges', '', 'judges', $_judges, $competition->Num_Judges);

		$_special_event = array('special_event' => array('text' => '','checked' => $competition->Special_Event));
		echo $classForm->checkboxes('Special Event', '', key($_special_event), $_special_event);

		$_closed = array('closed' => array('text' => '','checked' => ( $competition->Closed == 'Y' ? TRUE : FALSE )));
		echo $classForm->checkboxes('Closed', '', key($_closed), $_closed);

		$_scored = array('scored' => array('text' => '','checked' => ( $competition->Scored == 'Y' ? TRUE : FALSE )));
		echo $classForm->checkboxes('Scored', '', key($_scored), $_scored);

		echo $classForm->close_table();
		echo $classForm->submit('submit', 'Update Competition', array('class' => 'button-primary'));
		if ( $wp_http_referer ) {
			echo $classForm->hidden('wp_http_referer', esc_url($wp_http_referer));
		}
		echo $classForm->hidden('competition', $competition->ID);
		echo $classForm->hidden('update', true);
		echo $classForm->hidden('action', 'edit');
		$classForm->setNonce_action($competition->ID);
		echo $classForm->nonce_field();
		echo $classForm->close();
		echo '<script type="text/javascript">' . "\n";
		echo 'jQuery(function($) {' . "\n";
		echo ' $.datepicker.setDefaults({' . "\n";
		echo '   dateFormat: \'yy-mm-dd\', ' . "\n";
		echo '   showButtonPanel: true, ' . "\n";
		echo '   buttonImageOnly: true, ' . "\n";
		echo '   buttonImage: "' . $this->_settings->getSetting('plugin_url') . '/images/calendar.png", ' . "\n";
		echo '   showOn: "both"' . "\n";
		echo ' });' . "\n";
		echo '	$( "#date" ).datepicker();' . "\n";
		echo '	$( "#close-date" ).datepicker();' . "\n";
		echo '});', "\n";
		echo "</script>";
		$this->admin_footer();
	}

	/**
	 * Display the page to confirm the deletion of the selected competitions.
	 *
	 * @param string $redirect
	 * @param string $referer
	 *
	 */
	private function _displayPageCompetitionOpenClose ($action)
	{
		global $wpdb;

		if ( $action == 'open' ) {
			$title = 'Open Competitions';
			$action_verb = 'openend';
		}
		if ( $action == 'close ' ) {
			$title = 'Close Competitions';
			$action_verb = 'closed';
		}

		if ( empty($_REQUEST['competitions']) ) {
			$competitionIdsArray = array(intval($_REQUEST['competition']));
		} else {
			$competitionIdsArray = (array) $_REQUEST['competitions'];
		}

		$classForm = $this->_classes->load_class('Form', 'system', false);

		$this->admin_header($title);
		echo $classForm->open('', array('method' => 'post','id' => 'updatecompetitions','name' => 'updatecompetitions'));
		wp_nonce_field($action . '-competitions');
		echo $this->_referer;

		echo '<p>' . _n('You have specified this competition to be ' . $action_verb . ':', 'You have specified these competitions to be ' . $action_verb . '::', count($competitionIdsArray)) . '</p>';

		foreach ( $competitionIdsArray as $competitionID ) {
			$sqlWhere = $wpdb->prepare('ID=%d', $competitionID);
			$competition = $this->_rpsdb->getCompetitions(array('where' => $sqlWhere));
			$competition = $competition[0];
			echo "<li><input type=\"hidden\" name=\"competitions[]\" value=\"" . esc_attr($competitionID) . "\" />" . sprintf(__('ID #%1s: %2s - %3s - %4s - %5s'), $competitionID, mysql2date(get_option('date_format'), $competition->Competition_Date), $competition->Theme, $competition->Classification, $competition->Medium) . "</li>\n";
		}

		echo $classForm->hidden('action', 'do' . $action);
		echo $classForm->submit('openclose', 'Confirm', array('class' => 'button-secondary'));

		echo $classForm->close();
		$this->admin_footer();
	}

	/**
	 * Display the competion in a list
	 */
	private function _displayPageCompetitionList ()
	{
		global $screen_layout_columns;

		$messages = array();
		if ( isset($_GET['update']) ) {
			switch ( $_GET['update'] )
			{
				case 'del':
				case 'del_many':
					$deleteCount = isset($_GET['deleteCount']) ? (int) $_GET['deleteCount'] : 0;
					$messages[] = '<div id="message" class="updated"><p>' . sprintf(_n('Competition deleted.', '%s competitions deleted.', $deleteCount), number_format_i18n($deleteCount)) . '</p></div>';
					break;
				case 'open_many':
					$openCount = isset($_GET['count']) ? (int) $_GET['count'] : 0;
					$messages[] = '<div id="message" class="updated"><p>' . sprintf(_n('Competition opened.', '%s competitions opened.', $openCount), number_format_i18n($openCount)) . '</p></div>';
					break;
				case 'close_many':
					$closeCount = isset($_GET['count']) ? (int) $_GET['count'] : 0;
					$messages[] = '<div id="message" class="updated"><p>' . sprintf(_n('Competition closed.', '%s competitions closed.', $closeCount), number_format_i18n($closeCount)) . '</p></div>';
					break;
			}
		}

		if ( !empty($messages) ) {
			foreach ( $messages as $msg )
				echo $msg;
		}

		echo '<div class="wrap avhrps-wrap">';
		echo $this->_displayIcon('index');
		echo '<h2>Competitions: ' . __('All Competitions', 'avh-rps');

		if ( isset($_REQUEST['s']) && $_REQUEST['s'] ) {
			printf('<span class="subtitle">' . sprintf(__('Search results for &#8220;%s&#8221;'), wp_html_excerpt(esc_html(stripslashes($_REQUEST['s'])), 50)) . '</span>');
		}
		echo '</h2>';

		$this->_competition_list->views();
		echo '<form id="rps-competition-form" action="" method="get">';
		echo '<input type="hidden" name="page" value="' . AVH_RPS_Define::MENU_SLUG_COMPETITION . '">';

		echo '<input type="hidden" name="_total" value="' . esc_attr($this->_competition_list->get_pagination_arg('total_items')) . '" />';
		echo '<input type="hidden" name="_per_page" value="' . esc_attr($this->_competition_list->get_pagination_arg('per_page')) . '" />';
		echo '<input type="hidden" name="_page" value="' . esc_attr($this->_competition_list->get_pagination_arg('page')) . '" />';

		if ( isset($_REQUEST['paged']) ) {
			echo '<input type="hidden" name="paged"	value="' . esc_attr(absint($_REQUEST['paged'])) . '" />';
		}
		// $this->_competition_list->search_box(__('Find IP', 'avh-rps'), 'find_ip');
		$this->_competition_list->display();
		echo '</form>';

		echo '<div id="ajax-response"></div>';
		$this->_printAdminFooter();
		echo '</div>';
	}

	public function actionLoadPagehookCompetitionAdd ()
	{
		global $current_screen;

		$this->_competition_list = $this->_classes->load_class('CompetitionList', 'plugin', true);
		add_filter('screen_layout_columns', array($this,'filterScreenLayoutColumns'), 10, 2);
		// WordPress core Styles and Scripts
		wp_enqueue_script('common');
		wp_enqueue_script('jquery-ui-datepicker');
		// Plugin Style and Scripts
		// wp_enqueue_script('avhrps-competition-js');

		wp_enqueue_style('avhrps-admin-css');
		wp_enqueue_style('avhrps-jquery-css');
	}

	public function menuCompetitionAdd ()
	{
		$option_name = 'competition_add';
		// @var $classForm AVH_Form
		$classForm = $this->_classes->load_class('Form', 'system', false);
		$classForm->setOption_name('competition_add');

		// @format_off
		$formDefaultOptions = array (
				'date' => '',
				'theme' => '',
				'medium_bwd' => TRUE,
				'medium_cd' => TRUE,
				'medium_bwp' => TRUE,
				'medium_cp' => TRUE,
				'class_b' => TRUE,
				'class_a' => TRUE,
				'class_s' => TRUE,
				'max_entries' => '2',
				'judges' => '1',
				'special_event' => FALSE
			);
		// @format_on
		$formOptions = $formDefaultOptions;
		if ( isset($_POST['action']) ) {
			switch ( $_POST['action'] )
			{
				case 'add':
					$classForm->setNonce_action(get_current_user_id());
					check_admin_referer($classForm->getNonce_action());
					$formNewOptions = $formDefaultOptions;
					$formOptions = $_POST[$classForm->getOption_name()];

					$mediumArray = array();
					$classArray = array();
					$errorMsgArray = array();
					foreach ( $formDefaultOptions as $optionKey => $optionValue ) {

						// Every field in a form is set except unchecked checkboxes. Set an unchecked checkbox to FALSE.
						$newval = ( isset($formOptions[$optionKey]) ? stripslashes($formOptions[$optionKey]) : FALSE );
						$current_value = $formDefaultOptions[$optionKey];
						switch ( $optionKey )
						{
							case 'date':
								// Validate
								break;

							case 'theme':
								// Validate
								break;
						}
						if ( substr($optionKey, 0, 7) == 'medium_' ) {
							$formNewOptions[$optionKey] = (bool) $newval;
							if ( $formNewOptions[$optionKey] ) {
								$mediumArray[] = $optionKey;
								continue;
							}
						}
						if ( substr($optionKey, 0, 6) == 'class_' ) {
							$formNewOptions[$optionKey] = (bool) $newval;
							if ( $formNewOptions[$optionKey] ) {
								$classArray[] = $optionKey;
								continue;
							}
						}
						$formNewOptions[$optionKey] = $newval;
					}

					if ( empty($mediumArray) ) {
						$errorMsgArray[] = 'No medium selected. At least one medium needs to be selected';
					}

					if ( empty($classArray) ) {
						$errorMsgArray[] = 'No classification selected. At least one classification needs to be selected';
					}

					if ( empty($errorMsgArray) ) {
						$this->_message = 'Competition Added';
						$this->_status = 'updated';

						// @format_off
				// @TODO: This is needed because of the old program, someday it needs to be cleaned up.
				$medium_convert = array(
							'medium_bwd'	=> 'B&W Digital',
							'medium_cd'		=> 'Color Digital',
							'medium_bwp'	=> 'B&W Print',
							'medium_cp'		=> 'Color Print'
					);

				$classification_convert = array (
						'class_b' => 'Beginner',
						'class_a' => 'Advanced',
						'class_s' => 'Salon'
				);
				// @format_on
						$data['Competition_Date'] = $formNewOptions['date'];
						$data['Theme'] = $formNewOptions['theme'];
						$data['Max_Entries'] = $formNewOptions['max_entries'];
						$data['Num_Judges'] = $formNewOptions['judges'];
						$data['Special_Event'] = ( $formNewOptions['special_event'] ? 'Y' : 'N' );
						foreach ( $mediumArray as $medium ) {
							$data['Medium'] = $medium_convert[$medium];
							foreach ( $classArray as $classification ) {
								$data['Classification'] = $classification_convert[$classification];
								$competition_ID = $this->_rpsdb->insertCompetition($data);
								if ( is_wp_error($competition_ID) ) {
									wp_die($competition_ID);
								}
							}
						}
					} else {
						$this->_message = $errorMsgArray;
						$this->_status = 'error';
					}
					$this->_displayMessage();
					$formOptions = $formNewOptions;
					break;
			}
		}

		$this->admin_header('Add Competition');

		echo $classForm->open(admin_url('admin.php') . '?page=' . AVH_RPS_Define::MENU_SLUG_COMPETITION_ADD, array('method' => 'post','id' => 'rps-competitionadd'));
		echo $classForm->open_table();
		echo $classForm->text('Date', '', 'date', $formOptions['date']);
		echo $classForm->text('Theme', '', 'theme', $formOptions['theme'], array('maxlength' => '32'));

		// @format_off
		$_medium = array ( 'medium_bwd' => array ( 'text' => 'B&W Digital', 'checked' => $formOptions['medium_bwd'] ),
							'medium_cd' => array ( 'text' => 'Color Digital', 'checked' => $formOptions['medium_cd'] ),
							'medium_bwp' => array ( 'text' => 'B&W Print', 'checked' => $formOptions['medium_bwp'] ),
							'medium_cp' => array ( 'text' => 'Color Digital', 'checked' => $formOptions['medium_cp'] )
						);
		// @format_on
		echo $classForm->checkboxes('Medium', '', key($_medium), $_medium);
		unset($_medium);

		// @format_off
		$_classification = array ( 'class_b' => array ( 'text' => 'Beginner', 'checked' => $formOptions['class_b'] ),
									'class_a' => array ( 'text' => 'Advanced', 'checked' => $formOptions['class_a'] ),
									'class_s' => array ( 'text' => 'Salon', 'checked' => $formOptions['class_s'] )
							);
		// @format_on
		echo $classForm->checkboxes('Classification', '', key($_classification), $_classification);
		unset($_classification);

		$_max_entries = array('1' => '1','2' => '2','3' => '3','4' => '4','5' => '5','6' => '6','7' => '7','8' => '8','9' => '9','10' => '10');
		echo $classForm->select('Max Entries', '', 'max_entries', $_max_entries, $formOptions['max_entries']);
		unset($_max_entries);

		$_judges = array('1' => '1','2' => '2','3' => '3','4' => '4','5' => '5');
		echo $classForm->select('No. Judges', '', 'judges', $_judges, $formOptions['judges']);
		unset($_judges);

		$_special_event = array('special_event' => array('text' => '','checked' => $formOptions['special_event']));
		echo $classForm->checkboxes('Special Event', '', key($_special_event), $_special_event);
		unset($_special_event);

		echo $classForm->close_table();
		echo $classForm->submit('submit', 'Add Competition', array('class' => 'button-primary'));
		echo $classForm->hidden('action', 'add');
		$classForm->setNonce_action(get_current_user_id());
		echo $classForm->nonce_field();
		echo $classForm->close();
		echo '<script type="text/javascript">' . "\n";
		echo 'jQuery(function($) {' . "\n";
		echo '	$( "#date" ).datepicker({ dateFormat: \'yy-mm-dd\', showButtonPanel: true });' . "\n";
		echo '});', "\n";
		echo "</script>";
		$this->admin_footer();
	}

	public function actionLoadPagehookEntries ()
	{
		global $current_screen;

		$this->_entries_list = $this->_classes->load_class('EntriesList', 'plugin', true);
		$this->_handleRequestEntries();

		add_filter('screen_layout_columns', array($this,'filterScreenLayoutColumns'), 10, 2);
		// WordPress core Styles and Scripts
		wp_enqueue_script('jquery-ui-datepicker');
		wp_enqueue_script('common');
		wp_enqueue_script('wp-lists');
		wp_enqueue_script('postbox');
		wp_enqueue_style('css/dashboard');
		// Plugin Style and Scripts
		// wp_enqueue_script('avhrps-competition-js');

		wp_enqueue_style('avhrps-admin-css');
		wp_enqueue_style('avhrps-jquery-css');
	}

	/**
	 * Handle the HTTP Request before the page of the menu Entries is displayed.
	 * This is needed for the redirects.
	 */
	private function _handleRequestEntries ()
	{

		if ( isset($_REQUEST['wp_http_referer']) ) {
			$redirect = remove_query_arg(array('wp_http_referer','updated','delete_count'), stripslashes($_REQUEST['wp_http_referer']));
		} else {
			$redirect = admin_url('admin.php') . '?page=' . AVH_RPS_Define::MENU_SLUG_ENTRIES;
		}

		$doAction = $this->_entries_list->current_action();
		switch ( $doAction )
		{
			case 'delete':
				check_admin_referer('bulk-entries');
				if ( empty($_REQUEST['entries']) && empty($_REQUEST['entries']) ) {
					wp_redirect($redirect);
					exit();
				}
				break;

			case 'edit':
				if ( empty($_REQUEST['entry']) ) {
					wp_redirect($redirect);
					exit();
				}
				break;
			case 'dodelete':
				check_admin_referer('delete-entries');
				if ( empty($_REQUEST['entries']) ) {
					wp_redirect($redirect);
					exit();
				}
				$entryIds = $_REQUEST['entries'];

				$deleteCount = 0;

				foreach ( (array) $entryIds as $id ) {
					$id = (int) $id;
					$this->_rpsdb->deleteEntry($id);
					++$deleteCount;
				}
				$redirect = add_query_arg(array('deleteCount' => $deleteCount,'update' => 'del_many'), $redirect);
				wp_redirect($redirect);
				break;

			default:
				if ( !empty($_GET['_wp_http_referer']) ) {
					wp_redirect(remove_query_arg(array('_wp_http_referer','_wpnonce'), stripslashes($_SERVER['REQUEST_URI'])));
					exit();
				}
				$pagenum = $this->_entries_list->get_pagenum();
				$this->_entries_list->prepare_items();
				$total_pages = $this->_entries_list->get_pagination_arg('total_pages');
				if ( $pagenum > $total_pages && $total_pages > 0 ) {
					wp_redirect(add_query_arg('paged', $total_pages));
					exit();
				}
				break;
		}
	}

	/**
	 * Display the page for the menu Entries
	 */
	public function menuEntries ()
	{
		$doAction = $this->_entries_list->current_action();
		switch ( $doAction )
		{
			case 'delete':
				$this->_displayPageEntriesDelete();
				break;

			case 'edit':
				$this->_displayPageEntriesEdit();
				break;

			default:
				$this->_displayPageEntriesList();
				break;
		}
	}

	/**
	 * Display the entries in a list
	 */
	private function _displayPageEntriesList ()
	{
		global $screen_layout_columns;

		$messages = array();
		if ( isset($_GET['update']) ) {
			switch ( $_GET['update'] )
			{
				case 'del':
				case 'del_many':
					$deleteCount = isset($_GET['deleteCount']) ? (int) $_GET['deleteCount'] : 0;
					$messages[] = '<div id="message" class="updated"><p>' . sprintf(_n('Competition deleted.', '%s competitions deleted.', $deleteCount), number_format_i18n($deleteCount)) . '</p></div>';
					break;
			}
		}

		if ( !empty($messages) ) {
			foreach ( $messages as $msg )
				echo $msg;
		}

		echo '<div class="wrap avhrps-wrap">';
		echo $this->_displayIcon('index');
		echo '<h2>Entries: ' . __('All Entries', 'avh-rps');

		if ( isset($_REQUEST['s']) && $_REQUEST['s'] ) {
			printf('<span class="subtitle">' . sprintf(__('Search results for &#8220;%s&#8221;'), wp_html_excerpt(esc_html(stripslashes($_REQUEST['s'])), 50)) . '</span>');
		}
		echo '</h2>';

		$this->_entries_list->views();
		echo '<form id="rps-entries-form" action="" method="get">';
		echo '<input type="hidden" name="page" value="' . AVH_RPS_Define::MENU_SLUG_ENTRIES . '">';

		echo '<input type="hidden" name="_total" value="' . esc_attr($this->_entries_list->get_pagination_arg('total_items')) . '" />';
		echo '<input type="hidden" name="_per_page" value="' . esc_attr($this->_entries_list->get_pagination_arg('per_page')) . '" />';
		echo '<input type="hidden" name="_page" value="' . esc_attr($this->_entries_list->get_pagination_arg('page')) . '" />';

		if ( isset($_REQUEST['paged']) ) {
			echo '<input type="hidden" name="paged"	value="' . esc_attr(absint($_REQUEST['paged'])) . '" />';
		}
		$this->_entries_list->display();
		echo '</form>';

		echo '<div id="ajax-response"></div>';
		$this->_printAdminFooter();
		echo '</div>';
	}

	/**
	 * Display the page to confirm the deletion of the selected entries.
	 */
	private function _displayPageEntriesDelete ()
	{
		global $wpdb;
		$classForm = $this->_classes->load_class('Form', 'system', false);

		if ( empty($_REQUEST['entries']) ) {
			$entryIdsArray = array(intval($_REQUEST['entries']));
		} else {
			$entryIdsArray = (array) $_REQUEST['entries'];
		}

		$this->admin_header('Delete Entries');
		echo $classForm->open('', array('method' => 'post','id' => 'updateentries','name' => 'updateentries'));

		echo '<p>' . _n('You have specified this entry for deletion:', 'You have specified these entries for deletion:', count($entryIdsArray)) . '</p>';

		$goDelete = 0;
		foreach ( $entryIdsArray as $entryID ) {

			$entry = $this->_rpsdb->getEntryInfo($entryID, OBJECT);
			if ( $entry !== NULL ) {
				$user = get_user_by('id', $entry->Member_ID);
				$competition = $this->_rpsdb->getCompetitionByID2($entry->Competition_ID, OBJECT);
				echo "<li>";
				echo $classForm->hidden('entries[]', $entryID);
				printf(__('ID #%1s: <strong>%2s</strong> by <em>%3s %4s</em> for the competition <em>%5s</em> on %6s'), $entryID, $entry->Title, $user->first_name, $user->last_name, $competition->Theme, mysql2date(get_option('date_format'), $competition->Competition_Date));
				echo "</li>\n";
				$goDelete++;
			}
		}
		if ( $goDelete ) {
			echo $classForm->hidden('action', 'dodelete');
			echo $classForm->submit('delete', 'Confirm Deletion', array('class' => 'button-secondary delete'));
		} else {
			echo '<p>There are no valid entries to delete</p>';
		}

		wp_nonce_field('delete-entries');
		echo $this->_referer;

		echo $classForm->close();
		$this->admin_footer();
	}

	private function _displayPageEntriesEdit ()
	{
		global $wpdb;

		$updated = false;
		// @var $classForm AVH_Form
		$classForm = $this->_classes->load_class('Form', 'system', false);
		$classForm->setOption_name('entry-edit');

		if ( isset($_POST['update']) ) {
			$classForm->setNonce_action($_POST['entry']);
			check_admin_referer($classForm->getNonce_action());
			if ( !current_user_can('rps_edit_entries') ) {
				wp_die(__('Cheatin&#8217; uh?'));
			}
			$updated = $this->_updateEntry();
		}

		$vars = ( array('action','redirect','entry','wp_http_referer') );
		for ( $i = 0; $i < count($vars); $i += 1 ) {
			$var = $vars[$i];
			if ( empty($_POST[$var]) ) {
				if ( empty($_GET[$var]) )
					$$var = '';
				else
					$$var = $_GET[$var];
			} else {
				$$var = $_POST[$var];
			}
		}

		$wp_http_referer = remove_query_arg(array('update'), stripslashes($wp_http_referer));
		$entry = $this->_rpsdb->getEntryInfo($_REQUEST['entry'], OBJECT);

		$this->admin_header('Edit Entry');

		if ( isset($_POST['update']) ) {
			echo '<div id="message" class="updated">';
			if ( $updated ) {
				echo '<p><strong>Entry updated.</strong></p>';
			} else {
				echo '<p><strong>Entry not updated.</strong></p>';
			}
			if ( $wp_http_referer ) {
				echo '<p><a href="' . esc_url($wp_http_referer) . '">&larr; Back to Entries</a></p>';
			}
			echo '</div>';
		}

		$queryEdit = array('page' => AVH_RPS_Define::MENU_SLUG_ENTRIES);
		echo $classForm->open(admin_url('admin.php') . '?' . http_build_query($queryEdit, '', '&'), array('method' => 'post','id' => 'rps-entryedit'));
		echo $classForm->open_table();

		$_user = get_user_by('id', $entry->Member_ID);
		echo '<h3>Photographer: ' . $_user->first_name . ' ' . $_user->last_name . "</h3>\n";
		echo "<img src=\"" . $this->_core->rpsGetThumbnailUrl(get_object_vars($entry), 200) . "\" />\n";
		echo $classForm->text('Title', '', 'title', $entry->Title);
		echo $classForm->close_table();
		echo $classForm->submit('submit', 'Update Entry', array('class' => 'button-primary'));
		if ( $wp_http_referer ) {
			echo $classForm->hidden('wp_http_referer', esc_url($wp_http_referer));
		}
		echo $classForm->hidden('entry', $entry->ID);
		echo $classForm->hidden('update', true);
		echo $classForm->hidden('action', 'edit');
		$classForm->setNonce_action($entry->ID);
		echo $classForm->nonce_field();
		echo $classForm->close();
		$this->admin_footer();
	}

	private function _updateEntry ()
	{
		$formOptions = $_POST['entry-edit'];
		$id = (int) $_POST['entry'];
		$entry = $this->_rpsdb->getEntryInfo($id);

		$return = FALSE;
		$formOptionsNew['title'] = empty($formOptions['title']) ? $entry['Title'] : $formOptions['title'];
		if ( $entry['Title'] != $formOptionsNew['title'] ) {
			$data = array('ID' => $id,'Title' => $formOptionsNew['title']);
			$return = $this->_rpsdb->updateEntry($data);
		}
		return $return;
	}

	/**
	 * Adds Settings next to the plugin actions
	 *
	 * @WordPress Filter plugin_action_links_avh-first-defense-against-spam/avh-fdas.php
	 *
	 * @param array $links
	 * @return array
	 *
	 * @since 1.0
	 */
	public function filterPluginActions ($links)
	{
		$folder = AVH_Common::getBaseDirectory($this->_settings->plugin_basename);
		$settings_link = '<a href="admin.php?page=' . $folder . '">' . __('Settings', 'avh-fdas') . '</a>';
		array_unshift($links, $settings_link); // before other links
		return $links;
	}

	/**
	 * Used when we set our own screen options.
	 *
	 * The filter needs to be set during construct otherwise it's not regonized.
	 *
	 * @param unknown_type $default
	 * @param unknown_type $option
	 * @param unknown_type $value
	 */
	public function filterSetScreenOption ($error_value, $option, $value)
	{
		$return = $error_value;
		switch ( $option )
		{
			case 'competitions_per_page':
				$value = (int) $value;
				$return = $value;
				if ( $value < 1 || $value > 999 ) {
					$return = $error_value;
				}
				break;
			default:
				$return = $error_value;
				break;
		}
		return $return;
	}

	/**
	 * Sets the amount of columns wanted for a particuler screen
	 *
	 * @WordPress filter screen_meta_screen
	 *
	 * @param
	 *        $screen
	 * @return strings
	 */
	public function filterScreenLayoutColumns ($columns, $screen)
	{
		switch ( $screen )
		{
			// case $this->_hooks['avhrps_menu_competition']:
			// $columns[$this->_hooks['avhfdas_menu_overview']] = 1;
			// break;
			// case $this->_hooks['avhrps_menu_competition_add']:
			// $columns[$this->_hooks['avhfdas_menu_general']] = 1;
			// break;
		}
		return $columns;
	}

	public function actionUser_Profile ($user_id)
	{
		$userID = $user_id->ID;
		$_rps_class_bw = get_user_meta($userID, 'rps_class_bw', true);
		$_rps_class_color = get_user_meta($userID, 'rps_class_color', true);
		$_rps_class_print_bw = get_user_meta($userID, 'rps_class_print_bw', true);
		$_rps_class_print_color = get_user_meta($userID, 'rps_class_print_color', true);

		$_classification = array('beginner' => 'Beginner','advanced' => 'Advanced','salon' => 'Salon');
		echo '<h3 id="rps">Competition Classification</h3>';
		echo '<table class="form-table">';

		echo '<tr>';
		echo '<th>Classification Digital B&W</th>';
		echo '<td>';
		if ( current_user_can('rps_edit_competition_classification') ) {
			$p = '';
			$r = '';
			echo '<select name="rps_class_bw" id="rps_class_bw">';
			foreach ( $_classification as $key => $value ) {
				if ( $key === $_rps_class_bw ) {
					$p = "\n\t<option selected='selected' value='" . esc_attr($key) . "'>$value</option>";
				} else {
					$r .= "\n\t<option value='" . esc_attr($key) . "'>$value</option>";
				}
			}
			echo $p . $r;
			echo '</select>';
		} else {
			echo $_classification[$_rps_class_bw];
		}
		echo '</td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th>Classification Digital Color</th>';
		echo '<td>';
		if ( current_user_can('rps_edit_competition_classification') ) {
			$p = '';
			$r = '';
			echo '<select name="rps_class_color" id="rps_class_color">';
			foreach ( $_classification as $key => $value ) {
				if ( $key === $_rps_class_color ) {
					$p = "\n\t<option selected='selected' value='" . esc_attr($key) . "'>$value</option>";
				} else {
					$r .= "\n\t<option value='" . esc_attr($key) . "'>$value</option>";
				}
			}
			echo $p . $r;
			echo '</select>';
		} else {
			echo $_classification[$_rps_class_color];
		}
		echo '</td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th>Classification Print B&W</th>';
		echo '<td>';
		if ( current_user_can('rps_edit_competition_classification') ) {
			$p = '';
			$r = '';
			echo '<select name="rps_class_print_bw" id="rps_class_print_bw">';
			foreach ( $_classification as $key => $value ) {
				if ( $key === $_rps_class_print_bw ) {
					$p = "\n\t<option selected='selected' value='" . esc_attr($key) . "'>$value</option>";
				} else {
					$r .= "\n\t<option value='" . esc_attr($key) . "'>$value</option>";
				}
			}
			echo $p . $r;
			echo '</select>';
		} else {
			echo $_classification[$_rps_class_print_bw];
		}
		echo '</td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th>Classification Print Color</th>';
		echo '<td>';
		if ( current_user_can('rps_edit_competition_classification') ) {
			$p = '';
			$r = '';
			echo '<select name="rps_class_print_color" id="rps_class_print_color">';
			foreach ( $_classification as $key => $value ) {
				if ( $key === $_rps_class_print_color ) {
					$p = "\n\t<option selected='selected' value='" . esc_attr($key) . "'>$value</option>";
				} else {
					$r .= "\n\t<option value='" . esc_attr($key) . "'>$value</option>";
				}
			}
			echo $p . $r;
			echo '</select>';
		} else {
			echo $_classification[$_rps_class_print_color];
		}
		echo '</td>';
		echo '</tr>';

		echo '</table>';
	}

	public function actionProfile_Update_Save ($user_id)
	{
		$userID = $user_id;
		if ( isset($_POST['rps_class_bw']) ) {
			$_rps_class_bw = $_POST["rps_class_bw"];
		} else {
			$_rps_class_bw = get_user_meta($userID, 'rps_class_bw', true);
		}
		if ( isset($_POST['rps_class_color']) ) {
			$_rps_class_color = $_POST['rps_class_color'];
		} else {
			$_rps_class_color = get_user_meta($userID, 'rps_class_color', true);
		}
		if ( isset($_POST['rps_class_print_bw']) ) {
			$_rps_class_print_bw = $_POST["rps_class_print_bw"];
		} else {
			$_rps_class_print_bw = get_user_meta($userID, 'rps_class_print_bw', true);
		}
		if ( isset($_POST['rps_class_print_color']) ) {
			$_rps_class_print_color = $_POST['rps_class_print_color'];
		} else {
			$_rps_class_print_color = get_user_meta($userID, 'rps_class_print_color', true);
		}

		update_user_meta($userID, "rps_class_bw", $_rps_class_bw);
		update_user_meta($userID, "rps_class_color", $_rps_class_color);
		update_user_meta($userID, "rps_class_print_bw", $_rps_class_print_bw);
		update_user_meta($userID, "rps_class_print_color", $_rps_class_print_color);
	}

	private function _updateCompetition ()
	{
		$formOptions = $_POST['competition-edit'];

		$formOptionsNew['date'] = $formOptions['date'];
		$formOptionsNew['close-date'] = $formOptions['close-date'];
		$formOptionsNew['close-time'] = $formOptions['close-time'];
		$formOptionsNew['theme'] = $formOptions['theme'];
		$formOptionsNew['medium'] = $formOptions['medium'];
		$formOptionsNew['classification'] = $formOptions['classification'];
		$formOptionsNew['max_entries'] = $formOptions['max_entries'];
		$formOptionsNew['judges'] = $formOptions['judges'];
		$formOptionsNew['special_event'] = isset($formOptions['special_event']) ? $formOptions['special_event'] : '';
		$formOptionsNew['closed'] = isset($formOptions['closed']) ? $formOptions['closed'] : '';
		$formOptionsNew['scored'] = isset($formOptions['scored']) ? $formOptions['scored'] : '';
		// @format_off
		$_medium = array ( 'medium_bwd'		=> 'B&W Digital',
							'medium_cd'		=> 'Color Digital',
							'medium_bwp'	=> 'B&W Print',
							'medium_cp'		=> 'Color Print'
					);
		$selectedMedium=array_search($competition->Medium, $_medium);
		// @format_on

		// @format_off
		$_classification = array ( 'class_b' => 'Beginner',
									'class_a' => 'Advanced',
									'class_s' => 'Salon',
			);
		// @format_on
		$data['ID'] = $_REQUEST['competition'];
		$data['Competition_Date'] = $formOptionsNew['date'];
		$data['Close_Date'] = $formOptionsNew['close-date'];
		$data['Theme'] = $formOptionsNew['theme'];
		$data['Max_Entries'] = $formOptionsNew['max_entries'];
		$data['Num_Judges'] = $formOptionsNew['judges'];
		$data['Special_Event'] = ( $formOptionsNew['special_event'] ? 'Y' : 'N' );
		$data['Closed'] = ( $formOptionsNew['closed'] ? 'Y' : 'N' );
		$data['Scored'] = ( $formOptionsNew['scored'] ? 'Y' : 'N' );
		$data['Medium'] = $_medium[$formOptionsNew['medium']];
		$data['Classification'] = $_classification[$formOptionsNew['classification']];
		$competition_ID = $this->_rpsdb->insertCompetition($data);
	}
	// ############ Admin WP Helper ##############

	/**
	 * Display plugin Copyright
	 */
	private function _printAdminFooter ()
	{
		echo '<div class="clear"></div>';
		echo '<p class="footer_avhfdas">';
		printf('&copy; Copyright 2012 <a href="http://blog.avirtualhome.com/" title="My Thoughts">Peter van der Does</a> | AVH RPS Competition version %s', AVH_RPS_Define::PLUGIN_VERSION);
		echo '</p>';
	}

	/**
	 * Display WP alert
	 */
	private function _displayMessage ()
	{
		$message = '';
		if ( is_array($this->_message) ) {
			foreach ( $this->_message as $key => $_msg ) {
				$message .= $_msg . "<br>";
			}
		} else {
			$message = $this->_message;
		}

		if ( $message != '' ) {
			$status = $this->_status;
			$this->_message = $this->_status = ''; // Reset
			$status = ( $status != '' ) ? $status : 'updated fade';
			echo '<div id="message"	class="' . $status . '">';
			echo '<p><strong>' . $message . '</strong></p></div>';
		}
	}

	/**
	 * Displays the icon needed.
	 * Using this instead of core in case we ever want to show our own icons
	 *
	 * @param $icon strings
	 * @return string
	 */
	private function _displayIcon ($icon)
	{
		return ( '<div class="icon32" id="icon-' . $icon . '"><br/></div>' );
	}

	/**
	 * Ouput formatted options
	 *
	 * @param array $option_data
	 * @return string
	 */
	private function _printOptions ($option_data, $option_actual)
	{
		// Generate output
		$output = '';
		$output .= "\n" . '<table class="form-table avhfdas-options">' . "\n";
		foreach ( $option_data as $option ) {
			$section = substr($option[0], strpos($option[0], '[') + 1);
			$section = substr($section, 0, strpos($section, ']['));
			$option_key = rtrim($option[0], ']');
			$option_key = substr($option_key, strpos($option_key, '][') + 2);
			// Helper
			if ( $option[2] == 'helper' ) {
				$output .= '<tr style="vertical-align: top;"><td class="helper" colspan="2">' . $option[4] . '</td></tr>' . "\n";
				continue;
			}
			switch ( $option[2] )
			{
				case 'checkbox':
					$input_type = '<input type="checkbox" id="' . $option[0] . '" name="' . $option[0] . '" value="' . esc_attr($option[3]) . '" ' . checked('1', $option_actual[$section][$option_key], false) . ' />' . "\n";
					$explanation = $option[4];
					break;
				case 'dropdown':
					$selvalue = explode('/', $option[3]);
					$seltext = explode('/', $option[4]);
					$seldata = '';
					foreach ( (array) $selvalue as $key => $sel ) {
						$seldata .= '<option value="' . $sel . '" ' . selected($sel, $option_actual[$section][$option_key], false) . ' >' . ucfirst($seltext[$key]) . '</option>' . "\n";
					}
					$input_type = '<select id="' . $option[0] . '" name="' . $option[0] . '">' . $seldata . '</select>' . "\n";
					$explanation = $option[5];
					break;
				case 'text-color':
					$input_type = '<input type="text" ' . ( ( $option[3] > 50 ) ? ' style="width: 95%" ' : '' ) . 'id="' . $option[0] . '" name="' . $option[0] . '" value="' . esc_attr(stripcslashes($option_actual[$section][$option_key])) . '" size="' . $option[3] . '" /><div class="box_color ' . $option[0] . '"></div>' . "\n";
					$explanation = $option[4];
					break;
				case 'textarea':
					$input_type = '<textarea rows="' . $option[5] . '" ' . ( ( $option[3] > 50 ) ? ' style="width: 95%" ' : '' ) . 'id="' . $option[0] . '" name="' . $option[0] . '" size="' . $option[3] . '" />' . esc_attr(stripcslashes($option_actual[$section][$option_key])) . '</textarea>';
					$explanation = $option[4];
					break;
				case 'text':
				default:
					$input_type = '<input type="text" ' . ( ( $option[3] > 50 ) ? ' style="width: 95%" ' : '' ) . 'id="' . $option[0] . '" name="' . $option[0] . '" value="' . esc_attr(stripcslashes($option_actual[$section][$option_key])) . '" size="' . $option[3] . '" />' . "\n";
					$explanation = $option[4];
					break;
			}
			// Additional Information
			$extra = '';
			if ( $explanation ) {
				$extra = '<br /><span class="description">' . __($explanation) . '</span>' . "\n";
			}
			// Output
			$output .= '<tr style="vertical-align: top;"><th align="left" scope="row"><label for="' . $option[0] . '">' . __($option[1]) . '</label></th><td>' . $input_type . '	' . $extra . '</td></tr>' . "\n";
		}
		$output .= '</table>' . "\n";
		return $output;
	}

	/**
	 * Display error message at bottom of comments.
	 *
	 * @param string $msg
	 *        Error Message. Assumed to contain HTML and be sanitized.
	 */
	private function _comment_footer_die ($msg)
	{
		echo "<div class='wrap'><p>$msg</p></div>";
		die();
	}

	/**
	 * Generates the header for admin pages
	 *
	 * @param string $title
	 *        The title to show in the main heading.
	 * @param bool $form
	 *        Whether or not the form should be included.
	 * @param string $option
	 *        The long name of the option to use for the current page.
	 * @param string $optionshort
	 *        The short name of the option to use for the current page.
	 * @param bool $contains_files
	 *        Whether the form should allow for file uploads.
	 */
	function admin_header ($title)
	{
		echo '<div class="wrap">';
		echo $this->_displayIcon('options-general');
		echo '<h2 id="rps-title">' . $title . '</h2>';
		echo '<div id="rps_content_top" class="postbox-container" style="width:100%;">';
		echo '<div class="metabox-holder">';
		echo '<div class="meta-box-sortables">';
	}

	/**
	 * Generates the footer for admin pages
	 *
	 * @param bool $submit
	 *        Whether or not a submit button should be shown.
	 * @param text $text
	 *        The text to be shown in the submit button.
	 */
	function admin_footer ()
	{
		echo '</div></div></div>';
		// $this->admin_sidebar();
		$this->_printAdminFooter();
		echo '</div>';
	}
}