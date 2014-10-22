<?php
namespace RpsCompetition\Frontend;

use RpsCompetition\Common\Helper as CommonHelper;
use RpsCompetition\Db\QueryCompetitions;
use RpsCompetition\Db\QueryMiscellaneous;
use RpsCompetition\Db\RpsDb;
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
    private $rpsdb;
    private $settings;

    /**
     * Constructor
     *
     * @param Settings $settings
     * @param RpsDb    $rpsdb
     */
    public function __construct(Settings $settings, RpsDb $rpsdb)
    {
        $this->settings = $settings;
        $this->rpsdb = $rpsdb;
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
        $this->buildWpseoSitemap($url);
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
     * Filter for the title of pages.
     *
     * @param array $title_array
     *
     * @return array
     */
    public function filterWpTitleParts($title_array)
    {
        global $post;

        $pages_array = CommonHelper::getDynamicPages();
        if (isset($pages_array[$post->ID])) {
            $query_competitions = new QueryCompetitions($this->settings, $this->rpsdb);
            $selected_date = get_query_var('selected_date');
            $date = new \DateTime($selected_date);
            $date_text = $date->format('F j, Y');
            $competitions = $query_competitions->getCompetitionByDates($selected_date);
            $competition = current($competitions);

            $new_title_array = array();
            $new_title_array[] = $post->post_title . ' for the theme "' .$competition->Theme . '" on ' . $date_text;
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

        $options = get_option('avh-rps');
        $pages_array = CommonHelper::getDynamicPages();
        if (isset($pages_array[$post->ID])) {

            $query_competitions = new QueryCompetitions($this->settings, $this->rpsdb);
            $query_miscellaneous = new QueryMiscellaneous($this->rpsdb);

            $selected_date = get_query_var('selected_date');
            $competitions = $query_competitions->getCompetitionByDates($selected_date);
            $competition = current($competitions);
            $theme = ucfirst($competition->Theme);
            $date = new \DateTime($selected_date);
            $date_text = $date->format('F j, Y');
            $entries_amount = $query_miscellaneous->countAllEntries($selected_date);

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
        $query_competitions = new QueryCompetitions($this->settings, $this->rpsdb);

        $last_scored = $query_competitions->query(array('where' => 'Scored="Y"', 'orderby' => 'Date_Modified', 'order' => 'DESC', 'number' => 1));
        $date = new \DateTime($last_scored->Date_Modified);

        $sitemap = '';
        $sitemap .= '<sitemap>' . "\n";
        $sitemap .= '<loc>' . wpseo_xml_sitemaps_base_url('competition-entries') . '-sitemap.xml</loc>' . "\n";
        $sitemap .= '<lastmod>' . htmlspecialchars($date->format('c')) . '</lastmod>' . "\n";
        $sitemap .= '</sitemap>' . "\n";
        $sitemap .= '<sitemap>' . "\n";
        $sitemap .= '<loc>' . wpseo_xml_sitemaps_base_url('competition-winners') . '-sitemap.xml</loc>' . "\n";
        $sitemap .= '<lastmod>' . htmlspecialchars($date->format('c')) . '</lastmod>' . "\n";
        $sitemap .= '</sitemap>' . "\n";

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
     */
    private function buildWpseoSitemap($url)
    {
        $query_competitions = new QueryCompetitions($this->settings, $this->rpsdb);
        $scored_competitions = $query_competitions->getScoredCompetitions('1970-01-01', '2200-01-01');

        $old_mod_date = 0;
        $old_key = 0;
        $sitemap_data = array();
        $location = '';
        $last_modified_date = '';
        /** @var QueryCompetitions $competition */
        foreach ($scored_competitions as $competition) {
            $competition_date = new \DateTime($competition->Competition_Date);
            $key = $competition_date->format('U');
            $date = new \DateTime($competition->Date_Modified, new \DateTimeZone(get_option('timezone_string')));
            $mod_date = $date->format('U');

            if ($key != $old_key) {
                $old_mod_date = 0;
                $location = $url . $competition_date->format('Y-m-d') . '/';
            }
            if ($mod_date > $old_mod_date) {
                $old_mod_date = $mod_date;
                $last_modified_date = $date->format('c');
            }

            $sitemap_data[$key] = array(
                'loc' => $location,
                'pri' => 0.8,
                'chf' => 'monthly',
                'mod' => $last_modified_date,
            );
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
            $output .= "\t\t<loc>" . $data['loc'] . "</loc>\n";
            $output .= "\t\t<lastmod>" . $data['mod'] . "</lastmod>\n";
            $output .= "\t\t<changefreq>" . $data['chf'] . "</changefreq>\n";
            $output .= "\t\t<priority>" . $data['pri'] . "</priority>\n";
            $output .= "\t</url>\n";
        }

        $sitemap = '<urlset xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
        $sitemap .= 'xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd" ';
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