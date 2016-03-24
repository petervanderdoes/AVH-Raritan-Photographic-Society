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
use RpsCompetition\Entity\Db\Entry;
use RpsCompetition\Helpers\PhotoHelper;

/**
 * Class Sitemap
 *
 * @package   RpsCompetition\Frontend\Plugins\Wpseo
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2016-2016, Peter van der Does
 */
class Sitemap
{
    private $photo_helper;
    private $query_competitions;
    private $query_miscellaneous;

    /**
     * Constructor
     *
     * @param QueryCompetitions  $query_competitions
     * @param QueryMiscellaneous $query_miscellaneous
     * @param PhotoHelper        $photo_helper
     *
     */
    public function __construct(QueryCompetitions $query_competitions,
                                QueryMiscellaneous $query_miscellaneous,
                                PhotoHelper $photo_helper)
    {
        $this->query_competitions = $query_competitions;
        $this->query_miscellaneous = $query_miscellaneous;
        $this->photo_helper = $photo_helper;
    }

    /**
     * Build sitemap for Competition Entries
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
     * @internal \RpsCompetition\Frontend\Frontend::setupWpSeoActionsFilters
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
     * Set the change frequency in the sitemap.
     *
     * @internal \RpsCompetition\Frontend\Frontend::setupWpSeoActionsFilters
     *
     * @return string
     */
    public function filterChangeFreq()
    {
        return 'yearly';
    }

    /**
     * Change the Sitemap data for the front page.
     * As WordPress SEO by Yoast does not change the data for the front page, we do this ourselves.
     * We set the priority to 100%, the Change Frequency to Daily, and set the last modified date
     * by getting the articles on the front page and using the most recent modified date of that post.
     * If we don't do this, the entry will take the modified date from the actual page, which never changes.
     *
     * @param array    $data
     * @param string   $type
     * @param \WP_Post $current_post
     *
     * @internal \RpsCompetition\Frontend\Frontend::setupWpSeoActionsFilters
     *
     * @return array
     */
    public function filterSitemapEntry($data, $type, $current_post)
    {
        if ($type === 'post' && $current_post->ID == get_option('page_on_front')) {
            $data['pri'] = 1;
            $data['chf'] = 'weekly';

            $post_date_modified = [];
            // Setup query for sticky posts.
            $sticky = get_option('sticky_posts');
            // Get the query for articles marked for Magazine Excerpts marked per post and in the selected categories.
            $queries = rps_suffusion_get_mag_section_queries([
                                                                 'meta_check_field' => 'suf_magazine_excerpt',
                                                                 'category_prefix'  => 'suf_mag_excerpt_categories',
                                                                 'to_skip'          => $sticky
                                                             ]);
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
     * Add extra sitemap link to existing sitemap root.
     *
     * @internal \RpsCompetition\Frontend\Frontend::setupWpSeoActionsFilters
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

            $date_modified = new \DateTime($competition->Date_Modified,
                                           new \DateTimeZone(get_option('timezone_string')));
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
            $sitemap .= '<loc>' .
                        wpseo_xml_sitemaps_base_url('competition-entries') .
                        '-sitemap' .
                        $year .
                        '.xml</loc>' .
                        "\n";
            $sitemap .= '<lastmod>' . htmlspecialchars($lastmod) . '</lastmod>' . "\n";
            $sitemap .= '</sitemap>' . "\n";
        }

        foreach ($years as $year => $lastmod) {
            $sitemap .= '<sitemap>' . "\n";
            $sitemap .= '<loc>' .
                        wpseo_xml_sitemaps_base_url('competition-winners') .
                        '-sitemap' .
                        $year .
                        '.xml</loc>' .
                        "\n";
            $sitemap .= '<lastmod>' . htmlspecialchars($lastmod) . '</lastmod>' . "\n";
            $sitemap .= '</sitemap>' . "\n";
        }

        return $sitemap;
    }

    /**
     * Build actual sitemap for Competition Entries or Competition Winners
     *
     * @param string $url
     * @param bool   $include_images
     */
    private function buildWpseoSitemap($url, $include_images = false)
    {
        $sitemap_n = get_query_var('sitemap_n');
        if (is_scalar($sitemap_n) && intval($sitemap_n) > 0) {
            $sitemap_n = intval($sitemap_n);
        } else {
            header('HTTP/1.1 404 Not Found');
            exit();
        }

        $date = new \DateTime();
        $date->setDate($sitemap_n, 1, 1);
        $start_date = $date->format('Y-m-d');
        $date->setDate($sitemap_n, 12, 31);
        $end_date = $date->format('Y-m-d');
        $scored_competitions = $this->query_competitions->getScoredCompetitions($start_date, $end_date);

        $sitemap_data = [];
        /** @var QueryCompetitions $competition */
        foreach ($scored_competitions as $competition) {
            $competition_date = new \DateTime($competition->Competition_Date);
            $key = $competition_date->format('U');
            $date = new \DateTime($competition->Date_Modified, new \DateTimeZone(get_option('timezone_string')));

            $sitemap_data[$key] = [
                'loc' => $url . $competition_date->format('Y-m-d') . '/',
                'pri' => 0.8,
                'chf' => 'yearly',
                'mod' => $date->format('c'),
            ];

            if ($include_images) {
                $entries = $this->query_miscellaneous->getAllEntries($competition_date->format('Y-m-d'));
                $data_images = [];
                if (is_array($entries)) {
                    /** @var Entry $record */
                    $image_data = [];
                    foreach ($entries as $record) {
                        $user_info = get_userdata($record->Member_ID);

                        $image_data['loc'] = $this->photo_helper->getThumbnailUrl($record->Server_File_Name,
                                                                                  '800');
                        $image_data['title'] = $record->Title;
                        $image_data['caption'] = $record->Title .
                                                 ' Credit: ' .
                                                 $user_info->user_firstname .
                                                 ' ' .
                                                 $user_info->user_lastname;
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

            if (isset($data['images']) && (is_array($data['images']) && $data['images'] !== [])) {
                foreach ($data['images'] as $img) {
                    if (!isset($img['loc']) || empty($img['loc'])) {
                        continue;
                    }
                    $output .= "\t\t<image:image>\n";
                    $output .= "\t\t\t<image:loc>" . esc_html($img['loc']) . "</image:loc>\n";
                    $output .= "\t\t\t<image:title>" .
                               _wp_specialchars(html_entity_decode($img['title'],
                                                                   ENT_QUOTES,
                                                                   esc_attr(get_bloginfo('charset')))) .
                               "</image:title>\n";
                    $output .= "\t\t\t<image:caption>" .
                               _wp_specialchars(html_entity_decode($img['caption'],
                                                                   ENT_QUOTES,
                                                                   esc_attr(get_bloginfo('charset')))) .
                               "</image:caption>\n";
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
        echo '<?xml-stylesheet type="text/xsl" href="' .
             preg_replace('/(^http[s]?:)/',
                          '',
                          esc_url(home_url('main-sitemap.xsl'))) .
             '"?>' .
             "\n";
        echo $sitemap;
        echo "\n" . '<!-- XML Sitemap generated by AVH Raritan Photographic Society -->';
    }
}
