# Sheets-to-mysql
A PHP script to load a tab from Google Sheets into a MYSql Database. 

<hr>
<strong>Import modes</strong>

There are five possible import modes, which can be decided on individually for each tab.

**Insert Mode:**

Description: Inserts all the rows from the Google Sheet into the MySQL table.
<br>Behavior: Every row from the Sheet is added to the table as a new row. There's no check for duplicate or existing rows.
Use Case: Useful when you want to add all data from the sheet to the table regardless of whether some rows already exist in the table.

**Replace Mode:**

Description: Replaces the entire MySQL table with the data from the Google Sheet.
<br>Behavior: The table is truncated (all existing data is deleted), and then all rows from the Sheet are inserted.
Use Case: Useful when you want the table to exactly match the current state of the Google Sheet, removing any old data.

**Upsert Mode:**

Description: Inserts new rows and updates existing ones based on a unique column.
<br>Behavior: For each row in the Sheet, the script checks whether a row with the same unique column value exists in the table. If it does, the row is updated; if not, a new row is inserted.
Use Case: Useful when you have a mixture of new and updated rows in the Google Sheet.

**Append Mode:**

Description: Appends all rows from the Google Sheet to the MySQL table.
<br>Behavior: Similar to Insert Mode, but can be differentiated by its use case.
Use Case: Useful when you want to keep adding new data to the table without affecting the existing rows, even if the sheet contains previously inserted rows.

**Unique Mode:**

Description: Inserts only the unique rows based on a unique column.
<br>Behavior: For each row in the Sheet, the script checks whether a row with the same unique column value exists in the table. If it doesn’t exist, a new row is inserted; if it does, the row is skipped.
Use Case: Useful when you want to add only new data from the sheet, preventing any duplicate rows based on the unique column.

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
