<?php

//* Import Sheets API using composer.
//* Composer is included as standard by most web hosts - your server probably supports it.

// composer require google/apiclient
require 'vendor/autoload.php';

class GoogleSheetToMySQL
{
    private $service;
    private $conn;
    private $spreadsheetId;
    private $createTables;
    
    public function __construct($service, $conn, $spreadsheetId, $createTables)
    {
        $this->service = $service;
        $this->conn = $conn;
        $this->spreadsheetId = $spreadsheetId;
        $this->createTables = $createTables;
    }
    
    public function importData($config)
    {
        foreach ($config as $sheetTitle => $sheetConfig) {
            $range = $sheetTitle;
            $response = $this->service->spreadsheets_values->get($this->spreadsheetId, $range);
            $values = $response->getValues();

            if (empty($values)) {
                print "No data found in $sheetTitle.\n";
                continue;
            }

            $header = array_shift($values);
            $tableName = preg_replace('/\s+/', '_', strtolower($sheetTitle));

            $tableExists = $this->conn->query("SHOW TABLES LIKE '$tableName'")->num_rows > 0;

            if ($this->createTables) {
                $this->handleCreateTables($tableExists, $tableName, $header, $sheetConfig);
            } else {
                $this->handleImportWithoutCreateTables($tableExists, $tableName, $header);
            }

            foreach ($values as $row) {
                $this->insertRow($tableName, $header, $row, $sheetConfig);
            }
        }
    }

    private function handleCreateTables($tableExists, $tableName, $header, $sheetConfig)
    {
        if (!$tableExists) {
            $this->createTable($tableName, $header);
        } else {
            $this->addMissingColumns($tableName, $header);
            if ($sheetConfig['mode'] === 'replace') {
                $this->conn->query("TRUNCATE TABLE $tableName");
            }
        }
    }

    private function handleImportWithoutCreateTables($tableExists, $tableName, $header)
    {
        if ($tableExists) {
            $existingColumnsResult = $this->conn->query("SHOW COLUMNS FROM $tableName");
            if ($existingColumnsResult->num_rows !== count($header)) {
                echo "Warning: The column count in table $tableName does not match.\n";
            }
        } else {
            echo "Warning: Table $tableName does not exist in the database.\n";
        }
    }

    private function createTable($tableName, $header)
    {
        $createTableSQL = "CREATE TABLE $tableName (";
        foreach ($header as $columnName) {
            $columnName = preg_replace('/\s+/', '_', strtolower($columnName));
            $createTableSQL .= "$columnName TEXT,";
        }
        $createTableSQL = rtrim($createTableSQL, ',') . ")";

        if (!$this->conn->query($createTableSQL)) {
            die("Table creation failed: " . $this->conn->error);
        }
    }

    private function addMissingColumns($tableName, $header)
    {
        $existingColumnsResult = $this->conn->query("SHOW COLUMNS FROM $tableName");
        $existingColumns = [];
        while ($column = $existingColumnsResult->fetch_assoc()) {
            $existingColumns[] = $column['Field'];
        }

        foreach ($header as $columnName) {
            $formattedColumnName = preg_replace('/\s+/', '_', strtolower($columnName));
            if (!in_array($formattedColumnName, $existingColumns)) {
                $addColumnSQL = "ALTER TABLE $tableName ADD COLUMN $formattedColumnName TEXT";
                if (!$this->conn->query($addColumnSQL)) {
                    die("Failed to add column $formattedColumnName to table $tableName: " . $this->conn->error);
                }
            }
        }
    }

    private function insertRow($tableName, $header, $row, $sheetConfig)
    {
        $insertRow = true;
        if ($sheetConfig['mode'] === 'unique') {
            $uniqueColumn = preg_replace('/\s+/', '_', strtolower($sheetConfig['unique_column']));
            $uniqueIndex = array_search($uniqueColumn, array_map('strtolower', $header));
            if ($uniqueIndex !== false) {
                $uniqueValue = mysqli_real_escape_string($this->conn, $row[$uniqueIndex]);
                $exists = $this->conn->query("SELECT * FROM $tableName WHERE $uniqueColumn = '$uniqueValue'")->num_rows > 0;
                $insertRow = !$exists;
            }
        }

        if ($insertRow) {
            $insertSQL = "INSERT INTO $tableName (";
            foreach ($header as $columnName) {
                $columnName = preg_replace('/\s+/', '_', strtolower($columnName));
                $insertSQL .= "$columnName,";
            }
            $insertSQL = rtrim($insertSQL, ',') . ") VALUES (";
            foreach ($row as $value) {
                $insertSQL .= "'" . mysqli_real_escape_string($this->conn, $value) . "',";
            }
            $insertSQL = rtrim($insertSQL, ',') . ")";

            if (!$this->conn->query($insertSQL)) {
                echo "Error: " . $insertSQL . "\n" . $this->conn->error;
            }
        }
    }
}

// Google Sheets API setup
$googleAccountKeyFilePath = __DIR__ . '/credentials.json';
$client = new Google_Client();
$client->setApplicationName('Google Sheets API PHP Quickstart');
$client->setScopes(Google_Service_Sheets::SPREADSHEETS_READONLY);
$client->setAuthConfig($googleAccountKeyFilePath);

$service = new Google_Service_Sheets($client);

// MySQL database setup
$dbHost = 'localhost';
$dbUsername = 'username';
$dbPassword = 'password';
$dbName = 'database_name';
$conn = new mysqli($dbHost, $dbUsername, $dbPassword, $dbName);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$createTables = false;
$spreadsheetId = 'your-spreadsheet-id';

$importer = new GoogleSheetToMySQL($service, $conn, $spreadsheetId, $createTables);

// Configuration array for sheets
$config = [
    'Sheet1' => ['mode' => 'replace'],
    // Add more sheets as needed
];

$importer->importData($config);

$conn->close();
