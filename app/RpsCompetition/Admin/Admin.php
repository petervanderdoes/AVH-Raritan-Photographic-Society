<?php
namespace RpsCompetition\Admin;

use Avh\Framework\Html\FormBuilder;
use Avh\Framework\Html\HtmlBuilder;
use Illuminate\Config\Repository as Settings;
use Illuminate\Http\Request;
use RpsCompetition\Application;
use RpsCompetition\Competition\ListTable as CompetitionListTable;
use RpsCompetition\Constants;
use RpsCompetition\Db\QueryCompetitions;
use RpsCompetition\Db\QueryEntries;
use RpsCompetition\Db\RpsDb;
use RpsCompetition\Entity\Db\Entry;
use RpsCompetition\Entries\ListTable as EntriesListTable;
use RpsCompetition\Helpers\CommonHelper;
use RpsCompetition\Helpers\PhotoHelper;
use Valitron\Validator;

/**
 * Class Admin
 *
 * @package   RpsCompetition\Admin
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2016, AVH Software
 */
final class Admin
{
    private $app;
    /** @var  \WP_List_Table */
    private $competition_list;
    /** @var  \WP_List_Table */
    private $entries_list;
    private $hooks   = [];
    private $message = '';
    private $referer;
    /** @var Request */
    private $request;
    /** @var RpsDb */
    private $rpsdb;
    /** @var  Settings */
    private $settings;
    private $status = '';

    /**
     * Constructor
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;

        $this->settings = $app->make('Settings');
        $this->rpsdb    = $app->make('RpsDb');
        $this->request  = $app->make('IlluminateRequest');

        // Admin menu
        add_action('admin_menu', [$this, 'actionAdminMenu']);
        add_action('admin_init', [$this, 'handleActionInit']);

        add_action('wp_ajax_setscore', [$this, 'handleAjax']);
        add_filter('user_row_actions', [$this, 'filterRpsUserActionLinks'], 10, 2);

        add_action('user_register', [$this, 'actionAddUserMeta']);
    }

    /**
     * Setup User metadata
     *
     * @param int $userID
     *
     * @internal Hook: user_register
     */
    public function actionAddUserMeta($userID)
    {
        update_user_meta($userID, 'rps_class_bw', 'beginner');
        update_user_meta($userID, 'rps_class_color', 'beginner');
        update_user_meta($userID, 'rps_class_print_bw', 'beginner');
        update_user_meta($userID, 'rps_class_print_color', 'beginner');
    }

    /**
     * Add the Tools and Options to the Management and Options page respectively.
     * Setup the Admin Menu pages
     *
     * @internal Hook: admin_menu
     */
    public function actionAdminMenu()
    {
        wp_register_style('avhrps-admin-css',
                          CommonHelper::getPluginUrl('avh-rps.admin.css', $this->settings->get('css_dir')),
                          ['wp-admin'],
                          Constants::PLUGIN_VERSION,
                          'screen');
        wp_register_style('avhrps-jquery-css',
                          CommonHelper::getPluginUrl('smoothness/jquery-ui-1.8.22.custom.css',
                                                     $this->settings->get('css_dir')),
                          ['wp-admin'],
                          '1.8.22',
                          'screen');
        wp_register_script('avhrps-comp-ajax',
                           CommonHelper::getPluginUrl('avh-rps.admin.ajax.js',
                                                      $this->settings->get('javascript_dir')),
                           ['jquery'],
                           false,
                           true);

        add_menu_page('All Competitions',
                      'Competitions',
                      'rps_edit_competitions',
                      Constants::MENU_SLUG_COMPETITION,
                      [$this, 'menuCompetition'],
                      '',
                      Constants::MENU_POSITION_COMPETITION);

        $this->hooks['avhrps_menu_competition']     = add_submenu_page(Constants::MENU_SLUG_COMPETITION,
                                                                       'All Competitions',
                                                                       'All Competitions',
                                                                       'rps_edit_competitions',
                                                                       Constants::MENU_SLUG_COMPETITION,
                                                                       [
                                                                           $this,
                                                                           'menuCompetition'
                                                                       ]);
        $this->hooks['avhrps_menu_competition_add'] = add_submenu_page(Constants::MENU_SLUG_COMPETITION,
                                                                       'Add Competition',
                                                                       'Add Competition',
                                                                       'rps_edit_competitions',
                                                                       Constants::MENU_SLUG_COMPETITION_ADD,
                                                                       [
                                                                           $this,
                                                                           'menuCompetitionAdd'
                                                                       ]);

        add_action('load-' . $this->hooks['avhrps_menu_competition'], [$this, 'actionLoadPagehookCompetition']);
        add_action('load-' . $this->hooks['avhrps_menu_competition_add'], [$this, 'actionLoadPagehookCompetitionAdd']);

        add_menu_page('All Entries',
                      'Entries',
                      'rps_edit_entries',
                      Constants::MENU_SLUG_ENTRIES,
                      [$this, 'menuEntries'],
                      '',
                      Constants::MENU_POSITION_ENTRIES);
        $this->hooks['avhrps_menu_entries'] = add_submenu_page(Constants::MENU_SLUG_ENTRIES,
                                                               'All Entries',
                                                               'All Entries',
                                                               'rps_edit_entries',
                                                               Constants::MENU_SLUG_ENTRIES,
                                                               [$this, 'menuEntries']);
        add_action('load-' . $this->hooks['avhrps_menu_entries'], [$this, 'actionLoadPagehookEntries']);
    }

    /**
     * Add the actions needed for to extended the user profile
     *
     * @see handleActionInit
     */
    public function actionInitUserFields()
    {
        add_action('edit_user_profile', [$this, 'actionUserProfile']);
        add_action('show_user_profile', [$this, 'actionUserProfile']);
        add_action('personal_options_update', [$this, 'actionProfileUpdateSave']);
        add_action('edit_user_profile_update', [$this, 'actionProfileUpdateSave']);
    }

    /**
     * Setup all that is needed for the page Competition
     *
     * @internal Hook: load-{page}
     */
    public function actionLoadPagehookCompetition()
    {
        $this->competition_list = new CompetitionListTable($this->rpsdb, $this->request);

        $this->handleRequestCompetition();

        add_filter('screen_layout_columns', [$this, 'filterScreenLayoutColumns'], 10, 2);
        // WordPress core Styles and Scripts
        wp_enqueue_script('common');
        wp_enqueue_script('wp-lists');
        // Plugin Style and Scripts
        wp_enqueue_script('avhrps-comp-ajax');
        wp_enqueue_script('jquery-ui-datepicker');

        wp_enqueue_style('avhrps-admin-css');
        wp_enqueue_style('avhrps-jquery-css');
    }

    /**
     * Setup all that is needed for the page "Add competition"
     *
     * @internal Hook: load-{page}
     */
    public function actionLoadPagehookCompetitionAdd()
    {
        $this->competition_list = new CompetitionListTable($this->rpsdb, $this->request);

        add_filter('screen_layout_columns', [$this, 'filterScreenLayoutColumns'], 10, 2);
        // WordPress core Styles and Scripts
        wp_enqueue_script('common');
        wp_enqueue_script('jquery-ui-datepicker');
        // Plugin Style and Scripts
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
        $this->entries_list = $this->competition_list = new EntriesListTable($this->rpsdb, $this->request);
        $this->handleRequestEntries();

        add_filter('screen_layout_columns', [$this, 'filterScreenLayoutColumns'], 10, 2);
        // WordPress core Styles and Scripts
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_script('common');
        wp_enqueue_script('wp-lists');
        wp_enqueue_script('postbox');
        wp_enqueue_style('css/dashboard');
        // Plugin Style and Scripts
        wp_enqueue_style('avhrps-admin-css');
        wp_enqueue_style('avhrps-jquery-css');
    }

    /**
     * Update the user meta concerning Classification when a user is updated.
     *
     * @param int $user_id
     *
     * @internal Hook: personal_options_update
     * @internal Hook: edit_user_profile_update
     */
    public function actionProfileUpdateSave($user_id)
    {
        $userID                = $user_id;
        $rps_class_bw          = $this->request->input('rps_class_bw', get_user_meta($userID, 'rps_class_bw', true));
        $rps_class_color       = $this->request->input('rps_class_color',
                                                       get_user_meta($userID, 'rps_class_color', true));
        $rps_class_print_bw    = $this->request->input('rps_class_print_bw',
                                                       get_user_meta($userID, 'rps_class_print_bw', true));
        $rps_class_print_color = $this->request->input('rps_class_print_color',
                                                       get_user_meta($userID, 'rps_class_print_color', true));

        update_user_meta($userID, 'rps_class_bw', $rps_class_bw);
        update_user_meta($userID, 'rps_class_color', $rps_class_color);
        update_user_meta($userID, 'rps_class_print_bw', $rps_class_print_bw);
        update_user_meta($userID, 'rps_class_print_color', $rps_class_print_color);
    }

    /**
     * Show the Classification meta on the user profile page.
     *
     * @param \WP_User $user
     *
     * @internal Hook: edit_user_profile
     * @internal Hook: show_user_profile
     */
    public function actionUserProfile(\WP_User $user)
    {
        $userID = $user->ID;

        $classification = ['beginner' => 'Beginner', 'advanced' => 'Advanced', 'salon' => 'Salon'];

        $formBuilder = new FormBuilder(new HtmlBuilder());

        echo '<h3 id="rps">Competition Classification</h3>';
        echo $formBuilder->openTable();

        $all_classifications = [
            [
                'label'    => 'Classification Digital B&W',
                'name'     => 'rps_class_bw',
                'selected' => get_user_meta($userID, 'rps_class_bw', true)
            ],
            [
                'label'    => 'Classification Digital Color',
                'name'     => 'rps_class_color',
                'selected' => get_user_meta($userID, 'rps_class_color', true)
            ],
            [
                'label'    => 'Classification Print B&W',
                'name'     => 'rps_class_print_bw',
                'selected' => get_user_meta($userID, 'rps_class_print_bw', true)
            ],
            [
                'label'    => 'Classification Print Color',
                'name'     => 'rps_class_print_color',
                'selected' => get_user_meta($userID, 'rps_class_print_color', true)
            ],
        ];

        foreach ($all_classifications as $data) {
            if (current_user_can('rps_edit_competition_classification')) {
                echo $formBuilder->outputLabel($formBuilder->label($data['name'], $data['name']));
                echo $formBuilder->outputField($formBuilder->select($data['name'],
                                                                    $classification,
                                                                    $data['selected']));
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
     * @param array    $actions
     * @param \WP_User $user
     *
     * @return array
     * @internal Hook: user_row_actions
     */
    public function filterRpsUserActionLinks($actions, \WP_User $user)
    {
        $link               = admin_url() . '?page=avh-rps-entries&user_id=' . $user->ID;
        $actions['entries'] = '<a href="' . $link . '">Entries</a>';

        return $actions;
    }

    /**
     * Sets the amount of columns wanted for a particuler screen
     *
     * @see      filter screen_meta_screen
     *
     * @param int $columns
     *
     * @internal Hook: screen_layout_columns
     * @return int
     */
    public function filterScreenLayoutColumns($columns)
    {
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
                $value  = (int) $value;
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
        if ($this->request->has('scored')) {
            $data     = [];
            $response = '';
            $result   = null;
            if ($this->request->input('scored') == 'Yes') {
                $data['ID']     = (int) $this->request->input('id');
                $data['Scored'] = 'N';
                $result         = $query_competitions->insertCompetition($data);
                $response       = json_encode(['text' => 'N', 'scored' => 'No', 'scoredtext' => 'Yes']);
            }
            if ($this->request->input('scored') == 'No') {
                $data['ID']     = (int) $this->request->input('id');
                $data['Scored'] = 'Y';
                $result         = $query_competitions->insertCompetition($data);
                $response       = json_encode(['text' => 'Y', 'scored' => 'Yes', 'scoredtext' => 'No']);
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
        /** @var \WP_Role $role */
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
     * Handle the menu Competition Add
     */
    public function menuCompetitionAdd()
    {
        $options            = get_option('avh-rps');
        $query_competitions = new QueryCompetitions($this->rpsdb);
        $formBuilder        = new FormBuilder(new HtmlBuilder());
        $formBuilder->setOptionName('competition_add');

        $data = [];

        $form_default_options = [
            'date'           => '',
            'theme'          => '',
            'medium'         => [
                'medium_bwd' => true,
                'medium_cd'  => true,
                'medium_bwp' => true,
                'medium_cp'  => true
            ],
            'classification' => [
                'class_b' => true,
                'class_a' => true,
                'class_s' => true
            ],
            'max-entries'    => '2',
            'judges'         => '1',
            'image_size'     => $options['default_image_size'],
            'special_event'  => false
        ];

        $form_options = $form_default_options;
        if ($this->request->has('action')) {
            switch ($this->request->input('action')) {
                case 'add':
                    check_admin_referer(get_current_user_id());
                    $form_new_options = $this->request->input($formBuilder->getOptionName());

                    $validator = new Validator($form_new_options);
                    $validator->rule('required', 'date')
                              ->message('{field} is required')
                              ->label('Date')
                    ;
                    $validator->rule('dateFormat', 'date', 'Y-m-d')
                              ->message('{field} should be in Y-m-d format')
                              ->label('Date')
                    ;
                    $validator->rule('required', 'theme')
                              ->message('{field} is required')
                              ->label('Theme')
                    ;
                    $validator->rule('required', 'medium')
                              ->message('No medium selected. At least one medium needs to be selected')
                    ;
                    $validator->rule('required', 'classification')
                              ->message('No classification selected. At least one classification needs to be selected')
                    ;
                    $validator->validate();

                    foreach ($form_default_options['medium'] as $key => $value) {
                        $form_new_options['medium'][$key] = (bool) avh_array_get($form_new_options,
                                                                                 'medium.' . $key,
                                                                                 false);
                    }
                    foreach ($form_default_options['classification'] as $key => $value) {
                        $form_new_options['classification'][$key] = (bool) avh_array_get($form_new_options,
                                                                                         'classification.' . $key,
                                                                                         false);
                    }

                    $form_new_options['special_event'] = (bool) avh_array_get($form_new_options,
                                                                              'special_event',
                                                                              false);

                    $validator_errors = $validator->errors();
                    if (empty($validator_errors)) {
                        $this->message = 'Competition Added';
                        $this->status  = 'updated';

                        $medium_array = Constants::getMediums();

                        $classification_array = Constants::getClassifications();

                        $data['Competition_Date'] = $form_new_options['date'];
                        $data['Theme']            = $form_new_options['theme'];
                        $data['Max_Entries']      = $form_new_options['max-entries'];
                        $data['Num_Judges']       = $form_new_options['judges'];
                        $data['Image_Size']       = $form_new_options['image_size'];
                        $data['Special_Event']    = ($form_new_options['special_event'] ? 'Y' : 'N');
                        $medium_keys              = array_keys($form_new_options['medium']);
                        foreach ($medium_keys as $medium_key) {
                            if ($form_new_options['medium'][$medium_key] === false) {
                                continue;
                            }
                            $data['Medium']      = $medium_array[$medium_key];
                            $classification_keys = array_keys($form_new_options['classification']);
                            foreach ($classification_keys as $classification_key) {
                                if ($form_new_options['classification'][$classification_key] === false) {
                                    continue;
                                }
                                $data['Classification'] = $classification_array[$classification_key];
                                $competition_ID         = $query_competitions->insertCompetition($data);
                                if (is_wp_error($competition_ID)) {
                                    wp_die($competition_ID);
                                }
                            }
                        }
                        unset($medium_keys, $classification_keys);
                    } else {
                        $this->message = $validator->errors();
                        $this->status  = 'error';
                    }
                    $this->displayMessage();
                    $form_options = $form_new_options;
                    break;
            }
            unset($query_competitions);
        }

        $this->displayPageCompetitionAdd($formBuilder, $form_options);
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
            foreach ($this->message as $messages) {
                foreach ($messages as $msg) {
                    $message .= $msg . '<br>';
                }
            }
        } else {
            $message = $this->message;
        }

        if ($message != '') {
            $status        = $this->status;
            $this->message = $this->status = ''; // Reset
            $status        = ($status != '') ? $status : 'updated fade';
            echo '<div id="message"	class="' . $status . '">';
            echo '<p><strong>' . $message . '</strong></p></div>';
        }
    }

    /**
     * Display the page for adding a competition.
     *
     * @param FormBuilder $formBuilder
     * @param array       $form_options
     */
    private function displayPageCompetitionAdd(FormBuilder $formBuilder, $form_options)
    {
        $this->displayAdminHeader('Add Competition');

        echo $formBuilder->open(admin_url('admin.php') . '?page=' . Constants::MENU_SLUG_COMPETITION_ADD,
                                [
                                    'method'         => 'post',
                                    'id'             => 'rps-competitionadd',
                                    'accept-charset' => get_bloginfo('charset')
                                ]);
        echo $formBuilder->openTable();
        echo $formBuilder->outputLabel($formBuilder->label('date', 'Date'));
        echo $formBuilder->outputField($formBuilder->text('date', $form_options['date']));
        echo $formBuilder->outputLabel($formBuilder->label('theme', 'Theme'));
        echo $formBuilder->outputField($formBuilder->text('theme', $form_options['theme'], ['maxlength' => '32']));

        $array_medium = [
            'medium_bwd' => [
                'text'    => 'B&W Digital',
                'value'   => $form_options['medium']['medium_bwd'],
                'checked' => $form_options['medium']['medium_bwd']
            ],
            'medium_cd'  => [
                'text'    => 'Color Digital',
                'value'   => $form_options['medium']['medium_cd'],
                'checked' => $form_options['medium']['medium_cd']
            ],
            'medium_bwp' => [
                'text'    => 'B&W Print',
                'value'   => $form_options['medium']['medium_bwp'],
                'checked' => $form_options['medium']['medium_bwp']
            ],
            'medium_cp'  => [
                'text'    => 'Color Print',
                'value'   => $form_options['medium']['medium_cp'],
                'checked' => $form_options['medium']['medium_cp']
            ]
        ];

        echo $formBuilder->outputLabel($formBuilder->label('medium', 'Medium'));
        echo $formBuilder->checkboxes('medium', $array_medium);
        unset($array_medium);

        $array_classification = [
            'class_b' => [
                'text'    => 'Beginner',
                'value'   => $form_options['classification']['class_b'],
                'checked' => $form_options['classification']['class_b']
            ],
            'class_a' => [
                'text'    => 'Advanced',
                'value'   => $form_options['classification']['class_a'],
                'checked' => $form_options['classification']['class_a']
            ],
            'class_s' => [
                'text'    => 'Salon',
                'value'   => $form_options['classification']['class_s'],
                'checked' => $form_options['classification']['class_s']
            ]
        ];

        echo $formBuilder->outputLabel($formBuilder->label('classification', 'Classification'));
        echo $formBuilder->checkboxes('classification', $array_classification);
        unset($array_classification);

        $array_max_entries = [
            '1'  => '1',
            '2'  => '2',
            '3'  => '3',
            '4'  => '4',
            '5'  => '5',
            '6'  => '6',
            '7'  => '7',
            '8'  => '8',
            '9'  => '9',
            '10' => '10'
        ];
        echo $formBuilder->outputLabel($formBuilder->label('max-entries', 'Max Entries'));
        echo $formBuilder->outputField($formBuilder->select('max-entries',
                                                            $array_max_entries,
                                                            $form_options['max-entries']));
        unset($array_max_entries);

        $array_judges = ['1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5'];
        echo $formBuilder->outputLabel($formBuilder->label('judges', 'No. Judges'));
        echo $formBuilder->outputField($formBuilder->select('judges', $array_judges, $form_options['judges']));
        unset($array_judges);

        $array_image_size = $this->getArrayImageSize();
        echo $formBuilder->outputLabel($formBuilder->label('image_size', 'Image Size'));
        echo $formBuilder->outputField($formBuilder->select('image_size',
                                                            $array_image_size,
                                                            $form_options['image_size']));
        unset($array_image_size);

        echo $formBuilder->outputLabel($formBuilder->label('special_event', 'Special Event'));
        echo $formBuilder->outputField($formBuilder->checkbox('special_event',
                                                              $form_options['special_event'],
                                                              $form_options['special_event']));

        echo $formBuilder->closeTable();
        echo $formBuilder->submit('submit', 'Add Competition', ['class' => 'button-primary']);
        echo $formBuilder->hidden('action', 'add');
        echo $formBuilder->fieldNonce(get_current_user_id());
        echo $formBuilder->close();
        $this->printDatepickerDefaults();
        echo '<script type="text/javascript">' . "\n";
        echo 'jQuery(function($) {' . "\n";
        echo '	$( "#date" ).datepicker({dateFormat: "yy-mm-dd"});' . "\n";
        echo '});', "\n";
        echo '</script>';
        $this->displayAdminFooter();
    }

    /**
     * Display the page to confirm the deletion of the selected competitions.
     */
    private function displayPageCompetitionDelete()
    {
        /**
         * @var \wpdb $wpdb
         */
        global $wpdb;

        $query_entries      = new QueryEntries($this->rpsdb);
        $query_competitions = new QueryCompetitions($this->rpsdb);

        if (!$this->request->has('competitions')) {
            $competitionIdsArray = [intval($this->request->input('competition'))];
        } else {
            $competitionIdsArray = (array) $this->request->input('competitions');
        }

        $formBuilder = new FormBuilder(new HtmlBuilder());

        $this->displayAdminHeader('Delete Competitions');
        echo $formBuilder->open('',
                                [
                                    'method'         => 'post',
                                    'id'             => 'updatecompetitions',
                                    'name'           => 'updatecompetitions',
                                    'accept-charset' => get_bloginfo('charset')
                                ]);
        wp_nonce_field('delete-competitions');
        echo $this->referer;

        echo '<p>' . _n('You have specified this competition for deletion:',
                        'You have specified these competitions for deletion:',
                        count($competitionIdsArray)) . '</p>';

        $goDelete = 0;
        foreach ($competitionIdsArray as $competitionID) {
            $sqlWhere    = $wpdb->prepare('Competition_ID=%d', $competitionID);
            $entries     = $query_entries->query(['where' => $sqlWhere, 'count' => true]);
            $sqlWhere    = $wpdb->prepare('ID=%d', $competitionID);
            $competition = $query_competitions->query(['where' => $sqlWhere]);
            /** @var QueryCompetitions $competition */
            $competition = $competition[0];
            if ($entries !== '0') {
                echo '<li>' .
                     sprintf(__('ID #%1s: %2s - %3s - %4s -%5s <strong>This competition will not be deleted. It still has %6s entries.</strong>'),
                             $competitionID,
                             mysql2date(get_option('date_format'), $competition->Competition_Date),
                             $competition->Theme,
                             $competition->Classification,
                             $competition->Medium,
                             $entries) .
                     "</li>\n";
            } else {
                echo '<li><input type="hidden" name="competitions[]" value="' .
                     esc_attr($competitionID) .
                     '" />' .
                     sprintf(__('ID #%1s: %2s - %3s - %4s - %5s'),
                             $competitionID,
                             mysql2date(get_option('date_format'), $competition->Competition_Date),
                             $competition->Theme,
                             $competition->Classification,
                             $competition->Medium) .
                     '</li>' .
                     "\n";
                $goDelete ++;
            }
        }
        if ($goDelete) {
            echo $formBuilder->hidden('action', 'dodelete');
            echo $formBuilder->submit('delete', 'Confirm Deletion', ['class' => 'button-secondary delete']);
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
        $options = get_option('avh-rps');
        /**
         * @var string $wp_http_referer
         */
        $query_competitions = new QueryCompetitions($this->rpsdb);

        $formBuilder = new FormBuilder(new HtmlBuilder());
        $formBuilder->setOptionName('competition-edit');

        $updated     = false;
        $formOptions = [];
        if ($this->request->has('update')) {
            $updated = $this->updateCompetition();
        }

        $wp_http_referer = $this->request->input('wp_http_referer', '');
        $wp_http_referer = remove_query_arg(['update'], stripslashes($wp_http_referer));

        $competition = $query_competitions->getCompetitionById($this->request->input('competition'));

        $formOptions['date']       = mysql2date('Y-m-d', $competition->Competition_Date);
        $formOptions['close-date'] = mysql2date('Y-m-d', $competition->Close_Date);
        $formOptions['close-time'] = mysql2date('H:i:s', $competition->Close_Date);

        $this->displayAdminHeader('Edit Competition');

        if ($this->request->has('update')) {
            if ($updated) {
                echo '<div id="message" class="notice notice-success">';
                echo '<p><strong>Competition updated.</strong></p>';
            } else {
                echo '<div id="message" class="notice notice-error">';
                echo '<p><strong>Competition not updated.</strong></p>';
            }
            if ($wp_http_referer) {
                echo '<p><a href="' . esc_url($wp_http_referer) . '">&larr; Back to Competitions</a></p>';
            }
            echo '</div>';
        }

        $queryEdit = ['page' => Constants::MENU_SLUG_COMPETITION];
        echo $formBuilder->open(admin_url('admin.php') . '?' . http_build_query($queryEdit, '', '&'),
                                [
                                    'method'         => 'post',
                                    'id'             => 'rps-competitionedit',
                                    'accept-charset' => get_bloginfo('charset')
                                ]);
        echo $formBuilder->openTable();
        echo $formBuilder->outputLabel($formBuilder->label('date', 'Date'));
        echo $formBuilder->outputField($formBuilder->text('date', $formOptions['date']));
        echo $formBuilder->outputLabel($formBuilder->label('theme', 'Theme'));
        echo $formBuilder->outputField($formBuilder->text('theme', $competition->Theme, ['maxlength' => '32']));
        echo $formBuilder->outputLabel($formBuilder->label('close-date', 'Closing Date'));
        echo $formBuilder->outputField($formBuilder->text('close-date', $formOptions['close-date']));

        $time = [];
        for ($hour = 0; $hour <= 23; $hour ++) {
            $time_val        = sprintf('%02d:00:00', $hour);
            $time_text       = date('g:i a', strtotime($time_val));
            $time[$time_val] = $time_text;
        }
        echo $formBuilder->outputLabel($formBuilder->label('close-time', 'Closing Time'));
        echo $formBuilder->outputField($formBuilder->select('close-time', $time, $formOptions['close-time']));

        $medium_array = Constants::getMediums();

        $selectedMedium = array_search($competition->Medium, $medium_array);
        echo $formBuilder->outputLabel($formBuilder->label('medium', 'Medium'));
        echo $formBuilder->outputField($formBuilder->select('medium',
                                                            $medium_array,
                                                            $selectedMedium,
                                                            ['autocomplete' => 'off']));

        $classification_array = Constants::getClassifications();

        $selectedClassification = array_search($competition->Classification, $classification_array);
        echo $formBuilder->outputLabel($formBuilder->label('classification', 'Classification'));
        echo $formBuilder->outputField($formBuilder->select('classification',
                                                            $classification_array,
                                                            $selectedClassification,
                                                            ['autocomplete' => 'off']));

        $max_entries = [
            '1'  => '1',
            '2'  => '2',
            '3'  => '3',
            '4'  => '4',
            '5'  => '5',
            '6'  => '6',
            '7'  => '7',
            '8'  => '8',
            '9'  => '9',
            '10' => '10'
        ];
        echo $formBuilder->outputLabel($formBuilder->label('max-entries', 'Max Entries'));
        echo $formBuilder->outputField($formBuilder->select('max-entries',
                                                            $max_entries,
                                                            $competition->Max_Entries,
                                                            ['autocomplete' => 'off']));

        $judges = ['1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5'];
        echo $formBuilder->outputLabel($formBuilder->label('judges', 'No. Judges'));
        echo $formBuilder->outputField($formBuilder->select('judges',
                                                            $judges,
                                                            $competition->Num_Judges,
                                                            ['autocomplete' => 'off']));

        $array_image_size = $this->getArrayImageSize();
        if ($competition->Image_Size === null) {
            $selected_image_size = $options['default_image_size'];
        } else {
            $selected_image_size = $competition->Image_Size;
        }
        echo $formBuilder->outputLabel($formBuilder->label('image_size', 'Image Size'));
        echo $formBuilder->outputField($formBuilder->select('image_size',
                                                            $array_image_size,
                                                            $selected_image_size,
                                                            ['autocomplete' => 'off']));
        unset($array_image_size);

        echo $formBuilder->outputLabel($formBuilder->label('special_event', 'Special Event'));
        echo $formBuilder->outputField($formBuilder->checkbox('special_event',
            ($competition->Special_Event == 'Y' ? true : false),
            ($competition->Special_Event == 'Y' ? true : false)));

        echo $formBuilder->outputLabel($formBuilder->label('closed', 'Closed'));
        echo $formBuilder->outputField($formBuilder->checkbox('closed',
            ($competition->Closed == 'Y' ? true : false),
            ($competition->Closed == 'Y' ? true : false)));

        echo $formBuilder->outputLabel($formBuilder->label('scored', 'Scored'));
        echo $formBuilder->outputField($formBuilder->checkbox('scored',
            ($competition->Scored == 'Y' ? true : false),
            ($competition->Scored == 'Y' ? true : false)));

        echo $formBuilder->closeTable();
        echo $formBuilder->submit('submit', 'Update Competition', ['class' => 'button-primary']);
        if (!empty($wp_http_referer)) {
            echo $formBuilder->hidden('wp_http_referer', esc_url($wp_http_referer));
        }
        echo $formBuilder->hidden('competition', $competition->ID);
        echo $formBuilder->hidden('update', true);
        echo $formBuilder->hidden('action', 'edit');
        echo $formBuilder->fieldNonce($competition->ID);
        echo $formBuilder->close();
        $this->printDatepickerDefaults();
        echo '<script type="text/javascript">' . "\n";
        echo 'jQuery(function($) {' . "\n";
        echo '	$( "#date" ).datepicker({dateFormat: "yy-mm-dd"});' . "\n";
        echo '	$( "#close-date" ).datepicker({dateFormat: "yy-mm-dd"});' . "\n";
        echo '});', "\n";
        echo '</script>';

        $this->displayAdminFooter();

        unset($query_competitions);
    }

    /**
     * Display the competition in a list
     */
    private function displayPageCompetitionList()
    {
        $messages = [];
        if ($this->request->has('update')) {
            switch ($this->request->input('update')) {
                case 'del':
                case 'del_many':
                    $deleteCount = (int) $this->request->input('deleteCount', 0);
                    $messages[]  = '<div id="message" class="updated"><p>' .
                                   sprintf(_n('Competition deleted.', '%s competitions deleted.', $deleteCount),
                                           number_format_i18n($deleteCount)) .
                                   '</p></div>';
                    break;
                case 'open_many':
                    $openCount  = (int) $this->request->input('count', 0);
                    $messages[] = '<div id="message" class="updated"><p>' .
                                  sprintf(_n('Competition opened.', '%s competitions opened.', $openCount),
                                          number_format_i18n($openCount)) .
                                  '</p></div>';
                    break;
                case 'close_many':
                    $closeCount = (int) $this->request->input('count', 0);
                    $messages[] = '<div id="message" class="updated"><p>' .
                                  sprintf(_n('Competition closed.', '%s competitions closed.', $closeCount),
                                          number_format_i18n($closeCount)) .
                                  '</p></div>';
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
            printf('<span class="subtitle">' . sprintf(__('Search results for &#8220;%s&#8221;'),
                                                       wp_html_excerpt(esc_html(stripslashes($this->request->input('s'))),
                                                                       50)) . '</span>');
        }
        echo '</h2>';

        $this->competition_list->views();
        $formBuilder = new FormBuilder(new HtmlBuilder());
        echo $formBuilder->open(null, ['id' => 'rps-competition-form', 'method' => 'get']);
        echo $formBuilder->hidden('page', Constants::MENU_SLUG_COMPETITION);
        echo $formBuilder->hidden('_total', $this->competition_list->get_pagination_arg('total_items'));
        echo $formBuilder->hidden('_per_page', $this->competition_list->get_pagination_arg('per_page'));
        echo $formBuilder->hidden('_page', $this->competition_list->get_pagination_arg('page'));

        if ($this->request->has('paged')) {
            echo $formBuilder->hidden('paged', absint($this->request->input('paged')));
        }
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
        /**
         * @var \wpdb $wpdb
         */
        global $wpdb;

        $query_competitions = new QueryCompetitions($this->rpsdb);

        $title       = '';
        $action_verb = '';
        if ($action == 'open') {
            $title       = 'Open Competitions';
            $action_verb = 'openend';
        }
        if ($action == 'close ') {
            $title       = 'Close Competitions';
            $action_verb = 'closed';
        }

        if (!$this->request->has('competitions')) {
            $competitionIdsArray = [intval($this->request->input('competition'))];
        } else {
            $competitionIdsArray = (array) $this->request->input('competitions');
        }

        $formBuilder = new FormBuilder(new HtmlBuilder());

        $this->displayAdminHeader($title);
        echo $formBuilder->open('',
                                [
                                    'method'         => 'post',
                                    'id'             => 'updatecompetitions',
                                    'name'           => 'updatecompetitions',
                                    'accept-charset' => get_bloginfo('charset')
                                ]);
        wp_nonce_field($action . '-competitions');
        echo $this->referer;

        echo '<p>' . _n('You have specified this competition to be ' . $action_verb . ':',
                        'You have specified these competitions to be ' . $action_verb . '::',
                        count($competitionIdsArray)) . '</p>';

        foreach ($competitionIdsArray as $competitionID) {
            $sqlWhere    = $wpdb->prepare('ID=%d', $competitionID);
            $competition = $query_competitions->query(['where' => $sqlWhere]);
            /** @var QueryCompetitions $competition */
            $competition = $competition[0];
            echo '<li><input type="hidden" name="competitions[]" value="' .
                 esc_attr($competitionID) .
                 '" />' .
                 sprintf(__('ID #%1s: %2s - %3s - %4s - %5s'),
                         $competitionID,
                         mysql2date(get_option('date_format'), $competition->Competition_Date),
                         $competition->Theme,
                         $competition->Classification,
                         $competition->Medium) .
                 '</li>' .
                 "\n";
        }

        echo $formBuilder->hidden('action', 'do' . $action);
        echo $formBuilder->submit('openclose', 'Confirm', ['class' => 'button-secondary']);

        echo $formBuilder->close();
        $this->displayAdminFooter();
        unset($query_competitions);
    }

    /**
     * Display the page to confirm the deletion of the selected entries.
     */
    private function displayPageEntriesDelete()
    {
        $query_entries      = new QueryEntries($this->rpsdb);
        $query_competitions = new QueryCompetitions($this->rpsdb);

        $formBuilder = new FormBuilder(new HtmlBuilder());

        if (!$this->request->has('entries')) {
            $entryIdsArray = [intval($this->request->input('entry'))];
        } else {
            $entryIdsArray = (array) $this->request->input('entries');
        }

        $this->displayAdminHeader('Delete Entries');
        echo $formBuilder->open('',
                                [
                                    'method'         => 'post',
                                    'id'             => 'updateentries',
                                    'name'           => 'updateentries',
                                    'accept-charset' => get_bloginfo('charset')
                                ]);

        echo '<p>' . _n('You have specified this entry for deletion:',
                        'You have specified these entries for deletion:',
                        count($entryIdsArray)) . '</p>';

        $goDelete = 0;
        foreach ($entryIdsArray as $entryID) {
            $entry = $query_entries->getEntryById($entryID);
            if ($entry !== null) {
                $user        = get_user_by('id', $entry->Member_ID);
                $competition = $query_competitions->getCompetitionById($entry->Competition_ID);
                echo '<li>';
                echo $formBuilder->hidden('entries[]', $entryID);
                printf(__('ID #%1s: <strong>%2s</strong> by <em>%3s %4s</em> for the competition <em>%5s</em> on %6s'),
                       $entryID,
                       $entry->Title,
                       $user->user_firstname,
                       $user->user_lastname,
                       $competition->Theme,
                       mysql2date(get_option('date_format'), $competition->Competition_Date));
                echo '</li>' . "\n";
                $goDelete ++;
            }
        }
        if ($goDelete) {
            echo $formBuilder->hidden('action', 'dodelete');
            echo $formBuilder->submit('delete', 'Confirm Deletion', ['class' => 'button-secondary delete']);
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
        $query_entries      = new QueryEntries($this->rpsdb);
        $query_competitions = new QueryCompetitions($this->rpsdb);
        /** @var PhotoHelper $photo_helper */
        $photo_helper = $this->app->make('PhotoHelper');

        $updated = false;

        $formBuilder = new FormBuilder(new HtmlBuilder());
        $formBuilder->setOptionName('entry-edit');

        if ($this->request->has('update')) {
            check_admin_referer($this->request->input('entry'));
            if (!current_user_can('rps_edit_entries')) {
                wp_die(__('Cheatin&#8217; uh?'));
            }
            $updated = $this->updateEntry();
        }

        $wp_http_referer = $this->request->input('wp_http_referer', '');
        $wp_http_referer = remove_query_arg(['update'], stripslashes($wp_http_referer));
        $entry           = $query_entries->getEntryById($this->request->input('entry'));
        $competition     = $query_competitions->getCompetitionById($entry->Competition_ID);

        $this->displayAdminHeader('Edit Entry');

        if ($this->request->has('update')) {

            if (is_wp_error($updated)) {
                echo '<div id="message" class="notice notice-error">';
                /** @var \WP_Error $updated */
                echo '<p><strong>Entry not updated.</strong>' .
                     $updated->get_error_message('rpsAdminEditEntry') .
                     '</p>';
            } else {
                echo '<div id="message" class="notice notice-success">';
                echo '<p><strong>Entry updated.</strong></p>';
            }
            if ($wp_http_referer) {
                echo '<p><a href="' . esc_url($wp_http_referer) . '">&larr; Back to Entries</a></p>';
            }
            echo '</div>';
        }

        $queryEdit = ['page' => Constants::MENU_SLUG_ENTRIES];
        echo $formBuilder->open(admin_url('admin.php') . '?' . http_build_query($queryEdit, '', '&'),
                                [
                                    'method'         => 'post',
                                    'id'             => 'rps-entryedit',
                                    'accept-charset' => get_bloginfo('charset')
                                ]);
        echo $formBuilder->openTable();

        $user = get_user_by('id', $entry->Member_ID);
        echo '<h3>Photographer: ' . $user->user_firstname . ' ' . $user->user_lastname . "</h3>\n";
        echo '<img src="' . $photo_helper->getThumbnailUrl($entry->Server_File_Name, '200') . '" />' . "\n";

        echo $formBuilder->outputLabel($formBuilder->label('title', 'Title'));
        echo $formBuilder->outputField($formBuilder->text('title', $entry->Title));

        $medium_array   = Constants::getMediums();
        $selectedMedium = array_search($competition->Medium, $medium_array);
        echo $formBuilder->outputLabel($formBuilder->label('medium', 'Medium'));
        echo $formBuilder->outputField($formBuilder->select('medium',
                                                            $medium_array,
                                                            $selectedMedium,
                                                            ['autocomplete' => 'off']));

        $classification_array   = Constants::getClassifications();
        $selectedClassification = array_search($competition->Classification, $classification_array);
        echo $formBuilder->outputLabel($formBuilder->label('classification', 'Classification'));
        echo $formBuilder->outputField($formBuilder->select('classification',
                                                            $classification_array,
                                                            $selectedClassification,
                                                            ['autocomplete' => 'off']));

        echo $formBuilder->closeTable();
        echo $formBuilder->submit('submit', 'Update Entry', ['class' => 'button-primary']);
        if (!empty($wp_http_referer)) {
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
        $messages = [];
        if ($this->request->has('update')) {
            switch ($this->request->input('update')) {
                case 'del':
                case 'del_many':
                    $deleteCount = (int) $this->request->input('deleteCount', 0);
                    $messages[]  = '<div id="message" class="updated"><p>' .
                                   sprintf(_n('Entry deleted.', '%s entries deleted.', $deleteCount),
                                           number_format_i18n($deleteCount)) .
                                   '</p></div>';
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
            printf('<span class="subtitle">' . sprintf(__('Search results for &#8220;%s&#8221;'),
                                                       wp_html_excerpt(esc_html(stripslashes($this->request->input('s'))),
                                                                       50)) . '</span>');
        }
        echo '</h2>';

        $this->entries_list->views();
        echo '<form id="rps-entries-form" action="" method="get">';
        echo '<input type="hidden" name="page" value="' . Constants::MENU_SLUG_ENTRIES . '">';

        echo '<input type="hidden" name="_total" value="' .
             esc_attr($this->entries_list->get_pagination_arg('total_items')) .
             '" />';
        echo '<input type="hidden" name="_per_page" value="' .
             esc_attr($this->entries_list->get_pagination_arg('per_page')) .
             '" />';
        echo '<input type="hidden" name="_page" value="' .
             esc_attr($this->entries_list->get_pagination_arg('page')) .
             '" />';

        if ($this->request->has('paged')) {
            echo '<input type="hidden" name="paged"	value="' .
                 esc_attr(absint($this->request->input('paged'))) .
                 '" />';
        }
        $this->entries_list->display();
        echo '</form>';

        echo '<div id="ajax-response"></div>';
        $this->printAdminFooter();
        echo '</div>';
    }

    /**
     * Perform the actual update of an entry.
     *
     * @param array             $formOptionsNew
     * @param int               $id
     * @param Entry             $entry
     * @param QueryCompetitions $competition Competition record
     *
     * @return bool|\WP_Error
     */
    private function doUpdateEntry($formOptionsNew, $id, Entry $entry, QueryCompetitions $competition)
    {
        $query_entries      = new QueryEntries($this->rpsdb);
        $query_competitions = new QueryCompetitions($this->rpsdb);
        /** @var PhotoHelper $photo_helper */
        $photo_helper         = $this->app->make('PhotoHelper');
        $medium_array         = Constants::getMediums();
        $classification_array = Constants::getClassifications();
        $return               = false;

        $old_file             = $this->request->server('DOCUMENT_ROOT') . $entry->Server_File_Name;
        $user                 = get_user_by('id', $entry->Member_ID);
        $relative_server_path = $photo_helper->getCompetitionPath($competition->Competition_Date,
                                                                  $classification_array[$formOptionsNew['classification']],
                                                                  $medium_array[$formOptionsNew['medium']]);
        $full_server_path     = $this->request->server('DOCUMENT_ROOT') . $relative_server_path;
        $dest_name            = sanitize_file_name($formOptionsNew['title']) . '+' . $user->user_login . '+' . time();

        $new_competition = $query_competitions->getCompetitionByDateClassMedium($competition->Competition_Date,
                                                                                $classification_array[$formOptionsNew['classification']],
                                                                                $medium_array[$formOptionsNew['medium']]);

        $data                     = [];
        $data['Competition_ID']   = $new_competition->ID;
        $data['ID']               = $id;
        $data['Server_File_Name'] = $relative_server_path . '/' . $dest_name . '.jpg';
        $data['Title']            = $formOptionsNew['title'];

        // Need to create the destination folder?
        CommonHelper::createDirectory($full_server_path);
        $updated = rename($old_file, $full_server_path . '/' . $dest_name . '.jpg');

        if ($updated) {
            $photo_helper->removeThumbnails(pathinfo($old_file, PATHINFO_DIRNAME),
                                            pathinfo($old_file, PATHINFO_FILENAME));
            $return = $query_entries->updateEntry($data);
        }

        unset($query_entries, $query_competitions, $photo_helper);

        return $return;
    }

    /**
     * Build array with image size used by the forms
     *
     * @return array
     */
    private function getArrayImageSize()
    {
        $array_image_size = [
            '1024' => '1024x768',
            '1400' => '1400x1050'
        ];
        ksort($array_image_size);

        return $array_image_size;
    }

    /**
     * Handle the HTTP Request before the page of the menu Competition is displayed.
     * This is needed for the redirects.
     */
    private function handleRequestCompetition()
    {
        $query_competitions = new QueryCompetitions($this->rpsdb);
        if ($this->request->has('wp_http_referer')) {
            $redirect = remove_query_arg(['wp_http_referer', 'updated', 'delete_count'],
                                         stripslashes($this->request->input('wp_http_referer')));
        } else {
            $redirect = admin_url('admin.php') . '?page=' . Constants::MENU_SLUG_COMPETITION;
        }

        $doAction = $this->competition_list->current_action();
        switch ($doAction) {
            case 'delete':
            case 'open':
            case 'close':
                check_admin_referer('bulk-competitions');
                if (!($this->request->has('competitions') || $this->request->has('competition'))) {
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
                    ++ $deleteCount;
                }
                $redirect = add_query_arg(['deleteCount' => $deleteCount, 'update' => 'del_many'], $redirect);
                wp_redirect($redirect);
                exit();

            case 'doopen':
                check_admin_referer('open-competitions');
                if (!$this->request->has('competitions')) {
                    wp_redirect($redirect);
                    exit();
                }
                $competitionIds = $this->request->input('competitions');
                $count          = 0;

                foreach ((array) $competitionIds as $id) {
                    $data           = [];
                    $data['ID']     = (int) $id;
                    $data['Closed'] = 'N';
                    $query_competitions->insertCompetition($data);
                    ++ $count;
                }
                $redirect = add_query_arg(['count' => $count, 'update' => 'open_many'], $redirect);
                wp_redirect($redirect);
                exit();

            case 'doclose':
                check_admin_referer('close-competitions');
                if (!$this->request->has('competitions')) {
                    wp_redirect($redirect);
                    exit();
                }
                $competitionIds = $this->request->input('competitions');
                $count          = 0;

                foreach ((array) $competitionIds as $id) {
                    $data           = [];
                    $data['ID']     = (int) $id;
                    $data['Closed'] = 'Y';
                    $query_competitions->insertCompetition($data);
                    ++ $count;
                }
                $redirect = add_query_arg(['count' => $count, 'update' => 'close_many'], $redirect);
                wp_redirect($redirect);
                exit();

            case 'setscore':
                if ($this->request->input('competition') !== '') {
                    check_admin_referer('score_' . $this->request->input('competition'));
                    $data           = [];
                    $data['ID']     = (int) $this->request->input('competition');
                    $data['Scored'] = 'Y';
                    $query_competitions->insertCompetition($data);
                }
                wp_redirect($redirect);
                exit();

            case 'Unsetscore':
                if ($this->request->input('competition') !== '') {
                    check_admin_referer('score_' . $this->request->input('competition'));
                    $data           = [];
                    $data['ID']     = (int) $this->request->input('competition');
                    $data['Scored'] = 'N';
                    $query_competitions->insertCompetition($data);
                }
                wp_redirect($redirect);
                exit();

            default:
                if ($this->request->has('_wp_http_referer')) {
                    wp_redirect(remove_query_arg(['_wp_http_referer', '_wpnonce'],
                                                 stripslashes($this->request->server('REQUEST_URI'))));
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
            $redirect = remove_query_arg(['wp_http_referer', 'updated', 'delete_count'],
                                         stripslashes($this->request->input('wp_http_referer')));
        } else {
            $redirect = admin_url('admin.php') . '?page=' . Constants::MENU_SLUG_ENTRIES;
        }

        $doAction = $this->entries_list->current_action();
        switch ($doAction) {
            case 'delete':
                check_admin_referer('bulk-entries');
                if (!($this->request->has('entries') || $this->request->has('entry'))) {
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
                    ++ $deleteCount;
                }
                $redirect = add_query_arg(['deleteCount' => $deleteCount, 'update' => 'del_many'], $redirect);
                wp_redirect($redirect);
                exit();

            default:
                if ($this->request->has('_wp_http_referer')) {
                    wp_redirect(remove_query_arg(['_wp_http_referer', '_wpnonce'],
                                                 stripslashes($this->request->server('REQUEST_URI'))));
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
     * Perform check to see if the updated entry is valid.
     *
     * @param array             $formOptionsNew
     * @param Entry             $entry       Entry record
     * @param QueryCompetitions $competition Competition record
     *
     * @return bool|\WP_Error
     */
    private function isValidEntry($formOptionsNew, Entry $entry, $competition)
    {
        $query_entries        = new QueryEntries($this->rpsdb);
        $query_competitions   = new QueryCompetitions($this->rpsdb);
        $medium_array         = Constants::getMediums();
        $classification_array = Constants::getClassifications();
        $return               = true;
        $user                 = get_user_by('id', $entry->Member_ID);

        if ($competition->Medium !== $formOptionsNew['medium'] ||
            $competition->Classification !== $formOptionsNew['classiification']
        ) {
            $new_competition = $query_competitions->getCompetitionByDateClassMedium($competition->Competition_Date,
                                                                                    $classification_array[$formOptionsNew['classification']],
                                                                                    $medium_array[$formOptionsNew['medium']]);

            $max_per_id = $query_entries->countEntriesByCompetitionId($new_competition->ID, $user->ID);
            if ($max_per_id >= $new_competition->Max_Entries) {
                $error_message = 'The maximum of ' .
                                 $new_competition->Max_Entries .
                                 ' entries into this competition has been reached. Update cancelled.';

                $return = new \WP_Error('rpsAdminEditEntry', $error_message);
            }
        }
        unset($query_entries, $query_competitions);

        return $return;
    }

    /**
     * Display plugin Copyright
     */
    private function printAdminFooter()
    {
        echo '<div class="clear"></div>';
        echo '<p class="footer_avhfdas">';
        printf('&copy; Copyright 2012-%s <a href="http://blog.avirtualhome.com/" title="My Thoughts">Peter van der Does</a> | AVH RPS Competition version %s',
               date('Y'),
               Constants::PLUGIN_VERSION);
        echo '</p>';
    }

    /**
     * Set the default values for teh jQuery Datepicker plugin
     */
    private function printDatepickerDefaults()
    {
        echo '<script type="text/javascript">' . "\n";
        echo 'jQuery(function($) {' . "\n";
        echo ' $.datepicker.setDefaults({' . "\n";
        echo '   showButtonPanel: true, ' . "\n";
        echo '   buttonImageOnly: true, ' . "\n";
        echo '   buttonImage: "' . CommonHelper::getPluginUrl('calendar.png',
                                                              $this->settings->get('images_dir')) . '", ' . "\n";
        echo '   showOn: "both"' . "\n";
        echo ' });' . "\n";
        echo '});', "\n";
        echo '</script>';
    }

    /**
     * Update a competition after a POST
     */
    private function updateCompetition()
    {
        $options            = get_option('avh-rps');
        $query_competitions = new QueryCompetitions($this->rpsdb);
        $formOptionsNew     = [];
        $data               = [];
        $formOptions        = $this->request->input('competition-edit');

        $formOptionsNew['date']           = $formOptions['date'];
        $formOptionsNew['close-date']     = $formOptions['close-date'];
        $formOptionsNew['close-time']     = $formOptions['close-time'];
        $formOptionsNew['theme']          = $formOptions['theme'];
        $formOptionsNew['medium']         = $formOptions['medium'];
        $formOptionsNew['classification'] = $formOptions['classification'];
        $formOptionsNew['max-entries']    = $formOptions['max-entries'];
        $formOptionsNew['judges']         = $formOptions['judges'];
        $formOptionsNew['image_size']     = isset($formOptions['image_size']) ? $formOptions['image_size'] : $options['default_image_size'];
        $formOptionsNew['special_event']  = isset($formOptions['special_event']) ? $formOptions['special_event'] : '';
        $formOptionsNew['closed']         = isset($formOptions['closed']) ? $formOptions['closed'] : '';
        $formOptionsNew['scored']         = isset($formOptions['scored']) ? $formOptions['scored'] : '';

        $medium_array = Constants::getMediums();

        $classification_array     = Constants::getClassifications();
        $data['ID']               = $this->request->input('competition');
        $data['Competition_Date'] = $formOptionsNew['date'];
        $data['Close_Date']       = $formOptionsNew['close-date'] . ' ' . $formOptionsNew['close-time'];
        $data['Theme']            = $formOptionsNew['theme'];
        $data['Max_Entries']      = $formOptionsNew['max-entries'];
        $data['Num_Judges']       = $formOptionsNew['judges'];
        $data['Image_Size']       = $formOptionsNew['image_size'];
        $data['Special_Event']    = ($formOptionsNew['special_event'] ? 'Y' : 'N');
        $data['Closed']           = ($formOptionsNew['closed'] ? 'Y' : 'N');
        $data['Scored']           = ($formOptionsNew['scored'] ? 'Y' : 'N');
        $data['Medium']           = $medium_array[$formOptionsNew['medium']];
        $data['Classification']   = $classification_array[$formOptionsNew['classification']];
        $competition_ID           = $query_competitions->insertCompetition($data);

        unset($query_competitions);

        return !is_wp_error($competition_ID);
    }

    /**
     * Update an entry after a POST
     *
     * @return \WP_Error|bool
     */
    private function updateEntry()
    {
        $query_entries     = new QueryEntries($this->rpsdb);
        $competition_query = new QueryCompetitions($this->rpsdb);

        $formOptions = $this->request->input('entry-edit');
        $id          = (int) $this->request->input('entry');
        $entry       = $query_entries->getEntryById($id);
        /** @var QueryCompetitions $competition */
        $competition = $competition_query->getCompetitionById($entry->Competition_ID);

        $medium_array         = Constants::getMediums();
        $classification_array = Constants::getClassifications();

        $selectedMedium         = array_search($competition->Medium, $medium_array);
        $selectedClassification = array_search($competition->Classification, $classification_array);

        $formOptionsNew                   = [];
        $formOptionsNew['title']          = empty($formOptions['title']) ? $entry->Title : $formOptions['title'];
        $formOptionsNew['medium']         = empty($formOptions['medium']) ? $selectedMedium : $formOptions['medium'];
        $formOptionsNew['classification'] = empty($formOptions['classification']) ? $selectedClassification : $formOptions['classification'];

        $return = $this->isValidEntry($formOptionsNew, $entry, $competition);
        if (!is_wp_error($return)) {
            $return = $this->doUpdateEntry($formOptionsNew, $id, $entry, $competition);
        }

        unset($query_entries);

        return $return;
    }
}
