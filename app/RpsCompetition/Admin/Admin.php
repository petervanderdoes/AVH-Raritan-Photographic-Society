<?php
namespace RpsCompetition\Admin;

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use RpsCompetition\Common\Core;
use RpsCompetition\Competition\ListTable as CompetitionListTable;
use RpsCompetition\Constants;
use RpsCompetition\Db\QueryCompetitions;
use RpsCompetition\Db\QueryEntries;
use RpsCompetition\Db\RpsDb;
use RpsCompetition\Entries\ListTable as EntriesListTable;
use RpsCompetition\Settings;
use Valitron\Validator;

/* @var $formBuilder \Avh\Html\FormBuilder */
final class Admin
{
    /**
     * @var CompetitionListTable
     */
    private $competition_list;
    /**
     * @var Container
     */
    private $container;
    /**
     * @var Core
     */
    private $core;
    /**
     * @var EntriesListTable
     */
    private $entries_list;
    private $hooks = array();
    private $message = '';
    private $referer;
    /**
     * @var Request
     */
    private $request;
    /**
     * @var RpsDb
     */
    private $rpsdb;
    /** @var  \Avh\DataHandler\DataHandler */
    private $settings;
    private $status = '';

    /**
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;

        // The Settings Registery
        $this->settings = $this->container->make('RpsCompetition\Settings');
        $this->request = $this->container->make('Illuminate\Http\Request');

        // Loads the CORE class
        $this->core = $this->container->make('RpsCompetition\Common\Core');
        // Admin URL and Pagination
        $this->core->admin_base_url = $this->settings->get('siteurl') . '/wp-admin/admin.php?page=';

        // Admin menu
        add_action('admin_menu', array($this, 'actionAdminMenu'));
        add_action('admin_init', array($this, 'handleActionInit'));

        add_action('wp_ajax_setscore', array($this, 'handleAjax'));
        add_filter('user_row_actions', array($this, 'filterRpsUserActionLinks'), 10, 2);

        add_action('user_register', array($this, 'actionAddUserMeta'));
    }

    /**
     * Setup User metadata
     *
     * @param integer $userID
     *
     * @internal Hook: user_register
     */
    public function actionAddUserMeta($userID)
    {
        update_user_meta($userID, "rps_class_bw", 'beginner');
        update_user_meta($userID, "rps_class_color", 'beginner');
        update_user_meta($userID, "rps_class_print_bw", 'beginner');
        update_user_meta($userID, "rps_class_print_color", 'beginner');
    }

    /**
     * Add the Tools and Options to the Management and Options page respectively.
     * Setup the Admin Menu pages
     *
     * @internal Hook: admin_menu
     */
    public function actionAdminMenu()
    {
        wp_register_style('avhrps-admin-css', plugins_url('/css/avh-rps.admin.css', $this->settings->get('plugin_basename')), array('wp-admin'), Constants::PLUGIN_VERSION, 'screen');
        wp_register_style('avhrps-jquery-css', plugins_url('/css/smoothness/jquery-ui-1.8.22.custom.css', $this->settings->get('plugin_basename')), array('wp-admin'), '1.8.22', 'screen');
        wp_register_script('avhrps-comp-ajax', plugins_url('/js/avh-rps.admin.ajax.js', $this->settings->get('plugin_basename')), array('jquery'), false, true);

        add_menu_page('All Competitions', 'Competitions', 'rps_edit_competitions', Constants::MENU_SLUG_COMPETITION, array($this, 'menuCompetition'), '', Constants::MENU_POSITION_COMPETITION);

        $this->hooks['avhrps_menu_competition'] = add_submenu_page(Constants::MENU_SLUG_COMPETITION, 'All Competitions', 'All Competitions', 'rps_edit_competitions', Constants::MENU_SLUG_COMPETITION, array($this, 'menuCompetition'));
        $this->hooks['avhrps_menu_competition_add'] = add_submenu_page(Constants::MENU_SLUG_COMPETITION,
                                                                       'Add Competition',
                                                                       'Add Competition',
                                                                       'rps_edit_competitions',
                                                                       Constants::MENU_SLUG_COMPETITION_ADD,
                                                                       array($this, 'menuCompetitionAdd'));

        add_action('load-' . $this->hooks['avhrps_menu_competition'], array($this, 'actionLoadPagehookCompetition'));
        add_action('load-' . $this->hooks['avhrps_menu_competition_add'], array($this, 'actionLoadPagehookCompetitionAdd'));

        add_menu_page('All Entries', 'Entries', 'rps_edit_entries', Constants::MENU_SLUG_ENTRIES, array($this, 'menuEntries'), '', Constants::MENU_POSITION_ENTRIES);
        $this->hooks['avhrps_menu_entries'] = add_submenu_page(Constants::MENU_SLUG_ENTRIES, 'All Entries', 'All Entries', 'rps_edit_entries', Constants::MENU_SLUG_ENTRIES, array($this, 'menuEntries'));
        add_action('load-' . $this->hooks['avhrps_menu_entries'], array($this, 'actionLoadPagehookEntries'));
    }

    /**
     * Add the actions needed for to extended the user profile
     *
     * @see handleActionInit
     */
    public function actionInitUserFields()
    {
        add_action('edit_user_profile', array($this, 'actionUserProfile'));
        add_action('show_user_profile', array($this, 'actionUserProfile'));
        add_action('personal_options_update', array($this, 'actionProfileUpdateSave'));
        add_action('edit_user_profile_update', array($this, 'actionProfileUpdateSave'));
    }

    /**
     * Setup all that is needed for the page Competition
     *
     * @internal Hook: load-{page}
     */
    public function actionLoadPagehookCompetition()
    {
        $this->rpsdb = $this->container->make('RpsCompetition\Db\RpsDb');
        $this->competition_list = $this->container->make('RpsCompetition\Competition\ListTable');

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
     * Setup all that is needed for the page "Add competition"
     *
     * @internal Hook: load-{page}
     */
    public function actionLoadPagehookCompetitionAdd()
    {
        $this->rpsdb = $this->container->make('RpsCompetition\Db\RpsDb');
        $this->competition_list = $this->container->make('RpsCompetition\Competition\ListTable');

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
     * Setup all that is needed for the page "Entries"
     *
     * @internal Hook: load-{page}
     */
    public function actionLoadPagehookEntries()
    {
        $this->rpsdb = $this->container->make('RpsCompetition\Db\RpsDb');
        $this->entries_list = $this->container->make('RpsCompetition\Entries\ListTable');
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
     * Update the user meta concerning Classification when a user is updated.
     *
     * @param integer $user_id
     *
     * @internal Hook: personal_options_update
     * @internal Hook: edit_user_profile_update
     */
    public function actionProfileUpdateSave($user_id)
    {
        $userID = $user_id;
        $rps_class_bw = $this->request->input('rps_class_bw', get_user_meta($userID, 'rps_class_bw', true));
        $rps_class_color = $this->request->input('rps_class_color', get_user_meta($userID, 'rps_class_color', true));
        $rps_class_print_bw = $this->request->input('rps_class_print_bw', get_user_meta($userID, 'rps_class_print_bw', true));
        $rps_class_print_color = $this->request->input('rps_class_print_color', get_user_meta($userID, 'rps_class_print_color', true));

        update_user_meta($userID, "rps_class_bw", $rps_class_bw);
        update_user_meta($userID, "rps_class_color", $rps_class_color);
        update_user_meta($userID, "rps_class_print_bw", $rps_class_print_bw);
        update_user_meta($userID, "rps_class_print_color", $rps_class_print_color);
    }

    /**
     * Show the Classification meta on the user profile page.
     *
     * @param integer $user_id
     *
     * @internal Hook: edit_user_profile
     * @internal Hook: show_user_profile
     */
    public function actionUserProfile($user_id)
    {
        $userID = $user_id->ID;

        $classification = array('beginner' => 'Beginner', 'advanced' => 'Advanced', 'salon' => 'Salon');

        $formBuilder = $this->container->make('Avh\Html\FormBuilder');

        echo '<h3 id="rps">Competition Classification</h3>';
        echo $formBuilder->openTable();

        $all_classifications = array(
            array('label' => 'Classification Digital B&W', 'name' => 'rps_class_bw', 'selected' => get_user_meta($userID, 'rps_class_bw', true)),
            array('label' => 'Classification Digital Color', 'name' => 'rps_class_color', 'selected' => get_user_meta($userID, 'rps_class_color', true)),
            array('label' => 'Classification Print B&W', 'name' => 'rps_class_print_bw', 'selected' => get_user_meta($userID, 'rps_class_print_bw', true)),
            array('label' => 'Classification Print Color', 'name' => 'rps_class_print_color', 'selected' => get_user_meta($userID, 'rps_class_print_color', true)),
        );

        foreach ($all_classifications as $data) {

            if (current_user_can('rps_edit_competition_classification')) {
                echo $formBuilder->outputLabel($formBuilder->label($data['name'], $data['name']));
                echo $formBuilder->outputField($formBuilder->select($data['name'], $classification, $data['selected']));
            } else {
                echo '<tr>';
                echo '<th>' . $data['label'] . '</th>';
                echo '<td>' . $classification[$data['selected']] . '</td>';
                echo '</tr>';
            }
        }

        echo $formBuilder->closeTable();
    }

    /**
     * Generates the footer for admin pages.
     */
    public function displayAdminFooter()
    {
        echo '</div></div></div>';
        // $this->admin_sidebar();
        $this->printAdminFooter();
        echo '</div>';
    }

    /**
     * Generates the header for admin pages
     *
     * @param string $title The title to show in the main heading.
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
     * Add row action link to users list to display all their entries.
     *
     * @param unknown $actions
     * @param unknown $user
     *
     * @internal Hook: user_row_actions
     * @return string
     */
    public function filterRpsUserActionLinks($actions, $user)
    {
        $link = admin_url() . "?page=avh-rps-entries&user_id=" . $user->ID;
        $actions['entries'] = "<a href='$link'>Entries</a>";

        return $actions;
    }

    /**
     * Sets the amount of columns wanted for a particuler screen
     *
     * @see      filter screen_meta_screen
     *
     * @param int $columns
     * @param int $screen
     *
     * @internal Hook: screen_layout_columns
     * @return string
     */
    public function filterScreenLayoutColumns($columns, $screen)
    {
        //switch ($screen) {
        // We can define columns here
        // case $this->hooks['avhrps_menu_competition']:
        // $columns[$this->hooks['avhfdas_menu_overview']] = 1;
        // break;
        // case $this->hooks['avhrps_menu_competition_add']:
        // $columns[$this->hooks['avhfdas_menu_general']] = 1;
        // break;
        //}

        return $columns;
    }

    /**
     * Used when we set our own screen options.
     * The filter needs to be set during construct otherwise it's not recognized.
     *
     * @param int    $error_value
     * @param string $option
     * @param int    $value
     *
     * @internal Hook:
     * @return int
     */
    public function filterSetScreenOption($error_value, $option, $value)
    {
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
     * Runs during the action init.
     *
     * @internal Hook: admin_init
     */
    public function handleActionInit()
    {
        $this->initRoles();
        $this->actionInitUserFields();

        return;
    }

    /**
     * Handle the Ajax callback
     *
     * @internal Hook: wp_ajax_setscore
     */
    public function handleAjax()
    {
        $query_competitions = new QueryCompetitions($this->rpsdb);
        $this->rpsdb = $this->container->make('RpsCompetition\Db\RpsDb');
        if ($this->request->has('scored')) {
            $data = array();
            $response = '';
            $result = null;
            if ($this->request->input('scored') == 'Yes') {
                $data['ID'] = (int) $this->request->input('id');
                $data['Scored'] = 'N';
                $result = $query_competitions->insertCompetition($data);
                $response = json_encode(array('text' => 'N', 'scored' => 'No', 'scoredtext' => 'Yes'));
            }
            if ($this->request->input('scored') == 'No') {
                $data['ID'] = (int) $this->request->input('id');
                $data['Scored'] = 'Y';
                $result = $query_competitions->insertCompetition($data);
                $response = json_encode(array('text' => 'Y', 'scored' => 'Yes', 'scoredtext' => 'No'));
            }
            if (is_wp_error($result)) {
                echo 'Error updating competition';
            } else {
                echo $response;
            }
        }
        unset($query_competitions);
        die();
    }

    /**
     * Setup Roles
     *
     * @see handleActionInit
     */
    public function initRoles()
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
     * Show the page to add a competition
     */
    public function menuCompetitionAdd()
    {
        $query_competitions = new QueryCompetitions($this->rpsdb);

        $data = array();

        $formBuilder = $this->container->make('Avh\Html\FormBuilder');
        $formBuilder->setOptionName('competition_add');

        $formDefaultOptions = array(
            'date'           => '',
            'theme'          => '',
            'medium'         => array(
                'medium_bwd' => true,
                'medium_cd'  => true,
                'medium_bwp' => true,
                'medium_cp'  => true
            ),
            'classification' => array(
                'class_b' => true,
                'class_a' => true,
                'class_s' => true
            ),
            'max-entries'    => '2',
            'judges'         => '1',
            'special-event'  => false
        );

        $formOptions = $formDefaultOptions;
        if ($this->request->has('action')) {
            switch ($this->request->input('action')) {
                case 'add':
                    check_admin_referer(get_current_user_id());
                    $formNewOptions = $this->request->input($formBuilder->getOptionName());

                    $v = new Validator($formNewOptions);
                    $v->rule('required', 'date')
                        ->message('{field} is required')
                        ->label('Date');
                    $v->rule('dateFormat', 'date', 'Y-m-d')
                        ->message('{field} should be in Y-m-d format')
                        ->label('Date');
                    $v->rule('required', 'theme')
                        ->message('{field} is required')
                        ->label('Theme');
                    $v->rule('required', 'medium')->message('No medium selected. At least one medium needs to be selected');
                    $v->rule('required', 'classification')->message('No classification selected. At least one classification needs to be selected');
                    $v->validate();

                    foreach ($formDefaultOptions['medium'] as $key => $value) {
                        $formNewOptions['medium'][$key] = avh_array_get($formNewOptions, 'medium.' . $key, false);
                    }
                    foreach ($formDefaultOptions['classification'] as $key => $value) {
                        $formNewOptions['classification'][$key] = avh_array_get($formNewOptions, 'classification.' . $key, false);
                    }
                    $formNewOptions['special-event'] = avh_array_get($formNewOptions, 'special-event', false);

                    $x = $v->errors();
                    if (empty($x)) {
                        $this->message = 'Competition Added';
                        $this->status = 'updated';

                        // @TODO: This is needed because of the old program, someday it needs to be cleaned up.
                        $medium_convert = array(
                            'medium_bwd' => 'B&W Digital',
                            'medium_cd'  => 'Color Digital',
                            'medium_bwp' => 'B&W Prints',
                            'medium_cp'  => 'Color Prints'
                        );

                        $classification_convert = array(
                            'class_b' => 'Beginner',
                            'class_a' => 'Advanced',
                            'class_s' => 'Salon'
                        );

                        $data['Competition_Date'] = $formNewOptions['date'];
                        $data['Theme'] = $formNewOptions['theme'];
                        $data['Max_Entries'] = $formNewOptions['max-entries'];
                        $data['Num_Judges'] = $formNewOptions['judges'];
                        $data['Special_Event'] = ($formNewOptions['special-event'] ? 'Y' : 'N');
                        foreach ($formNewOptions['medium'] as $medium_key => $value) {
                            $data['Medium'] = $medium_convert[$medium_key];
                            foreach ($formNewOptions['classification'] as $classification_key => $value) {
                                $data['Classification'] = $classification_convert[$classification_key];
                                $competition_ID = $query_competitions->insertCompetition($data);
                                if (is_wp_error($competition_ID)) {
                                    wp_die($competition_ID);
                                }
                            }
                        }
                    } else {
                        $this->message = $v->errors();
                        $this->status = 'error';
                    }
                    $this->displayMessage();
                    $formOptions = $formNewOptions;
                    break;
            }
            unset($query_competitions);
        }

        $this->displayAdminHeader('Add Competition');

        echo $formBuilder->open(admin_url('admin.php') . '?page=' . Constants::MENU_SLUG_COMPETITION_ADD, array('method' => 'post', 'id' => 'rps-competitionadd', 'accept-charset' => get_bloginfo('charset')));
        echo $formBuilder->openTable();
        echo $formBuilder->outputLabel($formBuilder->label('date', 'Date'));
        echo $formBuilder->outputField($formBuilder->text('date', $formOptions['date']));
        echo $formBuilder->outputLabel($formBuilder->label('theme', 'Theme'));
        echo $formBuilder->outputField($formBuilder->text('theme', $formOptions['theme'], array('maxlength' => '32')));

        $array_medium = array(
            'medium_bwd' => array(
                'text'    => 'B&W Digital',
                'value'   => $formOptions['medium']['medium_bwd'],
                'checked' => $formOptions['medium']['medium_bwd']
            ),
            'medium_cd'  => array(
                'text'    => 'Color Digital',
                'value'   => $formOptions['medium']['medium_cd'],
                'checked' => $formOptions['medium']['medium_cd']
            ),
            'medium_bwp' => array(
                'text'    => 'B&W Print',
                'value'   => $formOptions['medium']['medium_bwp'],
                'checked' => $formOptions['medium']['medium_bwp']
            ),
            'medium_cp'  => array(
                'text'    => 'Color Digital',
                'value'   => $formOptions['medium']['medium_cp'],
                'checked' => $formOptions['medium']['medium_cp']
            )
        );

        echo $formBuilder->outputLabel($formBuilder->label('medium', 'Medium'));
        echo $formBuilder->checkboxes('medium', $array_medium);
        unset($array_medium);

        $array_classification = array(
            'class_b' => array(
                'text'    => 'Beginner',
                'value'   => $formOptions['classification']['class_b'],
                'checked' => $formOptions['classification']['class_b']
            ),
            'class_a' => array(
                'text'    => 'Advanced',
                'value'   => $formOptions['classification']['class_a'],
                'checked' => $formOptions['classification']['class_a']
            ),
            'class_s' => array(
                'text'    => 'Salon',
                'value'   => $formOptions['classification']['class_s'],
                'checked' => $formOptions['classification']['class_s']
            )
        );

        echo $formBuilder->outputLabel($formBuilder->label('classification', 'Classification'));
        echo $formBuilder->checkboxes('classification', $array_classification);
        unset($array_classification);

        $array_max_entries = array('1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5', '6' => '6', '7' => '7', '8' => '8', '9' => '9', '10' => '10');
        echo $formBuilder->outputLabel($formBuilder->label('max-entries', 'Max Entries'));
        echo $formBuilder->outputField($formBuilder->select('max-entries', $array_max_entries, $formOptions['max-entries']));
        unset($array_max_entries);

        $array_judges = array('1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5');
        echo $formBuilder->outputLabel($formBuilder->label('judges', 'No. Judges'));
        echo $formBuilder->outputField($formBuilder->select('judges', $array_judges, $formOptions['judges']));
        unset($array_judges);

        $array_special_event = array('special-event' => array('text' => '', 'checked' => $formOptions['special-event']));
        echo $formBuilder->outputLabel($formBuilder->label('special-event', 'Special Event'));
        echo $formBuilder->outputField($formBuilder->checkbox('special-event', $formOptions['special-event'], $formOptions['special-event']));
        unset($array_special_event);

        echo $formBuilder->closeTable();
        echo $formBuilder->submit('submit', 'Add Competition', array('class' => 'button-primary'));
        echo $formBuilder->hidden('action', 'add');
        echo $formBuilder->fieldNonce(get_current_user_id());
        echo $formBuilder->close();
        echo '<script type="text/javascript">' . "\n";
        echo 'jQuery(function($) {' . "\n";
        echo '	$( "#date" ).datepicker({ dateFormat: \'yy-mm-dd\', showButtonPanel: true });' . "\n";
        echo '});', "\n";
        echo "</script>";
        $this->displayAdminFooter();
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
     * Displays the icon needed.
     * Using this instead of core in case we ever want to show our own icons
     *
     * @param string $icon
     *
     * @return string
     */
    private function displayIcon($icon)
    {
        return ('<div class="icon32" id="icon-' . $icon . '"><br/></div>');
    }

    /**
     * Display WP alert
     */
    private function displayMessage()
    {
        $message = '';
        if (is_array($this->message)) {
            foreach ($this->message as $_msg) {
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
     * Display the page to confirm the deletion of the selected competitions.
     */
    private function displayPageCompetitionDelete()
    {
        global $wpdb;

        $query_entries = new QueryEntries($this->rpsdb);
        $query_competitions = new QueryCompetitions($this->rpsdb);

        if (!$this->request->has('competitions')) {
            $competitionIdsArray = array(intval($this->request->input('competition')));
        } else {
            $competitionIdsArray = (array) $this->request->input('competitions');
        }

        $formBuilder = $this->container->make('Avh\Html\FormBuilder');

        $this->displayAdminHeader('Delete Competitions');
        echo $formBuilder->open('', array('method' => 'post', 'id' => 'updatecompetitions', 'name' => 'updatecompetitions', 'accept-charset' => get_bloginfo('charset')));
        wp_nonce_field('delete-competitions');
        echo $this->referer;

        echo '<p>' . _n('You have specified this competition for deletion:', 'You have specified these competitions for deletion:', count($competitionIdsArray)) . '</p>';

        $goDelete = 0;
        foreach ($competitionIdsArray as $competitionID) {

            $sqlWhere = $wpdb->prepare('Competition_ID=%d', $competitionID);
            $entries = $query_entries->query(array('where' => $sqlWhere, 'count' => true));
            $sqlWhere = $wpdb->prepare('ID=%d', $competitionID);
            $competition = $query_competitions->query(array('where' => $sqlWhere));
            $competition = $competition[0];
            if ($entries !== "0") {
                echo "<li>" . sprintf(__('ID #%1s: %2s - %3s - %4s -%5s <strong>This competition will not be deleted. It still has %6s entries.</strong>'),
                                      $competitionID,
                                      mysql2date(get_option('date_format'), $competition->Competition_Date),
                                      $competition->Theme,
                                      $competition->Classification,
                                      $competition->Medium,
                                      $entries) . "</li>\n";
            } else {
                echo "<li><input type=\"hidden\" name=\"competitions[]\" value=\"" . esc_attr($competitionID) . "\" />" . sprintf(__('ID #%1s: %2s - %3s - %4s - %5s'),
                                                                                                                                  $competitionID,
                                                                                                                                  mysql2date(get_option('date_format'), $competition->Competition_Date),
                                                                                                                                  $competition->Theme,
                                                                                                                                  $competition->Classification,
                                                                                                                                  $competition->Medium) . "</li>\n";
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
        unset($query_entries, $query_competitions);
    }

    /**
     * Display the page to edit a competition.

     */
    private function displayPageCompetitionEdit()
    {
        /**
         * @var string $wp_http_referer
         */
        $query_competitions = new QueryCompetitions($this->rpsdb);

        $formBuilder = $this->container->make('Avh\Html\FormBuilder');
        $formBuilder->setOptionName('competition-edit');

        $updated = false;
        $formOptions = array();
        if ($this->request->has('update')) {
            $updated = $this->updateCompetition();
        }
        $vars = (array('action', 'redirect', 'competition', 'wp_http_referer'));
        for ($i = 0; $i < count($vars); $i += 1) {
            $var = $vars[$i];
            $$var = $this->request->input($var, '');
        }

        $wp_http_referer = remove_query_arg(array('update'), stripslashes($wp_http_referer));

        $competition = $query_competitions->getCompetitionById($this->request->input('competition'));

        $formOptions['date'] = mysql2date('Y-m-d', $competition->Competition_Date);
        $formOptions['close-date'] = mysql2date('Y-m-d', $competition->Close_Date);
        $formOptions['close-time'] = mysql2date('H:i:s', $competition->Close_Date);

        $this->displayAdminHeader('Edit Competition');

        if ($this->request->has('update')) {
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
        echo $formBuilder->outputLabel($formBuilder->label('date', 'Date'));
        echo $formBuilder->outputField($formBuilder->text('date', $formOptions['date']));
        echo $formBuilder->outputLabel($formBuilder->label('theme', 'Theme'));
        echo $formBuilder->outputField($formBuilder->text('theme', $competition->Theme, array('maxlength' => '32')));
        echo $formBuilder->outputLabel($formBuilder->label('close-date', 'Closing Date'));
        echo $formBuilder->outputField($formBuilder->text('close-date', $formOptions['close-date']));

        $time = array();
        for ($hour = 0; $hour <= 23; $hour++) {
            $time_val = sprintf("%02d:00:00", $hour);
            $time_text = date("g:i a", strtotime($time_val));
            $time[$time_val] = $time_text;
        }
        // echo $formBuilder->select('Closing Time', 'close-time', $time, $formOptions['close-time'], array('autocomplete' => 'off'));
        echo $formBuilder->outputLabel($formBuilder->label('close-time', 'Closing Time'));
        echo $formBuilder->outputField($formBuilder->select('close-time', $time, $formOptions['close-time']));

        $_medium = array(
            'medium_bwd' => 'B&W Digital',
            'medium_cd'  => 'Color Digital',
            'medium_bwp' => 'B&W Prints',
            'medium_cp'  => 'Color Prints'
        );

        $selectedMedium = array_search($competition->Medium, $_medium);
        echo $formBuilder->outputLabel($formBuilder->label('medium', 'Medium'));
        echo $formBuilder->outputField($formBuilder->select('medium', $_medium, $selectedMedium, array('autocomplete' => 'off')));

        $_classification = array(
            'class_b' => 'Beginner',
            'class_a' => 'Advanced',
            'class_s' => 'Salon'
        );

        $selectedClassification = array_search($competition->Classification, $_classification);
        echo $formBuilder->outputLabel($formBuilder->label('classification', 'Classification'));
        echo $formBuilder->outputField($formBuilder->select('classification', $_classification, $selectedClassification, array('autocomplete' => 'off')));

        $_max_entries = array('1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5', '6' => '6', '7' => '7', '8' => '8', '9' => '9', '10' => '10');
        echo $formBuilder->outputLabel($formBuilder->label('max-entries', 'Max Entries'));
        echo $formBuilder->outputField($formBuilder->select('max-entries', $_max_entries, $competition->Max_Entries, array('autocomplete' => 'off')));

        $_judges = array('1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5');
        echo $formBuilder->outputLabel($formBuilder->label('judges', 'No. Judges'));
        echo $formBuilder->outputField($formBuilder->select('judges', $_judges, $competition->Num_Judges, array('autocomplete' => 'off')));

        $_special_event = array('special-event' => array('text' => '', 'checked' => $competition->Special_Event));
        echo $formBuilder->outputLabel($formBuilder->label('special-event', 'Special Event'));
        echo $formBuilder->outputField($formBuilder->checkbox('special-event', $_special_event));

        $_closed = array('closed' => array('text' => '', 'checked' => ($competition->Closed == 'Y' ? true : false)));
        echo $formBuilder->outputLabel($formBuilder->label('closed', 'Closed'));
        echo $formBuilder->outputField($formBuilder->checkbox('closed', $_closed));

        $_scored = array('scored' => array('text' => '', 'checked' => ($competition->Scored == 'Y' ? true : false)));
        echo $formBuilder->outputLabel($formBuilder->label('scored', 'Scored'));
        echo $formBuilder->outputField($formBuilder->checkbox('scored', $_scored));

        echo $formBuilder->closeTable();
        echo $formBuilder->submit('submit', 'Update Competition', array('class' => 'button-primary'));
        if ($wp_http_referer) {
            echo $formBuilder->hidden('wp_http_referer', esc_url($wp_http_referer));
        }
        echo $formBuilder->hidden('competition', $competition->ID);
        echo $formBuilder->hidden('update', true);
        echo $formBuilder->hidden('action', 'edit');
        echo $formBuilder->fieldNonce($competition->ID);
        echo $formBuilder->close();
        echo '<script type="text/javascript">' . "\n";
        echo 'jQuery(function($) {' . "\n";
        echo ' $.datepicker.setDefaults({' . "\n";
        echo '   dateFormat: \'yy-mm-dd\', ' . "\n";
        echo '   showButtonPanel: true, ' . "\n";
        echo '   buttonImageOnly: true, ' . "\n";
        echo '   buttonImage: "' . plugins_url("/images/calendar.png", $this->settings->get('plugin_basename')) . '", ' . "\n";
        echo '   showOn: "both"' . "\n";
        echo ' });' . "\n";
        echo '	$( "#date" ).datepicker();' . "\n";
        echo '	$( "#close-date" ).datepicker();' . "\n";
        echo '});', "\n";
        echo "</script>";
        $this->displayAdminFooter();

        unset($query_competitions);
    }

    /**
     * Display the competition in a list
     */
    private function displayPageCompetitionList()
    {
        $messages = array();
        if ($this->request->has('update')) {
            switch ($this->request->input('update')) {
                case 'del':
                case 'del_many':
                    $deleteCount = (int) $this->request->input('deleteCount', 0);
                    $messages[] = '<div id="message" class="updated"><p>' . sprintf(_n('Competition deleted.', '%s competitions deleted.', $deleteCount), number_format_i18n($deleteCount)) . '</p></div>';
                    break;
                case 'open_many':
                    $openCount = (int) $this->request->input('count', 0);
                    $messages[] = '<div id="message" class="updated"><p>' . sprintf(_n('Competition opened.', '%s competitions opened.', $openCount), number_format_i18n($openCount)) . '</p></div>';
                    break;
                case 'close_many':
                    $closeCount = (int) $this->request->input('count', 0);
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

        if ($this->request->has('s') && $this->request->input('s')) {
            printf('<span class="subtitle">' . sprintf(__('Search results for &#8220;%s&#8221;'), wp_html_excerpt(esc_html(stripslashes($this->request->input('s'))), 50)) . '</span>');
        }
        echo '</h2>';

        $this->competition_list->views();
        $formBuilder = $this->container->make('Avh\Html\FormBuilder');
        echo $formBuilder->open(null, array('id' => 'rps-competition-form', 'method' => 'get'));
        echo $formBuilder->hidden('page', Constants::MENU_SLUG_COMPETITION);
        echo $formBuilder->hidden('_total', $this->competition_list->get_pagination_arg('total_items'));
        echo $formBuilder->hidden('_per_page', $this->competition_list->get_pagination_arg('per_page'));
        echo $formBuilder->hidden('_page', $this->competition_list->get_pagination_arg('page'));

        if ($this->request->has('paged')) {
            echo $formBuilder->hidden('paged', absint($this->request->input('paged')));
        }
        // $this->competition_list->search_box(__('Find IP', 'avh-rps'), 'find_ip');
        $this->competition_list->display();
        echo $formBuilder->close();

        echo '<div id="ajax-response"></div>';
        $this->printAdminFooter();
        echo '</div>';
    }

    /**
     * Display the page to open or close competitions
     *
     * @param string $action
     */
    private function displayPageCompetitionOpenClose($action)
    {
        global $wpdb;

        $query_competitions = new QueryCompetitions($this->rpsdb);

        $title = '';
        $action_verb = '';
        if ($action == 'open') {
            $title = 'Open Competitions';
            $action_verb = 'openend';
        }
        if ($action == 'close ') {
            $title = 'Close Competitions';
            $action_verb = 'closed';
        }

        if (!$this->request->has('competitions')) {
            $competitionIdsArray = array(intval($this->request->input('competition')));
        } else {
            $competitionIdsArray = (array) $this->request->input('competitions');
        }

        $formBuilder = $this->container->make('Avh\Html\FormBuilder');

        $this->displayAdminHeader($title);
        echo $formBuilder->open('', array('method' => 'post', 'id' => 'updatecompetitions', 'name' => 'updatecompetitions', 'accept-charset' => get_bloginfo('charset')));
        wp_nonce_field($action . '-competitions');
        echo $this->referer;

        echo '<p>' . _n('You have specified this competition to be ' . $action_verb . ':', 'You have specified these competitions to be ' . $action_verb . '::', count($competitionIdsArray)) . '</p>';

        foreach ($competitionIdsArray as $competitionID) {
            $sqlWhere = $wpdb->prepare('ID=%d', $competitionID);
            $competition = $query_competitions->query(array('where' => $sqlWhere), ARRAY_A);
            $competition = $competition[0];
            echo "<li><input type=\"hidden\" name=\"competitions[]\" value=\"" . esc_attr($competitionID) . "\" />" . sprintf(__('ID #%1s: %2s - %3s - %4s - %5s'),
                                                                                                                              $competitionID,
                                                                                                                              mysql2date(get_option('date_format'), $competition->Competition_Date),
                                                                                                                              $competition->Theme,
                                                                                                                              $competition->Classification,
                                                                                                                              $competition->Medium) . "</li>\n";
        }

        echo $formBuilder->hidden('action', 'do' . $action);
        echo $formBuilder->submit('openclose', 'Confirm', array('class' => 'button-secondary'));

        echo $formBuilder->close();
        $this->displayAdminFooter();
        unset($query_competitions);
    }

    /**
     * Display the page to confirm the deletion of the selected entries.
     */
    private function displayPageEntriesDelete()
    {
        $query_entries = new QueryEntries($this->rpsdb);
        $query_competitions = new QueryCompetitions($this->rpsdb);

        $formBuilder = $this->container->make('Avh\Html\FormBuilder');

        if (!$this->request->has('entries')) {
            $entryIdsArray = array(intval($this->request->input('entry')));
        } else {
            $entryIdsArray = (array) $this->request->input('entries');
        }

        $this->displayAdminHeader('Delete Entries');
        echo $formBuilder->open('', array('method' => 'post', 'id' => 'updateentries', 'name' => 'updateentries', 'accept-charset' => get_bloginfo('charset')));

        echo '<p>' . _n('You have specified this entry for deletion:', 'You have specified these entries for deletion:', count($entryIdsArray)) . '</p>';

        $goDelete = 0;
        foreach ($entryIdsArray as $entryID) {

            $entry = $query_entries->getEntryById($entryID, OBJECT);
            if ($entry !== null) {
                $user = get_user_by('id', $entry->Member_ID);
                $competition = $query_competitions->getCompetitionById($entry->Competition_ID);
                echo "<li>";
                echo $formBuilder->hidden('entries[]', $entryID);
                printf(__('ID #%1s: <strong>%2s</strong> by <em>%3s %4s</em> for the competition <em>%5s</em> on %6s'),
                       $entryID,
                       $entry->Title,
                       $user->first_name,
                       $user->last_name,
                       $competition->Theme,
                       mysql2date(get_option('date_format'), $competition->Competition_Date));
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
        unset($query_entries, $query_competitions);
    }

    /**
     * Display the page to edit Entries
     */
    private function displayPageEntriesEdit()
    {
        /**
         * @var string $wp_http_referer
         */
        $query_entries = new QueryEntries($this->rpsdb);
        $query_competitions = new QueryCompetitions($this->rpsdb);

        $updated = false;

        $formBuilder = $this->container->make('Avh\Html\FormBuilder');
        $formBuilder->setOptionName('entry-edit');

        if ($this->request->has('update')) {
            check_admin_referer($this->request->input('entry'));
            if (!current_user_can('rps_edit_entries')) {
                wp_die(__('Cheatin&#8217; uh?'));
            }
            $updated = $this->updateEntry();
        }

        $vars = (array('action', 'redirect', 'entry', 'wp_http_referer'));
        for ($i = 0; $i < count($vars); $i += 1) {
            $var = $vars[$i];
            $$var = $this->request->input($var, '');
        }

        $wp_http_referer = remove_query_arg(array('update'), stripslashes($wp_http_referer));
        $entry = $query_entries->getEntryById($this->request->input('entry'), OBJECT);
        $competition = $query_competitions->getCompetitionById($entry->Competition_ID);

        $this->displayAdminHeader('Edit Entry');

        if ($this->request->has('update')) {
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
        echo "<img src=\"" . $this->core->rpsGetThumbnailUrl($entry, 200) . "\" />\n";

        echo $formBuilder->outputLabel($formBuilder->label('title', 'Title'));
        echo $formBuilder->outputField($formBuilder->text('title', $entry->Title));

        $medium_array = array(
            'medium_bwd' => 'B&W Digital',
            'medium_cd'  => 'Color Digital',
            'medium_bwp' => 'B&W Prints',
            'medium_cp'  => 'Color Prints'
        );
        $selectedMedium = array_search($competition->Medium, $medium_array);
        echo $formBuilder->outputLabel($formBuilder->label('medium', 'Medium'));
        echo $formBuilder->outputField($formBuilder->select('medium', $medium_array, $selectedMedium, array('autocomplete' => 'off')));

        $_classification = array(
            'class_b' => 'Beginner',
            'class_a' => 'Advanced',
            'class_s' => 'Salon'
        );
        $selectedClassification = array_search($competition->Classification, $_classification);
        echo $formBuilder->outputLabel($formBuilder->label('classification', 'Classification'));
        echo $formBuilder->outputField($formBuilder->select('classification', $_classification, $selectedClassification, array('autocomplete' => 'off')));

        echo $formBuilder->closeTable();
        echo $formBuilder->submit('submit', 'Update Entry', array('class' => 'button-primary'));
        if ($wp_http_referer) {
            echo $formBuilder->hidden('wp_http_referer', esc_url($wp_http_referer));
        }
        echo $formBuilder->hidden('entry', $entry->ID);
        echo $formBuilder->hidden('update', true);
        echo $formBuilder->hidden('action', 'edit');
        echo $formBuilder->fieldNonce($entry->ID);
        echo $formBuilder->close();
        $this->displayAdminFooter();

        unset($query_entries, $query_competitions);
    }

    /**
     * Display the entries in a list
     */
    private function displayPageEntriesList()
    {
        $messages = array();
        if ($this->request->has('update')) {
            switch ($this->request->input('update')) {
                case 'del':
                case 'del_many':
                    $deleteCount = (int) $this->request->input('deleteCount', 0);
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

        if ($this->request->has('s') && $this->request->input('s')) {
            printf('<span class="subtitle">' . sprintf(__('Search results for &#8220;%s&#8221;'), wp_html_excerpt(esc_html(stripslashes($this->request->input('s'))), 50)) . '</span>');
        }
        echo '</h2>';

        $this->entries_list->views();
        echo '<form id="rps-entries-form" action="" method="get">';
        echo '<input type="hidden" name="page" value="' . Constants::MENU_SLUG_ENTRIES . '">';

        echo '<input type="hidden" name="_total" value="' . esc_attr($this->entries_list->get_pagination_arg('total_items')) . '" />';
        echo '<input type="hidden" name="_per_page" value="' . esc_attr($this->entries_list->get_pagination_arg('per_page')) . '" />';
        echo '<input type="hidden" name="_page" value="' . esc_attr($this->entries_list->get_pagination_arg('page')) . '" />';

        if ($this->request->has('paged')) {
            echo '<input type="hidden" name="paged"	value="' . esc_attr(absint($this->request->input('paged'))) . '" />';
        }
        $this->entries_list->display();
        echo '</form>';

        echo '<div id="ajax-response"></div>';
        $this->printAdminFooter();
        echo '</div>';
    }

    /**
     * Handle the HTTP Request before the page of the menu Competition is displayed.
     * This is needed for the redirects.
     */
    private function handleRequestCompetition()
    {
        $query_competitions = new QueryCompetitions($this->rpsdb);
        if ($this->request->has('wp_http_referer')) {
            $redirect = remove_query_arg(array('wp_http_referer', 'updated', 'delete_count'), stripslashes($this->request->input('wp_http_referer')));
        } else {
            $redirect = admin_url('admin.php') . '?page=' . Constants::MENU_SLUG_COMPETITION;
        }

        $doAction = $this->competition_list->current_action();
        switch ($doAction) {
            case 'delete':
            case 'open':
            case 'close':
                check_admin_referer('bulk-competitions');
                if (!($this->request->has('competitions') && $this->request->has('competition'))) {
                    wp_redirect($redirect);
                    exit();
                }
                break;

            case 'edit':
                if (!$this->request->has('competition')) {
                    wp_redirect($redirect);
                    exit();
                }
                break;

            case 'dodelete':
                check_admin_referer('delete-competitions');
                if (!$this->request->has('competitions')) {
                    wp_redirect($redirect);
                    exit();
                }
                $competitionIds = $this->request->input('competitions');

                $deleteCount = 0;

                foreach ((array) $competitionIds as $id) {
                    $id = (int) $id;
                    $query_competitions->deleteCompetition($id);
                    ++$deleteCount;
                }
                $redirect = add_query_arg(array('deleteCount' => $deleteCount, 'update' => 'del_many'), $redirect);
                wp_redirect($redirect);
                break;

            case 'doopen':
                check_admin_referer('open-competitions');
                if (!$this->request->has('competitions')) {
                    wp_redirect($redirect);
                    exit();
                }
                $competitionIds = $this->request->input('competitions');
                $count = 0;

                foreach ((array) $competitionIds as $id) {
                    $data = array();
                    $data['ID'] = (int) $id;
                    $data['Closed'] = 'N';
                    $query_competitions->insertCompetition($data);
                    ++$count;
                }
                $redirect = add_query_arg(array('count' => $count, 'update' => 'open_many'), $redirect);
                wp_redirect($redirect);
                break;

            case 'doclose':
                check_admin_referer('close-competitions');
                if (!$this->request->has('competitions')) {
                    wp_redirect($redirect);
                    exit();
                }
                $competitionIds = $this->request->input('competitions');
                $count = 0;

                foreach ((array) $competitionIds as $id) {
                    $data = array();
                    $data['ID'] = (int) $id;
                    $data['Closed'] = 'Y';
                    $query_competitions->insertCompetition($data);
                    ++$count;
                }
                $redirect = add_query_arg(array('count' => $count, 'update' => 'close_many'), $redirect);
                wp_redirect($redirect);
                break;

            case 'setscore':
                if ($this->request->input('competition') !== '') {
                    check_admin_referer('score_' . $this->request->input('competition'));
                    $data = array();
                    $data['ID'] = (int) $this->request->input('competition');
                    $data['Scored'] = 'Y';
                    $query_competitions->insertCompetition($data);
                }
                wp_redirect($redirect);
                break;
            case 'Unsetscore':
                if ($this->request->input('competition') !== '') {
                    check_admin_referer('score_' . $this->request->input('competition'));
                    $data = array();
                    $data['ID'] = (int) $this->request->input('competition');
                    $data['Scored'] = 'N';
                    $query_competitions->insertCompetition($data);
                }
                wp_redirect($redirect);
                break;
            default:
                if ($this->request->has('_wp_http_referer')) {
                    wp_redirect(remove_query_arg(array('_wp_http_referer', '_wpnonce'), stripslashes($this->request->server('REQUEST_URI'))));
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
        unset($query_competitions);
    }

    /**
     * Handle the HTTP Request before the page of the menu Entries is displayed.
     * This is needed for the redirects.
     */
    private function handleRequestEntries()
    {
        $query_entries = new QueryEntries($this->rpsdb);

        if ($this->request->has('wp_http_referer')) {
            $redirect = remove_query_arg(array('wp_http_referer', 'updated', 'delete_count'), stripslashes($this->request->input('wp_http_referer')));
        } else {
            $redirect = admin_url('admin.php') . '?page=' . Constants::MENU_SLUG_ENTRIES;
        }

        $doAction = $this->entries_list->current_action();
        switch ($doAction) {
            case 'delete':
                check_admin_referer('bulk-entries');
                if (!($this->request->has('entries') && $this->request->has('entry'))) {
                    wp_redirect($redirect);
                    exit();
                }
                break;

            case 'edit':
                if (!$this->request->has('entry')) {
                    wp_redirect($redirect);
                    exit();
                }
                break;
            case 'dodelete':
                check_admin_referer('delete-entries');
                if (!$this->request->has('entries')) {
                    wp_redirect($redirect);
                    exit();
                }
                $entryIds = $this->request->input('entries');

                $deleteCount = 0;

                foreach ((array) $entryIds as $id) {
                    $id = (int) $id;
                    $query_entries->deleteEntry($id);
                    ++$deleteCount;
                }
                $redirect = add_query_arg(array('deleteCount' => $deleteCount, 'update' => 'del_many'), $redirect);
                wp_redirect($redirect);
                break;

            default:
                if ($this->request->has('_wp_http_referer')) {
                    wp_redirect(remove_query_arg(array('_wp_http_referer', '_wpnonce'), stripslashes($this->request->server('REQUEST_URI'))));
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
        unset($query_entries);
    }

    /**
     * Display plugin Copyright
     */
    private function printAdminFooter()
    {
        echo '<div class="clear"></div>';
        echo '<p class="footer_avhfdas">';
        printf('&copy; Copyright 2012-%s <a href="http://blog.avirtualhome.com/" title="My Thoughts">Peter van der Does</a> | AVH RPS Competition version %s', date("Y"), Constants::PLUGIN_VERSION);
        echo '</p>';
    }

    /**
     * Update a competition after a POST
     */
    private function updateCompetition()
    {
        $query_competitions = new QueryCompetitions($this->rpsdb);
        $formOptionsNew = array();
        $data = array();
        $formOptions = $this->request->input('competition-edit');

        $formOptionsNew['date'] = $formOptions['date'];
        $formOptionsNew['close-date'] = $formOptions['close-date'];
        $formOptionsNew['close-time'] = $formOptions['close-time'];
        $formOptionsNew['theme'] = $formOptions['theme'];
        $formOptionsNew['medium'] = $formOptions['medium'];
        $formOptionsNew['classification'] = $formOptions['classification'];
        $formOptionsNew['max-entries'] = $formOptions['max-entries'];
        $formOptionsNew['judges'] = $formOptions['judges'];
        $formOptionsNew['special-event'] = isset($formOptions['special-event']) ? $formOptions['special-event'] : '';
        $formOptionsNew['closed'] = isset($formOptions['closed']) ? $formOptions['closed'] : '';
        $formOptionsNew['scored'] = isset($formOptions['scored']) ? $formOptions['scored'] : '';

        $_medium = array('medium_bwd' => 'B&W Digital', 'medium_cd' => 'Color Digital', 'medium_bwp' => 'B&W Prints', 'medium_cp' => 'Color Prints');

        $_classification = array('class_b' => 'Beginner', 'class_a' => 'Advanced', 'class_s' => 'Salon');
        $data['ID'] = $this->request->input('competition');
        $data['Competition_Date'] = $formOptionsNew['date'];
        $data['Close_Date'] = $formOptionsNew['close-date'] . ' ' . $formOptionsNew['close-time'];
        $data['Theme'] = $formOptionsNew['theme'];
        $data['Max_Entries'] = $formOptionsNew['max-entries'];
        $data['Num_Judges'] = $formOptionsNew['judges'];
        $data['Special_Event'] = ($formOptionsNew['special-event'] ? 'Y' : 'N');
        $data['Closed'] = ($formOptionsNew['closed'] ? 'Y' : 'N');
        $data['Scored'] = ($formOptionsNew['scored'] ? 'Y' : 'N');
        $data['Medium'] = $_medium[$formOptionsNew['medium']];
        $data['Classification'] = $_classification[$formOptionsNew['classification']];
        $competition_ID = $query_competitions->insertCompetition($data);

        unset($query_competitions);
        if (is_wp_error($competition_ID)) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Update an entry after a POST
     *
     * @return boolean
     */
    private function updateEntry()
    {
        $query_entries = new QueryEntries($this->rpsdb);
        $formOptions = $this->request->input('entry-edit');
        $id = (int) $this->request->input('entry');
        $entry = $query_entries->getEntryById($id);

        $return = false;
        $formOptionsNew = array();
        $formOptionsNew['title'] = empty($formOptions['title']) ? $entry['Title'] : $formOptions['title'];
        if ($entry['Title'] != $formOptionsNew['title']) {
            $data = array('ID' => $id, 'Title' => $formOptionsNew['title']);
            $return = $query_entries->updateEntry($data);
        }

        unset($query_entries);

        return $return;
    }
}
