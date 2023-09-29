# Sheets-to-mysql
A PHP script to load a tab from Google Sheets into a MYSql Database. 
<hr>
<strong>Settings</strong>

Two settings control how the script interacts with the database. These settings give you flexibility and control over how the script behaves, allowing you to choose between preserving the existing database structure and adapting it to accommodate new data structures from the sheets. 

Be mindful that altering a table's structure in a production database can have unintended consequences - these settings allow you to "lock" the table structure when you're sure it won't change.

<blockquote>

<strong>$createTables:</strong>

When set to true, the script will create a new table in the database for each sheet if it doesn’t already exist.

If set to false, the script will not create any new tables, and it will only attempt to import data into existing tables. If a corresponding table for a sheet does not exist, the data from that sheet will not be imported.


<strong>$modifyTables:</strong>

When set to true, the script will check the existing structure of the tables in the database against the columns in the sheets. If a sheet contains columns that do not exist in the corresponding table, the script will modify the table structure by adding the new columns.

If set to false, the script will not make any modifications to the table structures. Only the data in the columns that already exist in the tables will be imported, and any additional columns present in the sheets will be ignored.

</blockquote>

<hr>
<strong>Import modes</strong>

There are four possible import modes, which can be decided on individually for each tab you want to import from Sheets.

<blockquote>

**Append Mode:**

Description: Inserts all the rows from the Google Sheet into the MySQL table.
<br>Behavior: Every row from the Sheet is added to the table as a new row. There is no check for duplicate or existing rows.
Use Case: Useful when you want to add all data from the sheet to the table regardless of whether some rows already exist in the table.

**Replace Mode:**

Description: Replaces the entire MySQL table with the data from the Google Sheet.
<br>Behavior: The table is truncated (all existing data is deleted), and then all rows from the Sheet are inserted.
Use Case: Useful when you want the table to exactly match the current state of the Google Sheet, removing any old data.

**Upsert Mode:**

Description: Inserts new rows and updates existing ones based on a unique column.
<br>Behavior: For each row in the Sheet, the script checks whether a row with the same unique column value exists in the table. If it does, the row is updated; if not, a new row is inserted.
Use Case: Useful when you have a mixture of new and updated rows in the Google Sheet.

**Unique Mode:**

Description: Inserts only the unique rows based on a unique column.
<br>Behavior: For each row in the Sheet, the script checks whether a row with the same unique column value exists in the table. If it doesn’t exist, a new row is inserted; if it does, the row is skipped.
Use Case: Useful when you want to add only new data from the sheet, preventing any duplicate rows based on the unique column.

</blockquote>

<hr>

**Please note**

This script is being actively developed (Oct 2023) - it may not be stable yet.

There are some validation / integrity checks, but it is likely to result in a 500 error if it is executed without all the appropraite credentials and settings.

<hr>
<strong>Version History</strong>

0.31
* fix to bring replace mode into line with expected behaviour
* Added basic confirmation message

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
