<?php

namespace Stanford\TrackCovidGileadEpicAssistant;

require_once "emLoggerTrait.php";

use \REDCap;

class TrackCovidGileadEpicAssistant extends \ExternalModules\AbstractExternalModule
{

    use emLoggerTrait;

    CONST EM_EVENT_NAME = "baseline_arm_1";


    public function __construct()
    {
        parent::__construct();
        // Other code to run when object is instantiated
    }

    public function redcap_save_record($project_id, $record, $instrument, $event_id, $group_id, $survey_hash = NULL, $response_id = NULL, $repeat_instance = 1) {

        $em_event_id = REDCap::getEventIdFromUniqueEvent(self::EM_EVENT_NAME);


        if ($event_id != $em_event_id) {
            // This is the wrong event
            // $this->emDebug("Wrong Event");
            return;
        }

        $this->emDebug("Updating $record");

        $records = $this->getRecordData(array($record));
        $updates = $this->parseRecords($records);
        $q = $this->updateRecords($updates);

        if (!empty($q['errors'])) {
            REDCap::logEvent("Error updating in " . $this->getModuleName(), json_encode($updates),"",$record, $event_id);
        }

    }

    public function getRecordData($records = null) {
        $params = [
            "project_id"    => $this->getProjectId(),
            "records"       => $records,
            "return_format" => "json",
            "events"        => [ self::EM_EVENT_NAME ],
        ];

        $filterLogic = $this->getProjectSetting('filter-logic');
        if (!empty($filterLogic)) $params['filterLogic'] = $filterLogic;

        $q = REDCap::getData($params);
        return json_decode($q,true);
    }


    /**
     * Parse through the records to return an array of updates
     * @param $data array  A json-exported redcap array of records
     * @return array
     */
    public function parseRecords($results) {

        $updates = [];

        $force = $this->getProjectSetting('force-update');

        foreach ($results as $r) {

            $sex       = $this->parseSex($r);
            $lang      = $this->parseLang($r);
            $ethnicity = $this->parseEthnicity($r);

            $update = [];

            if (( $force || empty($r['epic_lang']))     && !empty($lang))      $update['epic_lang']      = $lang;
            if (( $force || empty($r['epic_ethnicity']))&& !empty($ethnicity)) $update['epic_ethnicity'] = $ethnicity;
            if (( $force || empty($r['epic_sex']))      && !empty($sex))       $update['epic_sex']       = $sex;


            if (!empty($update)) {
                $update[REDCap::getRecordIdField()] = $r[REDCap::getRecordIdField()];
                $update['redcap_event_name']        = $this::EM_EVENT_NAME;

                $updates[] = $update;
            }
        }

        return $updates;
    }

    public function updateRecords($updates) {
        $this->emDebug('updates',$updates);
        $q = REDCap::saveData($this->getProjectId(), 'json', json_encode($updates));
        //$this->emDebug($q);
        // return json_encode($updates);
        return $q;
    }

    // Good
    public function parseSex($row) {
        $saad = $row["saab"];
        switch ($saad) {
            case "1":
                $sex = "M";
                break;
            case "2":
                $sex = "F";
                break;
            default:
                $sex = "U";
                break;
        }
        return $sex;
    }

    // Good
    public function parseLang($row)
    {
        global $module;

        $lang = $row['non_english'];

        switch ($lang) {
            case "1":
                $result = "SPA";
                break;
            case "2":
                $result = "CAN";
                break;
            case "3":
                $result = "MDN";
                break;
            case "4":
                $result = "RUS";
                break;
            case "5":
                $result = "TGL";
                break;
            case "6":
                $result = "FRE";
                break;
            case "7":
                $result = "KOR";
                break;
            case "8":
                $result = "VIE";
                break;
            default:
                $result = "ENG";
        }

        return trim($result);

    }

    public function parseEthnicity($row) {
        // Figure out ethnicity
        $latino_origin = $row['latino_origin'];
        switch ($latino_origin) {
            case "1":
                $ethnicity = "Hispanic/Latino";
                break;
            case "0":
                $ethnicity = "Non-Hispanic/Non-Latino";
                break;
            default:
                $ethnicity = "Unknown";
        }
        return trim($ethnicity);
    }


}
