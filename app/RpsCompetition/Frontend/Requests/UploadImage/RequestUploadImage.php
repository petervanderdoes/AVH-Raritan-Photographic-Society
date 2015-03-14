<?php

namespace RpsCompetition\Frontend\Requests\UploadImage;

use Illuminate\Http\Request as IlluminateRequest;
use RpsCompetition\Common\Helper as CommonHelper;
use RpsCompetition\Entity\Forms\UploadImage;
use RpsCompetition\Form\Type\UploadImageType;
use RpsCompetition\Settings;
use Symfony\Component\Form\FormFactory;

/**
 * Class RequestUploadImage
 *
 * @author    Peter van der Does
 * @copyright Copyright (c) 2015, AVH Software
 * @package   RpsCompetition\Frontend\Requests\UploadImage
 */
class RequestUploadImage
{
    private $entity;
    private $form_factory;
    private $model;
    private $request;
    private $settings;
    private $upload_image_type;

    /**
     * @param UploadImage             $entity
     * @param UploadImageType         $upload_image_type
     * @param RequestUploadImageModel $model
     * @param IlluminateRequest       $request
     * @param FormFactory             $form_factory
     * @param Settings                $settings
     */
    public function __construct(
        UploadImage $entity,
        UploadImageType $upload_image_type,
        RequestUploadImageModel $model,
        IlluminateRequest $request,
        FormFactory $form_factory,
        Settings $settings
    ) {
        $this->entity = $entity;
        $this->upload_image_type = $upload_image_type;
        $this->model = $model;
        $this->request = $request;
        $this->form_factory = $form_factory;
        $this->settings = $settings;
    }

    /**
     * Handle POST request for uploading a photo.
     * This method handles the POST request generated when uploading a photo
     * The action is called from the theme!
     *
     * @see      Shortcodes::shortcodeUploadImage
     * @internal Hook: suffusion_before_post
     */
    public function handleUploadImage()
    {

        /** @var \Symfony\Component\Form\Form $form */
        $form = $this->form_factory->create(
            $this->upload_image_type,
            $this->entity,
            ['attr' => ['id' => 'uploadentry']]
        )
        ;
        $form->handleRequest($this->request);

        $redirect_to = $this->entity->getWpGetReferer();
        // Just return if user clicked Cancel
        CommonHelper::isRequestCanceled($form, 'cancel', $redirect_to);

        if (!$form->isValid()) {
            $errors = $form->getErrors();
            $this->settings->set('formerror', $errors);

            return;
        }

        $success = $this->model->all();
        if ($success === true) {
            wp_redirect($redirect_to);
            exit();
        }
    }
}
