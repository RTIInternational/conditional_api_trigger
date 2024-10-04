# Conditional API Trigger
A REDCap module that executes an API call (to any API) when a certain condition has been met upon saving a record.

## Prerequisites
- REDCap >= 8.0.3

## Manual Installation
- Copy the provided module into the modules directory of your REDCap installation.  
- Go to **Control Center > Manage External Modules** and enable Conditional API Trigger.

## How to use
You can define multiple triggers in **Project Settings**. For each trigger, define:  
- **Instrument** (required)- This is the instrument that the trigger will fire upon saving.  
- **Condition** (required)- This is the condition (in addition to the above instrument being saved) that will cause the trigger to fire. This field should evaluate to true/false.  
- **API URL** (required)- This is full API URL of the API request. You can pipe variables and smart tags in here.
- **API Method** (requierd)- The HTTP method to use in your API request. (GET or POST)  
- **API Data** - This is a string to use in the POST data for your request. This should be formatted however the API expects it. You can pipe variables and smart tags in here.  
- **API Header** - This is a string to use in the header for your request. This should be formatted however the API expects it. You can pipe variables and smart tags in here.  
- **Separate Post Data** - Determine whether or not the system should take the API data and split it out in the system.  
- **Data Item Separator** - The character used to separate out fields in the API Data. By default, ; is used.  
- **Data Value Separator** - The character used to separate field name and value in the API Data. By default, = is used.  
- **Run Once Field** - Field that gets filled in with a 1 when the API trigger is hit. You can check this in the condition if you don't want the API to trigger more than once.  
- **Do simple JSON parsing?** - If the data output is JSON, this will allow you to pull out a single field.  
- **JSON Parsing Key** - This is the key to look for in the JSON if you decide to use JSON parsing. For instance "name": "Bobby", you would enter `name` and get back `Bobby`.  
- **JSON Output is an array?** - If the incoming JSON is in an array and you want the value from a certain array index, check off this.  
- **JSON Array Index (0 based)** - If you check off the above, put the index you want here. Use 0 for the first record. This is useful if you are pulling REDCap data, it will always come as an array.  
- **Replace {{ }} with [ ] after piping?** - We noticed that if you're using the REDCap API it is difficult to use filterLogic. This is due to the fact that the syntax denotes fields the same way you typically pipe in variables, which I do. Therefore `[record_id] = [record_id]` will not work. If you have that issue, check this off and format it as `{{record_id}} = [record_id]` and it will fix this issue.  

## Example (Locking)
As this module was originally designed to use the Locking API external module, I will use that below:  
**Instrument** `locking`  
**Condition** `[lock] = 1`  
**API URL** `https://redcapedc.rti.org/dev_dave/api/?NOAUTH&type=module&prefix=locking_api&page=lock`  
**API Method** `POST`  
**API Data** `token=ABC123FAKETOKENFAKETOKEN&returnFormat=json&record=[record_id]&event=[event-name]&instrument=consent`  
**Separate Post Data** `Unchecked`  

The above example will call the Locking API to lock the consent form whenever the `locking` instrument is saved and the lock varialbe is set to Yes.

## Example (REDCap API)  
This example imports a value into another REDCap project.  
**Instrument** `consent`  
**Condition** `[consent_yn] = '1'`  
**API URL** `https://redcapedc.rti.org/dev_dave/api/`  
**API Method** `POST`  
**API Data** `token=ABC123FAKETOKENFAKETOKEN;content=record;action=import;format=json;type=flat;overwriteBehavior=normal;forceAutoNumber=false;data=[{"record_id":"[record_id]","redcap_event_name":"event_1_arm_1","otherconsentvariable":"[consent_yn]"}];returnContent=ids;returnFormat=json`  
**Separate Post Data** `Checked`  
**Data Item Separator** `;`  
**Data Value Separator** `=`  