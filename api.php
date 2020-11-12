<?php  

    header('Access-Control-Allow-Origin: *');
    header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
    header("Allow: GET, POST, OPTIONS, PUT, DELETE");

    class Api extends Rest{

        public $dbConn;

		public function __construct(){

			parent::__construct();

			$db = new Db();
			$this->dbConn = $db->connect();

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

            /*
            if (intval($aciertos["TOTAL_ACIERTOS"]) == 0) {
                
                $query = "  SELECT *
                            FROM CATASTRO.CDO_CRITERIO_CC
                            WHERE FASE = 1
                            ORDER BY ORDEN ASC";

            }else{

                

            }
            */

            $query = "  SELECT *
                        FROM CATASTRO.CDO_CRITERIO_CC
                        WHERE FASE = $fase
                        ORDER BY ORDEN ASC";

            $stid = oci_parse($this->dbConn, $query);
            oci_execute($stid);

            $criterios = [];

            while ($data = oci_fetch_array($stid, OCI_ASSOC)) {
                
                $id_criterio = $data["ID"];

                $query = "  SELECT *
                            FROM CATASTRO.CDO_DETALLE_CRITERIO_CC
                            WHERE ID_CRITERIO = $id_criterio
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

            $data = [];

            // Fechas
            $mes = date('m/Y');

            if ($filtro == 'pendientes') {
                
                $query = "  SELECT CD.CODIGOCLASE, CD.DOCUMENTO, CD.ANIO, TO_CHAR(CD.FECHA, 'DD/MM/YYYY HH24:MI:SS') AS FECHA_INGRESO,  
                        CB.USER_APLIC USUARIO, TO_CHAR(CB.FECHA_FINALIZACION, 'DD/MM/YYYY HH24:MI:SS') AS FECHA_FINALIZACION, CB.CODTRAMITE, CT.NOMBRE TRAMITE
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
                        AND CD.STATUS = 6
                        ORDER BY CD.FECHA DESC";

            }else if($filtro == 'realizado'){

                $query = "  SELECT CD.CODIGOCLASE, CD.DOCUMENTO, CD.ANIO, TO_CHAR(CD.FECHA, 'DD/MM/YYYY HH24:MI:SS') AS FECHA_INGRESO,  
                        CB.USER_APLIC USUARIO, TO_CHAR(CB.FECHA_FINALIZACION, 'DD/MM/YYYY HH24:MI:SS') AS FECHA_FINALIZACION, CB.CODTRAMITE, CT.NOMBRE TRAMITE
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
                        )
                        ORDER BY CD.FECHA DESC";

            }else if($filtro == 'todas'){

                $query = "  SELECT CD.CODIGOCLASE, CD.DOCUMENTO, CD.ANIO, TO_CHAR(CD.FECHA, 'DD/MM/YYYY HH24:MI:SS') AS FECHA_INGRESO,  
                        CB.USER_APLIC USUARIO, TO_CHAR(CB.FECHA_FINALIZACION, 'DD/MM/YYYY HH24:MI:SS') AS FECHA_FINALIZACION, CB.CODTRAMITE, CT.NOMBRE TRAMITE
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
                
                $query = "  INSERT INTO CATASTRO.CDO_CALIDAD_CC (DOCUMENTO, ANIO, FECHA, ACIERTO, COMENTARIO) VALUES ('$documento->DOCUMENTO', '$documento->ANIO', SYSDATE, 'S', '$comentario')";

                $stid = oci_parse($this->dbConn, $query);

                oci_execute($stid);

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
                
                //$comentario = addslashes($comentario);

               

                $comentario = explode("'", $comentario);

                if (count($comentario) > 1) {

                    $comentario = $comentario[0] . "''" .$comentario[1];  

                }else{

                    $comentario = $comentario[0];

                }
                
                $query = "  INSERT INTO CATASTRO.CDO_CALIDAD_CC (DOCUMENTO, ANIO, FECHA, ERROR, USUARIO, COMENTARIO) VALUES ('$documento->DOCUMENTO', '$documento->ANIO', SYSDATE, 'S', '$documento->USUARIO', '$comentario')";

                // $query = 'INSERT INTO CATASTRO.CDO_CALIDAD_CC (DOCUMENTO, ANIO, FECHA, ERROR, USUARIO, COMENTARIO) VALUES (' . '"' .$documento->DOCUMENTO . '",' . '"' .$documento->ANIO . '", "SYSDATE", "S", ' . '"' .$documento->USUARIO . '",' .  '"' . $comentario . '")';
                
                // $comentario;
                //$this->returnResponse(SUCCESS_RESPONSE, $comentario);


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

                /*
                $query_update = "   UPDATE CDO_DOCUMENTO SET STATUS = '1' 
                                    WHERE DOCUMENTO = '$documento->DOCUMENTO' AND ANIO = '$documento->ANIO' 
                                    AND CODIGOCLASE = 3";

                $stid = oci_parse($this->dbConn, $query_update);

                oci_execute($stid);
                */
                

            } catch (\Throwable $th) {
               


            }

        }

        public function obtener_detalle(){

            $data = [];

            $documento = (object) $this->param["documento"];

            // Si el expediente ha pasado el control de calidad
            $query = "  SELECT ID, DOCUMENTO, ANIO, TO_CHAR(FECHA, 'DD/MM/YYYY HH24:MI:SS') AS FECHA, ACIERTO, COMENTARIO
                        FROM CATASTRO.CDO_CALIDAD_CC 
                        WHERE DOCUMENTO = '$documento->DOCUMENTO'
                        AND ANIO = '$documento->ANIO'
                        AND ACIERTO IS NOT NULL";

            $stid = oci_parse($this->dbConn, $query);
            oci_execute($stid);

            $aciertos = [];
            
            while ($data = oci_fetch_array($stid, OCI_ASSOC)) {

                $aciertos [] = $data;

            }

            //$acierto = oci_fetch_array($stid, OCI_ASSOC);

            // Listado de errores

            $query = "  SELECT ID, DOCUMENTO, ANIO, TO_CHAR(FECHA, 'DD/MM/YYYY HH24:MI:SS') AS FECHA, ACIERTO, ERROR, COMENTARIO, USUARIO
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

                $query = "  SELECT DISTINCT(T2.ID_CRITERIO) AS CRITERIO, T3.NOMBRE
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

            

        }

    }

?>