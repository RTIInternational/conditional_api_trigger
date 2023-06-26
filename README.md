# Conditional API Trigger
A REDCap module that executes an API call (to any API) when a certain condition has been met upon saving a record.

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