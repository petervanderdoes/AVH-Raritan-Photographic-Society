<?php
namespace RpsCompetition\Frontend;

use Avh\Html\FormBuilder;
use Avh\Html\HtmlBuilder;
use Illuminate\Config\Repository as Settings;
use Illuminate\Http\Request as IlluminateRequest;
use RpsCompetition\Db\QueryEntries;
use RpsCompetition\Db\RpsDb;
use RpsCompetition\Helpers\PhotoHelper;
use RpsCompetition\Helpers\SeasonHelper;
use Twig_Environment;
use Twig_Loader_Filesystem;

/**
 * Class View
 *
 * @package   RpsCompetition\Frontend
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2015, AVH Software
 */
class FrontendView
{
    /** @var FormBuilder */
    private $form_builder;
    /** @var HtmlBuilder */
    private $html_builder;
    /** @var PhotoHelper */
    private $photo_helper;
    private $request;
    private $rpsdb;
    /** @var SeasonHelper */
    private $season_helper;
    private $settings;
    /** @var Twig_Environment */
    private $twig;

    /**
     * Constructor
     *
     * @param Settings          $settings
     * @param RpsDb             $rpsdb
     * @param IlluminateRequest $request
     */
    public function __construct(Settings $settings, RpsDb $rpsdb, IlluminateRequest $request)
    {
        $this->settings = $settings;
        $this->rpsdb = $rpsdb;
        $this->request = $request;
        $this->html_builder = new HtmlBuilder();
        $this->form_builder = new FormBuilder($this->html_builder);
        $this->photo_helper = new PhotoHelper($this->settings, $this->request, $this->rpsdb);
        $this->season_helper = new SeasonHelper($this->settings, $this->rpsdb);
        $loader = new Twig_Loader_Filesystem($this->settings->get('template_dir'));
        if (WP_LOCAL_DEV !== true) {
            $this->twig = new Twig_Environment(
                $loader, ['cache' => $this->settings->get('upload_dir') . '/twig-cache/']
            );
        } else {
            $this->twig = new Twig_Environment($loader);
        }
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
        $data['images'] = [];
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
        $images = [];
        foreach ($entries as $entry) {
            $images[] = $this->photo_helper->getThumbnailUrl($entry->Server_File_Name, 'fb_thumb');
        }
        $template = $this->twig->loadTemplate('facebook.html.twig');

        return $template->render(['images' => $images]);
    }

    /**
     * Display the Gallery as Masonry.
     *
     * @param array $attachments
     *
     * @return string
     */
    public function renderGalleryMasonry($attachments)
    {
        $data = [];
        $caption_data = [];

        foreach ($attachments as $id => $attachment) {
            $img_url = wp_get_attachment_url($id);
            $home_url = home_url();
            if (substr($img_url, 0, strlen($home_url)) == $home_url) {
                /** @var QueryEntries $entry */
                $entry = new \stdClass;
                $img_relative_path = substr($img_url, strlen($home_url));
                $entry->Server_File_Name = $img_relative_path;
                $entry->ID = $attachment->ID;

                if (trim($attachment->post_excerpt)) {
                    $caption_data['title'] = $attachment->post_excerpt;
                } else {
                    $caption_data['title'] = $attachment->post_title;
                }
                $caption_data['first_name'] = get_post_meta($attachment->ID, '_rps_photographer_name', true);
                $caption_data['last_name'] = '';

                $data['images'][] = $this->dataPhotoMasonry($entry, '150w', $caption_data);
            }
        }

        $template = $this->twig->loadTemplate('gallery-masonry.html.twig');

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
        $data['images'] = [];
        foreach ($data['records'] as $recs) {
            $data['images'][] = $this->dataPhotoGallery($recs, $data['thumb_size']);
        }
        $template = $this->twig->loadTemplate('showcase.html.twig');
        unset ($data['records']);

        return $template->render($data);
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
        $data = [];
        $data['title'] = $title;
        $data['credit'] = $first_name . ' ' . $last_name;

        return $data;
    }

    /**
     * Collect needed data to render a photo in masonry style.
     *
     * @param QueryEntries $record
     * @param string       $thumb_size
     *
     * @return array<string,string|array>
     */
    private function dataPhotoGallery($record, $thumb_size)
    {

        $data = [];
        $user_info = get_userdata($record->Member_ID);
        $title = $record->Title;
        $last_name = $user_info->user_lastname;
        $first_name = $user_info->user_firstname;
        $data['url_large'] = $this->photo_helper->getThumbnailUrl($record->Server_File_Name, '800');
        $data['url_thumb'] = $this->photo_helper->getThumbnailUrl($record->Server_File_Name, $thumb_size);
        $data['dimensions'] = $this->photo_helper->getThumbnailImageSize($record->Server_File_Name, $thumb_size);
        $data['title'] = $title . ' by ' . $first_name . ' ' . $last_name;
        $data['caption'] = $this->dataPhotoCredit($title, $first_name, $last_name);

        return $data;
    }

    /**
     * Collect needed data to render a photo in masonry style.
     *
     * @param QueryEntries $record
     * @param string       $thumb_size
     * @param array        $caption
     *
     * @return array<string,string|array>
     */
    private function dataPhotoMasonry($record, $thumb_size, $caption)
    {
        $data = [];
        $data['url_large'] = $this->photo_helper->getThumbnailUrl($record->Server_File_Name, '800');
        $data['url_thumb'] = $this->photo_helper->getThumbnailUrl($record->Server_File_Name, $thumb_size);
        $data['dimensions'] = $this->photo_helper->getThumbnailImageSize($record->Server_File_Name, $thumb_size);
        $data['caption'] = $this->dataPhotoCredit($caption['title'], $caption['first_name'], $caption['last_name']);

        return $data;
    }
}
