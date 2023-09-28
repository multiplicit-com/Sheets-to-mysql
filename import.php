<?php

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

require_once 'vendor/autoload.php';

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
        $this->client->setAuthConfig('/path/to/credentials.json');
        
        $this->service = new \Google_Service_Sheets($this->client);
        $this->spreadsheetId = $spreadsheetId;
        $this->conn = new mysqli($dbConfig['host'], $dbConfig['username'], $dbConfig['password'], $dbConfig['database']);
        $this->config = $config;
        $this->createTables = $createTables;
        $this->modifyTables = $modifyTables;
    }

    public function importSheets()
    {
        foreach ($this->config as $sheetName => $sheetSettings) {
            $response = $this->service->spreadsheets_values->get($this->spreadsheetId, $sheetName);
            $values = $response->getValues();
            $header = array_shift($values);

            $tableName = preg_replace('/\W+/', '', strtolower($sheetName));

            if ($this->createTables) {
                $this->createOrUpdateTable($tableName, $header);
            }

            if ($this->modifyTables) {
                $this->addNewColumns($tableName, $header);
            }

            $mode = $sheetSettings['mode'] ?? 'insert';
            switch ($mode) {
                case 'insert':
                    $this->insertRows($tableName, $header, $values);
                    break;
                case 'insertUnique':
                    $uniqueColumn = $sheetSettings['uniqueColumn'];
                    $this->insertUniqueRows($tableName, $header, $values, $uniqueColumn);
                    break;
                case 'upsert':
                    $uniqueColumn = $sheetSettings['uniqueColumn'];
                    $this->upsertRows($tableName, $header, $values, $uniqueColumn);
                    break;
                case 'append':
                    $this->appendRows($tableName, $header, $values);
                    break;
            }
        }
    }

    private function createOrUpdateTable($tableName, $header)
    {
        //* Function create or update table based on header
        $columns = implode(', ', array_map(function ($col) {
            return "`$col` TEXT";
        }, $header));
        $sql = "CREATE TABLE IF NOT EXISTS `$tableName` ($columns)";
        $this->conn->query($sql);
    }

    private function addNewColumns($tableName, $header)
    {
        //* Function check and add new columns in the table found in the header
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
        foreach ($values as $row) {
            $rowData = array_combine($header, $row);
            $columns = implode(', ', array_map(function ($col) {
                return "`$col`";
            }, array_keys($rowData)));
            $valuesStr = implode(', ', array_map(function ($val) {
                return "'" . $this->conn->real_escape_string($val) . "'";
            }, $rowData));
            $sql = "INSERT INTO `$tableName` ($columns) VALUES ($valuesStr)";
            $this->conn->query($sql);
        }
    }

    private function insertUniqueRows($tableName, $header, $values, $uniqueColumn)
    {
        //* Function insert unique rows into the table based on the uniqueColumn
        $uniqueColumn = $this->conn->real_escape_string($uniqueColumn);
        foreach ($values as $row) {
            $rowData = array_combine($header, $row);
            $columns = implode(', ', array_map(function ($col) {
                return "`$col`";
            }, array_keys($rowData)));
            $valuesStr = implode(', ', array_map(function ($val) {
                return "'" . $this->conn->real_escape_string($val) . "'";
            }, $rowData));

            $uniqueValue = $rowData[$uniqueColumn];
            $sqlCheck = "SELECT * FROM `$tableName` WHERE `$uniqueColumn` = '$uniqueValue'";
            $result = $this->conn->query($sqlCheck);

            if ($result->num_rows === 0) {
                $sql = "INSERT INTO `$tableName` ($columns) VALUES ($valuesStr)";
                $this->conn->query($sql);
            }
        }
    }

    private function upsertRows($tableName, $header, $values, $uniqueColumn)
    {
        //* Function upsert rows into the table based on the uniqueColumn
        $uniqueColumn = $this->conn->real_escape_string($uniqueColumn);
        foreach ($values as $row) {
            $rowData = array_combine($header, $row);
            $columns = implode(', ', array_map(function ($col) {
                return "`$col`";
            }, array_keys($rowData)));
            $valuesStr = implode(', ', array_map(function ($val) {
                return "'" . $this->conn->real_escape_string($val) . "'";
            }, $rowData));

            $uniqueValue = $rowData[$uniqueColumn];
            $sqlCheck = "SELECT * FROM `$tableName` WHERE `$uniqueColumn` = '$uniqueValue'";
            $result = $this->conn->query($sqlCheck);

            if ($result->num_rows === 0) {
                $sql = "INSERT INTO `$tableName` ($columns) VALUES ($valuesStr)";
            } else {
                $updateStr = implode(', ', array_map(function ($col, $val) {
                    return "`$col` = '" . $this->conn->real_escape_string($val) . "'";
                }, array_keys($rowData), $rowData));
                $sql = "UPDATE `$tableName` SET $updateStr WHERE `$uniqueColumn` = '$uniqueValue'";
            }
            $this->conn->query($sql);
        }
    }

    private function appendRows($tableName, $header, $values)
    {
        //* Function append rows to the existing table
        $this->insertRows($tableName, $header, $values);
    }
}

//* Instantiate the class and call the importSheets method
$importer = new GoogleSheetImporter($spreadsheetId, $dbConfig, $sheetConfig, $createTables, $modifyTables);
$importer->importSheets();

?>
