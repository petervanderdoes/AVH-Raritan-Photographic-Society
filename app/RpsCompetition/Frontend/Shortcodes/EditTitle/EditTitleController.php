<?php
namespace RpsCompetition\Frontend\Shortcodes\EditTitle;

use RpsCompetition\Frontend\Shortcodes\ShortcodeView;
use RpsCompetition\Settings;

/**
 * Class EditTitleController
 *
 * @author    Peter van der Does
 * @copyright Copyright (c) 2015, AVH Software
 * @package   RpsCompetition\Frontend\Shortcodes\EditTitle
 */
class EditTitleController
{
    /** @var EditTitleModel */
    private $model;
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
     * @param array  $attr    The shortcode argument list
     * @param string $content The content of a shortcode when it wraps some content.
     * @param string $tag     The shortcode name
     *
     * @return string
     *
     * @see Frontend::actionHandleHttpPostRpsEditTitle
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
