# Sheets-to-mysql
A script to load a tab from sheets into a MYSql Database. 

There are four possible import modes, which can be decided on individually for each tab.

* replace everything
* Append to bottom of sheet
* Only import new unique rows, decided by a single nominated column.
* "Upsert" - add new rows and modify existing ones. Useful if there are extra columns that you want to retain.
