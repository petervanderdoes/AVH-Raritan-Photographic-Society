<?php

namespace RpsCompetition\Frontend\Requests\MyEntries;


use RpsCompetition\Db\QueryEntries;
use RpsCompetition\Photo\Helper as PhotoHelper;
use RpsCompetition\Settings;

class RequestMyEntriesModel {
    private $photo_helper;
    private $query_entries;
    private $settings;

    /**
     * Constructor
     *
     * @param QueryEntries $query_entries
     * @param PhotoHelper  $photo_helper
     * @param Settings     $settings
     */
    public function __construct(QueryEntries $query_entries, PhotoHelper $photo_helper, Settings $settings) {

        $this->query_entries = $query_entries;
        $this->photo_helper = $photo_helper;
        $this->settings = $settings;
    }

    /**
     * Delete competition entries
     *
     * @param array $entries Array of entries ID to delete.
     */
    public function deleteCompetitionEntries($entries)
    {
        if (is_array($entries)) {
            foreach ($entries as $id) {

                $entry_record = $this->query_entries->getEntryById($id);
                if ($entry_record == false) {
                    $this->settings->set(
                        'errmsg',
                        sprintf('<b>Failed to SELECT competition entry with ID %s from database</b><br>', $id)
                    )
                    ;
                } else {
                    // Delete the record from the database
                    $result = $this->query_entries->deleteEntry($id);
                    if ($result === false) {
                        $this->settings->set(
                            'errmsg',
                            sprintf('<b>Failed to DELETE competition entry %s from database</b><br>')
                        )
                        ;
                    } else {
                        // Delete the file from the server file system
                        $this->photo_helper->deleteEntryFromDisk($entry_record);
                    }
                }
            }
        }
    }
}
