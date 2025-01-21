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
        $resultField = $this->getProjectSetting('result_field');
        $resultEvent = $this->getProjectSetting('result_event');
        $jsonParsing = $this->getProjectSetting('json_parsing');
        $jsonKey = $this->getProjectSetting('json_parsing_key');
        $jsonIsArray = $this->getProjectSetting('json_is_array');
        $jsonArrayIndex = $this->getProjectSetting('json_array_index');
        $sanitizeBrackets = $this->getProjectSetting('sanitize_brackets');

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
                            
                            if (!$Proj->isRepeatingForm($event_id, $instrument) && !$Proj->isRepeatingEvent($event_id)) {
                                $data[$record][$event_id][$runOnceField[$i]] = '1';
                            } else if ($Proj->isRepeatingEvent($event_id)) {
                                $data[$record]['repeat_instances'][$event_id][''][$repeat_instance][$runOnceField[$i]] = '1';
                            } else if ($Proj->isRepeatingForm($event_id, $instrument)) {
                                $data[$record]['repeat_instances'][$event_id][$instrument][$repeat_instance][$runOnceField[$i]] = '1';
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

                        $params = array('project_id' => $project_id, 'data_format' => 'array', 'data' => $data);
                        if (sizeof($data) > 0) {
                            REDCap::saveData($params);
                        }
                    }
                }
            }
        }
    }

    // looks for convertMDYtoYMD() and converts the containing data to that date
    private function replaceYMD($formData)
    {
        $begin = strpos($formData, "convertMDYtoYMD(");
        if ($begin === false)
        {
            return $formData;
        }
        else
        {
            $end = strpos($formData, ")", $begin);
            $toconvert = substr($formData, $begin + 16, $end - ($begin+16));
            $date = date_create_from_format("m-d-Y", $toconvert);
            $date = date_format($date, "Y-m-d");
            return $this->replaceYMD(substr_replace($formData, $date, $begin, ($end - $begin + 1)));
        }
    }

    // looks for addSlashes<<<>>> and adds slashes to the containing data. Not using () to keep from mucking things up when parenthesis appears in the code
    private function replaceSlashes($formData)
    {
        $begin = strpos($formData, "addSlashes<<<");
        if ($begin === false)
        {
            return $formData;
        }
        else
        {
            $end = strpos($formData, ">>>", $begin);
            $toconvert = substr($formData, $begin + 13, $end - ($begin+13));
            $toconvert = addslashes($toconvert);
            return $this->replaceSlashes(substr_replace($formData, $toconvert, $begin, ($end - $begin + 3)));
        }
    }

    private function parseResponse($response, $jsonParsing, $jsonKey, $jsonIsArray, $jsonArrayIndex)
    {
        if ($jsonParsing == '1') {
            $response = json_decode($response, true);
            if ($jsonIsArray == '1') {
                return $response[$jsonArrayIndex][$jsonKey];
            } else {
                return $response[$jsonKey];
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
