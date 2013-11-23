<?php
namespace RpsCompetition\Admin;

use RpsCompetition\Competition\ListTable as CompetitionListTable;
use RpsCompetition\Entries\ListTable as EntriesListTable;
use RpsCompetition\Common\Core;
use RpsCompetition\Settings;
use RpsCompetition\Constants;
use Avh\Html\FormBuilder;
use Avh\Utility\Common;
use Avh\Di\Container;
use RpsCompetition\Db\RpsDb;

final class Admin
{

    private $message = '';

    private $status = '';

    /**
     *
     * @var Core
     */
    private $core;

    /**
     *
     * @var Settings
     */
    private $settings;

    /**
     *
     * @var RpsDb
     */
    private $rpsdb;

    /**
     *
     * @var CompetitionListTable
     */
    private $competition_list;

    /**
     *
     * @var Container
     */
    private $container;

    /**
     *
     * @var EntriesListTable
     */
    private $entries_list;

    private $add_disabled_notice = false;

    private $hooks = array();

    private $referer;

    /**
     * PHP5 Constructor
     *
     * @return unknown_type
     */
    public function __construct(\Avh\Di\Container $container)
    {
        $this->container = $container;

        // The Settings Registery
        $this->settings = $this->container->resolve('\RpsCompetition\Settings');

        // Loads the CORE class
        $this->core = $container->resolve('\RpsCompetition\Common\Core');
        // Admin URL and Pagination
        $this->core->admin_base_url = $this->settings->siteurl . '/wp-admin/admin.php?page=';
        if (isset($_GET['pagination'])) {
            $this->core->actual_page = (int) $_GET['pagination'];
        }

        // Admin menu
        add_action('admin_menu', array($this, 'actionAdminMenu'));
        add_action('admin_init', array($this, 'handleActionInit'));

        add_action('wp_ajax_setscore', array($this, 'handleAjax'));
        add_filter('user_row_actions', array($this, 'filterRpsUserActionLinks'), 10, 2);

        add_action('user_register', array($this, 'actionAddUserMeta'));
    }

    /**
     * Runs during the action init.
     */
    public function handleActionInit()
    {
        $this->actionInitRoles();
        $this->actionInitUserFields();

        return;
    }

    /**
     * Setup Roles
     *
     * @WordPress Action init
     */
    public function actionInitRoles()
    {
        // Get the administrator role.
        $role = get_role('administrator');

        // If the administrator role exists, add required capabilities for the plugin.
        if (!empty($role)) {

            // Role management capabilities.
            $role->add_cap('rps_edit_competition_classification');
            $role->add_cap('rps_edit_competitions');
            $role->add_cap('rps_edit_entries');
        }
    }

    public function actionAddUserMeta($userID)
    {
        update_user_meta($userID, "rps_class_bw", 'beginner');
        update_user_meta($userID, "rps_class_color", 'beginner');
        update_user_meta($userID, "rps_class_print_bw", 'beginner');
        update_user_meta($userID, "rps_class_print_color", 'beginner');
    }

    /**
     * Add the actions needed for to extended the user profile
     */
    public function actionInitUserFields()
    {
        add_action('edit_user_profile', array($this, 'actionUserProfile'));
        add_action('show_user_profile', array($this, 'actionUserProfile'));
        add_action('personal_options_update', array($this, 'actionProfileUpdateSave'));
        add_action('edit_user_profile_update', array($this, 'actionProfileUpdateSave'));
    }

    /**
     * Add the Tools and Options to the Management and Options page respectively
     *
     * @WordPress Action admin_menu
     */
    public function actionAdminMenu()
    {
        wp_register_style('avhrps-admin-css', plugins_url('/css/avh-rps.admin.css', $this->settings->plugin_basename), array('wp-admin'), Constants::PLUGIN_VERSION, 'screen');
        wp_register_style('avhrps-jquery-css', plugins_url('/css/smoothness/jquery-ui-1.8.22.custom.css', $this->settings->plugin_basename), array('wp-admin'), '1.8.22', 'screen');
        wp_register_script('avhrps-comp-ajax', plugins_url('/js/avh-rps.admin.ajax.js', $this->settings->plugin_basename), array('jquery'), false, true);

        add_menu_page('All Competitions', 'Competitions', 'rps_edit_competitions', Constants::MENU_SLUG_COMPETITION, array($this, 'menuCompetition'), '', Constants::MENU_POSITION_COMPETITION);

        $this->hooks['avhrps_menu_competition'] = add_submenu_page(Constants::MENU_SLUG_COMPETITION, 'All Competitions', 'All Competitions', 'rps_edit_competitions', Constants::MENU_SLUG_COMPETITION, array($this, 'menuCompetition'));
        $this->hooks['avhrps_menu_competition_add'] = add_submenu_page(Constants::MENU_SLUG_COMPETITION, 'Add Competition', 'Add Competition', 'rps_edit_competitions', Constants::MENU_SLUG_COMPETITION_ADD, array($this, 'menuCompetitionAdd'));

        add_action('load-' . $this->hooks['avhrps_menu_competition'], array($this, 'actionLoadPagehookCompetition'));
        add_action('load-' . $this->hooks['avhrps_menu_competition_add'], array($this, 'actionLoadPagehookCompetitionAdd'));

        add_menu_page('All Entries', 'Entries', 'rps_edit_entries', Constants::MENU_SLUG_ENTRIES, array($this, 'menuEntries'), '', Constants::MENU_POSITION_ENTRIES);
        $this->hooks['avhrps_menu_entries'] = add_submenu_page(Constants::MENU_SLUG_ENTRIES, 'All Entries', 'All Entries', 'rps_edit_entries', Constants::MENU_SLUG_ENTRIES, array($this, 'menuEntries'));
        add_action('load-' . $this->hooks['avhrps_menu_entries'], array($this, 'actionLoadPagehookEntries'));
    }

    /**
     * Setup all that is needed for the page Competition
     */
    public function actionLoadPagehookCompetition()
    {
        global $current_screen;
        $this->rpsdb = $this->container->resolve('\RpsCompetition\Db\RpsDb');
        $this->competition_list = $this->container->resolve('\RpsCompetition\Competition\ListTable');

        $this->handleRequestCompetition();

        add_filter('screen_layout_columns', array($this, 'filterScreenLayoutColumns'), 10, 2);
        // WordPress core Styles and Scripts
        wp_enqueue_script('common');
        wp_enqueue_script('wp-lists');
        // Plugin Style and Scripts
        wp_enqueue_script('avhrps-comp-ajax');

        wp_enqueue_style('avhrps-admin-css');
        wp_enqueue_style('avhrps-jquery-css');

        // add_screen_option('per_page', array ( 'label' => _x('IP\'s', 'ip\'s per page (screen options)'), 'default' => 20, 'option' => 'ipcachelog_per_page' ));
        // add_contextual_help($current_screen, '<p>' . __('You can manage IP\'s added to the IP cache Log. This screen is customizable in the same ways as other management screens, and you can act on IP\'s using the on-hover action links or the Bulk Actions.') . '</p>');
    }

    /**
     * Handle the HTTP Request before the page of the menu Competition is displayed.
     * This is needed for the redirects.
     */
    private function handleRequestCompetition()
    {
        if (isset($_REQUEST['wp_http_referer'])) {
            $redirect = remove_query_arg(array('wp_http_referer', 'updated', 'delete_count'), stripslashes($_REQUEST['wp_http_referer']));
        } else {
            $redirect = admin_url('admin.php') . '?page=' . Constants::MENU_SLUG_COMPETITION;
        }

        $doAction = $this->competition_list->current_action();
        switch ($doAction) {
            case 'delete':
            case 'open':
            case 'close':
                check_admin_referer('bulk-competitions');
                if (empty($_REQUEST['competitions']) && empty($_REQUEST['competition'])) {
                    wp_redirect($redirect);
                    exit();
                }
                break;

            case 'edit':
                if (empty($_REQUEST['competition'])) {
                    wp_redirect($redirect);
                    exit();
                }
                break;

            case 'dodelete':
                check_admin_referer('delete-competitions');
                if (empty($_REQUEST['competitions'])) {
                    wp_redirect($redirect);
                    exit();
                }
                $competitionIds = $_REQUEST['competitions'];

                $deleteCount = 0;

                foreach ((array) $competitionIds as $id) {
                    $id = (int) $id;
                    $this->rpsdb->deleteCompetition($id);
                    ++$deleteCount;
                }
                $redirect = add_query_arg(array('deleteCount' => $deleteCount, 'update' => 'del_many'), $redirect);
                wp_redirect($redirect);
                break;

            case 'doopen':
                check_admin_referer('open-competitions');
                if (empty($_REQUEST['competitions'])) {
                    wp_redirect($redirect);
                    exit();
                }
                $competitionIds = $_REQUEST['competitions'];
                $count = 0;

                foreach ((array) $competitionIds as $id) {
                    $data['ID'] = (int) $id;
                    $data['Closed'] = 'N';
                    $this->rpsdb->insertCompetition($data);
                    ++$count;
                }
                $redirect = add_query_arg(array('count' => $count, 'update' => 'open_many'), $redirect);
                wp_redirect($redirect);
                break;

            case 'doclose':
                check_admin_referer('close-competitions');
                if (empty($_REQUEST['competitions'])) {
                    wp_redirect($redirect);
                    exit();
                }
                $competitionIds = $_REQUEST['competitions'];
                $count = 0;

                foreach ((array) $competitionIds as $id) {
                    $data['ID'] = (int) $id;
                    $data['Closed'] = 'Y';
                    $this->rpsdb->insertCompetition($data);
                    ++$count;
                }
                $redirect = add_query_arg(array('count' => $count, 'update' => 'close_many'), $redirect);
                wp_redirect($redirect);
                break;

            case 'setscore':
                if (!empty($_REQUEST['competition'])) {
                    check_admin_referer('score_' . $_REQUEST['competition']);
                    $data['ID'] = (int) $_REQUEST['competition'];
                    $data['Scored'] = 'Y';
                    $this->rpsdb->insertCompetition($data);
                }
                wp_redirect($redirect);
                break;
            case 'Unsetscore':
                if (!empty($_REQUEST['competition'])) {
                    check_admin_referer('score_' . $_REQUEST['competition']);
                    $data['ID'] = (int) $_REQUEST['competition'];
                    $data['Scored'] = 'N';
                    $this->rpsdb->insertCompetition($data);
                }
                wp_redirect($redirect);
                break;
            default:
                if (!empty($_GET['_wp_http_referer'])) {
                    wp_redirect(remove_query_arg(array('_wp_http_referer', '_wpnonce'), stripslashes($_SERVER['REQUEST_URI'])));
                    exit();
                }
                $pagenum = $this->competition_list->get_pagenum();
                $this->competition_list->prepare_items();
                $total_pages = $this->competition_list->get_pagination_arg('total_pages');
                if ($pagenum > $total_pages && $total_pages > 0) {
                    wp_redirect(add_query_arg('paged', $total_pages));
                    exit();
                }
                break;
        }
    }

    /**
     * Handle the Ajax callback
     */
    public function handleAjax()
    {
        $this->rpsdb = $this->container->resolve('\RpsCompetition\Db\RpsDb');
        if (isset($_POST['scored'])) {
            if ($_POST['scored'] == 'Yes') {
                $data['ID'] = (int) $_POST['id'];
                $data['Scored'] = 'N';
                $result = $this->rpsdb->insertCompetition($data);
                $response = json_encode(array('text' => 'N', 'scored' => 'No', 'scoredtext' => 'Yes'));
            }
            if ($_POST['scored'] == 'No') {
                $data['ID'] = (int) $_POST['id'];
                $data['Scored'] = 'Y';
                $result = $this->rpsdb->insertCompetition($data);
                $response = json_encode(array('text' => 'Y', 'scored' => 'Yes', 'scoredtext' => 'No'));
            }
            if (is_wp_error($result)) {
                echo 'Error updating competition';
            } else {
                echo $response;
            }
        }
        die();
    }

    /**
     * Display the page for the menu Competition
     */
    public function menuCompetition()
    {
        $doAction = $this->competition_list->current_action();
        switch ($doAction) {
            case 'delete':
                $this->displayPageCompetitionDelete();
                break;

            case 'edit':
                $this->displayPageCompetitionEdit();
                break;

            case 'open':
                $this->displayPageCompetitionOpenClose('open');
                break;

            case 'close':
                $this->displayPageCompetitionOpenClose('close');
                break;

            default:
                $this->displayPageCompetitionList();
                break;
        }
    }

    /**
     * Display the page to confirm the deletion of the selected competitions.
     */
    private function displayPageCompetitionDelete()
    {
        global $wpdb;

        if (empty($_REQUEST['competitions'])) {
            $competitionIdsArray = array(intval($_REQUEST['competition']));
        } else {
            $competitionIdsArray = (array) $_REQUEST['competitions'];
        }

        $formBuilder = $this->container->resolve('\Avh\Html\FormBuilder');
        /**
         * @var $formBuilder FormBuilder
         */
        $this->displayAdminHeader('Delete Competitions');
        echo $formBuilder->open('', array('method' => 'post', 'id' => 'updatecompetitions', 'name' => 'updatecompetitions', 'accept-charset' => get_bloginfo('charset')));
        wp_nonce_field('delete-competitions');
        echo $this->referer;

        echo '<p>' . _n('You have specified this competition for deletion:', 'You have specified these competitions for deletion:', count($competitionIdsArray)) . '</p>';

        $goDelete = 0;
        foreach ($competitionIdsArray as $competitionID) {

            $sqlWhere = $wpdb->prepare('Competition_ID=%d', $competitionID);
            $entries = $this->rpsdb->getEntries(array('where' => $sqlWhere, 'count' => true));
            $sqlWhere = $wpdb->prepare('ID=%d', $competitionID);
            $competition = $this->rpsdb->getCompetitions(array('where' => $sqlWhere));
            $competition = $competition[0];
            if ($entries !== "0") {
                echo "<li>" . sprintf(__('ID #%1s: %2s - %3s - %4s -%5s <strong>This competition will not be deleted. It still has %6s entries.</strong>'), $competitionID, mysql2date(get_option('date_format'), $competition->Competition_Date), $competition->Theme, $competition->Classification, $competition->Medium, $entries) . "</li>\n";
            } else {
                echo "<li><input type=\"hidden\" name=\"competitions[]\" value=\"" . esc_attr($competitionID) . "\" />" . sprintf(__('ID #%1s: %2s - %3s - %4s - %5s'), $competitionID, mysql2date(get_option('date_format'), $competition->Competition_Date), $competition->Theme, $competition->Classification, $competition->Medium) . "</li>\n";
                $goDelete++;
            }
        }
        if ($goDelete) {
            echo $formBuilder->hidden('action', 'dodelete');
            echo $formBuilder->submit('delete', 'Confirm Deletion', array('class' => 'button-secondary delete'));
        } else {
            echo '<p>There are no valid competitions to delete</p>';
        }
        echo $formBuilder->close();
        $this->displayAdminFooter();
    }

    /**
     * Display the page to edit a competition.
     */
    private function displayPageCompetitionEdit()
    {
        global $wpdb;

        $formBuilder = $this->container->resolve('\Avh\Html\FormBuilder');
        $formBuilder->setOptionName('competition-edit');

        if (isset($_POST['update'])) {
            $updated = $this->updateCompetition();
        }
        $vars = (array('action', 'redirect', 'competition', 'wp_http_referer'));
        for ($i = 0; $i < count($vars); $i += 1) {
            $var = $vars[$i];
            if (empty($_POST[$var])) {
                if (empty($_GET[$var])) {
                    $$var = '';
                } else {
                    $$var = $_GET[$var];
                }
            } else {
                $$var = $_POST[$var];
            }
        }

        $wp_http_referer = remove_query_arg(array('update'), stripslashes($wp_http_referer));

        $competition = $this->rpsdb->getCompetitionByID2($_REQUEST['competition']);

        $formOptions['date'] = mysql2date('Y-m-d', $competition->Competition_Date);
        $formOptions['close-date'] = mysql2date('Y-m-d', $competition->Close_Date);
        $formOptions['close-time'] = mysql2date('H:i:s', $competition->Close_Date);

        $this->displayAdminHeader('Edit Competition');

        if (isset($_POST['update'])) {
            echo '<div id="message" class="updated">';
            if ($updated) {
                echo '<p><strong>Competition updated.</strong></p>';
            } else {
                echo '<p><strong>Competition not updated.</strong></p>';
            }
            if ($wp_http_referer) {
                echo '<p><a href="' . esc_url($wp_http_referer) . '">&larr; Back to Competitions</a></p>';
            }
            echo '</div>';
        }

        $queryEdit = array('page' => Constants::MENU_SLUG_COMPETITION);
        echo $formBuilder->open(admin_url('admin.php') . '?' . http_build_query($queryEdit, '', '&'), array('method' => 'post', 'id' => 'rps-competitionedit', 'accept-charset' => get_bloginfo('charset')));
        echo $formBuilder->openTable();
        echo $formBuilder->text('Date', '', 'date', $formOptions['date']);
        echo $formBuilder->text('Theme', '', 'theme', $competition->Theme, array('maxlength' => '32'));
        echo $formBuilder->text('Closing Date', '', 'close-date', $formOptions['close-date']);

        for ($hour = 0; $hour <= 23; $hour++) {
            $time_val = sprintf("%02d:00:00", $hour);
            $time_text = date("g:i a", strtotime($time_val));
            $time[$time_val] = $time_text;
        }
        // echo $formBuilder->select('Closing Time', '', 'close-time', $time, $formOptions['close-time'], array('autocomplete' => 'off'));
        echo $formBuilder->select('Closing Time', '', 'close-time', $time, $formOptions['close-time']);

        // @formatter:off
        $_medium = array(
                'medium_bwd' => 'B&W Digital',
                'medium_cd' => 'Color Digital',
                'medium_bwp' => 'B&W Prints',
                'medium_cp' => 'Color Prints'
            );
        // @formatter:on
        $selectedMedium = array_search($competition->Medium, $_medium);
        echo $formBuilder->select('Medium', '', 'medium', $_medium, $selectedMedium, array('autocomplete' => 'off'));

        // @formatter:off
        $_classification = array(
                'class_b' => 'Beginner',
                'class_a' => 'Advanced',
                'class_s' => 'Salon'
            );
        // @formatter:on
        $selectedClassification = array_search($competition->Classification, $_classification);
        echo $formBuilder->select('Classification', '', 'classification', $_classification, $selectedClassification, array('autocomplete' => 'off'));

        $_max_entries = array('1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5', '6' => '6', '7' => '7', '8' => '8', '9' => '9', '10' => '10');
        echo $formBuilder->select('Max Entries', '', 'max_entries', $_max_entries, $competition->Max_Entries, array('autocomplete' => 'off'));

        $_judges = array('1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5');
        echo $formBuilder->select('No. Judges', '', 'judges', $_judges, $competition->Num_Judges, array('autocomplete' => 'off'));

        $_special_event = array('special_event' => array('text' => '', 'checked' => $competition->Special_Event));
        echo $formBuilder->checkboxes('Special Event', '', key($_special_event), $_special_event);

        $_closed = array('closed' => array('text' => '', 'checked' => ($competition->Closed == 'Y' ? true : false)));
        echo $formBuilder->checkboxes('Closed', '', key($_closed), $_closed);

        $_scored = array('scored' => array('text' => '', 'checked' => ($competition->Scored == 'Y' ? true : false)));
        echo $formBuilder->checkboxes('Scored', '', key($_scored), $_scored);

        echo $formBuilder->closeTable();
        echo $formBuilder->submit('submit', 'Update Competition', array('class' => 'button-primary'));
        if ($wp_http_referer) {
            echo $formBuilder->hidden('wp_http_referer', esc_url($wp_http_referer));
        }
        echo $formBuilder->hidden('competition', $competition->ID);
        echo $formBuilder->hidden('update', true);
        echo $formBuilder->hidden('action', 'edit');
        $formBuilder->setNonceAction($competition->ID);
        echo $formBuilder->fieldNonce();
        echo $formBuilder->close();
        echo '<script type="text/javascript">' . "\n";
        echo 'jQuery(function($) {' . "\n";
        echo ' $.datepicker.setDefaults({' . "\n";
        echo '   dateFormat: \'yy-mm-dd\', ' . "\n";
        echo '   showButtonPanel: true, ' . "\n";
        echo '   buttonImageOnly: true, ' . "\n";
        echo '   buttonImage: "' . plugins_url("/images/calendar.png", $this->settings->plugin_basename) . '", ' . "\n";
        echo '   showOn: "both"' . "\n";
        echo ' });' . "\n";
        echo '	$( "#date" ).datepicker();' . "\n";
        echo '	$( "#close-date" ).datepicker();' . "\n";
        echo '});', "\n";
        echo "</script>";
        $this->displayAdminFooter();
    }

    /**
     * Display the page to open or close competitions
     *
     * @param string $redirect
     * @param string $referer
     *
     */
    private function displayPageCompetitionOpenClose($action)
    {
        global $wpdb;

        if ($action == 'open') {
            $title = 'Open Competitions';
            $action_verb = 'openend';
        }
        if ($action == 'close ') {
            $title = 'Close Competitions';
            $action_verb = 'closed';
        }

        if (empty($_REQUEST['competitions'])) {
            $competitionIdsArray = array(intval($_REQUEST['competition']));
        } else {
            $competitionIdsArray = (array) $_REQUEST['competitions'];
        }

        $formBuilder = $this->container->resolve('\Avh\Html\FormBuilder');

        $this->displayAdminHeader($title);
        echo $formBuilder->open('', array('method' => 'post', 'id' => 'updatecompetitions', 'name' => 'updatecompetitions', 'accept-charset' => get_bloginfo('charset')));
        wp_nonce_field($action . '-competitions');
        echo $this->referer;

        echo '<p>' . _n('You have specified this competition to be ' . $action_verb . ':', 'You have specified these competitions to be ' . $action_verb . '::', count($competitionIdsArray)) . '</p>';

        foreach ($competitionIdsArray as $competitionID) {
            $sqlWhere = $wpdb->prepare('ID=%d', $competitionID);
            $competition = $this->rpsdb->getCompetitions(array('where' => $sqlWhere));
            $competition = $competition[0];
            echo "<li><input type=\"hidden\" name=\"competitions[]\" value=\"" . esc_attr($competitionID) . "\" />" . sprintf(__('ID #%1s: %2s - %3s - %4s - %5s'), $competitionID, mysql2date(get_option('date_format'), $competition->Competition_Date), $competition->Theme, $competition->Classification, $competition->Medium) . "</li>\n";
        }

        echo $formBuilder->hidden('action', 'do' . $action);
        echo $formBuilder->submit('openclose', 'Confirm', array('class' => 'button-secondary'));

        echo $formBuilder->close();
        $this->displayAdminFooter();
    }

    /**
     * Display the competion in a list
     */
    private function displayPageCompetitionList()
    {
        global $screen_layout_columns;

        $messages = array();
        if (isset($_GET['update'])) {
            switch ($_GET['update']) {
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

        if (!empty($messages)) {
            foreach ($messages as $msg) {
                echo $msg;
            }
        }

        echo '<div class="wrap avhrps-wrap">';
        echo $this->displayIcon('index');
        echo '<h2>Competitions: ' . __('All Competitions', 'avh-rps');

        if (isset($_REQUEST['s']) && $_REQUEST['s']) {
            printf('<span class="subtitle">' . sprintf(__('Search results for &#8220;%s&#8221;'), wp_html_excerpt(esc_html(stripslashes($_REQUEST['s'])), 50)) . '</span>');
        }
        echo '</h2>';

        $this->competition_list->views();
        echo '<form id="rps-competition-form" action="" method="get">';
        echo '<input type="hidden" name="page" value="' . Constants::MENU_SLUG_COMPETITION . '">';

        echo '<input type="hidden" name="_total" value="' . esc_attr($this->competition_list->get_pagination_arg('total_items')) . '" />';
        echo '<input type="hidden" name="_per_page" value="' . esc_attr($this->competition_list->get_pagination_arg('per_page')) . '" />';
        echo '<input type="hidden" name="_page" value="' . esc_attr($this->competition_list->get_pagination_arg('page')) . '" />';

        if (isset($_REQUEST['paged'])) {
            echo '<input type="hidden" name="paged"	value="' . esc_attr(absint($_REQUEST['paged'])) . '" />';
        }
        // $this->competition_list->search_box(__('Find IP', 'avh-rps'), 'find_ip');
        $this->competition_list->display();
        echo '</form>';

        echo '<div id="ajax-response"></div>';
        $this->printAdminFooter();
        echo '</div>';
    }

    /**
     * Setup all that is needed for the page "Add competition"
     */
    public function actionLoadPagehookCompetitionAdd()
    {
        global $current_screen;
        $this->rpsdb = $this->container->resolve('\RpsCompetition\Db\RpsDb');
        $this->competition_list = $this->container->resolve('\RpsCompetition\Competition\ListTable');

        add_filter('screen_layout_columns', array($this, 'filterScreenLayoutColumns'), 10, 2);
        // WordPress core Styles and Scripts
        wp_enqueue_script('common');
        wp_enqueue_script('jquery-ui-datepicker');
        // Plugin Style and Scripts
        // wp_enqueue_script('avhrps-competition-js');

        wp_enqueue_style('avhrps-admin-css');
        wp_enqueue_style('avhrps-jquery-css');
    }

    /**
     * Show the page to add a competition
     */
    public function menuCompetitionAdd()
    {
        $option_name = 'competition_add';
        // @var $formBuilder AVH_Form
        $formBuilder = $this->container->resolve('\Avh\Html\FormBuilder');
        $formBuilder->setOptionName('competition_add');

        // @formatter:off
        $formDefaultOptions = array(
                'date' => '',
                'theme' => '',
                'medium_bwd' => true,
                'medium_cd' => true,
                'medium_bwp' => true,
                'medium_cp' => true,
                'class_b' => true,
                'class_a' => true,
                'class_s' => true,
                'max_entries' => '2',
                'judges' => '1',
                'special_event' => false
            );
        // @formatter:on
        $formOptions = $formDefaultOptions;
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add':
                    $formBuilder->setNonceAction(get_current_user_id());
                    check_admin_referer($formBuilder->getNonce_action());
                    $formNewOptions = $formDefaultOptions;
                    $formOptions = $_POST[$formBuilder->getOption_name()];

                    $mediumArray = array();
                    $classArray = array();
                    $errorMsgArray = array();
                    foreach ($formDefaultOptions as $optionKey => $optionValue) {

                        // Every field in a form is set except unchecked checkboxes. Set an unchecked checkbox to false.
                        $newval = (isset($formOptions[$optionKey]) ? stripslashes($formOptions[$optionKey]) : false);
                        $current_value = $formDefaultOptions[$optionKey];
                        switch ($optionKey) {
                            case 'date':
                                // Validate
                                break;

                            case 'theme':
                                // Validate
                                break;
                        }
                        if (substr($optionKey, 0, 7) == 'medium_') {
                            $formNewOptions[$optionKey] = (bool) $newval;
                            if ($formNewOptions[$optionKey]) {
                                $mediumArray[] = $optionKey;
                                continue;
                            }
                        }
                        if (substr($optionKey, 0, 6) == 'class_') {
                            $formNewOptions[$optionKey] = (bool) $newval;
                            if ($formNewOptions[$optionKey]) {
                                $classArray[] = $optionKey;
                                continue;
                            }
                        }
                        $formNewOptions[$optionKey] = $newval;
                    }

                    if (empty($mediumArray)) {
                        $errorMsgArray[] = 'No medium selected. At least one medium needs to be selected';
                    }

                    if (empty($classArray)) {
                        $errorMsgArray[] = 'No classification selected. At least one classification needs to be selected';
                    }

                    if (empty($errorMsgArray)) {
                        $this->message = 'Competition Added';
                        $this->status = 'updated';

                        // @formatter:off
                        // @TODO: This is needed because of the old program, someday it needs to be cleaned up.
                        $medium_convert = array(
                                'medium_bwd' => 'B&W Digital',
                                'medium_cd' => 'Color Digital',
                                'medium_bwp' => 'B&W Prints',
                                'medium_cp' => 'Color Prints'
                            );

                        $classification_convert = array(
                                'class_b' => 'Beginner',
                                'class_a' => 'Advanced',
                                'class_s' => 'Salon'
                            );
                        // @formatter:on
                        $data['Competition_Date'] = $formNewOptions['date'];
                        $data['Theme'] = $formNewOptions['theme'];
                        $data['Max_Entries'] = $formNewOptions['max_entries'];
                        $data['Num_Judges'] = $formNewOptions['judges'];
                        $data['Special_Event'] = ($formNewOptions['special_event'] ? 'Y' : 'N');
                        foreach ($mediumArray as $medium) {
                            $data['Medium'] = $medium_convert[$medium];
                            foreach ($classArray as $classification) {
                                $data['Classification'] = $classification_convert[$classification];
                                $competition_ID = $this->rpsdb->insertCompetition($data);
                                if (is_wp_error($competition_ID)) {
                                    wp_die($competition_ID);
                                }
                            }
                        }
                    } else {
                        $this->message = $errorMsgArray;
                        $this->status = 'error';
                    }
                    $this->displayMessage();
                    $formOptions = $formNewOptions;
                    break;
            }
        }

        $this->displayAdminHeader('Add Competition');

        echo $formBuilder->open(admin_url('admin.php') . '?page=' . Constants::MENU_SLUG_COMPETITION_ADD, array('method' => 'post', 'id' => 'rps-competitionadd', 'accept-charset' => get_bloginfo('charset')));
        echo $formBuilder->openTable();
        echo $formBuilder->text('Date', '', 'date', $formOptions['date']);
        echo $formBuilder->text('Theme', '', 'theme', $formOptions['theme'], array('maxlength' => '32'));

        // @formatter:off
        $_medium = array(
            'medium_bwd' => array(
                'text' => 'B&W Digital',
                'checked' => $formOptions['medium_bwd']
            ),
            'medium_cd' => array(
                'text' => 'Color Digital',
                'checked' => $formOptions['medium_cd']
            ),
            'medium_bwp' => array(
                'text' => 'B&W Print',
                'checked' => $formOptions['medium_bwp']
            ),
            'medium_cp' => array(
                'text' => 'Color Digital',
                'checked' => $formOptions['medium_cp']
            )
        );
        // @formatter:on
        echo $formBuilder->checkboxes('Medium', '', key($_medium), $_medium);
        unset($_medium);

        // @formatter:off
        $_classification = array(
            'class_b' => array(
                'text' => 'Beginner',
                'checked' => $formOptions['class_b']
            ),
            'class_a' => array(
                'text' => 'Advanced',
                'checked' => $formOptions['class_a']
            ),
            'class_s' => array(
                'text' => 'Salon',
                'checked' => $formOptions['class_s']
            )
        );
        // @formatter:on
        echo $formBuilder->checkboxes('Classification', '', key($_classification), $_classification);
        unset($_classification);

        $_max_entries = array('1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5', '6' => '6', '7' => '7', '8' => '8', '9' => '9', '10' => '10');
        echo $formBuilder->select('Max Entries', '', 'max_entries', $_max_entries, $formOptions['max_entries']);
        unset($_max_entries);

        $_judges = array('1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5');
        echo $formBuilder->select('No. Judges', '', 'judges', $_judges, $formOptions['judges']);
        unset($_judges);

        $_special_event = array('special_event' => array('text' => '', 'checked' => $formOptions['special_event']));
        echo $formBuilder->checkboxes('Special Event', '', key($_special_event), $_special_event);
        unset($_special_event);

        echo $formBuilder->closeTable();
        echo $formBuilder->submit('submit', 'Add Competition', array('class' => 'button-primary'));
        echo $formBuilder->hidden('action', 'add');
        $formBuilder->setNonceAction(get_current_user_id());
        echo $formBuilder->fieldNonce();
        echo $formBuilder->close();
        echo '<script type="text/javascript">' . "\n";
        echo 'jQuery(function($) {' . "\n";
        echo '	$( "#date" ).datepicker({ dateFormat: \'yy-mm-dd\', showButtonPanel: true });' . "\n";
        echo '});', "\n";
        echo "</script>";
        $this->displayAdminFooter();
    }

    /**
     * Setup all that is needed for the page "Entries"
     */
    public function actionLoadPagehookEntries()
    {
        global $current_screen;

        $this->rpsdb = $this->container->resolve('\RpsCompetition\Db\RpsDb');
        $this->entries_list = $this->container->resolve('\RpsCompetition\Entries\ListTable');
        $this->handleRequestEntries();

        add_filter('screen_layout_columns', array($this, 'filterScreenLayoutColumns'), 10, 2);
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
    private function handleRequestEntries()
    {
        if (isset($_REQUEST['wp_http_referer'])) {
            $redirect = remove_query_arg(array('wp_http_referer', 'updated', 'delete_count'), stripslashes($_REQUEST['wp_http_referer']));
        } else {
            $redirect = admin_url('admin.php') . '?page=' . Constants::MENU_SLUG_ENTRIES;
        }

        $doAction = $this->entries_list->current_action();
        switch ($doAction) {
            case 'delete':
                check_admin_referer('bulk-entries');
                if (empty($_REQUEST['entries']) && empty($_REQUEST['entry'])) {
                    wp_redirect($redirect);
                    exit();
                }
                break;

            case 'edit':
                if (empty($_REQUEST['entry'])) {
                    wp_redirect($redirect);
                    exit();
                }
                break;
            case 'dodelete':
                check_admin_referer('delete-entries');
                if (empty($_REQUEST['entries'])) {
                    wp_redirect($redirect);
                    exit();
                }
                $entryIds = $_REQUEST['entries'];

                $deleteCount = 0;

                foreach ((array) $entryIds as $id) {
                    $id = (int) $id;
                    $this->rpsdb->deleteEntry($id);
                    ++$deleteCount;
                }
                $redirect = add_query_arg(array('deleteCount' => $deleteCount, 'update' => 'del_many'), $redirect);
                wp_redirect($redirect);
                break;

            default:
                if (!empty($_GET['_wp_http_referer'])) {
                    wp_redirect(remove_query_arg(array('_wp_http_referer', '_wpnonce'), stripslashes($_SERVER['REQUEST_URI'])));
                    exit();
                }
                $pagenum = $this->entries_list->get_pagenum();
                $this->entries_list->prepare_items();
                $total_pages = $this->entries_list->get_pagination_arg('total_pages');
                if ($pagenum > $total_pages && $total_pages > 0) {
                    wp_redirect(add_query_arg('paged', $total_pages));
                    exit();
                }
                break;
        }
    }

    /**
     * Display the page for the menu Entries
     */
    public function menuEntries()
    {
        $doAction = $this->entries_list->current_action();
        switch ($doAction) {
            case 'delete':
                $this->displayPageEntriesDelete();
                break;

            case 'edit':
                $this->displayPageEntriesEdit();
                break;

            default:
                $this->displayPageEntriesList();
                break;
        }
    }

    /**
     * Display the entries in a list
     */
    private function displayPageEntriesList()
    {
        global $screen_layout_columns;

        $messages = array();
        if (isset($_GET['update'])) {
            switch ($_GET['update']) {
                case 'del':
                case 'del_many':
                    $deleteCount = isset($_GET['deleteCount']) ? (int) $_GET['deleteCount'] : 0;
                    $messages[] = '<div id="message" class="updated"><p>' . sprintf(_n('Entry deleted.', '%s entries deleted.', $deleteCount), number_format_i18n($deleteCount)) . '</p></div>';
                    break;
            }
        }

        if (!empty($messages)) {
            foreach ($messages as $msg) {
                echo $msg;
            }
        }

        echo '<div class="wrap avhrps-wrap">';
        echo $this->displayIcon('index');
        echo '<h2>Entries: ' . __('All Entries', 'avh-rps');

        if (isset($_REQUEST['s']) && $_REQUEST['s']) {
            printf('<span class="subtitle">' . sprintf(__('Search results for &#8220;%s&#8221;'), wp_html_excerpt(esc_html(stripslashes($_REQUEST['s'])), 50)) . '</span>');
        }
        echo '</h2>';

        $this->entries_list->views();
        echo '<form id="rps-entries-form" action="" method="get">';
        echo '<input type="hidden" name="page" value="' . Constants::MENU_SLUG_ENTRIES . '">';

        echo '<input type="hidden" name="_total" value="' . esc_attr($this->entries_list->get_pagination_arg('total_items')) . '" />';
        echo '<input type="hidden" name="_per_page" value="' . esc_attr($this->entries_list->get_pagination_arg('per_page')) . '" />';
        echo '<input type="hidden" name="_page" value="' . esc_attr($this->entries_list->get_pagination_arg('page')) . '" />';

        if (isset($_REQUEST['paged'])) {
            echo '<input type="hidden" name="paged"	value="' . esc_attr(absint($_REQUEST['paged'])) . '" />';
        }
        $this->entries_list->display();
        echo '</form>';

        echo '<div id="ajax-response"></div>';
        $this->printAdminFooter();
        echo '</div>';
    }

    /**
     * Display the page to confirm the deletion of the selected entries.
     */
    private function displayPageEntriesDelete()
    {
        global $wpdb;
        $formBuilder = $this->container->resolve('\Avh\Html\FormBuilder');

        if (empty($_REQUEST['entries'])) {
            $entryIdsArray = array(intval($_REQUEST['entry']));
        } else {
            $entryIdsArray = (array) $_REQUEST['entries'];
        }

        $this->displayAdminHeader('Delete Entries');
        echo $formBuilder->open('', array('method' => 'post', 'id' => 'updateentries', 'name' => 'updateentries', 'accept-charset' => get_bloginfo('charset')));

        echo '<p>' . _n('You have specified this entry for deletion:', 'You have specified these entries for deletion:', count($entryIdsArray)) . '</p>';

        $goDelete = 0;
        foreach ($entryIdsArray as $entryID) {

            $entry = $this->rpsdb->getEntryInfo($entryID, OBJECT);
            if ($entry !== null) {
                $user = get_user_by('id', $entry->Member_ID);
                $competition = $this->rpsdb->getCompetitionByID2($entry->Competition_ID, OBJECT);
                echo "<li>";
                echo $formBuilder->hidden('entries[]', $entryID);
                printf(__('ID #%1s: <strong>%2s</strong> by <em>%3s %4s</em> for the competition <em>%5s</em> on %6s'), $entryID, $entry->Title, $user->first_name, $user->last_name, $competition->Theme, mysql2date(get_option('date_format'), $competition->Competition_Date));
                echo "</li>\n";
                $goDelete++;
            }
        }
        if ($goDelete) {
            echo $formBuilder->hidden('action', 'dodelete');
            echo $formBuilder->submit('delete', 'Confirm Deletion', array('class' => 'button-secondary delete'));
        } else {
            echo '<p>There are no valid entries to delete</p>';
        }

        wp_nonce_field('delete-entries');
        echo $this->referer;

        echo $formBuilder->close();
        $this->displayAdminFooter();
    }

    /**
     * Display the page to edit Entries
     */
    private function displayPageEntriesEdit()
    {
        global $wpdb;

        $updated = false;
        // @var $formBuilder AVH_Form
        $formBuilder = $this->container->resolve('\Avh\Html\FormBuilder');
        $formBuilder->setOptionName('entry-edit');

        if (isset($_POST['update'])) {
            $formBuilder->setNonceAction($_POST['entry']);
            check_admin_referer($formBuilder->getNonce_action());
            if (!current_user_can('rps_edit_entries')) {
                wp_die(__('Cheatin&#8217; uh?'));
            }
            $updated = $this->updateEntry();
        }

        $vars = (array('action', 'redirect', 'entry', 'wp_http_referer'));
        for ($i = 0; $i < count($vars); $i += 1) {
            $var = $vars[$i];
            if (empty($_POST[$var])) {
                if (empty($_GET[$var])) {
                    $$var = '';
                } else {
                    $$var = $_GET[$var];
                }
            } else {
                $$var = $_POST[$var];
            }
        }

        $wp_http_referer = remove_query_arg(array('update'), stripslashes($wp_http_referer));
        $entry = $this->rpsdb->getEntryInfo($_REQUEST['entry'], OBJECT);
        $competition = $this->rpsdb->getCompetitionByID2($entry->Competition_ID);

        $this->displayAdminHeader('Edit Entry');

        if (isset($_POST['update'])) {
            echo '<div id="message" class="updated">';
            if ($updated) {
                echo '<p><strong>Entry updated.</strong></p>';
            } else {
                echo '<p><strong>Entry not updated.</strong></p>';
            }
            if ($wp_http_referer) {
                echo '<p><a href="' . esc_url($wp_http_referer) . '">&larr; Back to Entries</a></p>';
            }
            echo '</div>';
        }

        $queryEdit = array('page' => Constants::MENU_SLUG_ENTRIES);
        echo $formBuilder->open(admin_url('admin.php') . '?' . http_build_query($queryEdit, '', '&'), array('method' => 'post', 'id' => 'rps-entryedit', 'accept-charset' => get_bloginfo('charset')));
        echo $formBuilder->openTable();

        $_user = get_user_by('id', $entry->Member_ID);
        echo '<h3>Photographer: ' . $_user->first_name . ' ' . $_user->last_name . "</h3>\n";
        echo "<img src=\"" . $this->core->rpsGetThumbnailUrl(get_object_vars($entry), 200) . "\" />\n";
        echo $formBuilder->text('Title', '', 'title', $entry->Title);

        // @formatter:off
        $medium_array = array(
                'medium_bwd' => 'B&W Digital',
                'medium_cd' => 'Color Digital',
                'medium_bwp' => 'B&W Prints',
                'medium_cp' => 'Color Prints'
            );
        // @formatter:on
        $selectedMedium = array_search($competition->Medium, $medium_array);
        echo $formBuilder->select('Medium', '', 'medium', $medium_array, $selectedMedium, array('autocomplete' => 'off'));

        // @formatter:off
        $_classification = array(
                'class_b' => 'Beginner',
                'class_a' => 'Advanced',
                'class_s' => 'Salon'
            );
        // @formatter:on
        $selectedClassification = array_search($competition->Classification, $_classification);
        echo $formBuilder->select('Classification', '', 'classification', $_classification, $selectedClassification, array('autocomplete' => 'off'));

        echo $formBuilder->closeTable();
        echo $formBuilder->submit('submit', 'Update Entry', array('class' => 'button-primary'));
        if ($wp_http_referer) {
            echo $formBuilder->hidden('wp_http_referer', esc_url($wp_http_referer));
        }
        echo $formBuilder->hidden('entry', $entry->ID);
        echo $formBuilder->hidden('update', true);
        echo $formBuilder->hidden('action', 'edit');
        $formBuilder->setNonceAction($entry->ID);
        echo $formBuilder->fieldNonce();
        echo $formBuilder->close();
        $this->displayAdminFooter();
    }

    /**
     * Update an entry after a POST
     *
     * @return boolean
     */
    private function updateEntry()
    {
        $formOptions = $_POST['entry-edit'];
        $id = (int) $_POST['entry'];
        $entry = $this->rpsdb->getEntryInfo($id);

        $return = false;
        $formOptionsNew['title'] = empty($formOptions['title']) ? $entry['Title'] : $formOptions['title'];
        if ($entry['Title'] != $formOptionsNew['title']) {
            $data = array('ID' => $id, 'Title' => $formOptionsNew['title']);
            $return = $this->rpsdb->updateEntry($data);
        }

        return $return;
    }

    /**
     * Add row action link to users list to display all their entries.
     *
     * @param unknown $actions
     * @param unknown $user
     * @return string
     */
    public function filterRpsUserActionLinks($actions, $user)
    {
        $link = admin_url() . "?page=avh-rps-entries&user_id=" . $user->ID;
        $actions['entries'] = "<a href='$link'>Entries</a>";

        return $actions;
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
    public function filterPluginActions($links)
    {
        $folder = Common::getBaseDirectory($this->settings->plugin_basename);
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
    public function filterSetScreenOption($error_value, $option, $value)
    {
        $return = $error_value;
        switch ($option) {
            case 'competitions_per_page':
                $value = (int) $value;
                $return = $value;
                if ($value < 1 || $value > 999) {
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
     *            $screen
     * @return strings
     */
    public function filterScreenLayoutColumns($columns, $screen)
    {
        switch ($screen) {
            // case $this->hooks['avhrps_menu_competition']:
            // $columns[$this->hooks['avhfdas_menu_overview']] = 1;
            // break;
            // case $this->hooks['avhrps_menu_competition_add']:
            // $columns[$this->hooks['avhfdas_menu_general']] = 1;
            // break;
        }

        return $columns;
    }

    /**
     * Show the Classification meta on the user profile page.
     *
     * @param int $user_id
     */
    public function actionUserProfile($user_id)
    {
        $userID = $user_id->ID;
        $_rps_class_bw = get_user_meta($userID, 'rps_class_bw', true);
        $_rps_class_color = get_user_meta($userID, 'rps_class_color', true);
        $_rps_class_print_bw = get_user_meta($userID, 'rps_class_print_bw', true);
        $_rps_class_print_color = get_user_meta($userID, 'rps_class_print_color', true);

        $_classification = array('beginner' => 'Beginner', 'advanced' => 'Advanced', 'salon' => 'Salon');
        echo '<h3 id="rps">Competition Classification</h3>';
        echo '<table class="form-table">';

        echo '<tr>';
        echo '<th>Classification Digital B&W</th>';
        echo '<td>';
        if (current_user_can('rps_edit_competition_classification')) {
            $p = '';
            $r = '';
            echo '<select name="rps_class_bw" id="rps_class_bw">';
            foreach ($_classification as $key => $value) {
                if ($key === $_rps_class_bw) {
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
        if (current_user_can('rps_edit_competition_classification')) {
            $p = '';
            $r = '';
            echo '<select name="rps_class_color" id="rps_class_color">';
            foreach ($_classification as $key => $value) {
                if ($key === $_rps_class_color) {
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
        if (current_user_can('rps_edit_competition_classification')) {
            $p = '';
            $r = '';
            echo '<select name="rps_class_print_bw" id="rps_class_print_bw">';
            foreach ($_classification as $key => $value) {
                if ($key === $_rps_class_print_bw) {
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
        if (current_user_can('rps_edit_competition_classification')) {
            $p = '';
            $r = '';
            echo '<select name="rps_class_print_color" id="rps_class_print_color">';
            foreach ($_classification as $key => $value) {
                if ($key === $_rps_class_print_color) {
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

    /**
     * Update the user meta concerning Classification when a user is updated.
     *
     * @param int $user_id
     */
    public function actionProfileUpdateSave($user_id)
    {
        $userID = $user_id;
        if (isset($_POST['rps_class_bw'])) {
            $_rps_class_bw = $_POST["rps_class_bw"];
        } else {
            $_rps_class_bw = get_user_meta($userID, 'rps_class_bw', true);
        }
        if (isset($_POST['rps_class_color'])) {
            $_rps_class_color = $_POST['rps_class_color'];
        } else {
            $_rps_class_color = get_user_meta($userID, 'rps_class_color', true);
        }
        if (isset($_POST['rps_class_print_bw'])) {
            $_rps_class_print_bw = $_POST["rps_class_print_bw"];
        } else {
            $_rps_class_print_bw = get_user_meta($userID, 'rps_class_print_bw', true);
        }
        if (isset($_POST['rps_class_print_color'])) {
            $_rps_class_print_color = $_POST['rps_class_print_color'];
        } else {
            $_rps_class_print_color = get_user_meta($userID, 'rps_class_print_color', true);
        }

        update_user_meta($userID, "rps_class_bw", $_rps_class_bw);
        update_user_meta($userID, "rps_class_color", $_rps_class_color);
        update_user_meta($userID, "rps_class_print_bw", $_rps_class_print_bw);
        update_user_meta($userID, "rps_class_print_color", $_rps_class_print_color);
    }

    /**
     * Update a comeptition after a POST
     */
    private function updateCompetition()
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

        $_medium = array('medium_bwd' => 'B&W Digital', 'medium_cd' => 'Color Digital', 'medium_bwp' => 'B&W Prints', 'medium_cp' => 'Color Prints');
        $selectedMedium = array_search($competition->Medium, $_medium);

        $_classification = array('class_b' => 'Beginner', 'class_a' => 'Advanced', 'class_s' => 'Salon');
        $data['ID'] = $_REQUEST['competition'];
        $data['Competition_Date'] = $formOptionsNew['date'];
        $data['Close_Date'] = $formOptionsNew['close-date'] . ' ' . $formOptionsNew['close-time'];
        $data['Theme'] = $formOptionsNew['theme'];
        $data['Max_Entries'] = $formOptionsNew['max_entries'];
        $data['Num_Judges'] = $formOptionsNew['judges'];
        $data['Special_Event'] = ($formOptionsNew['special_event'] ? 'Y' : 'N');
        $data['Closed'] = ($formOptionsNew['closed'] ? 'Y' : 'N');
        $data['Scored'] = ($formOptionsNew['scored'] ? 'Y' : 'N');
        $data['Medium'] = $_medium[$formOptionsNew['medium']];
        $data['Classification'] = $_classification[$formOptionsNew['classification']];
        $competition_ID = $this->rpsdb->insertCompetition($data);

        if (is_wp_error($competition_ID)) {
            return false;
        } else {
            return true;
        }
    }
    // ############ Admin WP Helper ##############

    /**
     * Display plugin Copyright
     */
    private function printAdminFooter()
    {
        echo '<div class="clear"></div>';
        echo '<p class="footer_avhfdas">';
        printf('&copy; Copyright 2012 <a href="http://blog.avirtualhome.com/" title="My Thoughts">Peter van der Does</a> | AVH RPS Competition version %s', Constants::PLUGIN_VERSION);
        echo '</p>';
    }

    /**
     * Display WP alert
     */
    private function displayMessage()
    {
        $message = '';
        if (is_array($this->message)) {
            foreach ($this->message as $key => $_msg) {
                $message .= $_msg . "<br>";
            }
        } else {
            $message = $this->message;
        }

        if ($message != '') {
            $status = $this->status;
            $this->message = $this->status = ''; // Reset
            $status = ($status != '') ? $status : 'updated fade';
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
    private function displayIcon($icon)
    {
        return ('<div class="icon32" id="icon-' . $icon . '"><br/></div>');
    }

    /**
     * Display error message at bottom of comments.
     *
     * @param string $msg
     *            Error Message. Assumed to contain HTML and be sanitized.
     */
    private function displayCommentFooterDie($msg)
    {
        echo "<div class='wrap'><p>$msg</p></div>";
        die();
    }

    /**
     * Generates the header for admin pages
     *
     * @param string $title
     *            The title to show in the main heading.
     * @param bool $form
     *            Whether or not the form should be included.
     * @param string $option
     *            The long name of the option to use for the current page.
     * @param string $optionshort
     *            The short name of the option to use for the current page.
     * @param bool $contains_files
     *            Whether the form should allow for file uploads.
     */
    public function displayAdminHeader($title)
    {
        echo '<div class="wrap">';
        echo $this->displayIcon('options-general');
        echo '<h2 id="rps-title">' . $title . '</h2>';
        echo '<div id="rps_content_top" class="postbox-container" style="width:100%;">';
        echo '<div class="metabox-holder">';
        echo '<div class="meta-box-sortables">';
    }

    /**
     * Generates the footer for admin pages
     *
     * @param bool $submit
     *            Whether or not a submit button should be shown.
     * @param text $text
     *            The text to be shown in the submit button.
     */
    public function displayAdminFooter()
    {
        echo '</div></div></div>';
        // $this->admin_sidebar();
        $this->printAdminFooter();
        echo '</div>';
    }
}
