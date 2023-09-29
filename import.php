<?php

//* Composer dependency for Google Sheets API
require_once 'vendor/autoload.php';

//* User Configurable Settings
$spreadsheetId = 'your_spreadsheet_id';

//* Create tables which don't exist yet?
$createTables = true;

//* Insert columns which don't exist in the table yet?
$modifyTables = true;

$dbConfig = [
    'host' => 'localhost',
    'username' => 'root',
    'password' => '',
    'database' => 'database_name',
];

$sheetConfig = [
    'Sheet1' => ['mode' => 'replace'],
    'Sheet2' => ['mode' => 'append'],
    'Sheet3' => ['mode' => 'unique', 'unique_column' => 'id'],
    'Sheet4' => ['mode' => 'upsert', 'unique_column' => 'id'],
];

class GoogleSheetImporter
{
    private $client;
    private $service;
    private $spreadsheetId;
    private $conn;
    private $config;
    private $createTables;
    private $modifyTables;

        public function __construct($spreadsheetId, $dbConfig, $config, $createTables, $modifyTables)
    {
        $this->client = new \Google_Client();
        $this->client->setApplicationName('Google Sheets to MySQL');
        $this->client->setScopes([\Google_Service_Sheets::SPREADSHEETS_READONLY]);
        $this->client->setAccessType('offline');
        $this->client->setAuthConfig('credentials.json');

        $this->service = new \Google_Service_Sheets($this->client);
        $this->spreadsheetId = $spreadsheetId;
        $this->conn = new mysqli($dbConfig['host'], $dbConfig['username'], $dbConfig['password'], $dbConfig['database']);
        $this->config = $config;
        $this->createTables = $createTables;
        $this->modifyTables = $modifyTables;
    }

public function importSheets()
{
    foreach ($this->config as $sheetName => $sheetSettings) 
    {
        $progressMessage="Processing Sheet: $sheetName";
        
        $progressMessage="Method: $sheetName";
        
        $tableName = preg_replace('/\W+/', '', strtolower($sheetName));
        
        // Print table summary before import
        $progressMessage.=$this->printTableSummary($tableName, 'before');
        
        $response = $this->service->spreadsheets_values->get($this->spreadsheetId, $sheetName);
        $values = $response->getValues();
        $header = array_shift($values);

        if ($this->createTables) {
            $this->createOrUpdateTable($tableName, $header);
        }

        if ($this->modifyTables) {
            $this->addNewColumns($tableName, $header);
        }

        $mode = $sheetSettings['mode'] ?? 'insert';
        switch ($mode) {
            case 'replace':
                $this->conn->query("TRUNCATE TABLE `$tableName`");
                // Intentional fall-through to 'insert'
            case 'insert':
                $this->insertRows($tableName, $header, $values);
                break;
            case 'unique':
                $uniqueColumn = $sheetSettings['unique_column'];
                $this->insertUniqueRows($tableName, $header, $values, $uniqueColumn);
                break;
            case 'upsert':
                $uniqueColumn = $sheetSettings['unique_column'];
                $this->upsertRows($tableName, $header, $values, $uniqueColumn);
                break;
            case 'append':
                $this->appendRows($tableName, $header, $values);
                break;
        }
        
        // Print table summary after import
        $progressMessage.=$this->printTableSummary($tableName, 'after');
        
        $progressMessage.= "\n$sheetName has been processed and imported into $tableName";
    }
    
     echo '<p>'.nl2br($progressMessage).'</p>';
     
     //"Import Completed.\n";
}

private function printTableSummary($tableName, $time)
{
    $result = $this->conn->query("SELECT COUNT(*) as count FROM `$tableName`");
    $row = $result->fetch_assoc();
    $count = $row['count'];
    
    if ($time === 'before') {
        return("\nBefore import, table $tableName had $count rows.");
    } else {
        return("\nAfter import, table $tableName has $count rows.");
    }
    
}

    private function createOrUpdateTable($tableName, $header)
    {
        $columns = implode(', ', array_map(function ($col) {
            return "`$col` TEXT";
        }, $header));
        $sql = "CREATE TABLE IF NOT EXISTS `$tableName` ($columns)";
        $this->conn->query($sql);
    }

    private function addNewColumns($tableName, $header)
    {
        $existingColumns = $this->getColumns($tableName);
        $newColumns = array_diff($header, $existingColumns);
        foreach ($newColumns as $col) {
            $sql = "ALTER TABLE `$tableName` ADD COLUMN `$col` TEXT";
            $this->conn->query($sql);
        }
    }

    private function getColumns($tableName)
    {
        $result = $this->conn->query("DESCRIBE `$tableName`");
        $columns = [];
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
        return $columns;
    }

    private function insertRows($tableName, $header, $values)
    {
        $rowsSql = [];
        foreach ($values as $row) {
            $rowData = array_combine($header, $row);
            $valuesStr = implode(', ', array_map(function ($val) {
                return "'" . $this->conn->real_escape_string($val) . "'";
            }, $rowData));
            $rowsSql[] = "($valuesStr)";
        }
        if (!empty($rowsSql)) {
            $columns = implode(', ', array_map(function ($col) {
                return "`$col`";
            }, $header));
            $sql = "INSERT INTO `$tableName` ($columns) VALUES " . implode(', ', $rowsSql);
            $this->conn->query($sql);
        }
    }

    private function insertUniqueRows($tableName, $header, $values, $uniqueColumn)
    {
        $rowsSql = [];
        $existingValues = $this->getExistingValues($tableName, $uniqueColumn);
        foreach ($values as $row) {
            $rowData = array_combine($header, $row);
            if (!in_array($rowData[$uniqueColumn], $existingValues)) {
                $valuesStr = implode(', ', array_map(function ($val) {
                    return "'" . $this->conn->real_escape_string($val) . "'";
                }, $rowData));
                $rowsSql[] = "($valuesStr)";
            }
        }
        if (!empty($rowsSql)) {
            $columns = implode(', ', array_map(function ($col) {
                return "`$col`";
            }, $header));
            $sql = "INSERT INTO `$tableName` ($columns) VALUES " . implode(', ', $rowsSql);
            $this->conn->query($sql);
        }
    }

    private function upsertRows($tableName, $header, $values, $uniqueColumn)
    {
        $rowsSql = [];
        foreach ($values as $row) {
            $rowData = array_combine($header, $row);
            $valuesStr = implode(', ', array_map(function ($val) {
                return "'" . $this->conn->real_escape_string($val) . "'";
            }, $rowData));
            $rowsSql[] = "($valuesStr)";
            $updateStr = implode(', ', array_map(function ($col, $val) {
                return "`$col` = '" . $this->conn->real_escape_string($val) . "'";
            }, array_keys($rowData), $rowData));
            $updateSql[] = $updateStr;
        }
        if (!empty($rowsSql)) {
            $columns = implode(', ', array_map(function ($col) {
                return "`$col`";
            }, $header));
            $sql = "INSERT INTO `$tableName` ($columns) VALUES " . implode(', ', $rowsSql) . " ON DUPLICATE KEY UPDATE " . implode(', ', $updateSql);
            $this->conn->query($sql);
        }
    }

    private function appendRows($tableName, $header, $values)
    {
        $this->insertRows($tableName, $header, $values);
    }

    private function getExistingValues($tableName, $columnName)
    {
        $result = $this->conn->query("SELECT `$columnName` FROM `$tableName`");
        $values = [];
        while ($row = $result->fetch_assoc()) {
            $values[] = $row[$columnName];
        }
        return $values;
    }
}

 $importer = new GoogleSheetImporter($spreadsheetId, $dbConfig, $sheetConfig, $createTables, $modifyTables);
 $importer->importSheets();

?>
