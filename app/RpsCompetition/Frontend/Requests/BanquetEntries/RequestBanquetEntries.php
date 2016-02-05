<?php

namespace RpsCompetition\Frontend\Requests\BanquetEntries;

use Illuminate\Http\Request as IlluminateRequest;
use RpsCompetition\Entity\Form\BanquetEntries as EntityFormBanquetEntries;
use RpsCompetition\Form\Type\BanquetEntriesType;
use RpsCompetition\Helpers\CommonHelper;
use Symfony\Component\Form\FormFactory;

/**
 * Class RequestBanquetEntries
 *
 * @package   RpsCompetition\Frontend\Requests\BanquetEntries
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2016, AVH Software
 */
class RequestBanquetEntries
{
    /**
     * @var BanquetEntriesType
     */
    private $banquet_current_user_type;
    private $entity;
    /**
     * @var FormFactory
     */
    private $form_factory;
    private $model;
    /**
     * @var IlluminateRequest
     */
    private $request;

    /**
     * @param EntityFormBanquetEntries   $entity
     * @param BanquetEntriesType         $banquet_current_user_type
     * @param RequestBanquetEntriesModel $model
     * @param IlluminateRequest          $request
     * @param FormFactory                $form_factory
     */
    public function __construct(
        EntityFormBanquetEntries $entity,
        BanquetEntriesType $banquet_current_user_type,
        RequestBanquetEntriesModel $model,
        IlluminateRequest $request,
        FormFactory $form_factory
    ) {

        $this->entity = $entity;
        $this->model = $model;
        $this->banquet_current_user_type = $banquet_current_user_type;
        $this->request = $request;
        $this->form_factory = $form_factory;
    }

    /**
     * Handle POST request for the Banquet Entries.
     * This method handles the POST request generated on the page for Banquet Entries
     * The action is called from the theme!
     *
     * @internal Hook: wp
     */
    public function handleBanquetEntries()
    {
        /** @var \Symfony\Component\Form\Form|\Symfony\Component\Form\FormInterface $form */
        $form = $this->form_factory->create(
            $this->banquet_current_user_type,
            $this->entity,
            ['attr' => ['id' => 'banquetentries']]
        );
        $form->handleRequest($this->request);

        $this->model->removeUpdateSession();
        $redirect_to = $this->entity->getWpGetReferer();
        CommonHelper::isRequestCanceled($form, 'cancel', $redirect_to);

        if ($form->has('update')) {
            if ($form->get('update')
                     ->isClicked()
            ) {
                $this->handleUpdate();
            }
        }

        wp_redirect($this->request->fullUrl());
        exit();
    }

    /**
     * Handles the required functions for when a user submits their Banquet Entries
     */
    private function handleUpdate()
    {
        $this->model->deleteAllEntries();
        $this->model->addSelectedEntries();
        $this->model->setUpdateSession();
    }
}
