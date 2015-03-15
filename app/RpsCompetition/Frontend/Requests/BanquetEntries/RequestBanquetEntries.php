<?php

namespace RpsCompetition\Frontend\Requests\BanquetEntries;

use Illuminate\Http\Request as IlluminateRequest;
use RpsCompetition\Common\Helper as CommonHelper;
use RpsCompetition\Entity\Forms\BanquetCurrentUser as BanquetCurrentUserEntity;
use RpsCompetition\Form\Type\BanquetCurrentUserType;
use Symfony\Component\Form\FormFactory;

/**
 * Class RequestBanquetEntries
 *
 * @author    Peter van der Does
 * @copyright Copyright (c) 2015, AVH Software
 * @package   RpsCompetition\Frontend\Requests\BanquetEntries
 */
class RequestBanquetEntries
{
    /**
     * @var BanquetCurrentUserType
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
     * @param BanquetCurrentUserEntity   $entity
     * @param BanquetCurrentUserType     $banquet_current_user_type
     * @param RequestBanquetEntriesModel $model
     * @param IlluminateRequest          $request
     * @param FormFactory                $form_factory
     */
    public function __construct(
        BanquetCurrentUserEntity $entity,
        BanquetCurrentUserType $banquet_current_user_type,
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
        /** @var \Symfony\Component\Form\Form $form */
        $form = $this->form_factory->create(
            $this->banquet_current_user_type,
            $this->entity,
            ['attr' => ['id' => 'banquetentries']]
        )
        ;
        $form->handleRequest($this->request);

        $redirect_to = $this->entity->getWpGetReferer();
        CommonHelper::isRequestCanceled($form, 'cancel', $redirect_to);

        if ($form->get('update')
                 ->isClicked()
        ) {
            $this->handleUpdate();
        }
    }

    /**
     * Handles the required functions for when a user submits their Banquet Entries
     *
     */
    private function handleUpdate()
    {
        $this->model->deleteAllEntries();
        $this->model->addSelectedEntries();
    }
}
