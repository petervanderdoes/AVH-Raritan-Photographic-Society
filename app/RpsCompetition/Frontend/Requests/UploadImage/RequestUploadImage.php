<?php

namespace RpsCompetition\Frontend\Requests\UploadImage;

use Illuminate\Config\Repository as Settings;
use Illuminate\Http\Request as IlluminateRequest;
use RpsCompetition\Entity\Form\UploadImage as EntityFormUploadImage;
use RpsCompetition\Form\Type\UploadImageType;
use RpsCompetition\Helpers\CommonHelper;
use Symfony\Component\Form\FormFactory;

/**
 * Class RequestUploadImage
 *
 * @package   RpsCompetition\Frontend\Requests\UploadImage
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2016, AVH Software
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
     * @param EntityFormUploadImage   $entity
     * @param UploadImageType         $upload_image_type
     * @param RequestUploadImageModel $model
     * @param IlluminateRequest       $request
     * @param FormFactory             $form_factory
     * @param Settings                $settings
     */
    public function __construct(
        EntityFormUploadImage $entity,
        UploadImageType $upload_image_type,
        RequestUploadImageModel $model,
        IlluminateRequest $request,
        FormFactory $form_factory,
        Settings $settings
    ) {
        $this->entity            = $entity;
        $this->upload_image_type = $upload_image_type;
        $this->model             = $model;
        $this->request           = $request;
        $this->form_factory      = $form_factory;
        $this->settings          = $settings;
    }

    /**
     * Handle POST request for uploading a photo.
     * This method handles the POST request generated when uploading a photo
     * The action is called from the theme!
     *
     * @internal Hook: suffusion_before_post
     * @see      Shortcodes::shortcodeUploadImage
     */
    public function handleUploadImage()
    {

        /** @var \Symfony\Component\Form\Form $form */
        $form = $this->form_factory->create($this->upload_image_type,
                                            $this->entity,
                                            ['attr' => ['id' => 'uploadentry']]);
        $form->handleRequest($this->request);

        $redirect_to = $this->entity->getWpGetReferer();
        // Just return if user clicked Cancel
        CommonHelper::isRequestCanceled($form, 'cancel', $redirect_to);

        if (!$form->isValid()) {
            $errors = $form->getErrors();
            $this->settings->set('formerror', $errors);

            return;
        }

        $success = $this->model->handleUploadImage($form);
        if ($success === true) {
            wp_redirect($redirect_to);
            exit();
        }
    }
}
