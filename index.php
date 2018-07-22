<?php

define( "DB_DSN", "your_dsn" );
define( "DB_USERNAME", "your_database_username" );
define( "DB_PASSWORD", "your_database_password" );

require_once "DB.php";

// TESTING CRUD
try {
    
    $db = DB::getInstance();
    
    # 1. READING
    $query = "SELECT * FROM groups";
    var_dump(  $db->query($query)->getResults() );

    # 2. INSERT
    /*
    $db->setTableName( "groups" );
    $field_values = array( "group_name" => "guests" );
    if ( !($db->insert($field_values)->getError() === false) ) {
        throw new Exception( "There was a problem inserting data into the \"group\" table." );
    } else {
        $last_insert_id = $db->query("SELECT LAST_INSERT_ID()")->first(); // Only in MySQL
        var_dump( $last_insert_id );
        var_dump("Insert successfully!");
    }
    */
    
    # 3. UPDATE
    /* 
    $db->setTableName( "groups" );
    $db->setWhere( "WHERE group_id = :group_id" );
    $fields = array( "group_name" );
    $values = array( "group_name" => "Guests", "group_id" => 3 );
    if ( !($db->update($fields, $values)->getError() === false) ) {
        throw new Exception( "There was a problem updating data in the \"group\" table." );
    } else {
        var_dump("Update successfully!");
    }
    */

    # 4. DELETE
    /*
    $db->setTableName( "groups" );
    $db->setWhere( "WHERE group_id = :group_id" );
    $values = array( "group_id" => 3 );
    if ( !($db->delete($values)->getError() === false) ) {
        throw new Exception( "There was a problem deleting data in the \"group\" table." );
    } else {
        var_dump("Delete successfully!");
    }
    */
} catch ( Exception $e ) {
    var_dump( $e->getMessage() );
}
