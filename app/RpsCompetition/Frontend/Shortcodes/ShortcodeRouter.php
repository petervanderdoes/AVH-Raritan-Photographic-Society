<?php

namespace RpsCompetition\Frontend\Shortcodes;

use Avh\Utility\ShortcodesAbstract;

if (!class_exists('AVH_RPS_Client')) {
    header('Status: 403 Forbidden');
    header('HTTP/1.1 403 Forbidden');
    exit();
}

/**
 * Class ShortcodeRouter
 *
 * @package RpsCompetition\Frontend\Shortcodes
 */
class ShortcodeRouter extends ShortcodesAbstract
{
    public function initializeShortcodes()
    {
        $this->register('rps_category_winners', 'shortcodeCategoryWinners');
        $this->register('rps_monthly_winners', 'shortcodeMonthlyWinners', 'MonthlyWinners');
        $this->register('rps_scores_current_user', 'shortcodeScoresCurrentUser');
        $this->register('rps_banquet_current_user', 'shortcodeBanquetCurrentUser');
        $this->register('rps_all_scores', 'shortcodeAllScores');
        $this->register('rps_my_entries', 'shortcodeMyEntries', 'MyEntries');
        $this->register('rps_edit_title', 'shortcodeEditTitle', 'EditTitleController');
        $this->register('rps_upload_image', 'shortcodeUploadImage', 'UploadImageController');
        $this->register('rps_email', 'shortcodeEmail');
        $this->register('rps_person_winners', 'shortcodePersonWinners', 'PersonWinners');
        $this->register('rps_monthly_entries', 'shortcodeMonthlyEntries', 'MonthlyEntries');
    }
}
