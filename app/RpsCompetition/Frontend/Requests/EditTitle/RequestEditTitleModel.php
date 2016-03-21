<?php

namespace RpsCompetition\Frontend\Requests\EditTitle;

use Illuminate\Http\Request as IlluminateRequest;
use RpsCompetition\Db\QueryCompetitions;
use RpsCompetition\Db\QueryEntries;
use RpsCompetition\Entity\Form\EditTitle as EntityFormEditTitle;
use RpsCompetition\Helpers\PhotoHelper;

/**
 * Class RequestEditTitleModel
 *
 * @package   RpsCompetition\Frontend\Requests\EditTitle
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2016, AVH Software
 */
class RequestEditTitleModel
{
    private $entity;
    private $photo_helper;
    private $query_competitions;
    private $query_entries;
    private $request;

    /**
     * @param EntityFormEditTitle $entity
     * @param QueryCompetitions   $query_competitions
     * @param QueryEntries        $query_entries
     * @param PhotoHelper         $photo_helper
     * @param IlluminateRequest   $request
     */
    public function __construct(EntityFormEditTitle $entity,
                                QueryCompetitions $query_competitions,
                                QueryEntries $query_entries,
                                PhotoHelper $photo_helper,
                                IlluminateRequest $request)
    {

        $this->entity = $entity;
        $this->photo_helper = $photo_helper;
        $this->query_competitions = $query_competitions;
        $this->query_entries = $query_entries;
        $this->request = $request;
    }

    /**
     * @param string $server_file_name
     * @param string $new_title
     */
    public function updateTitle($server_file_name, $new_title)
    {
        $competition = $this->query_competitions->getCompetitionByEntryId($this->entity->getId());
        if ($competition == null) {
            wp_die('Failed to SELECT competition for entry ID: ' . $this->entity->getId());
            exit();
        }

        // Rename the image file on the server file system
        $path = $this->photo_helper->getCompetitionPath($competition->Competition_Date,
                                                        $competition->Classification,
                                                        $competition->Medium);
        $old_file_parts = pathinfo($server_file_name);
        $old_file_name = $old_file_parts['basename'];
        $ext = $old_file_parts['extension'];
        $current_user = wp_get_current_user();
        $new_file_name_noext = sanitize_file_name($new_title) .
                               '+' .
                               $current_user->user_login .
                               '+' .
                               filemtime($this->request->server('DOCUMENT_ROOT') . $server_file_name);
        $new_file_name = $new_file_name_noext . '.' . $ext;
        if (!$this->photo_helper->renameImageFile($path, $old_file_name, $new_file_name)) {
            die('<b>Failed to rename image file</b><br>Path: ' .
                $path .
                '<br>Old Name: ' .
                $old_file_name .
                '<br>New Name: ' .
                $new_file_name_noext);
        }

        // Update the Title and File Name in the database
        $updated_data = [
            'ID'               => $this->entity->getId(),
            'Title'            => $new_title,
            'Server_File_Name' => $path . '/' . $new_file_name,
            'Date_Modified'    => current_time('mysql')
        ];
        $result = $this->query_entries->updateEntry($updated_data);
        if ($result === false) {
            wp_die('Failed to UPDATE entry record from database');
            exit();
        }
    }
}
