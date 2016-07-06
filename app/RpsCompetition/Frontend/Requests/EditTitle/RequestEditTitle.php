<?php

namespace RpsCompetition\Frontend\Requests\EditTitle;

use Illuminate\Config\Repository as Settings;
use Illuminate\Http\Request as IlluminateRequest;
use RpsCompetition\Entity\Form\EditTitle as EntityFormEditTitle;
use RpsCompetition\Form\Type\EditTitleType;
use RpsCompetition\Helpers\CommonHelper;
use Symfony\Component\Form\FormFactory;

/**
 * Class RequestEditTitle
 *
 * @package   RpsCompetition\Frontend\Requests\EditTitle
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2016, AVH Software
 */
class RequestEditTitle
{
    private $edit_title_type;
    private $entity;
    private $form_factory;
    private $model;
    private $request;
    private $settings;

    /**
     * Constructor
     *
     * @param EntityFormEditTitle   $entity
     * @param EditTitleType         $edit_title_type
     * @param RequestEditTitleModel $model
     * @param IlluminateRequest     $request
     * @param FormFactory           $form_factory
     * @param Settings              $settings
     */
    public function __construct(
        EntityFormEditTitle $entity,
        EditTitleType $edit_title_type,
        RequestEditTitleModel $model,
        IlluminateRequest $request,
        FormFactory $form_factory,
        Settings $settings
    ) {

        $this->entity          = $entity;
        $this->edit_title_type = $edit_title_type;
        $this->request         = $request;
        $this->form_factory    = $form_factory;
        $this->settings        = $settings;
        $this->model           = $model;
    }

    /**
     * Handle POST request for the editing the title of a photo.
     * This method handles the POST request generated on the page Edit Title
     * The action is called from the theme!
     *
     * @internal Hook: suffusion_before_post
     * @see      Shortcodes::shortcodeEditTitle
     */
    public function handleRequestEditTitle()
    {

        $form = $this->form_factory->create($this->edit_title_type,
                                            $this->entity,
                                            ['attr' => ['id' => 'edittitle']]);
        $form->handleRequest($this->request);

        $redirect_to = $this->entity->getWpGetReferer();

        // Just return if user clicked Cancel
        CommonHelper::isRequestCanceled($form, 'cancel', $redirect_to);

        if (!$form->isValid()) {
            $errors = $form->getErrors();
            $this->settings->set('formerror', $errors);

            return;
        }

        $server_file_name = $this->entity->getServerFileName();
        $new_title        = $this->entity->getNewTitle();

        if ($this->entity->getNewTitle() !== $this->entity->getTitle()) {
            $this->model->updateTitle($server_file_name, $new_title);
        }
        $redirect_to = $this->entity->getWpGetReferer();
        wp_redirect($redirect_to);
        exit();
    }
}
