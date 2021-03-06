<?php

namespace RpsCompetition\Frontend\Requests\UploadImage;

use Avh\Framework\Network\Session;
use Illuminate\Config\Repository as Settings;
use Illuminate\Http\Request;
use RpsCompetition\Db\QueryCompetitions;
use RpsCompetition\Db\QueryEntries;
use RpsCompetition\Entity\Db\Competition;
use RpsCompetition\Entity\Form\UploadImage as EntityFormUploadImage;
use RpsCompetition\Helpers\CommonHelper;
use RpsCompetition\Helpers\ImageSizeHelper;
use RpsCompetition\Helpers\PhotoHelper;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

/**
 * Class RequestUploadImageModel
 *
 * @package   RpsCompetition\Frontend\Requests\UploadImage
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2016, AVH Software
 */
class RequestUploadImageModel
{
    private $classification;
    private $comp_date;
    private $dest_name;
    private $entity;
    /** @var Form $form */
    private $form;
    private $medium;
    private $photo_helper;
    private $query_competitions;
    private $query_entries;
    private $relative_server_path;
    private $request;
    private $session;
    private $settings;

    /**
     * Constructor
     *
     * @param EntityFormUploadImage $entity
     * @param Session               $session
     * @param Request               $request
     * @param Settings              $settings
     * @param QueryCompetitions     $query_competitions
     * @param QueryEntries          $query_entries
     * @param PhotoHelper           $photo_helper
     */
    public function __construct(
        EntityFormUploadImage $entity,
        Session $session,
        Request $request,
        Settings $settings,
        QueryCompetitions $query_competitions,
        QueryEntries $query_entries,
        PhotoHelper $photo_helper
    ) {

        $this->session            = $session;
        $this->request            = $request;
        $this->query_competitions = $query_competitions;
        $this->query_entries      = $query_entries;
        $this->photo_helper       = $photo_helper;
        $this->entity             = $entity;
        $this->settings           = $settings;
    }

    /**
     * Check if the upload is valid, move the uploaded file and create thumbnails
     *
     * @param Form $form
     *
     * @return bool
     */
    public function handleUploadImage($form)
    {
        $this->form = $form;

        if (!$this->hasMyEntriesSession()) {
            return false;
        }

        $record = $this->getCompetitionRecord();
        if ($record === null) {
            return false;
        }

        // Prepare the title and client file name for storing in the database
        $title = trim($this->entity->getTitle());

        if (!$this->isValid($record->ID, $record->Max_Entries, $title)) {
            return false;
        }
        $succes = $this->moveUploadedFile($title, $record->Image_Size);
        if ($succes === false) {
            return false;
        }

        $succes = $this->addEntry($record->ID, $title);
        if ($succes === false) {
            return false;
        }

        $this->photo_helper->createCommonThumbnails($this->query_entries->getEntryById($this->query_entries->getInsertId()));

        return true;
    }

    /**
     * Check if the uploaded entry is valid.
     * Checks include:
     * - Duplicate Title
     * - Maximum entries per competition
     * - Maximum entries per date (Hmm, not sure if this is needed)
     *
     * @param int    $comp_id
     * @param int    $max_entries
     * @param string $title
     *
     * @return bool
     */
    public function isValid($comp_id, $max_entries, $title)
    {
        // Before we go any further, make sure the title is not a duplicate of
        // an entry already submitted to this competition. Duplicate title result in duplicate
        // file names on the server
        if ($this->query_entries->checkDuplicateTitle($comp_id, $title, get_current_user_id())) {
            $error_message = 'You have already submitted an entry with a title of "' .
                             $title .
                             '" in this competition. Please submit your entry again with a different title.';
            $this->setFormError($this->form, $error_message, 'title');

            return false;
        }

        // Do a final check that the user hasn't exceeded the maximum images per competition.
        // If we don't check this at the last minute it may be possible to exceed the
        // maximum images per competition by having two upload windows open simultaneously.
        $max_per_id = $this->query_entries->countEntriesByCompetitionId($comp_id, get_current_user_id());
        if ($max_per_id >= $max_entries) {
            $error_message = 'You have already submitted the maximum of ' .
                             $max_entries .
                             ' entries into this competition. You must Remove an image before you can submit another';
            $this->setFormError($this->form, $error_message);

            return false;
        }

        $max_per_date = $this->query_entries->countEntriesByCompetitionDate($this->comp_date, get_current_user_id());
        if ($max_per_date >= $this->settings->get('club_max_entries_per_member_per_date')) {
            $max_entries_member_date = $this->settings->get('club_max_entries_per_member_per_date');
            $error_message           = 'You have already submitted the maximum of ' .
                                       $max_entries_member_date .
                                       ' entries for this competition date. You must Remove an image before you can submit another';
            $this->setFormError($this->form, $error_message);

            return false;
        }

        return true;
    }

    /**
     * Add the netry to the database.
     *
     * @param int    $comp_id
     * @param string $title
     *
     * @return bool
     */
    private function addEntry($comp_id, $title)
    {
        $file             = $this->request->file('form.file_name');
        $client_file_name = $file->getClientOriginalName();

        $server_file_name = $this->relative_server_path . '/' . $this->dest_name;
        $data             = [
            'Competition_ID'   => $comp_id,
            'Title'            => $title,
            'Client_File_Name' => $client_file_name,
            'Server_File_Name' => $server_file_name
        ];
        $result           = $this->query_entries->addEntry($data, get_current_user_id());
        if ($result === false) {
            $error_message = 'Failed to INSERT entry record into database';
            $this->setFormError($this->form, $error_message);
        }

        return $result;
    }

    /**
     * get the Competition record.
     *
     * @return bool|Competition
     */
    private function getCompetitionRecord()
    {
        $recs = $this->query_competitions->getCompetitionByDateClassMedium($this->comp_date,
                                                                           $this->classification,
                                                                           $this->medium);

        if ($recs === null) {
            $error_message = 'Competition ' .
                             $this->comp_date .
                             '/' .
                             $this->classification .
                             '/' .
                             $this->medium .
                             ' not found in database';
            $this->setFormError($this->form, $error_message);
        }

        return $recs;
    }

    /**
     * Get needed information from the session.
     *
     * @return bool
     */
    private function hasMyEntriesSession()
    {
        $return = true;
        if ($this->session->has('myentries')) {
            $subset               = $this->session->get('myentries.subset', null);
            $this->comp_date      = $this->session->get('myentries.' . $subset . '.competition_date', null);
            $this->medium         = $this->session->get('myentries.' . $subset . '.medium', null);
            $this->classification = $this->session->get('myentries.' . $subset . '.classification', null);
        } else {
            $error_message = 'Missing "myentries" in session.';
            $this->setFormError($this->form, $error_message);

            $return = false;
        }

        return $return;
    }

    /**
     * Actually move the uploaded file to it's final directory.
     *
     * @param string $title
     *
     * @return bool
     */
    private function moveUploadedFile($title, $image_size)
    {
        // Move the file to its final location
        $relative_server_path = $this->photo_helper->getCompetitionPath($this->comp_date,
                                                                        $this->classification,
                                                                        $this->medium);
        $full_server_path     = $this->request->server('DOCUMENT_ROOT') . $relative_server_path;

        $file               = $this->request->file('form.file_name');
        $uploaded_file_name = $file->getRealPath();
        $uploaded_file_info = getimagesize($uploaded_file_name);
        $dest_name          = $this->photo_helper->createFileName($title, $uploaded_file_name, 'jpg');
        // Need to create the destination folder?
        CommonHelper::createDirectory($full_server_path);

        // If the .jpg file is too big resize it
        $return           = true;
        $image_size_array = ImageSizeHelper::getImageSize($image_size);
        if ($uploaded_file_info[0] > $image_size_array['width'] ||
            $uploaded_file_info[1] > $image_size_array['height']
        ) {
            // Resize the image and deposit it in the destination directory
            $this->photo_helper->doResizeImage($uploaded_file_name,
                                               $full_server_path,
                                               $dest_name,
                                               $image_size);
        } else {
            try {
                $file->move($full_server_path, $dest_name);
            } catch (FileException $e) {
                $this->setFormError($this->form, $e->getMessage());

                $return = false;
            }
        }
        $this->relative_server_path = $relative_server_path;
        $this->dest_name            = $dest_name;

        return $return;
    }

    /**
     * Set the error for the form.
     *
     * @param Form        $form
     * @param string      $error_message
     * @param string|null $form_field
     *
     * @return void
     */
    private function setFormError(Form $form, $error_message, $form_field = null)
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
