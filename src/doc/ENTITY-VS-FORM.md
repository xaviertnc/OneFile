ENTITY MODEL vs. FORM MODEL
===========================
@author: C. Moller <xavier.tnc@gmail.com> ,
@date: 5 Jan 2017


ENTITY MODEL
============
Focuses on data Mapping, Import and Export from / to
external datasource(s) into a single local structure
that makes programming and reasoning about the data easier.

1. Entity Model RESPOSIBILITIES:
---------------------------------
* Manage LOAD / FETCH field values from datastore
* Manage SAVE / UPDATE field values to datastore
* Manage DATA MAPPING if the model contains fields from multiple datasources.


FORM MODEL
==========
Focuses on user-data-input management and NOT on data
mapping, import or export from / to external datasource(s).

2.1 Form Model RESPOSIBILITIES:
-------------------------------
* Manage INPUT META INFO like INPUT TYPE and other UI related information.
* Manage INPUT STATE accross submits until ready to persist.
* Manage INPUT VALIDATION
* Manage INPUT ERRORS
* Manage INPUT PARSING
* Manage INPUT DIRTY checking

2.2 Form Model STATE Handling:
------------------------------
* Form STATE should ONLY be SAVED on FAILED POST Requests. (e.g. On Validation Fail)
* Form STATE allows us to RECOVER the USER's INPUTS after redirecting back with errors.
* Form STATE must ALWAYS be FLASHED and never indefinitely SAVED to SESSION.
* GET requests should always prefer FLASHED STATE above INITIALIZING from an external source. (e.g. DB)
  If NO FLASHED STATE can be found, INITIALIZE the FORM from Ext-Source-ID or alternatively as a NEW / EMPTY form.
* To implement a MULIT-PAGE PERSISTED-STATE FORM WIZZARD, use the MultiState container / management / helper class.

2.3 Multi-Page / Multi-Form Wizzards:
-------------------------------------
* With MultiState, multiple form states are combined into a **SINGLE MULTI-STATE ARRAY** with entries for each form.
* MultiState persists between form submits and page changes until the multi form / page process is completed.
* MultiState should RE-FLASH its contents after every POST and / or load of a MULTI-PAGE process.
* On POST, all active FORMS should save their states to the MULTI-STATE container to ensure inputs are available to the next step.
* MultiState can collect states over **any number of consequtive pages**. The SAVED states do NOT have to be the same each time.
* At the end of a WIZZARD, you should have access to the **COMBINED STATE** of ALL the forms completed!
* A MultiState manager should have ZERO knowledge and link to the entities for which it is keeping state. e.g. FormModels
* Multistate should only care about holding a collection datasets/states under one umbrella and ensure that these states are transfered to the next step/page.
