<?php

namespace RpsCompetition\Frontend\Requests\BanquetEntries;

use Illuminate\Http\Request as IlluminateRequest;
use RpsCompetition\Db\QueryCompetitions;
use RpsCompetition\Db\QueryEntries;
use RpsCompetition\Entity\Forms\BanquetEntries as EntityBanquetEntries;
use RpsCompetition\Photo\Helper as PhotoHelper;
use RpsCompetition\Common\Helper as CommonHelper;

/**
 * Class RequestBanquetEntriesModel
 *
 * @author    Peter van der Does
 * @copyright Copyright (c) 2015, AVH Software
 * @package   RpsCompetition\Frontend\Requests\BanquetEntries
 */
class RequestBanquetEntriesModel
{
    private $entity;
    private $photo_helper;
    private $query_competitions;
    private $query_entries;
    /**
     * @var IlluminateRequest
     */
    private $request;

    /**
     * @param EntityBanquetEntries $entity
     * @param IlluminateRequest        $request
     * @param QueryEntries             $query_entries
     * @param QueryCompetitions        $query_competitions
     * @param PhotoHelper              $photo_helper
     *
     */
    public function __construct(
        EntityBanquetEntries $entity,
        IlluminateRequest $request,
        QueryEntries $query_entries,
        QueryCompetitions $query_competitions,
        PhotoHelper $photo_helper
    ) {

        $this->query_entries = $query_entries;
        $this->photo_helper = $photo_helper;
        $this->entity = $entity;
        $this->request = $request;
        $this->query_competitions = $query_competitions;
    }

    public function addSelectedEntries()
    {
        $entries = (array) $this->request->input('form.entry_id', []);
        foreach ($entries as $entry_id) {
            $entry = $this->query_entries->getEntryById($entry_id);
            $competition = $this->query_competitions->getCompetitionByID($entry->Competition_ID);
            $banquet_ids = json_decode(base64_decode($this->entity->getBanquetids()));
            foreach ($banquet_ids as $banquet_id) {
                $banquet_record = $this->query_competitions->getCompetitionByID($banquet_id);
                if ($competition->Medium == $banquet_record->Medium && $competition->Classification == $banquet_record->Classification) {
                    // Move the file to its final location
                    $path = $this->photo_helper->getCompetitionPath(
                        $banquet_record->Competition_Date,
                        $banquet_record->Classification,
                        $banquet_record->Medium
                    )
                    ;
                    CommonHelper::createDirectory($path);
                    $file_info = pathinfo($entry->Server_File_Name);
                    $new_file_name = $path . '/' . $file_info['basename'];
                    $original_filename = html_entity_decode(
                        $this->request->server('DOCUMENT_ROOT') . $entry->Server_File_Name,
                        ENT_QUOTES,
                        get_bloginfo('charset')
                    );
                    // Need to create the destination folder?
                    copy($original_filename, $this->request->server('DOCUMENT_ROOT') . $new_file_name);
                    $data = [
                        'Competition_ID' => $banquet_record->ID,
                        'Title' => $entry->Title,
                        'Client_File_Name' => $entry->Client_File_Name,
                        'Server_File_Name' => $new_file_name
                    ];
                    $this->query_entries->addEntry($data, get_current_user_id());
                }
            }
        }
    }

    public function deleteAllEntries()
    {
        $all_entries = json_decode(base64_decode($this->entity->getAllentries()));
        foreach ($all_entries as $entry_id) {
            $entry = $this->query_entries->getEntryById($entry_id);
            if ($entry !== null) {
                $this->query_entries->deleteEntry($entry->ID);
                $this->photo_helper->deleteEntryFromDisk($entry);
            }
        }
    }
}
