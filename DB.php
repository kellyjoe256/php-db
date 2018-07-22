<?php

class DB {
    /**
     * Stores the database instance
     * @var mixed
     */
    private static $_instance = null;
    /**
     * Stores the query string to be run
     * @var string
     */
    private $_query;
    /**
     * Results returned by query
     * @var mixed
     */
    private $_results;
    /**
     * Stores error indicator
     * @var boolean
     */
    private $_error;
    /**
     * How many rows where returned
     * @var int
     */
    private $_count;
    /**
     * Stores where query, if any
     * @var string
     */
    private $_where = "";
    /**
     * Stores value for the table being queried
     * @var string
     */
    private $_tableName = null;
    /**
     * Database connection
     * @var PDO
     */
    private $_connection;

    /**
     * Private Constructor method
     */
    private function __construct() {
        $this->_connection = new PDO(
            DB_DSN,
            DB_USERNAME,
            DB_PASSWORD
        );
        $this->_connection->
                setAttribute( PDO::ATTR_PERSISTENT, true );
        $this->_connection->
                setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
    }

    /**
     * Returns Database instance
     * @return DB
     */
    public static function getInstance() {
        if ( !(isset(self::$_instance)) ) {
            self::$_instance = new DB();
        }

        return self::$_instance;
    }

    /**
     * Performs all select queries especially joins or unions
     * @param string $query
     * @param array $values Optional
     * @param int $fetchMode Optional
     * @return $this
     */
    public function query( $query, array $values = null, $fetchMode = null ) {
        $this->_error = false;
        $this->_query = $query;
        $stmt = $this->prepareQuery();

        if ( $values ) {
            $stmt = $this->dynamicBindResult( $stmt, $values );
        }

        if ( $stmt->execute() ) {
            $pdoFetchMode = ( $fetchMode ) ? $fetchMode : PDO::FETCH_ASSOC;
            $this->_results = $this->getResultSet( $stmt, $pdoFetchMode );
            $this->_count = $stmt->rowCount();
        } else {
            $this->_error = true;
        }

        return $this;
    }

    /**
     * Performs a select on only one table, fetching either all
     * or particular fields/columns
     * @param array $fields
     * @param array $values
     * @param int $fetchMode Optional
     * @return $this
     */
    public function get( array $fields, array $values, $fetchMode = null ) {
        $query = sprintf(
            "SELECT %s FROM %s %s",
            implode(", ", $fields),
            $this->_tableName,
            $this->_where
        );

        $this->_query = $query;
        $stmt = $this->prepareQuery();
        $stmt = $this->dynamicBindResult( $stmt, $values );

        if ( $stmt->execute() ) {
            $pdoFetchMode = ( $fetchMode ) ? $fetchMode : PDO::FETCH_ASSOC;
            $this->_results = $this->getResultSet( $stmt, $pdoFetchMode );
            $this->_count = $stmt->rowCount();
        } else {
            $this->_error = true;
        }

        return $this;
    }

    /**
     * Returns boolean indicating whether an error occured
     * @return boolean
     */
    public function getError() {
        return $this->_error;
    }

    /**
     * Returns a results from query performed or
     * false if none
     * @return mixed
     */
    public function getResults() {
        if ( $this->getCount() ) {
            return $this->_results;
        }
        return false;
    }

    /**
     * Returns number of rows returned by query
     * @return int
     */
    public function getCount() {
        return $this->_count;
    }

    /**
     * Returns the first row of the results
     * returned from the query of false if no
     * rows were returned
     * @return mixed
     */
    public function first() {
        if ( $this->getCount() ) {
            $results = $this->getResults();
            return $results[0];
        }
        return false;
    }

    /**
     * Inserts a record into database table
     * @param array $values
     * @param string $storedProc Optional name of stored procedure
     * @return $this
     */
    public function insert( array $values, $storedProc = null ) {
        $fields = array_keys( $values );
        $placeholders = array();
        $insert_values = array();

        foreach ( $fields as $field ) {
            $placeholders[] = ":" . $field;
            $insert_values[":" . $field] = $values[$field];
        }

        if ( !$storedProc ) {
            $query = sprintf(
                "INSERT INTO %s ( %s ) VALUES ( %s )",
                $this->_tableName,
                implode( ", ", $fields ),
                implode( ", ", $placeholders )
            );
        } else {
            $storedProc = filter_var( $storedProc, FILTER_SANITIZE_STRING );
            $query = sprintf(
                "CALL %s ( %s )",
                $storedProc,
                implode( ", ", $placeholders )
            );
        }

        $this->_query = $query;
        $stmt = $this->prepareQuery();
        $stmt = $this->dynamicBindResult( $stmt, $insert_values );

        $this->_error = ( $stmt->execute() ) ? false : true;

        return $this;
    }

    /**
     * Updates record or records in the database table
     * @param array $fields
     * @param array $values
     * @param string $storedProc Optional name of stored procedure
     * @return $this
     */
    public function update( array $fields, array $values, $storedProc = null ) {
        $update_fields = array();
        $placeholders = array();

        foreach ( $fields as $field ) {
            if ( $storedProc ) {
                $placeholders[] = ":" . $field;
            }

            $update_fields[] = "$field = :$field";
        }

        if ( !$storedProc ) {
            $query = sprintf(
                "UPDATE %s SET %s %s",
                $this->_tableName,
                implode( ", ", $update_fields ),
                $this->_where
            );
        } else {
            $storedProc = filter_var( $storedProc, FILTER_SANITIZE_STRING );
            $query = sprintf(
                "CALL %s ( %s )",
                $storedProc,
                implode( ", ", $placeholders )
            );
        }

        $this->_query = $query;
        $stmt = $this->prepareQuery();
        $stmt = $this->dynamicBindResult( $stmt, $values );

        $this->_error = ( $stmt->execute() ) ? false : true;

        return $this;
    }

    /**
     * Delete a record or records from a database table
     * @param array $values
     * @param string $storedProc Optional name of stored procedure
     * @return $this
     */
    public function delete( array $values, $storedProc = null ) {
        if ( !$storedProc ) {
            $query = sprintf(
                "DELETE FROM %s %s",
                $this->_tableName,
                $this->_where
            );
        } else {
            $storedProc = filter_var( $storedProc, FILTER_SANITIZE_STRING );

            $placeholders = array();

            foreach ( $values as $k => $v ) {
                $placeholders[] = ":" . $k;
            }

            $query = sprintf(
                "CALL %s ( %s )",
                $storedProc,
                implode( ", ", $placeholders )
            );
        }

        $this->_query = $query;
        $stmt = $this->prepareQuery();
        $stmt = $this->dynamicBindResult( $stmt, $values  );

        $this->_error = ( $stmt->execute() ) ? false : true;

        return $this;
    }

    /**
     * Sets where property to be used as where query
     * that will be combined with the main select query
     * @param string $where_query
     */
    public function setWhere( $where_query ) {
        $where_query = trim( $where_query );
        /*
         * grab the first five letters after space
         * is stripped of whitespace
         */
        $first_five = substr( $where_query, 0, 5 );

        /*
         * check to see if where query begins with a where keyword
         * if not, add to the beginning of the where query
         */
        if ( strtolower($first_five) != "where" ) {
            $where_query = substr_replace( $where_query, " WHERE ", 0, 0 );
        }

        $this->_where = $where_query;
    }

    /**
     * Clears the where property so another where query
     * can be set
     */
    public function clearWhere() {
        $this->_where = "";
    }

    /**
     * Sets the table on which the query or sets
     * of queries will be performed
     * @param string $tableName
     */
    public function setTableName($tableName) {
        $this->_tableName = filter_var($tableName, FILTER_SANITIZE_STRING);
    }

    /**
     * Determines the type of value being operated
     * by the query and returns the value to be used
     * by the PDO for binding
     * @param type $value
     * @return string
     */
    private function determineType( $value ) {
        $type = "";
        switch ( gettype($value) ) {
            case "integer":
            case "double":
                $type = "i";
                break;
            case "string":
            default:
                $type = "s";
        }

        return $type;
    }

    /**
     * Dynamically bind values for query provided
     * depending on their type
     * @param PDOStatement $stmt
     * @param array $values
     * @return PDOStatement
     */
    private function dynamicBindResult( PDOStatement $stmt, array $values ) {
        foreach ( $values as $k => $v ) {
            $type = $this->determineType( $v );
            switch ( $type ) {
                case "i":
                    $stmt->
                        bindValue( $k, $v, PDO::PARAM_INT | PDO::PARAM_NULL );
                    break;
                case "s":
                    $stmt->
                        bindValue( $k, $v, PDO::PARAM_STR | PDO::PARAM_NULL );
                    break;
                default:
                    $stmt->
                        bindValue( $k, $v, PDO::PARAM_STR | PDO::PARAM_NULL );
                    break;
            }
        }

        return $stmt;
    }

    /**
     * Returns results from the select query
     * @param PDOStatement $stmt
     * @param int $pdoFetchMode
     * @return array
     */
    private function getResultSet( PDOStatement $stmt, $pdoFetchMode ) {
        $stmt->setFetchMode( $pdoFetchMode );
        return $stmt->fetchAll();
    }

    /**
     * Returns a prepared PDO Statement
     * @return PDOStatement
     */
    private function prepareQuery() {
        $stmt = $this->_connection->prepare( $this->_query );

        if ( !$stmt ) {
            trigger_error( "Problem preparing query", E_USER_ERROR );
        }

        return $stmt;
    }

    /**
     * Closes database connection
     */
    public function __destruct() {
        $this->_connection = null;
    }

}
