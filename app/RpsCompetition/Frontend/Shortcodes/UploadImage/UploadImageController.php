<?php
namespace RpsCompetition\Frontend\Shortcodes\UploadImage;

use Illuminate\Config\Repository as Settings;
use RpsCompetition\Frontend\Shortcodes\ShortcodeView;

/**
 * Class UploadImageController
 *
 * @package   RpsCompetition\Frontend\Shortcodes\UploadImage
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2016, AVH Software
 */
class UploadImageController
{
    private $model;
    private $settings;
    private $view;

    /**
     * Constructor
     *
     * @param ShortcodeView    $view
     * @param UploadImageModel $model
     * @param Settings         $settings
     */
    public function __construct(ShortcodeView $view, UploadImageModel $model, Settings $settings)
    {
        $this->view = $view;
        $this->model = $model;
        $this->settings = $settings;
    }

    /**
     * Displays the form to upload a new entry.
     *
     * @see Frontend::actionHandleHttpPostRpsUploadEntry
     *
     * @param array  $attr    The shortcode argument list
     * @param string $content The content of a shortcode when it wraps some content.
     * @param string $tag     The shortcode name
     *
     * @return string
     */
    public function shortcodeUploadImage($attr, $content, $tag)
    {
        if ($this->settings->has('formerror')) {
            $form = $this->model->getSubmittedForm();
        } else {
            $form = $this->model->getNewForm();
        }

        return $this->view->fetch('upload.html.twig', ['form' => $form->createView()]);
    }
}
