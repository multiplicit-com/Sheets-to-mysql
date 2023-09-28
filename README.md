# Sheets-to-mysql
A PHP script to load a tab from Google Sheets into a MYSql Database. 

There are four possible import modes, which can be decided on individually for each tab.

* Replace everything
* Append to bottom of sheet
* Only import new unique rows, decided by a single nominated column.
* "Upsert" - add new rows and modify existing ones. Useful if there are extra columns that you want to retain.

<hr>
<strong>Please note</strong>
This script is being actively developed (Oct 2023) - it may not be stable yet.

<hr>
<strong>Version History</strong>

0.3
* Added "upsert" mode to add new rows and modify existing ones, useful if other scripts have changed columns in the table.
* Added new "modifytables" flag to control whether the script can add new columns

0.2
* Added "append mode" to add new rows to the bottom of the table
* Added new "createtables" flag to cintrol whether the script can create new tables
* Updated to use batch inserts

0.11
* added ability to only import new unique rows, with a nomonated primary key

0.1
* Initial release, load values from a Google sheet into a database table

<hr>
<strong>PHP and Dependencies</strong>
Remember to ensure that the mysqli extension is enabled, as the script uses mysqli for database interactions. 

You will need to install the Google API Client Library for PHP via Composer, which requires PHP 5.4 or higher.

This script should be compatible with PHP 5.4 and above. 

The script uses short array syntax, which was introduced in PHP 5.4. It does not use any functions which were deprecated in PHP 7 or 8. 
