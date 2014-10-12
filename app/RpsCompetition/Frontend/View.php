<?php
namespace RpsCompetition\Frontend;

use Avh\Html\FormBuilder;
use Avh\Html\HtmlBuilder;
use Illuminate\Http\Request;
use RpsCompetition\Db\QueryEntries;
use RpsCompetition\Db\RpsDb;
use RpsCompetition\Photo\Helper as PhotoHelper;
use RpsCompetition\Season\Helper as SeasonHelper;
use RpsCompetition\Settings;
use Twig_Environment;
use Twig_Loader_Filesystem;

if (!class_exists('AVH_RPS_Client')) {
    header('Status: 403 Forbidden');
    header('HTTP/1.1 403 Forbidden');
    exit();
}

/**
 * Class View
 *
 * @package RpsCompetition\Frontend
 */
class View
{
    private $form_builder;
    private $html_builder;
    private $photo_helper;
    private $request;
    private $rpsdb;
    private $season_helper;
    private $settings;
    private $twig;

    /**
     * Constructor
     *
     * @param Settings $settings
     * @param RpsDb    $rpsdb
     * @param Request  $request
     */
    public function __construct(Settings $settings, RpsDb $rpsdb, Request $request)
    {
        $this->settings = $settings;
        $this->rpsdb = $rpsdb;
        $this->request = $request;
        $this->html_builder = new HtmlBuilder();
        $this->form_builder = new FormBuilder($this->html_builder);
        $this->photo_helper = new PhotoHelper($this->settings, $this->request, $this->rpsdb);
        $this->season_helper = new SeasonHelper($this->settings, $this->rpsdb);
        $loader = new Twig_Loader_Filesystem($this->settings->get('template_dir'));
        //$this->twig = new Twig_Environment($loader, array('cache' => $this->settings->get('upload_dir') .'/twig-cache/'));
        $this->twig = new Twig_Environment($loader);
    }

    /**
     * Collect needed data to render the Category Winners
     *
     * @param array $data
     *
     * @see Shortcodes::shortcodeCategoryWinners
     *
     * @return string
     */
    public function renderCategoryWinners($data)
    {
        $data['images'] = array();
        foreach ($data['records'] as $recs) {
            $data['images'][] = $this->dataPhotoGallery($recs, $data['thumb_size']);
        }
        $template = $this->twig->loadTemplate('category-winners.html.twig');
        unset ($data['records']);

        return $template->render($data);
    }

    /**
     * Display the Facebook thumbs for the Category Winners Page.
     *
     * @param array $entries
     *
     * @return string
     */
    public function renderCategoryWinnersFacebookThumbs($entries)
    {
        $images = array();
        foreach ($entries as $entry) {
            $images[] = $this->photo_helper->getThumbnailUrl($entry->Server_File_Name, 'fb_thumb');
        }
        $template = $this->twig->loadTemplate('facebook.html.twig');

        return $template->render(array('images' => $images));
    }

    /**
     * Display the form for selecting the month and season.
     *
     * @param string  $selected_season
     * @param string  $selected_date
     * @param boolean $is_scored_competitions
     * @param array   $months
     *
     * @return string
     */
    public function renderMonthAndSeasonSelectionForm($selected_season, $selected_date, $is_scored_competitions, $months)
    {
        global $post;
        $output = '<script type="text/javascript">';
        $output .= 'function submit_form(control_name) {' . "\n";
        $output .= '	document.month_season_form.submit_control.value = control_name;' . "\n";
        $output .= '	document.month_season_form.submit();' . "\n";
        $output .= '}' . "\n";
        $output .= '</script>';

        $action = home_url('/' . get_page_uri($post->ID));
        $output .= $this->form_builder->open($action, array('name' => 'month_season_form'));
        $output .= $this->form_builder->hidden('submit_control');
        $output .= $this->form_builder->hidden('selected_season', $selected_season);
        $output .= $this->form_builder->hidden('selected_date', $selected_date);

        if ($is_scored_competitions) {
            // Drop down list for months
            $output .= $this->getMonthsDropdown($months, $selected_date);
        } else {
            $output .= 'No scored competitions this season. ';
        }

        // Drop down list for season
        $output .= $this->season_helper->getSeasonDropdown($selected_season);
        $output .= $this->form_builder->close();

        return $output;
    }

    /**
     * Render the HTML for the Monthly Entries
     *
     * @param array $data
     *
     * @return string
     */
    public function renderMonthlyEntries($data)
    {
        $data['month_season_form'] = $this->dataMonthAndSeasonSelectionForm($data['months']);
        $data['images'] = array();
        if (is_array($data['entries'])) {
            // Iterate through all the award winners and display each thumbnail in a grid
            /** @var QueryEntries $entry */
            foreach ($data['entries'] as $entry) {
                $data['images'][] = $this->dataPhotoMasonry($entry, $data['thumb_size']);
            }
        }
        $template = $this->twig->loadTemplate('monthly-entries.html.twig');
        unset ($data['entries']);

        return $template->render($data);
    }

    /**
     *  Render the Person winners thumbnails.
     *
     * @param array $data
     *
     * @see Shortcodes::shortcodePersonWinners
     *
     * @return string
     */
    public function renderPersonWinners($data)
    {
        $data['images'] = array();
        foreach ($data['records'] as $recs) {
            $data['images'][] = $this->dataPhotoMasonry($recs, $data['thumb_size']);
        }
        unset ($data['records']);

        $template = $this->twig->loadTemplate('person-winners.html.twig');

        return $template->render($data);
    }

    /**
     * Render the Showcase competition thumbnails
     *
     * @param array $data
     *
     * @see Frontend::actionShowcaseCompetitionThumbnails
     *
     * @return string
     */
    public function renderShowcaseCompetitionThumbnails($data)
    {
        $data['images'] = array();
        foreach ($data['records'] as $recs) {
            $data['images'][] = $this->dataPhotoGallery($recs, $data['thumb_size']);
        }
        $template = $this->twig->loadTemplate('showcase.html.twig');
        unset ($data['records']);

        return $template->render($data);
    }

    /**
     * Collect needed data to render the Month and Season select form
     *
     * @param array $months
     *
     * @return array
     */
    private function dataMonthAndSeasonSelectionForm($months)
    {
        global $post;
        $data = array();
        $data['action'] = home_url('/' . get_page_uri($post->ID));
        $data['months'] = $months;
        $seasons = $this->season_helper->getSeasons();
        $data['seasons'] = array_combine($seasons, $seasons);

        return $data;
    }

    /**
     * Collect needed data to render the photo credit
     *
     * @param string $title
     * @param string $first_name
     * @param string $last_name
     *
     * @return array
     */
    private function dataPhotoCredit($title, $first_name, $last_name)
    {
        $data = array();
        $data['title'] = $title;
        $data['credit'] = "$first_name $last_name";

        return $data;;
    }

    /**
     * Collect needed data to render a photo in masonry style.
     *
     * @param QueryEntries $record
     *
     * @return array<string,string|array>
     */
    private function dataPhotoGallery($record, $thumb_size)
    {

        $data = array();
        $user_info = get_userdata($record->Member_ID);
        $title = $record->Title;
        $last_name = $user_info->user_lastname;
        $first_name = $user_info->user_firstname;
        $data['url_800'] = $this->photo_helper->getThumbnailUrl($record->Server_File_Name, '800');
        $data['url_thumb'] = $this->photo_helper->getThumbnailUrl($record->Server_File_Name, $thumb_size);
        $data['title'] = $title . ' by ' . $first_name . ' ' . $last_name;
        $data['caption'] = $this->dataPhotoCredit($title, $first_name, $last_name);

        return $data;
    }

    /**
     * Collect needed data to render a photo in masonry style.
     *
     * @param QueryEntries $record
     *
     * @return array<string,string|array>
     */
    private function dataPhotoMasonry($record, $thumb_size)
    {

        $data = array();
        $user_info = get_userdata($record->Member_ID);
        $title = $record->Title;
        $last_name = $user_info->user_lastname;
        $first_name = $user_info->user_firstname;
        $data['url_800'] = $this->photo_helper->getThumbnailUrl($record->Server_File_Name, '800');
        $data['url_thumb'] = $this->photo_helper->getThumbnailUrl($record->Server_File_Name, $thumb_size);
        $data['dimensions'] = $this->photo_helper->getThumbnailImageSize($record->Server_File_Name, $thumb_size);
        $data['title'] = $title . ' by ' . $first_name . ' ' . $last_name;
        $data['caption'] = $this->dataPhotoCredit($title, $first_name, $last_name);

        return $data;
    }

    /**
     * Display a dropdown for the given months
     *
     * @param array  $months
     * @param string $selected_month
     *
     * @return string
     */
    private function getMonthsDropdown($months, $selected_month)
    {

        $output = $this->form_builder->select('new_month', $months, $selected_month, array('onChange' => 'submit_form("new_month")'));

        return $output;
    }
}