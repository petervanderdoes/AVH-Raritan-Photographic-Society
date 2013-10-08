<?php
namespace RpsCompetition\Frontend;
use RpsCompetition\Settings;
use RpsCompetition\Common\Core;
use RpsCompetition\Db\RpsDb;

final class Shortcodes
{
	/**
	 *
	 * @var Core
	 */
	private $core;

	/**
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 *
	 * @var RpsDb
	 */
	private $rpsdb;
	private $shortcode_map;

	public function __construct (Settings $settings, RpsDb $_rpsdb, Core $core)
	{
		$this->shortcode_map = array('rps_category_winners' => 'CategoryWinners');
	}

	public function bootstrap ($atts, $content, $tag)
	{
		$method = $this->shortcode_map[$tag];
		ob_start();
		$html = '';
		if ( is_callable($this->method) ) {
			$html = $this->$method($atts, $content, $tag);
			$html .= ob_get_clean();
		}
		return $html;
	}

	private function CategoryWinners ($atts, $content, $tag)
	{
		global $wpdb;

		$class = 'Beginner';
		$award = '1';
		$date = '';
		extract($atts, EXTR_OVERWRITE);

		$competiton_date = date('Y-m-d H:i:s', strtotime($date));
		$award_map = array('1' => '1st','2' => '2nd','3' => '3rd','H' => 'HM');

		$entries = $this->rpsdb->getWinner($competiton_date, $award_map[$award], $class);

		echo '<section class="rps-showcase-category-winner">';
		echo '<div class="rps-sc-tile suf-tile-1c entry-content bottom">';

		echo '<div class="suf-gradient suf-tile-topmost">';
		echo '<h3>' . $class . '</h3>';
		echo '</div>';

		echo '<div class="rps-sc-text entry-content">';
		echo '<ul>';
		foreach ( $entries as $entry ) {
			$dateParts = explode(" ", $entry['Competition_Date']);
			$comp_date = $dateParts[0];
			$medium = $entry['Medium'];
			$classification = $entry['Classification'];
			$comp = "$classification<br>$medium";
			$title = $entry['Title'];
			$last_name = $entry['LastName'];
			$first_name = $entry['FirstName'];
			$award = $entry['Award'];

			echo '<li class="suf-widget">';
			echo '	<div class="image">';
			echo '	<a href="' . $this->core->rpsGetThumbnailUrl($entry, 800) . '" rel="rps-showcase' . tag_escape($classification) . '" title="' . $title . ' by ' . $first_name . ' ' . $last_name . '">';
			echo '	<img class="thumb_img" src="' . $this->core->rpsGetThumbnailUrl($entry, 250) . '" /></a>';
			echo '	</div>';
			echo "<div class='winner-heading'>$title<br />$first_name $last_name</div>";
			echo '</li>';
		}
		echo '</ul>';
		echo '</div>';
		echo '</section>';
	}
}