<?php

namespace RpsCompetition\Frontend\Requests\EditTitle;

use Avh\Network\Session;
use Illuminate\Http\Request as IlluminateRequest;
use RpsCompetition\Common\Helper as CommonHelper;
use RpsCompetition\Db\QueryCompetitions;
use RpsCompetition\Db\QueryEntries;
use RpsCompetition\Entity\Forms\EditTitle as EntityFormEditTitle;
use RpsCompetition\Form\Type\EditTitleType;
use RpsCompetition\Photo\Helper as PhotoHelper;
use RpsCompetition\Settings;
use Symfony\Component\Form\FormFactory;

/**
 * Class RequestEditTitle
 *
 * @author    Peter van der Does
 * @copyright Copyright (c) 2015, AVH Software
 * @package   RpsCompetition\Frontend\Requests\EditTitle
 */
class RequestEditTitle
{
    private $EditTitleType;
    private $entity;
    private $formFactory;
    private $photo_helper;
    private $query_competitions;
    private $query_entries;
    private $request;
    private $session;
    private $settings;

    /**
     * Constructor
     *
     * @param EntityFormEditTitle $entity
     * @param EditTitleType       $EditTitleType
     * @param QueryCompetitions   $query_competitions
     * @param QueryEntries        $query_entries
     * @param PhotoHelper         $photo_helper
     * @param IlluminateRequest   $request
     * @param FormFactory         $formFactory
     * @param Session             $session
     * @param Settings            $settings
     */
    public function __construct(
        EntityFormEditTitle $entity,
        EditTitleType $EditTitleType,
        QueryCompetitions $query_competitions,
        QueryEntries $query_entries,
        PhotoHelper $photo_helper,
        IlluminateRequest $request,
        FormFactory $formFactory,
        Session $session,
        Settings $settings
    ) {

        $this->query_competitions = $query_competitions;
        $this->entity = $entity;
        $this->EditTitleType = $EditTitleType;
        $this->request = $request;
        $this->formFactory = $formFactory;
        $this->session = $session;
        $this->query_entries = $query_entries;
        $this->photo_helper = $photo_helper;
        $this->settings = $settings;
    }

    /**
     * Handle POST request for the editing the title of a photo.
     * This method handles the POST request generated on the page Edit Title
     * The action is called from the theme!
     *
     * @see      Shortcodes::shortcodeEditTitle
     * @internal Hook: suffusion_before_post
     */
    public function handleRequestEditTitle()
    {
        global $post;

        if (is_object($post) && $post->ID == 75) {
            $entity = new EntityFormEditTitle();

            $form = $this->formFactory->create(
                new EditTitleType($entity),
                $this->entity,
                ['attr' => ['id' => 'edittitle']]
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

            $server_file_name = $this->entity->getServerFileName();
            $new_title = $this->entity->getNewTitle();
            if (get_magic_quotes_gpc()) {
                $server_file_name = stripslashes($server_file_name);
                $new_title = stripslashes($new_title);
            }

            if ($this->entity->getNewTitle() !== $this->entity->getTitle()) {
                $competition = $this->query_competitions->getCompetitionByEntryId($entity->getId());
                if ($competition == null) {
                    wp_die('Failed to SELECT competition for entry ID: ' . $entity->getId());
                }

                // Rename the image file on the server file system
                $path = $this->photo_helper->getCompetitionPath(
                    $competition->Competition_Date,
                    $competition->Classification,
                    $competition->Medium
                )
                ;
                $old_file_parts = pathinfo($server_file_name);
                $old_file_name = $old_file_parts['basename'];
                $ext = $old_file_parts['extension'];
                $current_user = wp_get_current_user();
                $new_file_name_noext = sanitize_file_name(
                        $new_title
                    ) . '+' . $current_user->user_login . '+' . filemtime(
                        $this->request->server('DOCUMENT_ROOT') . $server_file_name
                    );
                $new_file_name = $new_file_name_noext . '.' . $ext;
                if (!$this->photo_helper->renameImageFile($path, $old_file_name, $new_file_name)) {
                    die('<b>Failed to rename image file</b><br>Path: ' . $path . '<br>Old Name: ' . $old_file_name . '<br>New Name: ' . $new_file_name_noext);
                }

                // Update the Title and File Name in the database
                $updated_data = [
                    'ID'               => $entity->getId(),
                    'Title'            => $new_title,
                    'Server_File_Name' => $path . '/' . $new_file_name,
                    'Date_Modified'    => current_time('mysql')
                ];
                $result = $this->query_entries->updateEntry($updated_data);
                if ($result === false) {
                    wp_die('Failed to UPDATE entry record from database');
                }
            }
            $redirect_to = $this->entity->getWpGetReferer();
            wp_redirect($redirect_to);
            exit();
        }
    }

}
