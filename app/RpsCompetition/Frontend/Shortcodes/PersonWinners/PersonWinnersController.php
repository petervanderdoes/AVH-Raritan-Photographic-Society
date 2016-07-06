<?php
namespace RpsCompetition\Frontend\Shortcodes\PersonWinners;

use RpsCompetition\Frontend\Shortcodes\ShortcodeView;

/**
 * Class PersonWinnersController
 *
 * @package   RpsCompetition\Frontend\Shortcodes\PersonWinners
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2016, AVH Software
 */
class PersonWinnersController
{
    private $model;
    private $view;

    /**
     * Constructor
     *
     * @param ShortcodeView      $view
     * @param PersonWinnersModel $model
     */
    public function __construct(ShortcodeView $view, PersonWinnersModel $model)
    {
        $this->view  = $view;
        $this->model = $model;
    }

    /**
     * Display the eights and higher for a given member ID.
     *
     * @param array $attr     The shortcode argument list. Allowed arguments:
     *                        - id => The member ID
     *
     * @return string
     * @internal Shortcode: rps_person_winners
     */
    public function shortcodePersonWinners($attr)
    {
        $attr = shortcode_atts(['id' => 0, 'images' => 6], $attr);

        $data = $this->model->getPersonWinners($attr['id'], $attr['images']);

        return $this->view->fetch('person-winners.html.twig', $data);
    }
}
