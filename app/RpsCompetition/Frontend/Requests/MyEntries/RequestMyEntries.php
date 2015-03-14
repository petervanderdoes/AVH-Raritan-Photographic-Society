<?php

namespace RpsCompetition\Frontend\Requests\MyEntries;

use Avh\Network\Session;
use Illuminate\Http\Request as IlluminateRequest;
use RpsCompetition\Common\Helper as CommonHelper;
use RpsCompetition\Db\QueryCompetitions;
use RpsCompetition\Entity\Forms\MyEntries as EntityFormMyEntries;
use RpsCompetition\Form\Type\MyEntriesType;
use Symfony\Component\Form\FormFactory;

/**
 * Class RequestMyEntries
 *
 * @author    Peter van der Does
 * @copyright Copyright (c) 2015, AVH Software
 * @package   RpsCompetition\Frontend\Requests\MyEntries
 */
class RequestMyEntries
{
    private $entity;
    private $formFactory;
    private $model;
    private $myEntriesType;
    private $query_competitions;
    private $request;
    private $session;

    /**
     * Constructor
     *
     * @param EntityFormMyEntries   $entity
     * @param MyEntriesType         $myEntriesType
     * @param RequestMyEntriesModel $model
     * @param QueryCompetitions     $query_competitions
     * @param IlluminateRequest     $request
     * @param FormFactory           $formFactory
     * @param Session               $session
     */
    public function __construct(
        EntityFormMyEntries $entity,
        MyEntriesType $myEntriesType,
    RequestMyEntriesModel $model,
        QueryCompetitions $query_competitions,
        IlluminateRequest $request,
        FormFactory $formFactory,
        Session $session
    ) {

        $this->query_competitions = $query_competitions;
        $this->entity = $entity;
        $this->myEntriesType = $myEntriesType;
        $this->request = $request;
        $this->formFactory = $formFactory;
        $this->session = $session;
        $this->model = $model;
    }

    /**
     * Handle POST request for editing the entries of a user.
     * This method handles the POST request generated on the page for editing entries
     * The action is called from the theme!
     *
     * @see      Shortcodes::shortcodeMyEntries
     * @internal Hook: suffusion_before_post
     */
    public function handleHttpPostRpsMyEntries()
    {
        global $post;

        if (is_object($post) && ($post->ID == 56 || $post->ID == 58)) {
            $this->entity->setSelectComp($this->request->input('form.select_comp'));
            $this->entity->setSelectedMedium($this->request->input('form.selected_medium'));
            $form = $this->formFactory->create($this->myEntriesType, $this->entity, ['attr' => ['id' => 'myentries']]);
            $form->handleRequest($this->request);

            $page = explode('-', $post->post_name);
            $medium_subset = $page[1];

            if ($form->has('submit_control')) {
                $competition_date = $this->entity->getSelectComp();
                $classification = $this->entity->getClassification();
                $medium = $this->entity->getSelectedMedium();
                $entry_array = $this->request->input('form.entryid', null);

                switch ($this->entity->getSubmitControl()) {
                    case 'add':
                        if (!$this->query_competitions->checkCompetitionClosed(
                            $competition_date,
                            $classification,
                            $medium
                        )
                        ) {
                            $query = ['m' => $medium_subset];
                            $query = build_query($query);
                            $loc = '/member/upload-image/?' . $query;
                            wp_redirect($loc);
                            exit();
                        }
                        break;

                    case 'edit':
                        if (!$this->query_competitions->checkCompetitionClosed(
                            $competition_date,
                            $classification,
                            $medium
                        )
                        ) {
                            if (is_array($entry_array)) {
                                foreach ($entry_array as $id) {
                                    // @TODO Add Nonce
                                    $query = ['id' => $id, 'm' => $medium_subset];
                                    $query = build_query($query);
                                    $loc = '/member/edit-title/?' . $query;
                                    wp_redirect($loc);
                                    exit();
                                }
                            }
                        }
                        break;

                    case 'delete':
                        if (!$this->query_competitions->checkCompetitionClosed(
                            $competition_date,
                            $classification,
                            $medium
                        )
                        ) {
                            if ($entry_array !== null) {
                                $this->model->deleteCompetitionEntries($entry_array);
                                $redirect = get_permalink($post->ID);
                                wp_redirect($redirect);
                                exit();
                            }
                        }
                        break;
                }
                $medium_subset_medium = $this->session->get('myentries/subset');
                $classification = CommonHelper::getUserClassification(get_current_user_id(), $medium);
                $current_competition = $this->query_competitions->getCompetitionByDateClassMedium(
                    $competition_date,
                    $classification,
                    $medium
                )
                ;

                $this->session->set(
                    'myentries/' . $medium_subset_medium . '/competition_date',
                    $current_competition->Competition_Date
                )
                ;
                $this->session->set('myentries/' . $medium_subset_medium . '/medium', $current_competition->Medium);
                $this->session->set(
                    'myentries/' . $medium_subset_medium . '/classification',
                    $current_competition->Classification
                )
                ;
                $this->session->save();
                $redirect = get_permalink($post->ID);
                wp_redirect($redirect);
                exit();
            }
        }
    }
}
