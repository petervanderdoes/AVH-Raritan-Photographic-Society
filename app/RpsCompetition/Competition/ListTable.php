<?php
namespace RpsCompetition\Competition;

use Avh\Framework\Html\HtmlBuilder;
use Illuminate\Http\Request;
use RpsCompetition\Constants;
use RpsCompetition\Db\QueryCompetitions;
use RpsCompetition\Db\QueryEntries;
use RpsCompetition\Db\RpsDb;

/**
 * Class ListTable
 *
 * @package   RpsCompetition\Competition
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2016, AVH Software
 */
class ListTable extends \WP_List_Table
{
    public $extra_items;
    public $messages;
    public $screen;
    /** @var HtmlBuilder */
    private $html;
    private $request;
    private $rpsdb;

    /**
     * Constructor
     *
     * @param RpsDb   $rpsdb
     * @param Request $request
     *
     */
    public function __construct(RpsDb $rpsdb, Request $request)
    {
        $this->rpsdb   = $rpsdb;
        $this->request = $request;
        $this->html    = new HtmlBuilder();

        $this->screen   = 'avh_rps_page_avh_rps_competition_';
        $default_status = get_user_option('avhrps_competition_list_last_view');
        if (empty($default_status)) {
            $default_status = 'all';
        }
        $status = $this->request->input('avhrps_competition_list_status', $default_status);
        if (!in_array($status, ['all', 'open', 'closed', 'search'])) {
            $status = 'all';
        }
        if ($status != $default_status && 'search' != $status) {
            update_user_meta(get_current_user_id(), 'avhrps_competition_list_last_view', $status);
        }

        parent::__construct(['plural' => 'competitions', 'singular' => 'competition', 'ajax' => false]);
    }

    /**
     * @return bool
     */
    public function ajax_user_can()
    {
        return true;
    }

    /**
     * @param object $competition
     */
    public function column_cb($competition)
    {
        echo '<input type="checkbox" name="competitions[]" value="' . $competition->ID . '" />';
    }

    /**
     * @param object $competition
     */
    public function column_classification($competition)
    {
        echo $competition->Classification;
    }

    /**
     * @param object $competition
     */
    public function column_date($competition)
    {
        $date_text = mysql2date(get_option('date_format'), $competition->Competition_Date);
        echo $date_text;

        $url = admin_url('admin.php') . '?';

        $queryReferer    = ['page' => Constants::MENU_SLUG_COMPETITION];
        $wp_http_referer = 'admin.php?' . http_build_query($queryReferer, '', '&');

        $nonceDelete = wp_create_nonce('bulk-competitions');
        $queryDelete = [
            'page'        => Constants::MENU_SLUG_COMPETITION,
            'competition' => $competition->ID,
            'action'      => 'delete',
            '_wpnonce'    => $nonceDelete
        ];
        $urlDelete   = $url . http_build_query($queryDelete, '', '&');

        $queryEdit = [
            'page'            => Constants::MENU_SLUG_COMPETITION,
            'competition'     => $competition->ID,
            'action'          => 'edit',
            'wp_http_referer' => $wp_http_referer
        ];
        $urlEdit   = $url . http_build_query($queryEdit, '', '&');

        $actions           = [];
        $actions['delete'] = $this->html->anchor($urlDelete,
                                                 'Delete',
                                                 ['class' => 'delete', 'title' => 'Delete this competition']);
        $actions['edit']   = $this->html->anchor($urlEdit, 'Edit', ['title' => 'Edit this competition']);

        echo '<div class="row-actions">';
        $sep = '';
        foreach ($actions as $action => $link) {
            echo '<span class="set_' . $action . '">' . $sep . $link . '</span>';
            $sep = ' | ';
        }
        echo '</div>';
    }

    /**
     * @param object $competition
     */
    public function column_entries($competition)
    {
        /**
         * @var \wpdb $wpdb
         */
        global $wpdb;
        $query_entries = new QueryEntries($this->rpsdb);

        $sqlWhere = $wpdb->prepare('Competition_ID=%d', $competition->ID);
        $entries  = $query_entries->query(['where' => $sqlWhere, 'count' => true]);
        echo $entries;

        unset($query_entries);
    }

    /**
     * @param object $competition
     */
    public function column_medium($competition)
    {
        echo $competition->Medium;
    }

    /**
     * @param object $competition
     */
    public function column_scored($competition)
    {
        echo '<span class="text">' . $competition->Scored . '</span>';

        $actions = [];
        if ($competition->Scored == 'Y') {
            $actions['score'] = '<a class="adm-scored" data-scored="Yes" data-id="' . $competition->ID . '">No</a>';
        } else {
            $actions['score'] = '<a class="adm-scored" data-scored="No" data-id="' . $competition->ID . '">Yes</a>';
        }

        echo '<div class="row-actions">';
        $sep = '';
        foreach ($actions as $action => $link) {
            echo '<span class="set_' . $action . '">' . $sep . $link . '</span>';
            $sep = ' | ';
        }
        echo '</div>';
    }

    /**
     * @param object $competition
     */
    public function column_status($competition)
    {
        echo $competition->Closed;
        if ($competition->Closed == 'N') {
            echo ' (Closing: ' . mysql2date('Y-m-d', $competition->Close_Date) . ')';
        }
    }

    /**
     * @param object $competition
     */
    public function column_theme($competition)
    {
        echo $competition->Theme;
    }

    /**
     * @return bool|string
     */
    public function current_action()
    {
        if ($this->request->input('clear-recent-list')) {
            return 'clear-recent-list';
        }

        return parent::current_action();
    }

    /**
     *
     */
    public function display()
    {
        wp_nonce_field('fetch-list-' . get_class($this), '_ajax_fetch_list_nonce', false);

        $this->display_tablenav('top');

        echo '<table class="wp-list-table ' . implode(' ', $this->get_table_classes()) . '" cellspacing="0">';
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

    /**
     * @param string $which
     */
    public function extra_tablenav($which)
    {
        global $status;

        if ('top' === $which) {
            if ('recently_activated' == $status) {
                echo '<div class="alignleft actions">';
                submit_button(__('Clear List'), 'secondary', 'clear-recent-list', false);
                echo '</div>';
            }
        }
    }

    /**
     * @return array
     */
    public function get_bulk_actions()
    {
        global $competition_status;

        $actions = [];

        $actions['delete'] = __('Delete');
        if ('open' == $competition_status) {
            $actions['close'] = __('Close');
        } elseif ('closed' == $competition_status) {
            $actions['open'] = __('Open');
        }

        return $actions;
    }

    /**
     * @return array
     */
    public function get_columns()
    {
        return [
            'cb'             => '<input type="checkbox" />',
            'date'           => 'Date',
            'theme'          => 'Theme',
            'classification' => 'Classification',
            'medium'         => 'Medium',
            'status'         => 'Closed',
            'scored'         => 'Scored',
            'entries'        => 'Entries'
        ];
    }

    /**
     * @param string $competition_status
     *
     * @return int|mixed|void
     */
    public function get_per_page($competition_status = 'open')
    {
        $competitions_per_page = $this->get_items_per_page('competitions_per_page');
        $competitions_per_page = apply_filters('competitions_per_page', $competitions_per_page, $competition_status);

        return $competitions_per_page;
    }

    /**
     * @return string[]
     */
    public function get_sortable_columns()
    {
        return [''];
    }

    /**
     * @return array
     */
    public function get_views()
    {
        global $competition_status;

        $query_competitions = new QueryCompetitions($this->rpsdb);

        $num_competitions = $query_competitions->countCompetitions();
        $status_links     = [];
        $stati            = [
            'all'    => _nx_noop('All', 'All', 'competitions'),
            'open'   => _n_noop('Open <span class="count">(<span class="open-count">%s</span>)</span>',
                                'Open <span class="count">(<span class="open-count">%s</span>)</span>'),
            'closed' => _n_noop('Closed <span class="count">(<span class="closed-count">%s</span>)</span>',
                                'Closed <span class="count">(<span class="closed-count">%s</span>)</span>')
        ];

        $link = 'admin.php?page=' . Constants::MENU_SLUG_COMPETITION;

        foreach ($stati as $status => $label) {
            $class = ($status == $competition_status) ? ' class="current"' : '';

            if (!isset($num_competitions->$status)) {
                $num_competitions->$status = 10;
            }
            $link = add_query_arg('competition_status', $status, $link);
            // I toyed with this, but decided against it. Leaving it in here in case anyone thinks it is a good idea. ~ Mark if ( !empty( $_REQUEST['s'] ) ) $link = add_query_arg( 's', esc_attr( stripslashes( $_REQUEST['s'] ) ), $link );
            $status_links[$status] = '<a href="' .
                                     $link .
                                     '"' .
                                     $class .
                                     '>' .
                                     sprintf(translate_nooped_plural($label, $num_competitions->$status),
                                             number_format_i18n($num_competitions->$status)) .
                                     '</a>';
        }

        unset($query_competitions);

        return $status_links;
    }

    /**
     *
     */
    public function no_items()
    {
        _e('No competitions.');
    }

    /**
     *
     */
    public function prepare_items()
    {
        global $competition_status, $search;

        $query_competitions = new QueryCompetitions($this->rpsdb);

        $competition_status = $this->request->input('competition_status', 'open');
        if (!in_array($competition_status, ['all', 'open', 'closed'])) {
            $competition_status = 'open';
        }

        $search = $this->request->input('s', '');

        if ($competition_status == 'open') {
            $orderby = $this->request->input('orderby', 'Competition_Date ASC, Class_Code ASC, Medium');
        } else {
            $orderby = $this->request->input('orderby', 'Competition_Date DESC, Class_Code ASC, Medium');
        }
        $order = $this->request->input('order', '');

        $competitions_per_page = $this->get_per_page($competition_status);

        $doing_ajax = defined('DOING_AJAX') && DOING_AJAX;

        $number = (int) $this->request->input('number', $competitions_per_page + min(8, $competitions_per_page));

        $page = $this->get_pagenum();

        $start = (int) $this->request->input('start', ($page - 1) * $competitions_per_page);

        if ($doing_ajax && $this->request->has('offset')) {
            $start += (int) $this->request->input('offset', 0);
        }

        $args = [
            'status'  => $competition_status,
            'search'  => $search,
            'offset'  => $start,
            'number'  => $number,
            'orderby' => $orderby,
            'order'   => $order
        ];

        $competitions      = $query_competitions->query($args);
        $this->items       = array_slice($competitions, 0, $competitions_per_page);
        $this->extra_items = array_slice($competitions, $competitions_per_page);

        $total_competitions = $query_competitions->query(array_merge($args,
                                                                     [
                                                                         'count'  => true,
                                                                         'offset' => 0,
                                                                         'number' => 0
                                                                     ]));

        $this->set_pagination_args(['total_items' => $total_competitions, 'per_page' => $competitions_per_page]);

        unset($query_competitions);
    }

    /**
     * @param object $a_competition
     */
    public function single_row($a_competition)
    {
        $competition = $a_competition;
        $status      = ($competition->Closed == 'Y' ? '' : 'closed');
        echo '<tr id="competition-' . $competition->ID . '" class="' . $status . '">';
        $this->single_row_columns($competition);
        echo '</tr>';
    }
}
