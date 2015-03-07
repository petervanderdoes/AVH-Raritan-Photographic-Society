<?php
namespace RpsCompetition\Frontend\Shortcodes\UploadImage;

use RpsCompetition\Frontend\Shortcodes\ShortcodeView;
use RpsCompetition\Libs\Controller;
use RpsCompetition\Settings;

/**
 * Class UploadImageController
 *
 * @author    Peter van der Does
 * @copyright Copyright (c) 2015, AVH Software
 * @package   RpsCompetition\Frontend\Shortcodes\UploadImage
 */
class UploadImageController extends Controller
{
    /** @var UploadImageModel */
    private $model;
    /** @var ShortcodeView */
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
     * @param array  $attr    The shortcode argument list
     * @param string $content The content of a shortcode when it wraps some content.
     * @param string $tag     The shortcode name
     *
     * @return string
     *
     * @see Frontend::actionHandleHttpPostRpsUploadEntry
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
