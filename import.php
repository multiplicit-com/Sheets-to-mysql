<?php

require __DIR__ . '/vendor/autoload.php';

// User Configurable Settings
$dbConfig = [
    'host' => 'localhost',
    'username' => 'username',
    'password' => 'password',
    'dbname' => 'database_name',
];

$sheetConfig = [
    'Sheet1' => ['mode' => 'replace'],
    'Sheet2' => ['mode' => 'append'],
    'Sheet3' => ['mode' => 'unique', 'unique_column' => 'id'],
];

$spreadsheetId = 'your-spreadsheet-id';
$createTables = true;

class GoogleSheetImporter {
    private $service;
    private $conn;
    private $config;
    private $createTables;
    private $spreadsheetId;
    
    public function __construct($spreadsheetId, $dbConfig, $config, $createTables) {
        $client = new Google_Client();
        $client->setApplicationName('Google Sheets API PHP Quickstart');
        $client->setScopes(Google_Service_Sheets::SPREADSHEETS_READONLY);
        $client->setAuthConfig(__DIR__ . '/credentials.json');
        
        $this->service = new Google_Service_Sheets($client);
        $this->conn = new mysqli($dbConfig['host'], $dbConfig['username'], $dbConfig['password'], $dbConfig['dbname']);
        $this->config = $config;
        $this->spreadsheetId = $spreadsheetId;
        $this->createTables = $createTables;
        
        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }
    }
    
    public function importSheets() {
        foreach ($this->config as $sheetTitle => $sheetConfig) {
            $range = $sheetTitle;
            $response = $this->service->spreadsheets_values->get($this->spreadsheetId, $range);
            $values = $response->getValues();
            
            if (!$values) {
                echo "No data found for $sheetTitle.\n";
                continue;
            }

            $header = array_shift($values);
            $tableName = preg_replace('/\W+/', '', strtolower($sheetTitle));

            if ($this->createTables) {
                $this->createOrUpdateTable($tableName, $header);
            }
            
            switch ($sheetConfig['mode']) {
                case 'replace':
                    $this->conn->query("DELETE FROM `$tableName`");
                    // Fallthrough intended
                case 'append':
                    $this->insertRows($tableName, $header, $values);
                    break;
                case 'unique':
                    $uniqueColumn = $sheetConfig['unique_column'];
                    $this->insertUniqueRows($tableName, $header, $values, $uniqueColumn);
                    break;
            }
        }
        
        $this->conn->close();
    }
    
    private function createOrUpdateTable($tableName, $header) {
        $columns = [];
        foreach ($header as $field) {
            $fieldName = preg_replace('/\W+/', '', strtolower($field));
            $columns[] = "`$fieldName` TEXT";
        }

        $columnsSQL = implode(", ", $columns);
        $sql = "CREATE TABLE IF NOT EXISTS `$tableName` ($columnsSQL)";
        if ($this->conn->query($sql) === FALSE) {
            echo "Error creating table $tableName: " . $this->conn->error;
        }
    }

    private function insertRows($tableName, $header, $rows) {
        foreach ($rows as $row) {
            $values = array_map([$this->conn, 'real_escape_string'], $row);
            $valuesSQL = implode("', '", $values);
            $sql = "INSERT INTO `$tableName` (`" . implode('`, `', $header) . "`) VALUES ('$valuesSQL')";
            if ($this->conn->query($sql) === FALSE) {
                echo "Error inserting row into $tableName: " . $this->conn->error;
            }
        }
    }

    private function insertUniqueRows($tableName, $header, $rows, $uniqueColumn) {
        foreach ($rows as $row) {
            $values = array_map([$this->conn, 'real_escape_string'], $row);
            $valuesSQL = implode("', '", $values);
            $uniqueValue = $this->conn->real_escape_string($row[array_search($uniqueColumn, $header)]);
            $sql = "INSERT INTO `$tableName` (`" . implode('`, `', $header) . "`) VALUES ('$valuesSQL') 
                    ON DUPLICATE KEY UPDATE `$uniqueColumn` = '$uniqueValue'";
            if ($this->conn->query($sql) === FALSE) {
                echo "Error inserting unique row into $tableName: " . $this->conn->error;
            }
        }
    }
}

$importer = new GoogleSheetImporter($spreadsheetId, $dbConfig, $sheetConfig, $createTables);
$importer->importSheets();

?>
