<?php  
    
    header('Access-Control-Allow-Origin: *');
    header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
    header("Allow: GET, POST, OPTIONS, PUT, DELETE");

    include "db_rrhh.php";

    class Api extends Rest{

        public $dbConn;

		public function __construct(){

			parent::__construct();

			$db = new Db();
			$this->dbConn = $db->connect();

        }

        public function obtener_usuario(){

            try {
                
                if(!isset($_SESSION)){

                    session_start();
    
                }
    
                $nit = $_SESSION["nit"];
                    
                $dbc_rrhh = new Db_RRHH();
                $conn_rrhh = $dbc_rrhh->connect();
    
                $query = "  SELECT *
                            FROM RH_EMPLEADOS
                            WHERE NIT = '$nit'";
    
                $stid = oci_parse($conn_rrhh, $query);
                oci_execute($stid);
    
                $usuario = oci_fetch_array($stid, OCI_ASSOC);
                
                $usuario = $usuario["USUARIO"];
    
                $this->returnResponse(SUCCESS_RESPONSE, $usuario);

            } catch (\Throwable $th) {

                //throw $th;
                $this->returnResponse(SUCCESS_RESPONSE, $th->getMessage());
            }
            

        }

        public function obtener_criterios(){

            $documento = (object) $this->param['documento'];

            //Validar si ya existe un control de calidad exitoso
            $query = "  SELECT COUNT(*) AS TOTAL_ACIERTOS
                        FROM CATASTRO.CDO_CALIDAD_CC
                        WHERE DOCUMENTO = '$documento->DOCUMENTO' 
                        AND ANIO = '$documento->ANIO'
                        AND ACIERTO IS NOT NULL";

            $stid = oci_parse($this->dbConn, $query);
            oci_execute($stid);

            $aciertos = oci_fetch_array($stid, OCI_ASSOC);
            $fase = intval($aciertos["TOTAL_ACIERTOS"]) + 1;

            $query = "  SELECT *
                        FROM CATASTRO.CDO_CRITERIO_CC
                        WHERE FASE = $fase
                        AND DELETED_AT IS NULL
                        ORDER BY ORDEN ASC";

            $stid = oci_parse($this->dbConn, $query);
            oci_execute($stid);

            $criterios = [];

            while ($data = oci_fetch_array($stid, OCI_ASSOC)) {
                
                $id_criterio = $data["ID"];

                $query = "  SELECT *
                            FROM CATASTRO.CDO_DETALLE_CRITERIO_CC
                            WHERE ID_CRITERIO = $id_criterio
                            AND DELETED_AT IS NULL
                            ORDER BY ORDEN ASC";

                $stid_ = oci_parse($this->dbConn, $query);
                oci_execute($stid_);

                $detalle_criterio = [];

                while ($data_ = oci_fetch_array($stid_, OCI_ASSOC)) {
                    
                    $data_["NO_APLICA"] = false;
                    $detalle_criterio [] = $data_;

                }

                $data["DETALLE"] = $detalle_criterio;

                $criterios [] = $data;

            }

            $this->returnResponse(SUCCESS_RESPONSE, $criterios);

        }

        public function obtener_documentos(){

            $filtro = $this->param["filtro"];

            $year = date('Y');

            $data = [];

            // Fechas
            $mes = date('m/Y');

            if ($filtro == 'pendientes') {
                
                $query = "  SELECT 
                                CD.CODIGOCLASE, 
                                CD.DOCUMENTO, 
                                CD.ANIO, 
                                TO_CHAR(CD.FECHA, 'DD/MM/YYYY HH24:MI:SS') AS FECHA_INGRESO,  
                                CB.USER_APLIC USUARIO, 
                                TO_CHAR(CB.FECHA_FINALIZACION, 'DD/MM/YYYY HH24:MI:SS') AS FECHA_FINALIZACION, 
                                CB.CODTRAMITE, 
                                CT.NOMBRE TRAMITE
                        FROM CDO_BANDEJA CB, CDO_DOCUMENTO CD, CDO_TRAMITE CT
                        WHERE CB.CODIGOCLASE = CD.CODIGOCLASE
                        AND CB.DOCUMENTO = CD.DOCUMENTO
                        AND CB.ANIO = CD.ANIO
                        AND CB.CODTRAMITE = CT.CODTRAMITE
                        AND CB.CODTRAMITE IN (322,323)
                        AND TO_CHAR(CD.FECHA,'MM/YYYY') = '$mes'
                        and CB.DEPENDENCIA NOT IN (100,99)
                        AND CB.USER_APLIC NOT IN ('GCHAJCHALAC')
                        AND CD.DOCUMENTO NOT IN (
                            SELECT DOCUMENTO 
                            FROM CATASTRO.CDO_CALIDAD_CC 
                            WHERE ACIERTO = 'S'
                            AND ANIO = '$year'
                            GROUP BY DOCUMENTO
                            HAVING COUNT(*) > 1
                        )
                        AND CD.STATUS = 6
                        ORDER BY CD.FECHA DESC";

            }else if($filtro == 'realizado'){

                $query = "  SELECT 
                                CD.CODIGOCLASE, 
                                CD.DOCUMENTO, 
                                CD.ANIO, 
                                TO_CHAR(CD.FECHA, 'DD/MM/YYYY HH24:MI:SS') AS FECHA_INGRESO,  
                                CB.USER_APLIC USUARIO, 
                                TO_CHAR(CB.FECHA_FINALIZACION, 'DD/MM/YYYY HH24:MI:SS') AS FECHA_FINALIZACION, 
                                CB.CODTRAMITE, 
                                CT.NOMBRE TRAMITE
                            FROM CDO_BANDEJA CB, CDO_DOCUMENTO CD, CDO_TRAMITE CT
                            WHERE CB.CODIGOCLASE = CD.CODIGOCLASE
                            AND CB.DOCUMENTO = CD.DOCUMENTO
                            AND CB.ANIO = CD.ANIO
                            AND CB.CODTRAMITE = CT.CODTRAMITE
                            AND CB.CODTRAMITE IN (322,323)
                            AND TO_CHAR(CD.FECHA,'MM/YYYY') = '$mes'
                            and CB.DEPENDENCIA NOT IN (100,99)
                            AND CB.USER_APLIC NOT IN ('GCHAJCHALAC')
                            AND CD.DOCUMENTO IN (
                                SELECT DOCUMENTO 
                                FROM CATASTRO.CDO_CALIDAD_CC 
                                WHERE ACIERTO = 'S'
                                AND ANIO = '$year'
                                GROUP BY DOCUMENTO
                                HAVING COUNT(*) > 1
                            )
                            ORDER BY CD.FECHA DESC";

            }else if($filtro == 'todas'){

                $query = "  SELECT 
                                CD.CODIGOCLASE, 
                                CD.DOCUMENTO, 
                                CD.ANIO, 
                                TO_CHAR(CD.FECHA, 'DD/MM/YYYY HH24:MI:SS') AS FECHA_INGRESO,  
                                CB.USER_APLIC USUARIO, 
                                TO_CHAR(CB.FECHA_FINALIZACION, 'DD/MM/YYYY HH24:MI:SS') AS FECHA_FINALIZACION, 
                                CB.CODTRAMITE, 
                                CT.NOMBRE TRAMITE
                            FROM CDO_BANDEJA CB, CDO_DOCUMENTO CD, CDO_TRAMITE CT
                            WHERE CB.CODIGOCLASE = CD.CODIGOCLASE
                            AND CB.DOCUMENTO = CD.DOCUMENTO
                            AND CB.ANIO = CD.ANIO
                            AND CB.CODTRAMITE = CT.CODTRAMITE
                            AND CB.CODTRAMITE IN (322,323)
                            AND TO_CHAR(CD.FECHA,'MM/YYYY') = '$mes'
                            and CB.DEPENDENCIA NOT IN (100,99)
                            AND CB.USER_APLIC NOT IN ('GCHAJCHALAC')
                            AND CD.STATUS = 6
                            ORDER BY CD.FECHA DESC";

            }

            $stid = oci_parse($this->dbConn, $query);
            oci_execute($stid);

            $documentos = [];

            while ($data = oci_fetch_array($stid, OCI_ASSOC)) {
                
                $documento = $data["DOCUMENTO"];
                $year = $data["ANIO"];

                // Buscar si tiene reprocesos
                $query = "  SELECT COUNT(*) AS REPROCESOS
                            FROM CATASTRO.CDO_CALIDAD_CC
                            WHERE DOCUMENTO = '$documento'
                            AND ANIO = '$year'
                            AND ERROR IS NOT NULL";

                $stid_ = oci_parse($this->dbConn, $query);
                oci_execute($stid_);

                $total_reprocesos = oci_fetch_array($stid_, OCI_ASSOC);
                $data["REPROCESOS"] = $total_reprocesos["REPROCESOS"];

                //Fase
                $query = "  SELECT COUNT(*) AS TOTAL_ACIERTOS
                            FROM CATASTRO.CDO_CALIDAD_CC
                            WHERE DOCUMENTO = '$documento' 
                            AND ANIO = '$year'
                            AND ACIERTO IS NOT NULL";

                $stid_ = oci_parse($this->dbConn, $query);
                oci_execute($stid_);

                $aciertos = oci_fetch_array($stid_, OCI_ASSOC);
                $fase = intval($aciertos["TOTAL_ACIERTOS"]) + 1;

                if ($fase > 2) {
                    
                    $fase = 2;

                }

                $data["FASE"] = $fase;

                // Buscar si ya cumplio con el control de calidad

                $query = "  SELECT COUNT(ACIERTO) AS ACIERTOS
                            FROM CATASTRO.CDO_CALIDAD_CC
                            WHERE DOCUMENTO = '$documento'
                            AND ANIO = '$year'
                            AND ACIERTO IS NOT NULL";

                $stid_ = oci_parse($this->dbConn, $query);
                oci_execute($stid_);

                $acierto = oci_fetch_array($stid_, OCI_ASSOC);
                $data["ACIERTO"] = $acierto["ACIERTOS"];

                $data["FILTRO"] = $filtro;
                
                $documentos [] = $data;

            }

            // Conteo

            // Pendientes
            $query = "  SELECT COUNT(*) AS TOTAL_PENDIENTES
                        FROM CDO_BANDEJA CB, CDO_DOCUMENTO CD, CDO_TRAMITE CT
                        WHERE CB.CODIGOCLASE = CD.CODIGOCLASE
                        AND CB.DOCUMENTO = CD.DOCUMENTO
                        AND CB.ANIO = CD.ANIO
                        AND CB.CODTRAMITE = CT.CODTRAMITE
                        AND CB.CODTRAMITE IN (322,323)
                        AND TO_CHAR(CD.FECHA,'MM/YYYY') = '$mes'
                        and CB.DEPENDENCIA NOT IN (100,99)
                        AND CB.USER_APLIC NOT IN ('GCHAJCHALAC')
                        AND CD.DOCUMENTO NOT IN (
                            SELECT DOCUMENTO 
                            FROM CATASTRO.CDO_CALIDAD_CC 
                            WHERE ACIERTO = 'S'
                            GROUP BY DOCUMENTO
                            HAVING COUNT(*) > 1
                        )
                        AND CD.STATUS = 6";

            $stid = oci_parse($this->dbConn, $query);
            oci_execute($stid);

            $total_pendientes = oci_fetch_array($stid, OCI_ASSOC);

            $data["total_pendientes"] = $total_pendientes["TOTAL_PENDIENTES"];

            // Realizado
            $query = "  SELECT COUNT(*) AS TOTAL_REALIZADO
                        FROM CDO_BANDEJA CB, CDO_DOCUMENTO CD, CDO_TRAMITE CT
                        WHERE CB.CODIGOCLASE = CD.CODIGOCLASE
                        AND CB.DOCUMENTO = CD.DOCUMENTO
                        AND CB.ANIO = CD.ANIO
                        AND CB.CODTRAMITE = CT.CODTRAMITE
                        AND CB.CODTRAMITE IN (322,323)
                        AND TO_CHAR(CD.FECHA,'MM/YYYY') = '$mes'
                        and CB.DEPENDENCIA NOT IN (100,99)
                        AND CB.USER_APLIC NOT IN ('GCHAJCHALAC')
                        AND CD.DOCUMENTO IN (
                            SELECT DOCUMENTO 
                            FROM CATASTRO.CDO_CALIDAD_CC 
                            WHERE ACIERTO = 'S'
                            GROUP BY DOCUMENTO
                            HAVING COUNT(*) > 1
                        )";

            $stid = oci_parse($this->dbConn, $query);
            oci_execute($stid);

            $total_realizado = oci_fetch_array($stid, OCI_ASSOC);

            $data["total_realizado"] = $total_realizado["TOTAL_REALIZADO"];

            // Todas
            $query = "  SELECT COUNT(*) AS TOTAL_TODAS
                        FROM CDO_BANDEJA CB, CDO_DOCUMENTO CD, CDO_TRAMITE CT
                        WHERE CB.CODIGOCLASE = CD.CODIGOCLASE
                        AND CB.DOCUMENTO = CD.DOCUMENTO
                        AND CB.ANIO = CD.ANIO
                        AND CB.CODTRAMITE = CT.CODTRAMITE
                        AND CB.CODTRAMITE IN (322,323)
                        AND TO_CHAR(CD.FECHA,'MM/YYYY') = '$mes'
                        and CB.DEPENDENCIA NOT IN (100,99)
                        AND CB.USER_APLIC NOT IN ('GCHAJCHALAC')
                        AND CD.STATUS = 6";

            $stid = oci_parse($this->dbConn, $query);
            oci_execute($stid);

            $total_todas = oci_fetch_array($stid, OCI_ASSOC);

            $data["total_todas"] = $total_todas["TOTAL_TODAS"];

            $data["items"] = $documentos;

            if ($filtro == 'pendientes' || $filtro == 'realizado') {
                
                $headers = [
                    [
                        "text" => "Documento",
                        "value" => "DOCUMENTO",
                        "width" => "20%"
                    ],
                    [
                        "text" => "Ingreso",
                        "value" => "FECHA_INGRESO",
                        "width" => "20%"
                    ],
                    [
                        "text" => "Usuario",
                        "value" => "USUARIO",
                        "width" => "20%"
                    ],
                    [
                        "text" => "Fase",
                        "value" => "FASE",
                        "width" => "10%"
                    ],
                    // [
                    //     "text" => "Finalizado",
                    //     "value" => "FECHA_FINALIZACION",
                    //     "width" => "20%"
                    // ],
                    [
                        "text" => "Reprocesos",
                        "value" => "REPROCESOS",
                        "align" => "center",
                        "width" => "10%"
                    ],
                    [
                        "text" => "Acciones",
                        "value" => "accion",
                        "align" => "center",
                        "width" => "10%"
                    ]
                ];

            }else{

                $headers = [
                    [
                        "text" => "Documento",
                        "value" => "DOCUMENTO",
                        "width" => "15%"
                    ],
                    [
                        "text" => "Ingreso",
                        "value" => "FECHA_INGRESO",
                        "width" => "20%"
                    ],
                    [
                        "text" => "Usuario",
                        "value" => "USUARIO",
                        "width" => "15%"
                    ],
                    // [
                    //     "text" => "Finalizado",
                    //     "value" => "FECHA_FINALIZACION",
                    //     "width" => "20%"
                    // ],
                    [
                        "text" => "Estado",
                        "value" => "ESTADO",
                        "align" => "center",
                        "width" => "10%"
                    ],
                    [
                        "text" => "Reprocesos",
                        "value" => "REPROCESOS",
                        "align" => "center",
                        "width" => "10%"
                    ],
                    
                    [
                        "text" => "Acciones",
                        "value" => "accion",
                        "align" => "center",
                        "width" => "10%",
                        "sortable" => false
                    ]
                ];

            }

            

            $data["headers"] = $headers;

            $this->returnResponse(SUCCESS_RESPONSE, $data);

        }

        public function aceptar_documento(){

            $documento = (object) $this->param['documento'];
            $comentario = $this->param['comentario'];
            $no_aplican = $this->param['no_aplican'];

            try {
                
                /* Usuario que reaiza el control de calidad */ 

                if(!isset($_SESSION)){

                    session_start();
    
                }
    
                $nit = $_SESSION["nit"];
                    
                $dbc_rrhh = new Db_RRHH();
                $conn_rrhh = $dbc_rrhh->connect();
    
                $query = "  SELECT *
                            FROM RH_EMPLEADOS
                            WHERE NIT = '$nit'";
    
                $stid = oci_parse($conn_rrhh, $query);
                oci_execute($stid);
    
                $usuario = oci_fetch_array($stid, OCI_ASSOC);
                
                $usuario = $usuario["USUARIO"];

                $query = "  INSERT INTO CATASTRO.CDO_CALIDAD_CC (DOCUMENTO, ANIO, FECHA, ACIERTO, COMENTARIO, ENCARGADO_CALIDAD) VALUES ('$documento->DOCUMENTO', '$documento->ANIO', SYSDATE, 'S', '$comentario', '$usuario')";

                $stid = oci_parse($this->dbConn, $query);

                oci_execute($stid);

                /* Iniciar proceso de registro */

                // Obtener el último ID
                $query = "  SELECT ID FROM CATASTRO.CDO_CALIDAD_CC WHERE ROWNUM = 1 ORDER BY ID DESC";
                $stid = oci_parse($this->dbConn, $query);
                oci_execute($stid);

                $last_insert = oci_fetch_array($stid, OCI_ASSOC);
                $last_id = $last_insert["ID"];

                // Registrar los no aplican 
                foreach ($no_aplican as $no_aplica) {
                    
                    $id_error = $no_aplica["ID"];

                    $query = "  INSERT INTO CATASTRO.CDO_NO_APLICA_CALIDAD_CC (ID_CALIDAD, ID_CRITERIO) VALUES ($last_id, $id_error)";
                    $stid = oci_parse($this->dbConn, $query);
                    oci_execute($stid);

                }

                // Validar la fase del expediente 
                $query = "  SELECT COUNT(*) AS TOTAL_ACIERTOS
                            FROM CATASTRO.CDO_CALIDAD_CC
                            WHERE DOCUMENTO = '$documento->DOCUMENTO' 
                            AND ANIO = '$documento->ANIO'
                            AND ACIERTO IS NOT NULL";

                $stid = oci_parse($this->dbConn, $query);
                oci_execute($stid);

                $aciertos = oci_fetch_array($stid, OCI_ASSOC);
                $fase = intval($aciertos["TOTAL_ACIERTOS"]);

                if ($fase == 1) {
                    
                    // Se reasigna al tecnico
                    $query_update = "   UPDATE CDO_DOCUMENTO SET STATUS = '1' 
                                        WHERE DOCUMENTO = '$documento->DOCUMENTO' AND ANIO = '$documento->ANIO' 
                                        AND CODIGOCLASE = 3";

                    $stid = oci_parse($this->dbConn, $query_update);

                    oci_execute($stid);

                    // Colocar las tareas como preasignado
                    $query_update = "   UPDATE CDO_BANDEJA SET STATUS_TAREA = '1', FECHA_ASIGNACION = SYSDATE,          
                                    FECHA_FINALIZACION = NULL WHERE DOCUMENTO = '$documento->DOCUMENTO' AND ANIO = '$documento->ANIO' AND USER_APLIC = '$documento->USUARIO' AND CODIGOCLASE = 3";

                    $stid = oci_parse($this->dbConn, $query_update);

                    oci_execute($stid);

                }else{

                    // Finalizar
                    $query_update = "   UPDATE CDO_DOCUMENTO SET STATUS = '2', FECHA_FINALIZACION = SYSDATE 
                                        WHERE DOCUMENTO = '$documento->DOCUMENTO' AND ANIO = '$documento->ANIO' 
                                        AND CODIGOCLASE = 3";

                    $stid = oci_parse($this->dbConn, $query_update);

                    oci_execute($stid);

                    // Escribir en la bitacora
                    $query = "  INSERT INTO CDO_BITACORA (FECHA, USUARIO, CODIGOCLASE, DOCUMENTO, ANIO, TEXTO) VALUES (SYSDATE, 'GCHAJCHALAC', '3', '$documento->DOCUMENTO', '$documento->ANIO', 'TAREA FINALIZADA POR CONTROL DE CALIDAD')";

                    $stid = oci_parse($this->dbConn, $query);

                    oci_execute($stid);


                }

            } catch (\Throwable $th) {
                //throw $th;
            }

            $this->returnResponse(SUCCESS_RESPONSE, $documento);

        }

        public function rechazar_documento(){

            $documento = (object) $this->param['documento'];
            $errores = $this->param['errores'];
            $comentario = $this->param['comentario'];
            $no_aplican = $this->param['no_aplican'];

            try {
                
                if(!isset($_SESSION)){

                    session_start();
    
                }
    
                $nit = $_SESSION["nit"];
                    
                $dbc_rrhh = new Db_RRHH();
                $conn_rrhh = $dbc_rrhh->connect();
    
                $query = "  SELECT *
                            FROM RH_EMPLEADOS
                            WHERE NIT = '$nit'";
    
                $stid = oci_parse($conn_rrhh, $query);
                oci_execute($stid);
    
                $usuario = oci_fetch_array($stid, OCI_ASSOC);
                
                $usuario = $usuario["USUARIO"];

                /* Inicia proceso de registro */

                $comentario = explode("'", $comentario);

                if (count($comentario) > 1) {

                    $comentario = $comentario[0] . "''" .$comentario[1];  

                }else{

                    $comentario = $comentario[0];

                }
                
                $query = "  INSERT INTO CATASTRO.CDO_CALIDAD_CC (DOCUMENTO, ANIO, FECHA, ERROR, USUARIO, COMENTARIO, ENCARGADO_CALIDAD) VALUES ('$documento->DOCUMENTO', '$documento->ANIO', SYSDATE, 'S', '$documento->USUARIO', '$comentario', '$usuario')";

                $stid = oci_parse($this->dbConn, $query);
                oci_execute($stid);

                // Obtener el último ID

                $query = "  SELECT ID FROM CATASTRO.CDO_CALIDAD_CC WHERE ROWNUM = 1 ORDER BY ID DESC";
                $stid = oci_parse($this->dbConn, $query);
                oci_execute($stid);

                $last_insert = oci_fetch_array($stid, OCI_ASSOC);

                $last_id = $last_insert["ID"];

                // Registrar los errores
                foreach ($errores as $error) {
                    
                    $id_error = $error["ID"];

                    $query = "  INSERT INTO CATASTRO.CDO_ERROR_CALIDAD_CC (ID_CALIDAD, ID_CRITERIO) VALUES ($last_id, $id_error)";
                    $stid = oci_parse($this->dbConn, $query);
                    oci_execute($stid);

                }

                // Registrar los no aplican 
                foreach ($no_aplican as $no_aplica) {
                    
                    $id_error = $no_aplica["ID"];

                    $query = "  INSERT INTO CATASTRO.CDO_NO_APLICA_CALIDAD_CC (ID_CALIDAD, ID_CRITERIO) VALUES ($last_id, $id_error)";
                    $stid = oci_parse($this->dbConn, $query);
                    oci_execute($stid);

                }

                //Reasignar al técnico 

                $query_update = "   UPDATE CDO_DOCUMENTO SET STATUS = '1' WHERE DOCUMENTO = '$documento->DOCUMENTO' 
                                    AND ANIO = '$documento->ANIO' AND CODIGOCLASE = '3'";

                $stid = oci_parse($this->dbConn, $query_update);

                oci_execute($stid);

                //Las tareas colocarlas como preasignado
                $query_update = "   UPDATE CDO_BANDEJA SET STATUS_TAREA = '1', FECHA_ASIGNACION = SYSDATE,          
                                    FECHA_FINALIZACION = NULL WHERE DOCUMENTO = '$documento->DOCUMENTO' AND ANIO = '$documento->ANIO' AND USER_APLIC = '$documento->USUARIO' AND CODIGOCLASE = 3";

                $stid = oci_parse($this->dbConn, $query_update);

                oci_execute($stid);

            } catch (\Throwable $th) {
               

            }

        }

        public function obtener_detalle(){

            $data = [];

            $documento = (object) $this->param["documento"];

            // Si el expediente ha pasado el control de calidad
            $query = "  SELECT 
                            ID, 
                            DOCUMENTO, 
                            ANIO, 
                            TO_CHAR(FECHA, 'DD/MM/YYYY HH24:MI:SS') AS FECHA, 
                            ACIERTO, 
                            COMENTARIO,
                            OBSERVACIONES
                        FROM CATASTRO.CDO_CALIDAD_CC 
                        WHERE DOCUMENTO = '$documento->DOCUMENTO'
                        AND ANIO = '$documento->ANIO'
                        AND ACIERTO IS NOT NULL
                        ORDER BY FECHA ASC";

            $stid = oci_parse($this->dbConn, $query);
            oci_execute($stid);

            $aciertos = [];
            
            $i = 0;

            while ($data = oci_fetch_array($stid, OCI_ASSOC)) {

                $id_calidad = $data["ID"];
                /*
                    Obtener los items de cada una de las fases
                */

                $i++;
                    
                    $query = "  SELECT *
                                FROM CATASTRO.CDO_CRITERIO_CC
                                WHERE FASE = '$i'
                                ORDER BY ORDEN ASC";

                    $stid_ = oci_parse($this->dbConn, $query);
                    oci_execute($stid_);

                    $criterios = [];

                    while ($data_ = oci_fetch_array($stid_, OCI_ASSOC)) {
                        
                        $id_criterio = $data_["ID"];

                        /*
                            Buscar los elementos de cada uno de los criterios
                        */

                        $query = "  SELECT *
                                    FROM CATASTRO.CDO_DETALLE_CRITERIO_CC
                                    WHERE ID_CRITERIO = '$id_criterio'
                                    ORDER BY ORDEN ASC";

                        $stid2 = oci_parse($this->dbConn, $query);
                        oci_execute($stid2);

                        $items = [];

                        while ($data2 = oci_fetch_array($stid2, OCI_ASSOC)) {
                            
                            $id_item = $data2["ID"];

                            /*
                                Validar si alguno de los items no aplica
                            */
                            $query = "  SELECT *
                                        FROM CATASTRO.CDO_NO_APLICA_CALIDAD_CC
                                        WHERE ID_CALIDAD = '$id_calidad'
                                        AND ID_CRITERIO = '$id_item'";

                            $stid3 = oci_parse($this->dbConn, $query);
                            oci_execute($stid3);

                            $no_aplica = oci_fetch_array($stid3, OCI_ASSOC);

                            $data2["NO_APLICA"] = $no_aplica ? true : false;

                            $items [] = $data2;

                        }

                        $data_["ITEMS"] = $items;

                        $criterios [] = $data_;

                    }

                    $data["CRITERIOS"] = $criterios;


                $aciertos [] = $data;

            }

            // Listado de errores

            $query = "  SELECT 
                            ID, 
                            DOCUMENTO, 
                            ANIO, 
                            TO_CHAR(FECHA, 'DD/MM/YYYY HH24:MI:SS') AS FECHA, 
                            ACIERTO, 
                            ERROR, 
                            COMENTARIO, 
                            USUARIO
                        FROM CATASTRO.CDO_CALIDAD_CC
                        WHERE DOCUMENTO = '$documento->DOCUMENTO' 
                        AND ANIO = '$documento->ANIO'
                        AND ERROR IS NOT NULL";

            $stid = oci_parse($this->dbConn, $query);
            oci_execute($stid);

            $errores = [];

            while ($data = oci_fetch_array($stid, OCI_ASSOC)) {
                
                $errores [] = $data;
            }

            foreach ($errores as &$error) {
                
                $id_control = $error["ID"];

                $query = "  SELECT 
                                DISTINCT(T2.ID_CRITERIO) AS CRITERIO, 
                                T3.NOMBRE
                            FROM CATASTRO.CDO_ERROR_CALIDAD_CC T1
                            INNER JOIN CATASTRO.CDO_DETALLE_CRITERIO_CC T2
                            ON T1.ID_CRITERIO = T2.ID
                            INNER JOIN CATASTRO.CDO_CRITERIO_CC T3
                            ON T2.ID_CRITERIO = T3.ID
                            WHERE T1.ID_CALIDAD = $id_control
                            ORDER BY T2.ID_CRITERIO ASC";

                $stid = oci_parse($this->dbConn, $query);
                oci_execute($stid);

                $detalle_errores = [];

                while ($data = oci_fetch_array($stid, OCI_ASSOC)) {

                    $id_criterio = $data["CRITERIO"];

                    // Por cada clasificación de error buscar el detalle
                    $query = "  SELECT T2.*
                                FROM CATASTRO.CDO_ERROR_CALIDAD_CC T1
                                INNER JOIN CATASTRO.CDO_DETALLE_CRITERIO_CC T2
                                ON T1.ID_CRITERIO = T2.ID
                                WHERE T1.ID_CALIDAD = $id_control
                                AND T2.ID_CRITERIO = $id_criterio
                                ORDER BY T2.ORDEN ASC";

                    $stid_ = oci_parse($this->dbConn, $query);
                    oci_execute($stid_);

                    $listado_errores = [];

                    while ($data_ = oci_fetch_array($stid_, OCI_ASSOC)) {
                    
                        $listado_errores [] = $data_;

                    }

                    $data["LISTADO_ERRORES"] = $listado_errores;

                    $detalle_errores [] = $data;

                }

                $error["DETALLE"] = $detalle_errores;

            }

            $data["ERRORES"] = $errores;
            $data["ACIERTO"] = $aciertos;

            $this->returnResponse(SUCCESS_RESPONSE, $data);

        }

        public function grafica_pendientes(){

            $mes = $this->param["mes"];

            if (!$mes) {
                
                $mes = date('Y-m');
                $mes_grafica = date('m/Y');

            }else{

                $mes_grafica = date('m/Y', strtotime($mes));

            }

            $series = [];

            $data = [];

            try {
                
                /* Total de expedientes pendientes  */

                $query = "  SELECT COUNT(*) AS PENDIENTES
                            FROM CDO_BANDEJA CB, CDO_DOCUMENTO CD, CDO_TRAMITE CT
                            WHERE CB.CODIGOCLASE = CD.CODIGOCLASE
                            AND CB.DOCUMENTO = CD.DOCUMENTO
                            AND CB.ANIO = CD.ANIO
                            AND CB.CODTRAMITE = CT.CODTRAMITE
                            AND CB.CODTRAMITE IN (322,323)
                            AND TO_CHAR(CD.FECHA,'YYYY-MM') = '$mes'
                            and CB.DEPENDENCIA NOT IN (100,99)
                            AND CB.USER_APLIC NOT IN ('GCHAJCHALAC')
                            AND CD.DOCUMENTO NOT IN (
                                SELECT DOCUMENTO 
                                FROM CATASTRO.CDO_CALIDAD_CC 
                                WHERE ACIERTO = 'S'
                                GROUP BY DOCUMENTO
                                HAVING COUNT(*) > 1
                            )
                            AND CD.STATUS = 6";

                $stid = oci_parse($this->dbConn, $query);
                oci_execute($stid);

                $pendientes = oci_fetch_array($stid, OCI_ASSOC);

                if (intval($pendientes["PENDIENTES"]) > 0) {
                   
                    $arr_pendientes = [
                        "name" => "Pendientes",
                        "y" => intval($pendientes["PENDIENTES"]),
                        "color" => 'orange',
                        "sliced" => true,
                        "selected" => true
                    ];
    
                    $series [] = $arr_pendientes;

                }

                /* Total de expedientes rechazados */

                $query = "  SELECT COUNT(*) AS RECHAZADOS
                            FROM CDO_BANDEJA CB, CDO_DOCUMENTO CD, CDO_TRAMITE CT
                            WHERE CB.CODIGOCLASE = CD.CODIGOCLASE
                            AND CB.DOCUMENTO = CD.DOCUMENTO
                            AND CB.ANIO = CD.ANIO
                            AND CB.CODTRAMITE = CT.CODTRAMITE
                            AND CB.CODTRAMITE IN (322,323)
                            AND TO_CHAR(CD.FECHA,'YYYY-MM') = '$mes'
                            and CB.DEPENDENCIA NOT IN (100,99)
                            AND CB.USER_APLIC NOT IN ('GCHAJCHALAC')
                            AND CD.DOCUMENTO IN (SELECT DOCUMENTO FROM CATASTRO.CDO_CALIDAD_CC WHERE ERROR IS NOT NULL)";

                $stid = oci_parse($this->dbConn, $query);
                oci_execute($stid);

                $rechazados = oci_fetch_array($stid, OCI_ASSOC);

                if (intval($rechazados["RECHAZADOS"]) > 0) {
                    
                    $arr_rechazado = [
                        "name" => "Rechazados",
                        "y" => intval($rechazados["RECHAZADOS"]),
                        "color" => "#e33232"
                    ];
    
                    $series [] = $arr_rechazado;

                }

                /* Total de expedientes aceptados sin errores */

                $query = "  SELECT COUNT(*) AS CORRECTOS
                            FROM CDO_BANDEJA CB, CDO_DOCUMENTO CD, CDO_TRAMITE CT
                            WHERE CB.CODIGOCLASE = CD.CODIGOCLASE
                            AND CB.DOCUMENTO = CD.DOCUMENTO
                            AND CB.ANIO = CD.ANIO
                            AND CB.CODTRAMITE = CT.CODTRAMITE
                            AND CB.CODTRAMITE IN (322,323)
                            AND TO_CHAR(CD.FECHA,'YYYY-MM') = '$mes'
                            and CB.DEPENDENCIA NOT IN (100,99)
                            AND CB.USER_APLIC NOT IN ('GCHAJCHALAC')
                            AND CD.DOCUMENTO IN (
                                SELECT DOCUMENTO 
                                FROM CATASTRO.CDO_CALIDAD_CC 
                                WHERE ACIERTO IS NOT NULL
                                AND DOCUMENTO NOT IN (
                                    SELECT DOCUMENTO
                                    FROM CATASTRO.CDO_CALIDAD_CC
                                    WHERE ERROR IS  NOT NULL
                                )
                            )";

                $stid = oci_parse($this->dbConn, $query);
                oci_execute($stid);

                $correctos = oci_fetch_array($stid, OCI_ASSOC);

                if (intval($correctos["CORRECTOS"]) > 0) {
                    
                    $arr_correctos = [
                        "name" => "Correctos",
                        "y" => intval($correctos["CORRECTOS"]),
                        "color" => "#53b555"
                    ];
    
                    $series [] = $arr_correctos;

                }

                $data["series"] = $series;
                $data["mes"] = $mes;
                $data["mes_grafica"] = $mes_grafica;

            } catch (\Throwable $th) {
                //throw $th;
            }

            $this->returnResponse(SUCCESS_RESPONSE, $data);
        }

        public function grafica_errores(){

            $data = [];

            $mes = $this->param["mes"];

            if (!$mes) {
                
                $mes = date('Y-m');
                $mes_grafica = date('m/Y');

            }else{

                $mes_grafica = date('m/Y', strtotime($mes));

            }

            try {
                
                // Por cada criterio de calidad buscar las veces que se registro como error

                $query = "  SELECT T1.*, T2.NOMBRE AS CRITERIO
                            FROM CATASTRO.CDO_DETALLE_CRITERIO_CC T1
                            INNER JOIN CATASTRO.CDO_CRITERIO_CC T2
                            ON T1.ID_CRITERIO = T2.ID
                            ORDER BY T1.ID_CRITERIO, T1.ID ASC";

                $stid = oci_parse($this->dbConn, $query);
                oci_execute($stid);

                $criterios = [];

                while ($data = oci_fetch_array($stid, OCI_ASSOC)) {
                    
                    // Por cada criterio buscar la cantidad de veces que se ha registrado

                    $id_criterio = $data["ID"];

                    $query = "  SELECT COUNT(*) AS CANTIDAD
                                FROM CATASTRO.CDO_ERROR_CALIDAD_CC
                                WHERE ID_CALIDAD IN (
                                    SELECT ID
                                    FROM CATASTRO.CDO_CALIDAD_CC
                                    WHERE TO_CHAR(FECHA, 'YYYY-MM') = '$mes'
                                    AND ERROR IS NOT NULL
                                )
                                AND ID_CRITERIO = $id_criterio";

                    $stid_ = oci_parse($this->dbConn, $query);
                    oci_execute($stid_);

                    $cantidad = oci_fetch_array($stid_, OCI_ASSOC);

                    if (intval($cantidad["CANTIDAD"]) > 0) {
                        
                        $serie = [
                            "name" => $data["NOMBRE"],
                            "y" => intval($cantidad["CANTIDAD"])
                        ];

                        $data["CANTIDAD"] = $cantidad["CANTIDAD"];
                        $criterios [] = $serie;

                    }

                }

                $data["CRITERIOS"] = $criterios;
                $data["mes"] = $mes;
                $data["mes_grafica"] = $mes_grafica;

                $this->returnResponse(SUCCESS_RESPONSE, $data);

            } catch (\Throwable $th) {
                //throw $th;
            }

        }

        public function grafica_errores_usuario(){

            $data = [];

            $mes = $this->param["mes"];

            if (!$mes) {
                
                $mes = date('Y-m');
                $mes_grafica = date('m/Y');

            }else{

                $mes_grafica = date('m/Y', strtotime($mes));

            }

            try {
                
                // Usuarios

                $query = "  SELECT DISTINCT(USUARIO) AS USUARIO
                            FROM CATASTRO.CDO_CALIDAD_CC
                            WHERE ERROR IS NOT NULL
                            AND TO_CHAR(FECHA, 'YYYY-MM') = '$mes'";

                $stid = oci_parse($this->dbConn, $query);
                oci_execute($stid);

                $usuarios = [];

                while ($data = oci_fetch_array($stid, OCI_ASSOC)) {
                    
                    $usuarios [] = $data["USUARIO"];

                }

                // Errores

                $query = "  SELECT DISTINCT(T1.ID_CRITERIO), T2.NOMBRE
                            FROM CATASTRO.CDO_ERROR_CALIDAD_CC T1
                            INNER JOIN CATASTRO.CDO_DETALLE_CRITERIO_CC T2
                            ON T1.ID_CRITERIO = T2.ID
                            WHERE ID_CALIDAD IN (
                                SELECT ID
                                FROM CATASTRO.CDO_CALIDAD_CC
                                WHERE ERROR IS NOT NULL
                                AND TO_CHAR(FECHA, 'YYYY-MM') = '$mes'        
                            )";

                $stid = oci_parse($this->dbConn, $query);
                oci_execute($stid);

                $errores = [];

                while ($data = oci_fetch_array($stid, OCI_ASSOC)) {
                    
                    $errores [] = $data;

                }

                // Por cada error 
                $series = [];

                foreach ($errores as $error) {
                    
                    $id_criterio = $error["ID_CRITERIO"];

                    // Por cada usuario
                    $data_ = [];

                    foreach ($usuarios as $usuario) {
                        
                        $query = "  SELECT COUNT(*) AS CANTIDAD
                                    FROM CATASTRO.CDO_ERROR_CALIDAD_CC
                                    WHERE ID_CRITERIO = $id_criterio
                                    AND ID_CALIDAD IN (
                                        SELECT ID
                                        FROM CATASTRO.CDO_CALIDAD_CC
                                        WHERE USUARIO = '$usuario'
                                        AND ERROR IS NOT NULL 
                                        AND TO_CHAR(FECHA, 'YYYY-MM') = '$mes' 
                                    )";

                        $stid = oci_parse($this->dbConn, $query);
                        oci_execute($stid);

                        $cantidad = oci_fetch_array($stid, OCI_ASSOC);

                        if (intval($cantidad) > 0) {
                            
                            $data_ [] =  intval($cantidad["CANTIDAD"]);    

                        }

                    }

                    $serie = [
                        "name" => $error["NOMBRE"],
                        "data" => $data_
                    ];

                    $series [] = $serie;

                }

                $data["CATEGORIAS"] = $usuarios;
                $data["SERIES"] = $series;
                $data["mes"] = $mes;
                $data["mes_grafica"] = $mes_grafica;

                $this->returnResponse(SUCCESS_RESPONSE, $data);

            } catch (\Throwable $th) {
                //throw $th;
            }

        }

        public function generar_reportes(){

            $fecha = $this->param["fecha"];

            // Verificaciones
            $sql = "    SELECT T1.*
                        FROM CATASTRO.CDO_DETALLE_CRITERIO_CC T1
                        INNER JOIN CATASTRO.CDO_CRITERIO_CC T2
                        ON T1.ID_CRITERIO = T2.ID
                        WHERE T2.DELETED_AT IS NULL
                        AND T1.DELETED_AT IS NULL
                        ORDER BY T2.FASE, T2.ORDEN";

            $stid = oci_parse($this->dbConn, $sql);
            oci_execute($stid);

            $criterios = [];

            while ($row = oci_fetch_array($stid, OCI_ASSOC)) {
                
                $criterios [] = $row;

            }

            $sql = "SELECT 
                        CD.CODIGOCLASE, 
                        CD.DOCUMENTO, 
                        CD.ANIO, 
                        TO_CHAR(CD.FECHA, 'DD/MM/YYYY') AS FECHA_INGRESO, 
                        CB.USER_APLIC USUARIO, 
                        TO_CHAR(CB.FECHA_FINALIZACION, 'DD/MM/YYYY') AS FECHA_FINALIZACION, 
                        CB.CODTRAMITE, 
                        CT.NOMBRE TRAMITE,
                        CD.REFERENCIA 
                    FROM CDO_BANDEJA CB, CDO_DOCUMENTO CD, CDO_TRAMITE CT
                    WHERE CB.CODIGOCLASE = CD.CODIGOCLASE
                    AND CB.DOCUMENTO = CD.DOCUMENTO
                    AND CB.ANIO = CD.ANIO
                    AND CB.CODTRAMITE = CT.CODTRAMITE
                    AND CB.CODTRAMITE IN (322,323)
                    AND TO_CHAR(CD.FECHA,'YYYY-MM') = '$fecha'
                    and CB.DEPENDENCIA NOT IN (100,99)
                    AND CB.USER_APLIC NOT IN ('GCHAJCHALAC')
                    AND CD.DOCUMENTO IN (
                        SELECT DOCUMENTO 
                        FROM CATASTRO.CDO_CALIDAD_CC 
                        WHERE TO_CHAR(FECHA, 'YYYY-MM') = '$fecha'
                        GROUP BY DOCUMENTO
                        HAVING COUNT(*) > 1
                    )
                    ORDER BY CD.FECHA ASC";

            $stid = oci_parse($this->dbConn, $sql);
            oci_execute($stid);

            $expedientes = [];
            $counter = 0;

            while ($row = oci_fetch_array($stid, OCI_ASSOC)) {

                $counter++;
                $temp_criterios = [];

                $documento = $row["DOCUMENTO"];
                $anio = $row["ANIO"];
                $row["COUNTER"] = $counter;

                // Buscar la información para la nueva vista del reporte 
                $fases = (object) [
                    [
                        "id" => 1,
                        "text" => "FASE 1",
                        "items" => [] 
                    ],
                    [
                        "id" => 2,
                        "text" => "FASE 2",
                        "items" => []
                    ]
                ];

                foreach ($fases as &$fase) {

                    $fase = (object) $fase;

                    $query = "  SELECT *
                                FROM CATASTRO.CDO_CRITERIO_CC
                                WHERE FASE = $fase->id
                                AND DELETED_AT IS NULL
                                ORDER BY ORDEN";

                    $stid_ = oci_parse($this->dbConn, $query);
                    oci_execute($stid_);

                    $categorias = [];

                    while ($row_ = oci_fetch_array($stid_, OCI_ASSOC)) {

                        $id_categoria = $row_["ID"];

                        // Por cada categoria buscar el detalle
                        $query = "  SELECT *
                                    FROM CATASTRO.CDO_DETALLE_CRITERIO_CC
                                    WHERE ID_CRITERIO = $id_categoria
                                    AND DELETED_AT IS NULL
                                    ORDER BY ORDEN";

                        $stid2 = oci_parse($this->dbConn, $query);
                        oci_execute($stid2);           

                        $criterios_ = [];

                        while ($row2 = oci_fetch_array($stid2, OCI_ASSOC)) {
                            
                            $id_criterio = $row2["ID"];

                            // Buscar primero si no aplica
                            $query = "  SELECT COUNT(*) AS TOTAL
                                        FROM CATASTRO.CDO_NO_APLICA_CALIDAD_CC
                                        WHERE ID_CALIDAD IN (
                                            
                                            SELECT ID
                                            FROM CATASTRO.CDO_CALIDAD_CC
                                            WHERE DOCUMENTO = '$documento' 
                                            AND ANIO = '$anio'
                                        
                                        )
                                        AND ID_CRITERIO = '$id_criterio'";

                            $stid3 = oci_parse($this->dbConn, $query);
                            oci_execute($stid3);   

                            $total = oci_fetch_array($stid3, OCI_ASSOC);

                            if (intval($total["TOTAL"]) > 0) {
                                
                                $row2["VALUE"] = '-';
                                $row2["COLOR"] = null;

                            }else{

                                $query = "  SELECT COUNT(*) AS TOTAL
                                            FROM CATASTRO.CDO_ERROR_CALIDAD_CC
                                            WHERE ID_CALIDAD IN (
                                                
                                                SELECT ID
                                                FROM CATASTRO.CDO_CALIDAD_CC
                                                WHERE DOCUMENTO = '$documento' 
                                                AND ANIO = '$anio'
                                            
                                            )
                                            AND ID_CRITERIO = '$id_criterio'";

                                $stid3 = oci_parse($this->dbConn, $query);
                                oci_execute($stid3);   

                                $total = oci_fetch_array($stid3, OCI_ASSOC);

                                $row2["VALUE"] = intval($total["TOTAL"]);
                                $row2["COLOR"] = intval($total["TOTAL"]) > 0 ? 'error' : 'success';

                            }

                            // Buscar cuando el criterio se cumplio o si no aplica
                            $query = "  SELECT COUNT(*)
                                        FROM CDO_ERROR_CALIDAD_CC
                                        WHERE ";
                            $criterios_ [] = $row2;

                        }

                        $row_["CRITERIOS"] = $criterios_;
                        $row_["EXPAND"] = false;

                        $categorias [] = $row_;

                    }

                    $fase->items = $categorias;

                }

                $row["FASES"] = $fases;

                /* Buscar el responsable */
                $sql = "SELECT *
                        FROM CATASTRO.CDO_CALIDAD_CC
                        WHERE DOCUMENTO = '$documento'
                        AND ANIO = '$anio'
                        AND ENCARGADO_CALIDAD IS NOT NULL";

                $stid_ = oci_parse($this->dbConn, $sql);
                oci_execute($stid_);

                $result_ = oci_fetch_array($stid_, OCI_ASSOC);
                
                if($result_){

                    $row["ENCARGADO_CALIDAD"] = $result_["ENCARGADO_CALIDAD"];

                } 

                $i = 0;

                foreach ($criterios as $criterio) {

                    $id_criterio = $criterio["ID"];

                    $sql = "SELECT *
                            FROM CATASTRO.CDO_ERROR_CALIDAD_CC
                            WHERE ID_CRITERIO = $id_criterio
                            AND ID_CALIDAD IN (
                                SELECT ID
                                FROM CATASTRO.CDO_CALIDAD_CC
                                WHERE DOCUMENTO = '$documento'
                                AND ANIO = '$anio'
                            )";
                    
                    $stid_ = oci_parse($this->dbConn, $sql);
                    oci_execute($stid_);

                    $result = oci_fetch_array($stid_, OCI_ASSOC);

                    if ($result) {
                        
                        $temp_criterios [] = 1;

                    }else{

                        $temp_criterios [] = 0;

                    }

                    /* Buscar cuando no aplica */
                    $sql = "SELECT *
                            FROM CATASTRO.CDO_NO_APLICA_CALIDAD_CC
                            WHERE ID_CRITERIO = $id_criterio
                            AND ID_CALIDAD IN (
                                SELECT ID
                                FROM CATASTRO.CDO_CALIDAD_CC
                                WHERE DOCUMENTO = '$documento'
                                AND ANIO = '$anio'
                            )";

                    $stid_ = oci_parse($this->dbConn, $sql);
                    oci_execute($stid_);

                    $result = oci_fetch_array($stid_, OCI_ASSOC);

                    if ($result) {
                        
                        $temp_criterios [$i] = 2;

                    }

                    $i++;

                }

                $row["CRITERIOS"] = $temp_criterios;
                $row["RESULTADO"] = in_array(1, $temp_criterios) ? 'RECHAZADO' : 'ACEPTADO';
                
                $total_errores = array_count_values($temp_criterios);
                $row["ERRORES"] = count(array_keys($temp_criterios, 1));
                $expedientes [] = $row; 

            }

            $encabezado1 = [
                [
                    "TEXT" => "",
                    "COLSPAN" => 5
                ],
                [
                    "TEXT" => "Hallazgos",
                    "COLSPAN" => 0
                ],
                [
                    "TEXT" => "",
                    "COLSPAN" => 3
                ],
            ];

            $encabezado2 = [
                [
                    "TEXT" => "",
                    "COLSPAN" => 5
                ],
                
            ];

            // Obtener el encabezado 2
            $sql = "SELECT ID, NOMBRE AS TEXT
                    FROM CATASTRO.CDO_CRITERIO_CC
                    WHERE DELETED_AT IS NULL
                    ORDER BY FASE, ORDEN";

            $stid = oci_parse($this->dbConn, $sql);
            oci_execute($stid);

            $encabezado3 = [
                [
                    "TEXT" => "No.",
                    "COLSPAN" => 1
                ],
                [
                    "TEXT" => "Usuario",
                    "COLSPAN" => 1
                ],
                [
                    "TEXT" => "No. IUSI-CASO",
                    "COLSPAN" => 1,
                    "WIDTH" => "125px"
                ],
                [
                    "TEXT" => "No. WF",
                    "COLSPAN" => 1,
                    "WIDTH" => "125px"
                ],
                [
                    "TEXT" => "Fecha de Entrega",
                    "COLSPAN" => 1
                ],
            ];

            $i = 0;

            while ($row = oci_fetch_array($stid, OCI_ASSOC)) {
                
                $id = $row["ID"];

                $sql = "SELECT NOMBRE AS TEXT
                        FROM CATASTRO.CDO_DETALLE_CRITERIO_CC
                        WHERE ID_CRITERIO = $id
                        ORDER BY ORDEN";

                $stid_ = oci_parse($this->dbConn, $sql);
                oci_execute($stid_);

                $temp = [];

                while ($row_ = oci_fetch_array($stid_, OCI_ASSOC)) {
                    
                    $row_["COLSPAN"] = 1;
                    $row_["WIDTH"] = '150px';
                    $encabezado3 [] = $row_;
                    $temp [] = $row_;
                    $i++;
                }

                $encabezado1[1]["COLSPAN"] = $i;

                $row["COLSPAN"] = count($temp);
                $encabezado2 [] = $row; 

            }

            $end = [
                [
                    "TEXT" => "Total Errores",
                    "COLSPAN" => 1
                ],
                [
                    "TEXT" => "Resultado",
                    "COLSPAN" => 1
                ],
                [
                    "TEXT" => "Responsable de CC.",
                    "COLSPAN" => 1
                ],
            ];

            foreach ($end as $item) {
                array_push($encabezado3, $item); 
            }

            $table_headers = [
                [
                    "value" => "COUNTER",
                    "text" => "No.",
                    "sortable" => false,
                    "width" => "5%"
                ],
                [
                    "value" => "USUARIO",
                    "text" => "Usuario",
                    "sortable" => false,
                    "width" => "15%"
                ],
                [
                    "value" => "REFERENCIA",
                    "text" => "IUSI-CASO",
                    "sortable" => false
                ],
                [
                    "value" => "WF",
                    "text" => "No. WF",
                    "sortable" => false
                ],
                [
                    "value" => "FECHA_FINALIZACION",
                    "text" => "Fecha de Entrega",
                    "sortable" => false
                ],
                [
                    "value" => "TRAMITE",
                    "text" => "Trámite",
                    "sortable" => false
                ],
                [
                    "value" => "RESULTADO",
                    "text" => "Resultado",
                    "sortable" => false
                ],
                [
                    "value" => "data-table-expand",
                    "text" => "Acción",
                    "sortable" => false,
                    "align" => "center"
                ]
            ];

            $data = [
                "headers" => [
                    $encabezado1, $encabezado2, $encabezado3
                ],
                "items" => $expedientes,
                "table_headers" => $table_headers
            ];

            $this->returnResponse(SUCCESS_RESPONSE, $data);

        }

        public function grafica_total_tecnicos(){

            $data = [];

            $mes = $this->param["mes"];

            if (!$mes) {
                
                $mes = date('Y-m');
                $mes_grafica = date('m/Y');

            }else{

                $mes_grafica = date('m/Y', strtotime($mes));

            }

            try {
                
                $query = "SELECT CB.USER_APLIC USUARIO, COUNT(*) AS TOTAL
                        FROM CDO_BANDEJA CB, CDO_DOCUMENTO CD, CDO_TRAMITE CT
                        WHERE CB.CODIGOCLASE = CD.CODIGOCLASE
                        AND CB.DOCUMENTO = CD.DOCUMENTO
                        AND CB.ANIO = CD.ANIO
                        AND CB.CODTRAMITE = CT.CODTRAMITE
                        AND CB.CODTRAMITE IN (322,323)
                        AND TO_CHAR(CD.FECHA,'YYYY-MM') = '$mes'
                        and CB.DEPENDENCIA NOT IN (100,99)
                        AND CB.USER_APLIC NOT IN ('GCHAJCHALAC')
                        AND CD.DOCUMENTO IN (
                            SELECT DOCUMENTO 
                            FROM CATASTRO.CDO_CALIDAD_CC 
                            WHERE ACIERTO = 'S'
                            GROUP BY DOCUMENTO
                            HAVING COUNT(*) > 1
                        )
                        GROUP BY CB.USER_APLIC";

                $stid = oci_parse($this->dbConn, $query);
                oci_execute($stid);

                $datos = [];

                while ($row = oci_fetch_array($stid, OCI_ASSOC)) {
                
                    $temp = [];

                    $temp["name"] = $row["USUARIO"];
                    $temp["y"] = intval($row["TOTAL"]);

                    $datos [] = $temp;

                }

                $data["series"] = $datos; 
                $data["mes"] = $mes;
                $data["mes_grafica"] = $mes_grafica;

                $this->returnResponse(SUCCESS_RESPONSE, $data);

            } catch (\Throwable $th) {
                //throw $th;
            }

        }

        public function obtener_menu(){

            if(!isset($_SESSION)){

                session_start();

            }

            $nit = $_SESSION["nit"];

            $this->returnResponse(SUCCESS_RESPONSE, $nit);

        }

        public function detalle_rechazados(){

            $month = $this->param["month"];

            $query = "  SELECT *
                        FROM CATASTRO.CDO_CALIDAD_CC
                        WHERE ERROR = 'S'
                        AND TO_CHAR(FECHA, 'YYYY-MM') = '$month'";

            $stid = oci_parse($this->dbConn, $query);
            oci_execute($stid);

            $items = [];

            while ($row = oci_fetch_array($stid, OCI_ASSOC)) {
                
                $row["EXPEDIENTE"] = $row["DOCUMENTO"] . ' - ' . $row["ANIO"];

                $items [] = $row;

            }

            $headers = [
                [
                    "value" => "EXPEDIENTE",
                    "text" => "Expediente",
                    "width" => "25%",
                    "sortable" => false
                ],
                [
                    "value" => "FECHA",
                    "text" => "Fecha",
                    "width" => "20%",
                    "sortable" => false
                ],
                [
                    "value" => "USUARIO",
                    "text" => "Usuario",
                    "width" => "15%",
                    "sortable" => false
                ],
                [
                    "value" => "COMENTARIO",
                    "text" => "Comentario"
                ]
            ];

            $response = [
                "items" => $items,
                "headers" => $headers
            ];

            $this->returnResponse(SUCCESS_RESPONSE, $response);

        }

    }

?>