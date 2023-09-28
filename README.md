# Sheets-to-mysql
A script to load a tab from sheets into a MYSql Database. 

There are four possible import modes, which can be decided on individually for each tab.

* replace everything
* Append to bottom of sheet
* Only import new unique rows, decided by a single nominated column.
* "Upsert" - add new rows and modify existing ones. Useful if there are extra columns that you want to retain.

<hr>
Versions

0.3
* Added "upsert" mode to add new rows and modify existing ones, useful if other scripts have changed columns in the table.
* Added new "modifytables" flag to control whether the script can add new columns

0.2
* Added "append mode" to add new rows to the bottom of the table
* Added new "createtables" flag to cintrol whether the script can create new tables

0.11
* added ability to only import new unique rows, with a nomonated primary key

0.1
* Initial release, load values from a Google sheet into a database table
