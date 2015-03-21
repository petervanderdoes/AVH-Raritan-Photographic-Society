<?php
namespace RpsCompetition\Frontend\Shortcodes;

use Avh\Utility\ShortcodesAbstract;

/**
 * Class ShortcodeRouter
 *
 * @package   RpsCompetition\Frontend\Shortcodes
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2015, AVH Software
 */
class ShortcodeRouter extends ShortcodesAbstract
{
    public function initializeShortcodes()
    {
        $this->register('rps_category_winners', 'shortcodeCategoryWinners', 'CategoryWinnersController');
        $this->register('rps_monthly_winners', 'shortcodeMonthlyWinners', 'MonthlyWinnersController');
        $this->register('rps_scores_current_user', 'shortcodeScoresCurrentUser', 'ScoresCurrentUserController');
        $this->register('rps_banquet_current_user', 'shortcodeBanquetEntries', 'BanquetEntriesController');
        $this->register('rps_all_scores', 'shortcodeAllScores', 'AllScoresController');
        $this->register('rps_my_entries', 'shortcodeMyEntries', 'MyEntriesController');
        $this->register('rps_edit_title', 'shortcodeEditTitle', 'EditTitleController');
        $this->register('rps_upload_image', 'shortcodeUploadImage', 'UploadImageController');
        $this->register('rps_email', 'shortcodeEmail');
        $this->register('rps_person_winners', 'shortcodePersonWinners', 'PersonWinnersController');
        $this->register('rps_monthly_entries', 'shortcodeMonthlyEntries', 'MonthlyEntriesController');
    }
}
