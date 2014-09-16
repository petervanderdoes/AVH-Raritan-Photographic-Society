<?php
namespace RpsCompetition\Entries;

use Avh\Html\HtmlBuilder;
use Illuminate\Http\Request;
use RpsCompetition\Constants;
use RpsCompetition\Db\QueryCompetitions;
use RpsCompetition\Db\QueryEntries;
use RpsCompetition\Db\QueryMiscellaneous;
use RpsCompetition\Db\RpsDb;
use RpsCompetition\Season\Helper as SeasonHelper;
use RpsCompetition\Settings;

class ListTable extends \WP_List_Table
{
    public $messages;
    public $screen;
    /**
     * @var HtmlBuilder
     */
    private $html;
    /**
     * @var Request
     */
    private $request;
    /**
     * @var RpsDb
     */
    private $rpsdb;
    /**
     * @var Settings
     */
    private $settings;

    /**
     * @param Settings $settings
     * @param RpsDb    $rpsdb
     * @param Request  $request
     */
    public function __construct(Settings $settings, RpsDb $rpsdb, Request $request)
    {
        $this->settings = $settings;
        $this->rpsdb = $rpsdb;
        $this->html = new HtmlBuilder();
        $this->request = $request;

        $this->screen = 'avh_rps_page_avh_rps_entries_';
        $default_status = get_user_option('avhrps_entries_list_last_view');
        if (empty($default_status)) {
            $default_status = 'all';
        }
        $status = $this->request->input('avhrps_entries_list_status', $default_status);
        if (!in_array($status, array('all', 'search'))) {
            $status = 'all';
        }
        if ($status != $default_status && 'search' != $status) {
            update_user_meta(get_current_user_id(), 'avhrps_entries_list_last_view', $status);
        }

        parent::__construct(array('plural' => 'entries', 'singular' => 'entry', 'ajax' => false));
    }

    /**
     * @return bool|void
     */
    public function ajax_user_can()
    {
        return true;
    }

    /**
     * @param object $entry
     */
    public function column_award($entry)
    {
        echo $entry->Award;
    }

    /**
     * @param object $entry
     */

    public function column_cb($entry)
    {
        echo "<input type='checkbox' name='entries[]' value='$entry->ID' />";
    }

    /**
     * @param object $entry
     */
    public function column_competition($entry)
    {
        $query_competitions = new QueryCompetitions($this->rpsdb);

        $_competition = $query_competitions->getCompetitionById($entry->Competition_ID);
        $competition_text = $_competition->Theme . ' - ' . $_competition->Medium . ' - ' . $_competition->Classification;
        echo $competition_text;

        unset($query_competitions);
    }

    /**
     * @param object $entry
     */
    public function column_name($entry)
    {
        $_user = get_user_by('id', $entry->Member_ID);
        $queryUser = array('page' => Constants::MENU_SLUG_ENTRIES, 'user_id' => $_user->ID);
        $urlUser = admin_url('admin.php') . '?' . http_build_query($queryUser, '', '&');
        echo $this->html->anchor($urlUser, $_user->first_name . ' ' . $_user->last_name, array('title' => 'Entries for ' . $_user->first_name . ' ' . $_user->last_name));
    }

    /**
     * @param object $entry
     */
    public function column_score($entry)
    {
        echo $entry->Score;
    }

    /**
     * @param object $entry
     */
    public function column_season($entry)
    {
        $query_competitions = new QueryCompetitions($this->rpsdb);
        $options = get_option('avh-rps');

        $_competition = $query_competitions->getCompetitionById($entry->Competition_ID);
        if ($_competition != false) {
            $unix_date = mysql2date('U', $_competition->Competition_Date);
            $_competition_month = date('n', $unix_date);
            if ($_competition_month >= $options['season_start_month_num'] && $_competition_month <= $options['season_end_month_num']) {
                $_season_text = date('Y', $unix_date) . ' - ' . date('Y', strtotime('+1 year', $unix_date));
            } else {
                $_season_text = date('Y', strtotime('-1 year', $unix_date)) . ' - ' . date('Y', $unix_date);
            }
            echo $_season_text;
        } else {
            echo "Unknown Season";
        }
        unset($query_competitions);
    }

    /**
     * @param object $entry
     */
    public function column_title($entry)
    {
        echo $entry->Title;
        $url = admin_url('admin.php') . '?';

        $queryReferer = array('page' => Constants::MENU_SLUG_ENTRIES);
        $wp_http_referer = 'admin.php?' . http_build_query($queryReferer, '', '&');

        $nonceDelete = wp_create_nonce('bulk-entries');
        $queryDelete = array('page' => Constants::MENU_SLUG_ENTRIES, 'entry' => $entry->ID, 'action' => 'delete', '_wpnonce' => $nonceDelete);
        $urlDelete = $url . http_build_query($queryDelete, '', '&');

        $queryEdit = array('page' => Constants::MENU_SLUG_ENTRIES, 'entry' => $entry->ID, 'action' => 'edit', 'wp_http_referer' => $wp_http_referer);
        $urlEdit = $url . http_build_query($queryEdit, '', '&');

        $actions = array();
        $actions['delete'] = $this->html->anchor($urlDelete, 'Delete', array('class' => 'delete', 'title' => 'Delete this competition'));
        $actions['edit'] = $this->html->anchor($urlEdit, 'Edit', array('title' => 'Edit this entry'));

        echo '<div class="row-actions">';
        $sep = '';
        foreach ($actions as $action => $link) {
            echo "<span class='set_$action'>$sep$link</span>";
            $sep = ' | ';
        }
        echo '</div>';
    }

    /**
     * @return bool|string
     */
    public function current_action()
    {
        if ($this->request->has('clear-recent-list')) {
            return 'clear-recent-list';
        }

        return parent::current_action();
    }

    /**
     *
     */
    public function display()
    {
        //extract($this->_args);

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

    /**
     * @param string $which
     */
    public function extra_tablenav($which)
    {

        $query_miscellaneous = new QueryMiscellaneous($this->rpsdb);
        $query_competitions = new QueryCompetitions($this->rpsdb);
        $season_helper = new SeasonHelper($this->settings, $this->rpsdb);
        $options = get_option('avh-rps');

        echo '<div class="alignleft actions">';
        if ('top' == $which) {
            $_seasons = $query_miscellaneous->getSeasonList('DESC', $options['season_start_month_num'], $options['season_end_month_num']);
            $season = $this->request->input('filter-season', 0);
            echo '<select name="filter-season">';
            echo '<option' . selected($season, 0, false) . ' value="0">' . __('All seasons') . '</option>';
            foreach ($_seasons as $_season) {
                echo '<option' . selected($season, $_season, false) . ' value="' . esc_attr($_season) . '">' . $_season . '</option>';
            }
            echo '</select>';

            if ($this->request->has('filter-season') && $this->request->input('filter-season') != 0) {
                $theme_request = $this->request->input('filter-theme', 0);
                list ($season_start_date, $season_end_date) = $season_helper->getSeasonStartEnd($this->request->input('filter-season'));
                $competitions = $query_competitions->getCompetitionByDates($season_start_date, $season_end_date);

                $themes = array();
                /** @var QueryCompetitions $competition */
                foreach ($competitions as $competition) {
                    $themes[$competition->ID] = $competition->Theme;
                }
                ksort($themes);
                $themes = array_unique($themes);
                asort($themes);

                echo $this->html->element('select', array('name' => 'filter-theme'));
                echo '<option' . selected($theme_request, 0, false) . ' value="0">' . __('All Competition Themes') . '</option>';
                foreach ($themes as $theme_key => $theme_value) {
                    echo '<option' . selected($theme_request, $theme_key, false) . ' value="' . esc_attr($theme_key) . '">' . $theme_value . '</option>';
                }
                echo '</select>';
            }
            submit_button(__('Filter'), 'button', false, false, array('id' => 'entries-query-submit'));
        }
        echo '</div>';
        unset($query_miscellaneous, $query_competitions, $season_helper);
    }

    /**
     * @return array
     */
    public function get_bulk_actions()
    {
        $actions = array();
        $actions['delete'] = __('Delete');

        return $actions;
    }

    /**
     * @return array
     */
    public function get_columns()
    {
        return array('cb' => '<input type="checkbox" />', 'season' => 'Season', 'competition' => 'Competition', 'name' => 'Photographer', 'title' => 'Title', 'score' => 'Score', 'award' => 'Award');
    }

    /**
     * @param string $entry_status
     *
     * @return int|mixed|void
     */
    public function get_per_page($entry_status = 'all')
    {
        $entries_per_page = $this->get_items_per_page('entries_per_page');
        $entries_per_page = apply_filters('entries_per_page', $entries_per_page, $entry_status);

        return $entries_per_page;
    }

    /**
     * @return array
     */
    public function get_sortable_columns()
    {
        return array('');
    }

    /**
     *
     */
    public function no_items()
    {
        _e('No entries.');
    }

    /**
     *
     */
    public function prepare_items()
    {
        global $entry_status, $search;

        $query_entries = new QueryEntries($this->rpsdb);
        $query_competitions = new QueryCompetitions($this->rpsdb);
        $season_helper = new SeasonHelper($this->settings, $this->rpsdb);

        $entry_status = $this->request->input('entry_status', 'all');
        if (!in_array($entry_status, array('all'))) {
            $entry_status = 'all';
        }

        $search = $this->request->input('s', '');

        $orderby = $this->request->input('orderby', 'Competition_ID DESC, Member_ID');
        $order = $this->request->input('order', '');

        $entries_per_page = $this->get_per_page($entry_status);

        $doing_ajax = defined('DOING_AJAX') && DOING_AJAX;

        $number = (int) $this->request->input('number', $entries_per_page + min(8, $entries_per_page)); // Grab a few extra, when changing the 8 changes are need in avh-fdas.ipcachelist.js

        $where = '1=1';
        if ($this->request->has('user_id')) {
            $where = 'Member_ID=' . esc_sql($this->request->input('user_id'));
        }
        if ($this->request->has('filter-season') && $this->request->input('filter-season') != 0) {
            list ($season_start_date, $season_end_date) = $season_helper->getSeasonStartEnd($this->request->input('filter-season'));
            $where = $this->rpsdb->prepare('Competition_Date >= %s AND Competition_Date <= %s', $season_start_date, $season_end_date);

            $filter_theme = $this->request->input('filter-theme', 0);
            if ($filter_theme != 0) {
                $record = $query_competitions->getCompetitionById($filter_theme);
                $where .= $this->rpsdb->prepare(' AND Theme = %s', $record->Theme);
            }
            $sql_query = array('where' => $where);

            $competitions = $query_competitions->query($sql_query);

            $competition_ids = array(0);
            foreach ($competitions as $competition) {

                $competition_ids[] = $competition->ID;
            }
            $where = 'Competition_ID IN (' . implode(',', $competition_ids) . ')';
        }
        $page = $this->get_pagenum();

        $start = (int) $this->request->input('start', ($page - 1) * $entries_per_page);

        if ($doing_ajax && $this->request->has('offset')) {
            $start += (int) $this->request->input('offset');
        }

        $args = array('search' => $search, 'offset' => $start, 'number' => $number, 'orderby' => $orderby, 'order' => $order, 'where' => $where);

        $_entries = $query_entries->query($args);
        $this->items = array_slice($_entries, 0, $entries_per_page);
        $this->extra_items = array_slice($_entries, $entries_per_page);

        $total_entries = $query_entries->query(array_merge($args, array('count' => true, 'offset' => 0, 'number' => 0)));

        $this->set_pagination_args(array('total_items' => $total_entries, 'per_page' => $entries_per_page));

        unset($query_competitions, $query_entries, $season_helper);
    }

    /**
     * @param object $a_entry
     */
    public function single_row($a_entry)
    {
        $entry = $a_entry;
        echo '<tr id="entry-' . $entry->ID . '">';
        $this->single_row_columns($entry);
        echo "</tr>";
    }
}
