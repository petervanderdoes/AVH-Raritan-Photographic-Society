<?php
namespace RpsCompetition\Frontend;

use RpsCompetition\Definitions\ViewAbstract;

/**
 * Class FrontendView
 *
 * @package   RpsCompetition\Frontend
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2016, AVH Software
 */
class FrontendView extends ViewAbstract
{
    /**
     * Constructor
     *
     * @param string $template_dir
     * @param string $cache_dir
     *
     */
    public function __construct($template_dir, $cache_dir)
    {
        parent::__construct($template_dir, $cache_dir);
    }

    /**
     * Render Facebook
     *
     * @param array $data
     *
     * @return string
     */
    public function renderFacebookThumbs($data)
    {

        return $this->fetch('facebook.html.twig', $data);
    }

    /**
     * Render a Masonry Post Gallery
     *
     * @param array $data
     *
     * @return string
     */
    public function renderGalleryMasonry($data)
    {
        return $this->fetch('gallery-masonry.html.twig', $data);
    }

    /**
     * Render a regular Post Gallery
     *
     * @param array $data
     *
     * @return string
     */
    public function renderPostGallery($data)
    {

        return $this->fetch('post-gallery.html.twig', $data);
    }

    /**
     * Render the feed of attachements.
     *
     * @see \RpsCompetition\Frontend\Frontend::filterPostGallery
     *
     * @param array  $attachments
     * @param string $size
     *
     * @return string
     */
    public function renderPostGalleryFeed($attachments, $size)
    {
        $output = "\n";
        $attachments_id = array_keys($attachments);
        foreach ($attachments_id as $id) {
            $output .= wp_get_attachment_link($id, $size, true) . "\n";
        }

        return $output;
    }

    /**
     * Render the Showcase competition thumbnails
     *
     * @see Frontend::actionShowcaseCompetitionThumbnails
     *
     * @param array $data
     *
     * @return void
     */
    public function renderShowcaseCompetitionThumbnails($data)
    {
        $this->display('showcase.html.twig', $data);

        return;
    }
}
