<?php 

class Db_RRHH{

    protected $connection;

    protected $user = 'rrhh';
    protected $password = 'rrhhadmin';
    protected $dbName = '  (DESCRIPTION = (ADDRESS_LIST = (ADDRESS = (PROTOCOL = TCP)(HOST = 172.23.50.95)(PORT = 1521)))(CONNECT_DATA = (SERVICE_NAME = CATGIS)))';

    function connect(){

        $this->connection = oci_connect($this->user, $this->password, $this->dbName, 'UTF8');

        if (!$this->connection) {

            $e = oci_error();
            trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);

        }else{

            return $this->connection;

        }

    }

    function disconnect($conn){

        oci_close($conn);

    }

}

?>