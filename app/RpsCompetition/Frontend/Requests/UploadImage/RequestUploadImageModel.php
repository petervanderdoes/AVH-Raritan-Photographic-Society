<?php

namespace RpsCompetition\Frontend\Requests\UploadImage;

use Avh\Network\Session;
use Illuminate\Http\Request;
use RpsCompetition\Common\Helper as CommonHelper;
use RpsCompetition\Constants;
use RpsCompetition\Db\QueryCompetitions;
use RpsCompetition\Db\QueryEntries;
use RpsCompetition\Entity\Forms\UploadImage as EntityUploadImage;
use RpsCompetition\Photo\Helper as PhotoHelper;
use RpsCompetition\Settings;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

/**
 * Class RequestUploadImageModel
 *
 * @author    Peter van der Does
 * @copyright Copyright (c) 2015, AVH Software
 * @package   RpsCompetition\Frontend\Requests\UploadImage
 */
class RequestUploadImageModel
{
    private $entity;
    /** @var \Symfony\Component\Form\Form $form */
    private $form;
    private $photo_helper;
    private $query_competitions;
    private $query_entries;
    private $request;
    private $session;
    private $settings;

    /**
     * Constructor
     *
     * @param EntityUploadImage $entity
     * @param Session           $session
     * @param Request           $request
     * @param Settings          $settings
     * @param QueryCompetitions $query_competitions
     * @param QueryEntries      $query_entries
     * @param PhotoHelper       $photo_helper
     */
    public function __construct(
        EntityUploadImage $entity,
        Session $session,
        Request $request,
        Settings $settings,
        QueryCompetitions $query_competitions,
        QueryEntries $query_entries,
        PhotoHelper $photo_helper
    ) {

        $this->session = $session;
        $this->request = $request;
        $this->query_competitions = $query_competitions;
        $this->query_entries = $query_entries;
        $this->photo_helper = $photo_helper;
        $this->entity = $entity;
        $this->settings = $settings;
    }

    /**
     * @return bool
     */
    public function all()
    {
        // Retrieve and parse the selected competition cookie
        if ($this->session->has('myentries')) {
            $subset = $this->session->get('myentries/subset', null);
            $comp_date = $this->session->get('myentries/' . $subset . '/competition_date', null);
            $medium = $this->session->get('myentries/' . $subset . '/medium', null);
            $classification = $this->session->get('myentries/' . $subset . '/classification', null);
        } else {
            $error_message = 'Missing "myentries" in session.';
            $this->setFormError($this->form, $error_message);

            return false;
        }

        $recs = $this->query_competitions->getCompetitionByDateClassMedium(
            $comp_date,
            $classification,
            $medium,
            ARRAY_A
        )
        ;
        if ($recs) {
            $comp_id = $recs['ID'];
            $max_entries = $recs['Max_Entries'];
        } else {
            $error_message = 'Competition ' . $comp_date . '/' . $classification . '/' . $medium . ' not found in database';
            $this->setFormError($this->form, $error_message);

            return false;
        }

        // Prepare the title and client file name for storing in the database
        $title = trim($this->entity->getTitle());

        $file = $this->request->file('form.file_name');
        $client_file_name = $file->getClientOriginalName();

        // Before we go any further, make sure the title is not a duplicate of
        // an entry already submitted to this competition. Duplicate title result in duplicate
        // file names on the server
        if ($this->query_entries->checkDuplicateTitle($comp_id, $title, get_current_user_id())) {
            $error_message = 'You have already submitted an entry with a title of "' . $title . '" in this competition. Please submit your entry again with a different title.';
            $this->setFormError($this->form, $error_message, 'title');

            return false;
        }

        // Do a final check that the user hasn't exceeded the maximum images per competition.
        // If we don't check this at the last minute it may be possible to exceed the
        // maximum images per competition by having two upload windows open simultaneously.
        $max_per_id = $this->query_entries->countEntriesByCompetitionId($comp_id, get_current_user_id());
        if ($max_per_id >= $max_entries) {
            $error_message = 'You have already submitted the maximum of ' . $max_entries . ' entries into this competition. You must Remove an image before you can submit another';
            $this->setFormError($this->form, $error_message);

            return false;
        }

        $max_per_date = $this->query_entries->countEntriesByCompetitionDate($comp_date, get_current_user_id());
        if ($max_per_date >= $this->settings->get('club_max_entries_per_member_per_date')) {
            $max_entries_member_date = $this->settings->get('club_max_entries_per_member_per_date');
            $error_message = 'You have already submitted the maximum of ' . $max_entries_member_date . ' entries for this competition date. You must Remove an image before you can submit another';
            $this->setFormError($this->form, $error_message);

            return false;
        }

        // Move the file to its final location
        $relative_server_path = $this->photo_helper->getCompetitionPath($comp_date, $classification, $medium);
        $full_server_path = $this->request->server('DOCUMENT_ROOT') . $relative_server_path;

        $user = wp_get_current_user();
        $file = $this->request->file('form.file_name');
        $uploaded_file_name = $file->getRealPath();
        $uploaded_file_info = getimagesize($uploaded_file_name);
        $dest_name = sanitize_file_name($title) . '+' . $user->user_login . '+' . filemtime($uploaded_file_name);
        // Need to create the destination folder?
        CommonHelper::createDirectory($full_server_path);

        // If the .jpg file is too big resize it
        if ($uploaded_file_info[0] > Constants::IMAGE_MAX_WIDTH_ENTRY || $uploaded_file_info[1] > Constants::IMAGE_MAX_HEIGHT_ENTRY) {

            // Resize the image and deposit it in the destination directory
            $this->photo_helper->doResizeImage($uploaded_file_name, $full_server_path, $dest_name . '.jpg', 'FULL');
        } else {
            try {
                $file->move($full_server_path, $dest_name . '.jpg');
            } catch (FileException $e) {
                $this->setFormError($this->form, $e->getMessage());

                return false;
            }
        }

        $server_file_name = $relative_server_path . '/' . $dest_name . '.jpg';
        $data = [
            'Competition_ID' => $comp_id,
            'Title' => $title,
            'Client_File_Name' => $client_file_name,
            'Server_File_Name' => $server_file_name
        ];
        $result = $this->query_entries->addEntry($data, get_current_user_id());
        if ($result === false) {
            $error_message = 'Failed to INSERT entry record into database';
            $this->setFormError($this->form, $error_message);

            return false;
        }

        $this->photo_helper->createCommonThumbnails(
            $this->query_entries->getEntryById($this->query_entries->getInsertId())
        )
        ;

        return true;
    }

    /**
     * @param mixed $form
     */
    public function setForm($form)
    {
        $this->form = $form;
    }

    /**
     * Set the error for the form.
     *
     * @param \Symfony\Component\Form\Form $form
     * @param string                       $error_message
     * @param string|null                  $form_field
     *
     * @return void
     */
    private function setFormError($form, $error_message, $form_field = null)
    {
        if ($form_field === null) {
            $form->addError(new FormError('Error: ' . $error_message));
        } else {
            $form->get($form_field)
                 ->addError(new FormError($error_message))
            ;
        }
        $errors = $form->getErrors();
        $this->settings->set('formerror', $errors);
    }
}
