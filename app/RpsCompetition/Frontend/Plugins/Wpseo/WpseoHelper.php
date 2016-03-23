<?php
/**
 * Copyright 2016 Peter van der Does
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace RpsCompetition\Frontend\Plugins\Wpseo;

use Illuminate\Config\Repository as Settings;
use RpsCompetition\Db\QueryCompetitions;
use RpsCompetition\Db\QueryMiscellaneous;
use RpsCompetition\Helpers\CommonHelper;
use RpsCompetition\Helpers\PhotoHelper;

/**
 * Class WpseoHelper
 *
 * @package   RpsCompetition\Frontend
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2016, AVH Software
 */
class WpseoHelper
{
    private $photo_helper;
    private $query_competitions;
    private $query_miscellaneous;
    private $settings;

    /**
     * Constructor
     *
     * @param Settings           $settings
     * @param QueryCompetitions  $query_competitions
     * @param QueryMiscellaneous $query_miscellaneous
     * @param PhotoHelper        $photo_helper
     */
    public function __construct(Settings $settings,
                                QueryCompetitions $query_competitions,
                                QueryMiscellaneous $query_miscellaneous,
                                PhotoHelper $photo_helper)
    {

        $this->settings = $settings;
        $this->query_competitions = $query_competitions;
        $this->query_miscellaneous = $query_miscellaneous;
        $this->photo_helper = $photo_helper;
    }

    /**
     * Setup replace variables for WordPress Seo by Yoast.
     *
     * We use this variable to build a title page for the dynamic pages.
     */
    public function actionWpseoRegisterExtraReplacements()
    {
        wpseo_register_var_replacement('%%rpstitle%%', [$this, 'handleWpSeoTitleReplace']);
    }

    /**
     * Get title for og:title
     * By default the plugin uses the title as created to be shown in the browser which includes the site name.
     * Facebook recommends to exclude branding.
     *
     * @see https://developers.facebook.com/docs/sharing/best-practices#tags
     *
     * @param string $title
     *
     * @return string
     */
    public function filterOpenGraphTitle($title)
    {
        if (!is_front_page()) {
            $title = get_the_title();
        }

        return $title;
    }

    /**
     * Filter for the title of pages.
     *
     * If the page is one of the dynamic pages we build a better title for the page.
     *
     * @param array $title_array
     *
     * @return array
     */
    public function filterWpTitleParts($title_array)
    {
        global $post;

        if (!is_object($post)) {
            return $title_array;
        }
        $pages_array = CommonHelper::getDynamicPages();
        if (isset($pages_array[$post->ID])) {
            $selected_date = get_query_var('selected_date');
            $date = new \DateTime($selected_date);
            $date_text = $date->format('F j, Y');
            $competitions = $this->query_competitions->getCompetitionByDates($selected_date);
            $competition = current($competitions);

            $new_title_array = [];
            $new_title_array[] = $post->post_title . ' for the theme "' . $competition->Theme . '" on ' . $date_text;
            $title_array = $new_title_array;
        }

        return $title_array;
    }

    /**
     * Filter the meta description for the following pages:
     * - Monthly Entries
     * - Monthly Winners
     *
     * @param string $meta_description
     *
     * @return string
     */
    public function filterWpseoMetaDescription($meta_description)
    {
        global $post;

        if (!is_object($post)) {
            return $meta_description;
        }
        $options = get_option('avh-rps');
        $pages_array = CommonHelper::getDynamicPages();
        if (isset($pages_array[$post->ID])) {
            $selected_date = get_query_var('selected_date');
            $competitions = $this->query_competitions->getCompetitionByDates($selected_date);
            $competition = current($competitions);
            $theme = ucfirst($competition->Theme);
            $date = new \DateTime($selected_date);
            $date_text = $date->format('F j, Y');
            $entries_amount = $this->query_miscellaneous->countAllEntries($selected_date);

            if ($post->ID == $options['monthly_entries_post_id']) {
                $meta_description = 'The ' .
                                    $entries_amount .
                                    ' entries submitted to Raritan Photographic Society for the theme "' .
                                    $theme .
                                    '" held on ' .
                                    $date_text;
            }
            if ($post->ID == $options['monthly_winners_post_id']) {
                $meta_description = 'Out of ' .
                                    $entries_amount .
                                    ' entries, a jury selected these winners of the competition with the theme "' .
                                    $theme .
                                    '" held on ' .
                                    $date_text;
            }
        }

        return $meta_description;
    }

    /**
     * Filter for WordPress SEO plugin by Yoast: Use for the OpenGraph image property.
     * We only want to use images that are resized for Facebook shared link.
     * We add "_fb_thumb" to those thumbnail files.
     * If we return an empty string the image is not selected for the og:image meta information.
     *
     * @param string $img
     *
     * @return string
     */
    public function filterWpseoOpengraphImage($img)
    {
        if (strpos($img, '_fb_thumb.jpg') !== false) {
            return $img;
        }

        return '';
    }

    /**
     * Filter for WordPress SEO plugin by Yoast: Before analyzing the post content.
     * As some of the pages create dynamic images through shortcode we need to run the shortcode.
     * That's the only way the WordPress SEO plugin can see the images.
     * Running the shortcodes now does not effect the final rendering of the post.
     *
     * @param string $post_content
     *
     * @return string
     */
    public function filterWpseoPreAnalysisPostsContent($post_content)
    {
        if (has_shortcode($post_content, 'rps_category_winners')) {
            $post_content = do_shortcode($post_content);
            $this->settings->set('didFilterWpseoPreAnalysisPostsContent', true);

            return $post_content;
        }

        if (has_shortcode($post_content, 'rps_monthly_entries')) {
            $post_content = do_shortcode($post_content);
            $this->settings->set('didFilterWpseoPreAnalysisPostsContent', true);

            return $post_content;
        }

        if (has_shortcode($post_content, 'rps_monthly_winners')) {
            $post_content = do_shortcode($post_content);
            $this->settings->set('didFilterWpseoPreAnalysisPostsContent', true);

            return $post_content;
        }

        if (has_shortcode($post_content, 'gallery')) {
            $post_content = do_shortcode($post_content);
            $this->settings->set('didFilterWpseoPreAnalysisPostsContent', true);

            return $post_content;
        }

        return $post_content;
    }

    /**
     * Handle the replacement variable for WordPress Seo by Yoast.
     *
     * @return string
     */
    public function handleWpSeoTitleReplace()
    {
        global $post;

        $replacement = null;
        $title_array = [];

        if (is_string($post->post_title) && $post->post_title !== '') {
            $replacement = stripslashes($post->post_title);
        }
        $title_array[] = $replacement;

        $new = $this->filterWpTitleParts($title_array);

        return $new[0];
    }
}
