<?php
namespace RpsCompetition\Frontend\Shortcodes\EditTitle;

use Illuminate\Config\Repository as Settings;
use RpsCompetition\Frontend\Shortcodes\ShortcodeView;

/**
 * Class EditTitleController
 *
 * @package   RpsCompetition\Frontend\Shortcodes\EditTitle
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2016, AVH Software
 */
class EditTitleController
{
    /** @var EditTitleModel */
    private $model;
    /** @var Settings */
    private $settings;
    /** @var ShortcodeView */
    private $view;

    /**
     * Constructor
     *
     * @param ShortcodeView  $view
     * @param EditTitleModel $model
     * @param Settings       $settings
     */
    public function __construct(ShortcodeView $view, EditTitleModel $model, Settings $settings)
    {
        $this->view = $view;
        $this->model = $model;
        $this->settings = $settings;
    }

    /**
     * Display the form to edit the title of the selected entry
     *
     * @see Frontend::actionHandleHttpPostRpsEditTitle
     *
     * @param array  $attr    The shortcode argument list
     * @param string $content The content of a shortcode when it wraps some content.
     * @param string $tag     The shortcode name
     *
     * @return string
     */
    public function shortcodeEditTitle($attr, $content, $tag)
    {

        if ($this->settings->has('formerror')) {
            $form = $this->model->getSubmittedForm();
            $server_file_name = $form->get('server_file_name')
                                     ->getData()
            ;
        } else {
            $entry_id = $this->model->getEntryId();
            $entry = $this->model->getEntry($entry_id);
            $server_file_name = $entry->Server_File_Name;
            $form = $this->model->getNewForm($entry_id, $entry->Title, $entry->Server_File_Name);
        }
        $data = $this->model->getData($server_file_name);

        return $this->view->fetch('edit_title.html.twig', ['data' => $data, 'form' => $form->createView()]);
    }
}
