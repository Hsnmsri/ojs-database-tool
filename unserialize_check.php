<?php
require_once("tools/Cli.php");

use App\Cli;

Cli::clear();
unserialize_data($argv);

function unserialize_data($arguments)
{
    // check arguments
    if (count($arguments) < 4) {
        Cli::print_error("Command invalid!");
        print("\nfilename.php [table name] [field name] [unserialize field count]\n");
        print("\n");
        die();
    }

    // database connection
    print("\n Check database connection...\n");
    sleep(1);
    require_once("config.php");
    $db_connection;
    try {
        $db_connection = mysqli_connect(APP_CONFIG["database"]['host'], APP_CONFIG["database"]['username'], APP_CONFIG["database"]['password'], APP_CONFIG["database"]['name']);
    } catch (Exception $error) {
        Cli::print_error("Database Connection Failed!");
        die();
    }
    Cli::print_success("Connected.");
    sleep(1);

    // check table
    print("\n Check table...\n");
    sleep(1);
    $result = mysqli_fetch_all(mysqli_query($db_connection, "SHOW TABLES LIKE '" . $arguments[1] . "';"));
    try {
        if (count($result) == 0) {
            Cli::print_error("Table not found!");
            die();
        }
    } catch (Exception $error) {
        Cli::print_error("Table found error!");
        die();
    }
    Cli::print_success("Table founded.");
    sleep(1);

    //check table field
    print("\n Check table field...\n");
    sleep(1);
    $result = mysqli_fetch_all(mysqli_query($db_connection, "DESCRIBE `" . $arguments[1] . "`;"));
    $is_field_exist = false;
    foreach ($result as $key => $value) {
        if (in_array($arguments[2], $value)) {
            $is_field_exist = true;
            break;
        }
    }
    if (!$is_field_exist) {
        Cli::print_error("Field not exist on `" . $arguments[1] . "` table.");
        die();
    }
    Cli::print_success("Field exists.");
    sleep(1);

    // Proccssing
    print("\nStart unserializing data...\n");
    print("-----------------------------\n");

    global $error_count;
    $error_count = 0;
    global $now_data;
    $data_count;
    $data_select_count = 10;
    $offset = 0;

    $data_count = mysqli_fetch_row(mysqli_query($db_connection, "SELECT COUNT(*) as count FROM `" . $arguments[1] . "`;"))[0];

    $remaining_data_count = $data_count % $data_select_count;
    $last_number_offset = $data_count - $remaining_data_count;

    error_reporting(E_ALL & ~E_NOTICE);

    // Custom error handler function
    function customErrorHandler($errno, $errstr, $errfile, $errline)
    {
        global $error_count;
        global $now_data;
        if ($errno === E_NOTICE) {
            $error_count++;
            Cli::print_error("#" . $error_count . " | " . $now_data[2] . " => " . $now_data[3] . "\n");
            print("-----------\n");
            return true; // Suppress the notice
        }
        return false; // Continue with the default error handling
    }

    // Set the custom error handler
    set_error_handler('customErrorHandler');

    // Process
    while ($offset <= $data_count) {
        if ($offset == $last_number_offset) {
            $data_select_count = $remaining_data_count;
        }
        $query = "SELECT * FROM `" . $arguments[1] . "` LIMIT " . $data_select_count . " OFFSET " . $offset;
        $data = (mysqli_fetch_all(mysqli_query($db_connection, $query)));
        foreach ($data as $key => $value) {
            $now_data = $value;
            unserialize($value[$arguments[3]]);
        }
        $offset += $data_select_count;
    }

    // Restore the default error handler
    restore_error_handler();

    print("\n-----------------------------\n");
    Cli::print_success("Finish processing.");
    print("error count: " . $error_count . "\n");
    die();
}
