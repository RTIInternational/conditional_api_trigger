<?php

namespace ConditionalAPITriggerModule;

use \REDCap;
use \Piping;

use ExternalModules\AbstractExternalModule;

class ConditionalAPITriggerModule extends AbstractExternalModule
{
    public function redcap_save_record($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance)
    {
        global $Proj;

        // grab all the api conditions
        $apiForms = $this->getProjectSetting("instrument");
        $apiConditions = $this->getProjectSetting("condition");
        $apiUrls = $this->getProjectSetting("api_url");
        $apiMethods = $this->getProjectSetting("api_method");
        $apiData = $this->getProjectSetting("api_data");
        $apiHeaders = $this->getProjectSetting("api_header");
        $itemSeparator = $this->getProjectSetting("data_item_separator");
        $valueSeparator = $this->getProjectSetting("data_value_separator");
        $useSeparator = $this->getProjectSetting("separate_post_data");
        $runOnceField = $this->getProjectSetting('run_once_field');
        $runOnceEvent = $this->getProjectSetting('run_once_event');
        $resultField = $this->getProjectSetting('result_field');
        $resultEvent = $this->getProjectSetting('result_event');
        $jsonParsing = $this->getProjectSetting('json_parsing');
        $jsonKey = $this->getProjectSetting('json_parsing_key');
        $jsonIsArray = $this->getProjectSetting('json_is_array');
        $jsonArrayIndex = $this->getProjectSetting('json_array_index');
        $sanitizeBrackets = $this->getProjectSetting('sanitize_brackets');
        $lastRunDateField = $this->getProjectSetting('last_run_date_field');
        $lastRunDateEvent = $this->getProjectSetting('last_run_date_event');

        if ($apiForms != null) {

            $formCount = count($apiForms);

            // see if the form exists in the conditions link
            for ($i = 0; $i < $formCount; $i++) {
                if ($apiForms[$i] == $instrument) {
                    $func = $apiConditions[$i];
                    $followThrough = false;
                    if ($Proj->isRepeatingEvent($event_id)) {
                        $followThrough = REDCap::evaluateLogic($func, $project_id, $record, $event_id, $repeat_instance, $instrument);
                    } else {
                        $followThrough = REDCap::evaluateLogic($func, $project_id, $record, $event_id, $repeat_instance);
                    }

                    if ($followThrough == true) {
                        // grab all of the data for use in piping
                        $recordData = REDCap::getData($project_id, "array", $record);


                        // create the url
                        $url = Piping::replaceVariablesInLabel($apiUrls[$i], $record, $event_id, $repeat_instance, $recordData, false, $project_id, false);
                        // not sure why we'd ever want to have dates in the url but I'm CMA here
                        $url = $this->replaceYMD($url);

                        $method = $apiMethods[$i];

                        $conn = curl_init($url);
                        $formData = "";

                        curl_setopt($conn, CURLOPT_RETURNTRANSFER, true);
                        if ($apiData[$i] != "") {
                            $formData = Piping::replaceVariablesInLabel($apiData[$i], $record, $event_id, $repeat_instance, $recordData, false, $project_id, false);
                            $formData = $this->replaceYMD($formData);
                            $formData = $this->replaceSlashes($formData);
                            if ($sanitizeBrackets[$i] == '1') {
                                // replace {{ and }} with [ and ]
                                $formData = str_replace('{{', '[', $formData);
                                $formData = str_replace('}}', ']', $formData);
                            }
                            if ($useSeparator[$i] == "1") {
                                $formData = $this->buildPostArray($formData, ($itemSeparator[$i] == "" ? ";" : $itemSeparator[$i]), $valueSeparator[$i] == "" ? "=" : $valueSeparator[$i]);
                            }
                            curl_setopt($conn, CURLOPT_POSTFIELDS, $formData);
                        }

                        if ($method == "POST") curl_setopt($conn, CURLOPT_POST, 1);
                        $headerArr = array();
                        if ($apiHeaders[$i] != "") {
                            $headers = Piping::replaceVariablesInLabel($apiHeaders[$i], $record, $event_id, $repeat_instance, $recordData, false, $project_id, false);
                            $headers = $this->replaceYMD($headers);
                            $headers = $this->replaceSlashes($headers);
                            if ($sanitizeBrackets[$i] == '1') {
                                // replace {{ and }} with [ and ]
                                $headers = str_replace('{{', '[', $headers);
                                $headers = str_replace('}}', ']', $headers);
                            }
                            $headerArr = explode(";", $headers);
                        }
                        $headerArr[] = "Content-Length: " . strlen($formData);
                        curl_setopt($conn, CURLOPT_HTTPHEADER, $headerArr);

                        $response = curl_exec($conn);
                        curl_close($conn);

                        $data = array();

                        if ($runOnceField[$i] != '') {
                            $runOnceEventId = $event_id;
                            if ($runOnceEvent[$i] != '') {
                                $runOnceEventId = $runOnceEvent[$i];
                            }

                            if (!$Proj->isRepeatingForm($runOnceEventId, $instrument) && !$Proj->isRepeatingEvent($runOnceEventId)) {
                                $data[$record][$runOnceEventId][$runOnceField[$i]] = '1';
                            } else if ($Proj->isRepeatingEvent($runOnceEventId)) {
                                $data[$record]['repeat_instances'][$runOnceEventId][''][$repeat_instance][$runOnceField[$i]] = '1';
                            } else if ($Proj->isRepeatingForm($runOnceEventId, $instrument)) {
                                $data[$record]['repeat_instances'][$runOnceEventId][$instrument][$repeat_instance][$runOnceField[$i]] = '1';
                            }
                        }

                        if ($resultField[$i] != '') {
                            if ($resultEvent[$i] == '') {
                                if (!$Proj->isRepeatingForm($event_id, $instrument) && !$Proj->isRepeatingEvent($event_id)) {
                                    $data[$record][$event_id][$resultField[$i]] = $this->parseResponse($response, $jsonParsing[$i], $jsonKey[$i], $jsonIsArray[$i], $jsonArrayIndex[$i]);
                                } else if ($Proj->isRepeatingEvent($event_id)) {
                                    $data[$record]['repeat_instances'][$event_id][''][$repeat_instance][$resultField[$i]] = $this->parseResponse($response, $jsonParsing[$i], $jsonKey[$i], $jsonIsArray[$i], $jsonArrayIndex[$i]);
                                } else if ($Proj->isRepeatingForm($event_id, $instrument)) {
                                    $data[$record]['repeat_instances'][$event_id][$instrument][$repeat_instance][$resultField[$i]] = $this->parseResponse($response, $jsonParsing[$i], $jsonKey[$i], $jsonIsArray[$i], $jsonArrayIndex[$i]);
                                }
                            } else {
                                $data[$record][$resultEvent[$i]][$resultField[$i]] = $this->parseResponse($response, $jsonParsing[$i], $jsonKey[$i], $jsonIsArray[$i], $jsonArrayIndex[$i]);
                            }
                        }

                        // write the current date/(time?) to the last run date field
                        if ($lastRunDateField[$i] != '' && $lastRunDateEvent[$i] != '') {
                            $field_info = $Proj->metadata[$lastRunDateField[$i]];
                            $value = "";
                            if (substr($field_info['element_validation_type'], 0, 5) == "date_") {
                                $value = date("Y-m-d");
                            } else {
                                $value = date("Y-m-d H:i:s");
                            }
                            if (!$Proj->isRepeatingForm($event_id, $instrument) && !$Proj->isRepeatingEvent($event_id)) {
                                $data[$record][$lastRunDateEvent[$i]][$lastRunDateField[$i]] = $value;
                            } else if ($Proj->isRepeatingEvent($event_id)) {
                                $data[$record]['repeat_instances'][$lastRunDateEvent[$i]][''][$repeat_instance][$lastRunDateField[$i]] = $value;
                            } else if ($Proj->isRepeatingForm($event_id, $instrument)) {
                                $data[$record]['repeat_instances'][$lastRunDateEvent[$i]][$instrument][$repeat_instance][$lastRunDateField[$i]] = $value;
                            }
                        }

                        $params = array('project_id' => $project_id, 'data_format' => 'array', 'data' => $data);
                        if (sizeof($data) > 0) {
                            REDCap::saveData($params);
                        }
                    }
                }
            }
        }
    }

    public function hourlyTrigger($cronInfo)
    {
        foreach ($this->getProjectsWithModuleEnabled() as $localProjectId) {
            $this->setProjectId($localProjectId);

            $this->runHourlyTrigger($localProjectId);
        }

        return "The \"{$cronInfo['cron_description']}\" cron job completed successfully.";
    }

    private function runHourlyTrigger($project_id)
    {
        global $Proj;

        // grab all the api conditions
        $apiForms = $this->getProjectSetting("instrument", $project_id);
        $apiConditions = $this->getProjectSetting("condition", $project_id);
        $apiUrls = $this->getProjectSetting("api_url", $project_id);
        $apiMethods = $this->getProjectSetting("api_method", $project_id);
        $apiData = $this->getProjectSetting("api_data", $project_id);
        $apiHeaders = $this->getProjectSetting("api_header", $project_id);
        $itemSeparator = $this->getProjectSetting("data_item_separator", $project_id);
        $valueSeparator = $this->getProjectSetting("data_value_separator", $project_id);
        $useSeparator = $this->getProjectSetting("separate_post_data", $project_id);
        $runOnceField = $this->getProjectSetting('run_once_field', $project_id);
        $resultField = $this->getProjectSetting('result_field', $project_id);
        $resultEvent = $this->getProjectSetting('result_event', $project_id);
        $runHourly = $this->getProjectSetting('run_hourly', $project_id);
        $runOnceEvent = $this->getProjectSetting('run_once_event', $project_id);
        $recordField = $this->getRecordIdField($project_id);

        if ($apiForms != null) {

            $formCount = count($apiForms);

            // see if the form exists in the conditions link
            for ($i = 0; $i < $formCount; $i++) {
                if ($runHourly[$i] != "1") {
                    continue;
                }

                $params = array("project_id" => $project_id, "return_format" => "array", "fields" => $recordField, "filterLogic" => $apiConditions[$i]);
                $records = REDCap::getData($params);

                foreach ($records as $record => $recordIdData) {
                    // grab all of the data for use in piping
                    $recordData = REDCap::getData($project_id, "array", $record);

                    $event_id = $Proj->firstEventId;
                    $repeat_instance = 1;

                    // create the url
                    $url = Piping::replaceVariablesInLabel($apiUrls[$i], $record, $event_id, $repeat_instance, $recordData, true, $project_id, false);
                    // not sure why we'd ever want to have dates in the url but I'm CMA here
                    $url = $this->replaceYMD($url);

                    $method = $apiMethods[$i];

                    $conn = curl_init($url);

                    if ($method == "POST") curl_setopt($conn, CURLOPT_POST, 1);

                    curl_setopt($conn, CURLOPT_RETURNTRANSFER, true); // AM: changed from false for NPH
                    $formData = "";
                    if ($apiData[$i] != "") {
                        $formData = Piping::replaceVariablesInLabel($apiData[$i], $record, $event_id, $repeat_instance, $recordData, true, $project_id, false);
                        $formData = $this->replaceYMD($formData);
                        $formData = $this->replaceSlashes($formData);
                        if ($useSeparator[$i] == "1") {
                            $formData = $this->buildPostArray($formData, ($itemSeparator[$i] == "" ? ";" : $itemSeparator[$i]), $valueSeparator[$i] == "" ? "=" : $valueSeparator[$i]);
                        }
                        curl_setopt($conn, CURLOPT_POSTFIELDS, $formData);
                    }

                    $headerArr = array();
                    if ($apiHeaders[$i] != "") {
                        $headers = Piping::replaceVariablesInLabel($apiHeaders[$i], $record, $event_id, $repeat_instance, $recordData, true, $project_id, false);
                        $headers = $this->replaceYMD($headers);
                        $headers = $this->replaceSlashes($headers);
                        $headerArr = explode(";", $headers); // AM: added for NPH
                    }
                    if ($useSeparator[$i] == "1") {
                        $headerArr[] = "Content-Length: " . strlen($formData);
                    }
                    curl_setopt($conn, CURLOPT_HTTPHEADER, $headerArr);

                    $response = curl_exec($conn);
                    curl_close($conn);

                    $data = array();

                    if ($runOnceField[$i] != '' && $runOnceEvent[$i] != '') {

                        $data[$record][$runOnceEvent[$i]][$runOnceField[$i]] = '1';
                    }

                    if ($resultField[$i] != '' && $resultEvent[$i] != '') {
                        $data[$record][$resultEvent[$i]][$resultField[$i]] = $response;
                    }

                    $params = array('project_id' => $project_id, 'data_format' => 'array', 'data' => $data);
                    if (sizeof($data) > 0) {
                        REDCap::saveData($params);
                    }
                }
            }
        }
    }

    // looks for convertMDYtoYMD() and converts the containing data to that date
    private function replaceYMD($formData)
    {
        $begin = strpos($formData, "convertMDYtoYMD(");
        if ($begin === false) {
            return $formData;
        } else {
            $end = strpos($formData, ")", $begin);
            $toconvert = substr($formData, $begin + 16, $end - ($begin + 16));
            // if $toconvert is blank, don't convert the date just make it blank
            if (trim($toconvert) == "") {
                return $this->replaceYMD(substr_replace($formData, "", $begin, ($end - $begin + 1)));
            }
            $date = date_create_from_format("m-d-Y", $toconvert);
            $date = date_format($date, "Y-m-d");
            return $this->replaceYMD(substr_replace($formData, $date, $begin, ($end - $begin + 1)));
        }
    }

    // looks for addSlashes<<<>>> and adds slashes to the containing data. Not using () to keep from mucking things up when parenthesis appears in the code
    private function replaceSlashes($formData)
    {
        $begin = strpos($formData, "addSlashes<<<");
        if ($begin === false) {
            return $formData;
        } else {
            $end = strpos($formData, ">>>", $begin);
            $toconvert = substr($formData, $begin + 13, $end - ($begin + 13));
            $toconvert = addslashes($toconvert);
            return $this->replaceSlashes(substr_replace($formData, $toconvert, $begin, ($end - $begin + 3)));
        }
    }

    private function parseResponse($response, $jsonParsing, $jsonKey, $jsonIsArray, $jsonArrayIndex)
    {
        if ($jsonParsing == '1') {
            $response = json_decode($response, true);
            if ($jsonIsArray == '1') {
                if ($jsonKey == '') {
                    return $response[$jsonArrayIndex];
                } else {
                    return $response[$jsonArrayIndex][$jsonKey];
                }
            } else {
                if ($jsonKey == '') {
                    return $response;
                } else {
                    return $response[$jsonKey];
                }
            }
        } else {
            return $response;
        }
    }

    private function buildPostArray($formData, $itemSeparator, $valueSeparator)
    {
        $formDataArr1 = explode($itemSeparator, $formData);
        $outputArr = array();
        foreach ($formDataArr1 as $item) {
            $parts = explode($valueSeparator, $item);
            $outputArr[trim($parts[0])] = trim($parts[1]);
        }

        return http_build_query($outputArr, '', '&');
    }
}
