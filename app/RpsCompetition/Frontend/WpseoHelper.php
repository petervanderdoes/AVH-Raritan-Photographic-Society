<?php
namespace RpsCompetition\Frontend;

use RpsCompetition\Common\Helper as CommonHelper;
use RpsCompetition\Db\QueryCompetitions;
use RpsCompetition\Db\QueryMiscellaneous;
use RpsCompetition\Db\RpsDb;
use RpsCompetition\Photo\Helper as PhotoHelper;
use RpsCompetition\Settings;

if (!class_exists('AVH_RPS_Client')) {
    header('Status: 403 Forbidden');
    header('HTTP/1.1 403 Forbidden');
    exit();
}

/**
 * Class WpseoHelper
 *
 * @package RpsCompetition\Frontend
 */
class WpseoHelper
{
    /**
     * @var PhotoHelper
     */
    private $photo_helper;
    /**
     * @var QueryCompetitions
     */
    private $query_competitions;
    /**
     * @var QueryMiscellaneous
     */
    private $query_miscellaneous;
    /**
     * @var RpsDb
     */
    private $rpsdb;
    /**
     * @var Settings
     */
    private $settings;

    /**
     * Constructor
     *
     * @param Settings           $settings
     * @param RpsDb              $rpsdb
     * @param QueryCompetitions  $query_competitions
     * @param QueryMiscellaneous $query_miscellaneous
     * @param PhotoHelper        $photo_helper
     */
    public function __construct(Settings $settings, RpsDb $rpsdb, QueryCompetitions $query_competitions, QueryMiscellaneous $query_miscellaneous, PhotoHelper $photo_helper)
    {

        $this->settings = $settings;
        $this->rpsdb = $rpsdb;
        $this->query_competitions = $query_competitions;
        $this->query_miscellaneous = $query_miscellaneous;
        $this->photo_helper = $photo_helper;
    }

    /**
     * Setup replace variables for WordPress Seo by Yoast.
     */
    public function actionWpseoRegisterExtraReplacements()
    {
        wpseo_register_var_replacement('%%rpstitle%%', array($this, 'handleWpSeoTitleReplace'));
    }

    /**
     * Build sitemap for Competition Entries
     *
     */
    public function actionWpseoSitemapCompetitionEntries()
    {
        $options = get_option('avh-rps');
        $url = get_permalink($options['monthly_entries_post_id']);
        $this->buildWpseoSitemap($url, true);
        exit();
    }

    /**
     * Build sitemap for Competition Winners
     *
     */
    public function actionWpseoSitemapCompetitionWinners()
    {
        $options = get_option('avh-rps');
        $url = get_permalink($options['monthly_winners_post_id']);
        $this->buildWpseoSitemap($url);
        exit();
    }

    /**
     * Get title for og:title
     * By default the plugin uses the title as created to be shown in the browser which includes the site name.
     * Facebook recommends to exclude branding.
     *
     * @param string $title
     *
     * @see https://developers.facebook.com/docs/sharing/best-practices#tags
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
     * Change the Sitemap data for the front page.
     * As WordPress SEO by Yoast does not change the data for the front page, we do this ourselves.
     * We set the priority to 100%, the Change Frequency to Daily, and set the last modified date
     * by getting the articles on the front page and using the most recent modified date of that post.
     * If we don't do this, the entry will take the modified date from the actual page, which never changes.
     *
     * @param array  $data
     * @param string $type
     * @param object $current_post
     *
     * @return array
     */
    public function filterSitemapEntry($data, $type, $current_post)
    {
        if ($current_post->ID == get_option('page_on_front')) {
            $data['pri'] = 1;
            $data['chf'] = 'daily';

            $post_date_modified = [];
            // Setup query for sticky posts.
            $sticky = get_option('sticky_posts');
            // Get the query for articles marked for Magazine Excerpts marked per post and in the selected categories.
            $queries = rps_suffusion_get_mag_section_queries(array('meta_check_field' => 'suf_magazine_excerpt', 'category_prefix' => 'suf_mag_excerpt_categories', 'to_skip' => $sticky));
            foreach ($queries as $query) {
                $posts = get_posts($query->query);
                foreach ($posts as $p) {
                    $post_date_modified[] = $p->post_modified;
                }
            }
            rsort($post_date_modified);
            $date_modified = new \DateTime(reset($post_date_modified));
            $data['mod'] = $date_modified->format('c');
        }

        return $data;
    }

    /**
     * Filter for the title of pages.
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

            $new_title_array = array();
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
                $meta_description = 'The ' . $entries_amount . ' entries submitted to Raritan Photographic Society for the theme "' . $theme . '" held on ' . $date_text;
            }
            if ($post->ID == $options['monthly_winners_post_id']) {
                $meta_description = 'Out of ' . $entries_amount . ' entries, a jury selected these winners of the competition with the theme "' . $theme . '" held on ' . $date_text;
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
     * @param object $post
     *
     * @return string
     */
    public function filterWpseoPreAnalysisPostsContent($post_content, $post)
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
     * Add extra sitemap link to existing sitemap root.
     *
     * @return string
     */
    public function filterWpseoSitemapIndex()
    {
        $all_competitions = $this->query_competitions->getScoredCompetitions('1970-01-01', '2200-01-01');
        $years = [];
        $old_year = 0;
        $old_mod_date = 0;
        foreach ($all_competitions as $competition) {
            $date = new \DateTime($competition->Competition_Date);
            $year = $date->format('Y');

            $date_modified = new \DateTime($competition->Date_Modified, new \DateTimeZone(get_option('timezone_string')));
            $mod_date = $date_modified->format('U');
            $last_modified_date = $date_modified->format('c');

            if ($year != $old_year) {
                $old_mod_date = 0;
                $old_year = $year;
            }
            if ($mod_date > $old_mod_date) {
                $old_mod_date = $mod_date;
                $last_modified_date = $date_modified->format('c');
            }
            $years[$year] = $last_modified_date;
        }

        $sitemap = '';

        foreach ($years as $year => $lastmod) {
            $sitemap .= '<sitemap>' . "\n";
            $sitemap .= '<loc>' . wpseo_xml_sitemaps_base_url('competition-entries') . '-sitemap' . $year . '.xml</loc>' . "\n";
            $sitemap .= '<lastmod>' . htmlspecialchars($lastmod) . '</lastmod>' . "\n";
            $sitemap .= '</sitemap>' . "\n";
        }

        foreach ($years as $year => $lastmod) {
            $sitemap .= '<sitemap>' . "\n";
            $sitemap .= '<loc>' . wpseo_xml_sitemaps_base_url('competition-winners') . '-sitemap' . $year . '.xml</loc>' . "\n";
            $sitemap .= '<lastmod>' . htmlspecialchars($lastmod) . '</lastmod>' . "\n";
            $sitemap .= '</sitemap>' . "\n";
        }

        return $sitemap;
    }

    /**
     * Handle the replacement variable for WordPress Seo by Yoast.
     *
     * @param $foo
     *
     * @return string
     */
    public function handleWpSeoTitleReplace($foo)
    {
        global $post;

        $replacement = null;
        $title_array = array();

        if (is_string($post->post_title) && $post->post_title !== '') {
            $replacement = stripslashes($post->post_title);
        }
        $title_array[] = $replacement;

        $new = $this->filterWpTitleParts($title_array);

        return $new[0];
    }

    /**
     * Build actual sitemap for Competition Entries or Competition Winners
     *
     * @param string $url
     * @param bool   $include_images
     */
    private function buildWpseoSitemap($url, $include_images = false)
    {
        $n = get_query_var('sitemap_n');
        if (is_scalar($n) && intval($n) > 0) {
            $n = intval($n);
        } else {
            header("HTTP/1.1 404 Not Found");
            exit();
        }

        $date = new \DateTime();
        $date->setDate($n, 1, 1);
        $start_date = $date->format('Y-m-d');
        $date->setDate($n, 12, 31);
        $end_date = $date->format('Y-m-d');
        $scored_competitions = $this->query_competitions->getScoredCompetitions($start_date, $end_date);

        $sitemap_data = array();
        /** @var QueryCompetitions $competition */
        foreach ($scored_competitions as $competition) {
            $competition_date = new \DateTime($competition->Competition_Date);
            $key = $competition_date->format('U');
            $date = new \DateTime($competition->Date_Modified, new \DateTimeZone(get_option('timezone_string')));

            $sitemap_data[$key] = array(
                'loc' => $url . $competition_date->format('Y-m-d') . '/',
                'pri' => 0.8,
                'chf' => 'yearly',
                'mod' => $date->format('c'),
            );

            if ($include_images) {
                $entries = $this->query_miscellaneous->getAllEntries($competition_date->format('Y-m-d'));
                $data_images = [];
                if (is_array($entries)) {
                    /** @var QueryEntries $record */
                    $image_data = [];
                    foreach ($entries as $record) {
                        $user_info = get_userdata($record->Member_ID);

                        $image_data['loc'] = $this->photo_helper->getThumbnailUrl($record->Server_File_Name, '800');
                        $image_data['title'] = $record->Title;
                        $image_data['caption'] = $record->Title . ' Credit: ' . $user_info->user_firstname . ' ' . $user_info->user_lastname;
                        $data_images[] = $image_data;
                    }
                }
                $sitemap_data[$key]['images'] = $data_images;
            }
        }
        $this->outputWpseoSitemap($sitemap_data);
    }

    /**
     * Output the sitemap XML file.
     *
     * @param array $sitemap_data
     */
    private function outputWpseoSitemap($sitemap_data)
    {
        $output = '';
        foreach ($sitemap_data as $data) {
            $output .= "\t<url>\n";
            $output .= "\t\t<loc>" . htmlspecialchars($data['loc']) . "</loc>\n";
            $output .= "\t\t<lastmod>" . $data['mod'] . "</lastmod>\n";
            $output .= "\t\t<changefreq>" . $data['chf'] . "</changefreq>\n";
            $output .= "\t\t<priority>" . $data['pri'] . "</priority>\n";

            if (isset($data['images']) && (is_array($data['images']) && $data['images'] !== array())) {
                foreach ($data['images'] as $img) {
                    if (!isset($img['loc']) || empty($img['loc'])) {
                        continue;
                    }
                    $output .= "\t\t<image:image>\n";
                    $output .= "\t\t\t<image:loc>" . esc_html($img['loc']) . "</image:loc>\n";
                    $output .= "\t\t\t<image:title>" . _wp_specialchars(html_entity_decode($img['title'], ENT_QUOTES, esc_attr(get_bloginfo('charset')))) . "</image:title>\n";
                    $output .= "\t\t\t<image:caption>" . _wp_specialchars(html_entity_decode($img['caption'], ENT_QUOTES, esc_attr(get_bloginfo('charset')))) . "</image:caption>\n";
                    $output .= "\t\t</image:image>\n";
                }
            }
            $output .= "\t</url>\n";
        }

        $sitemap = '<urlset xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
        $sitemap .= 'xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" ';
        $sitemap .= 'xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        $sitemap .= $output;
        $sitemap .= '</urlset>';

        header('HTTP/1.1 200 OK', true, 200);
        // Prevent the search engines from indexing the XML Sitemap.
        header('X-Robots-Tag: noindex,follow', true);
        header('Content-Type: text/xml');
        echo '<?xml version="1.0" encoding="' . esc_attr(get_bloginfo('charset')) . '"?>';
        echo '<?xml-stylesheet type="text/xsl" href="' . preg_replace('/(^http[s]?:)/', '', esc_url(home_url('main-sitemap.xsl'))) . '"?>' . "\n";
        echo $sitemap;
        echo "\n" . '<!-- XML Sitemap generated by AVH Raritan Photographic Society -->';
    }
}
