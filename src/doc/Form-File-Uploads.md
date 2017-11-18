Form UI Based File Upload Scenarios:
====================================

* Unknown User
  * Single File
  * Multiple Files (Single Src/Dest DIR)

* Known User
  * Single File
  * Multiple Files (Single Src/Dest DIR)


PS: Multiple files into Multiple directories only makes sense if you have a File Manager APP that does not use a FORM as UI.


UNKNOWN USER
=============

* Source DIR = tmp/unique-id-for-this-upload
* The upload Unique ID gets generated when we generate the form. If in PHP, PHP will save the ID to SESSION, else we keep ID in JS



KNOWN USER
==========

* Source DIR = uploads/usertype/userid/user-dir-for-this-type-of-file



SINGLE FILE UPLOAD IN FORM
===========================

* On LOAD, only get the file we want to refer to. If we don't know the name upfront, then we return nothing until user uploads.
* FORM STATE will then hold the target filename while in process.
* MUST replace existing file if new file is selected or block if existing file not DELETED



MULTI-FILE UPLOAD IN FORM
=========================

* Formfield would propably be handled like any other multi-select field. e.g. Multi-checkbox or Multi-tags
* Only a single selection dir must be used to LOAD files from
* Can add, rename or delete select options
* Can donload all at once or individually.
* FORM STATE keeps track of the selection of no-yet-uploaded selections. The rest can be foundbe reading the source dir contents.
* Validation could prevent submitting a form with not-yet-uploaded files. Or just discard no-yet-uploaded file selections.
