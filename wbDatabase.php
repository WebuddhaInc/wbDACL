<?php

  /**********************************************************
  *
  * (c)2010 Webuddha, Holodyn Corporation
  *
  *   Database Class for WHMCS AddOns
  *
  *     new wbDatabase()
  *
  *     Public Methods
  *       Object    -> _construct( $config=array() )
  *       String    -> getCfgVal( $key )
  *       Object    -> getInstance()
  *       null      -> close_dbh()
  *       Reference -> &runQuery( $query )
  *       Reference -> runInsert( $tblName, $data, $xtra=null, $ignore=false )
  *       Reference -> runUpdate( $tblName, $data, $where, $xtra=null )
  *       Array     -> getRow( $item=0 )
  *       Array     -> getRows( $start=null, $limit=null )
  *       String    -> getValue( $field )
  *       Integer   -> getRowCount()
  *       Array     -> getFields( $tblName )
  *       Integer   -> getNextID( $tblName )
  *       Integer   -> getLastID()
  *       String    -> getErrMsg()
  *       Number    -> getErrNum()
  *       String    -> getEscaped( $str )
  *       Boolean   -> isNullDate( $val )
  *
  *     Private Methods
  *       null      -> _throwError( $msg=null, $num=400 )
  *
  *** CHANGELOG *********************************************
  *
  * v1.0.0 - Upgraded Class from whmcs_dbh
  * v1.1.0 - Updated for WHMCS v5.1.x series
  * v1.1.1 - Updated Charset Processing
  * v2.0.0 - Implemented MySQLi
  * v2.0.1 - Updated getRows() function
  * v2.0.2 - Upgrade Globals
  * v2.0.3 - Update global usage to preven PHP Notice
  * v2.0.4 - Added WHMCS definition to validation
  * v2.1.0 - Force MySQLi connection if type undefined
  *        - Add failover on persistent connection error
  * v2.1.1 - Replaced DS with DIRECTORY_SEPARATOR
  * v2.1.2 - Added PHP v5.3.0 requirement to MySQLi persistent connection
  *        - Add failover on persistent connection error
  * v2.1.3 - Corrected warning with isNullDate "Empty Needle"
  *
  **********************************************************/

class wbDatabase {

  /**********************************************************
  *
  **********************************************************/
  public $_dbh          = null;
  private $_driver      = 'mysql';
  private $_config      = null;
  private $_query       = null;
  private $_errorMsg    = null;
  private $_errorNum    = null;
  private $_result      = null;
  private $_nullDate    = '0000-00-00 00:00:00';
  static $_instance     = null;

  /**********************************************************
  *
  **********************************************************/
  public function __construct( $config=array() ) {

    // Default Config
      $this->_config = array(
        'type'    => 'mysqli',
        'port'    => null,
        'host'    => null,
        'name'    => null,
        'user'    => null,
        'pass'    => null,
        'hash'    => null,
        'prefix'  => null,
        'encode'  => null,
        'persist' => null
        );

    // Overwrite Config
      $persist = true;
      if( is_array($config) )
        foreach( $config AS $k => $v )
          if( array_key_exists($k,$this->_config) && $this->_config[$k] != $v ){
            $this->_config[$k] = $v;
            $persist = false;
          }
      $persist = !is_null($this->_config['persist']) ? $this->_config['persist'] : $persist;

    // MySQLi
      ob_start();
      if( $this->_config['type'] == 'mysqli' && class_exists('mysqli',false) ){
        $persist = version_compare(PHP_VERSION, '5.3.0', '>=') ? $persist : false;
        $this->_driver = 'mysqli';
        $this->_nullDate = '0000-00-00 00:00:00';
        $this->_dbh = new mysqli(
            ($persist ? 'p:' : '') . $this->_config['host'],
            $this->_config['user'],
            $this->_config['pass'],
            $this->_config['name'],
            $this->_config['port']
          );
        if( $persist && !is_null($this->_dbh) && $this->_dbh->connect_error )
          $this->_dbh = new mysqli(
              $this->_config['host'],
              $this->_config['user'],
              $this->_config['pass'],
              $this->_config['name'],
              $this->_config['port']
            );
        if( !$this->_dbh->connect_error ){
          // Character Encoding
          if( strlen($this->_config['encode']) )
            $this->runQuery("
              SET character_set_client = '". $this->getEscaped($this->_config['encode']) ."'
              , character_set_connection = '". $this->getEscaped($this->_config['encode']) ."'
              , character_set_results = '". $this->getEscaped($this->_config['encode']) ."'
              ");
        } else
          $this->_throwError('Unable to connect to database: ('.mysqli_connect_errno().') '.mysqli_connect_error(),500);
      }

    // ELSE MySQL
      else {
        $this->_driver = 'mysql';
        $this->_nullDate = '0000-00-00 00:00:00';
        if( $persist )
          $this->_dbh = mysql_pconnect(
            $this->_config['host'] . ($this->_config['port'] ? ':'.$this->_config['port'] : ''),
            $this->_config['user'],
            $this->_config['pass']
            );
        if( !$persist || is_null($this->_dbh) )
          $this->_dbh = mysql_connect(
            $this->_config['host'] . ($this->_config['port'] ? ':'.$this->_config['port'] : ''),
            $this->_config['user'],
            $this->_config['pass'],
            true);
        if( $this->_dbh ){
          if( $this->_dbh !== $whmcsmysql ){
            // Select Database
            mysql_select_db(
              $this->_config['name'],
              $this->_dbh
              ) or $this->_throwError('Unable to select database',500);
            // Character Encoding
            if( strlen($this->_config['encode']) && function_exists('mysql_set_charset') )
              mysql_set_charset($this->_config['encode'], $this->_dbh);
          }
        } else
          $this->_throwError('Unable to connect to database',500);
      }
      ob_end_clean();
      if( is_null(self::$_instance) )
        self::$_instance =& $this;

  } // ->__construct

  /**********************************************************
  *
  **********************************************************/
  public function getCfgVal( $key ) {
    return $this->_config[ $key ];
  } // ->getCfgVal

  /**********************************************************
  *
  **********************************************************/
  public static function &getInstance() {
    if( is_null(self::$_instance) )
      self::$_instance = new wbDatabase();
    $instance = clone self::$_instance;
    return $instance;
  } // ->getInstance

  /**********************************************************
  *
  **********************************************************/
  public function close_dbh() {
    if( $this->_driver == 'mysqli' )
      mysqli_close($this->_dbh);
    else
      mysql_close($this->_dbh);
  } // ->close_dbh

  /**********************************************************
  *
  **********************************************************/
  public function &runQuery( $query ){
    $this->_query = trim( ($this->_config['prefix'] ? preg_replace('/\#__/', $this->_config['prefix'], $query) : $query) );
    if( strlen($this->_query) ){
      if( $this->_driver == 'mysqli' ){
        if( is_resource($this->_result) )
          mysqli_free_result( $this->_result );
        $this->_result = mysqli_query($this->_dbh, $this->_query);
        if( !$this->_result )
          $this->_throwError();
        return $this->_result;
      } else {
        if( is_resource($this->_result) )
          mysql_free_result( $this->_result );
        $this->_result = mysql_query($this->_query, $this->_dbh);
        if( !$this->_result )
          $this->_throwError();
        return $this->_result;
      }
    }
    $this->_throwError('Invalid Query');
    return null;
  } // ->runQuery

  /**********************************************************
  *
  **********************************************************/
  public function runInsert( $tblName, $data, $xtra=null, $ignore=false ){
    $keys = $vals = array();
    foreach( $data AS $k=>$v ){
      $keys[] = $this->getEscaped($k);
      $vals[] = is_null($v) ? 'NULL' : "'" . $this->getEscaped($v) . "'";
    }
    return $this->runQuery("INSERT ".($ignore?'IGNORE':'')." INTO `". $this->getEscaped($tblName) ."` (`". implode('`,`',$keys) ."`) VALUES (". implode(',',$vals) .") ". $xtra);
  } // ->runInsert

  /**********************************************************
  *
  **********************************************************/
  public function runUpdate( $tblName, $data, $where, $xtra=null ){
    if( !count($where) )
      $this->_throwError('Missing Where Filters for Update Query',500);
    $pairs = array();
    foreach( $data AS $k=>$v )
      $pairs[] = "`".$this->getEscaped($k)."` = " . (is_null($v) ? 'NULL' : "'" . $this->getEscaped($v) . "'");
    return $this->runQuery("UPDATE `". $this->getEscaped($tblName) ."` SET ". implode(', ',$pairs) ." WHERE ". implode(' AND ',$where) . ' ' . $xtra);
  } // ->runUpdate

  /**********************************************************
  *
  **********************************************************/
  public function getRow( $item = null ) {
    $num  = $this->getRowCount();
    $row  = Array();
    if( $this->_driver == 'mysqli' ){
      if( !is_null($item) && mysqli_data_seek($this->_result, $item) === false )
        return $row;
      return mysqli_fetch_assoc($this->_result);
    } else {
      if( !is_null($item) && mysql_data_seek($this->_result, $item) === false )
        return $row;
      return mysql_fetch_assoc($this->_result);
    }
  } // ->getRow

  /**********************************************************
  *
  **********************************************************/
  public function getRows( $start = null, $limit = null ) {
    $num  = $this->getRowCount();
    $rows = Array();
    if( $this->_driver == 'mysqli' ){
      if( !is_null($start) && mysqli_data_seek($this->_result, $start) === false )
        return $rows;
      for( $i=0; $i<($num-(is_null($start)?0:$start)); $i++ )
        if( is_null($limit) || count($rows) < $limit )
          $rows[] = mysqli_fetch_assoc($this->_result);
      return $rows;
    } else {
      if( !is_null($start) && mysql_data_seek($this->_result, $start) === false )
        return $rows;
      for( $i=0; $i<($num-(is_null($start)?0:$start)); $i++ )
        if( is_null($limit) || count($rows) < $limit )
          $rows[] = mysql_fetch_assoc($this->_result);
      return $rows;
    }
  } // ->getRows

  /**********************************************************
  *
  **********************************************************/
  public function getValue( $field = null ) {
    if( $this->_driver == 'mysqli' ){
      if( $this->getRowCount() > 0 ){
        $row = mysqli_fetch_assoc($this->_result);
        if( count(array_keys($row)) ){
          if( !is_null($field) )
            return $row[$field];
          $keys = array_keys($row);
          return $row[ $keys[0] ];
        }
      }
    } else {
      if( $this->getRowCount() > 0 ){
        $row = mysql_fetch_assoc($this->_result);
        if( count(array_keys($row)) ){
          if( !is_null($field) )
            return $row[$field];
          $keys = array_keys($row);
          return $row[ $keys[0] ];
        }
      }
    }
    return null;
  } // ->getValue

  /**********************************************************
  *
  **********************************************************/
  public function getRowCount() {
    if( $this->_driver == 'mysqli' )
      return mysqli_num_rows($this->_result);
    else
      return mysql_numrows($this->_result);
  } // ->getRowCount

  /**********************************************************
  *
  **********************************************************/
  public function getFields( $tblName ) {
    $this->runQuery('SHOW COLUMNS FROM `'.$this->getEscaped($tblName).'`');
    $rows = $this->getRows(); $fields = Array();
    foreach( $rows AS $row ) $fields[] = $row['Field'];
    return $fields;
  } // ->getFields

  /**********************************************************
  *
  **********************************************************/
  public function getNextID( $tblName ) {
    $this->runQuery("SHOW TABLE STATUS LIKE '". $this->getEscaped($tblName) ."'");
    return $this->getValue('Auto_increment');
  } // ->getNextID

  /**********************************************************
  *
  **********************************************************/
  public function getLastID() {
    if( $this->_driver == 'mysqli' )
      return mysqli_insert_id( $this->_dbh );
    else
      return mysql_insert_id( $this->_dbh );
  } // ->getLastID

  /**********************************************************
  *
  **********************************************************/
  public function getErrMsg() {
    return (string)$this->_errorMsg;
  } // ->getErrMsg

  /**********************************************************
  *
  **********************************************************/
  public function getErrNum() {
    return (int)$this->_errorNum;
  } // ->getErrNum

  /**********************************************************
  *
  **********************************************************/
  public function getEscaped( $str ) {
    if( $this->_driver == 'mysqli' )
      return (string)mysqli_real_escape_string( $this->_dbh, $str );
    else
      return (string)mysql_real_escape_string( $str, $this->_dbh );
  } // ->getEscaped

  /**********************************************************
  *
  **********************************************************/
  public function getNullDate(){
    return $this->_nullDate;
  } // ->getNullDate

  /**********************************************************
  *
  **********************************************************/
  public function isNullDate( $val ){
    if( is_null($val) || (string)$val == (string)$this->_nullDate )
      return true;
    return false;
  } // ->isNullDate

  /**********************************************************
  *
  **********************************************************/
  private function _throwError( $msg=null, $num=400 ){
    if( !is_null($msg) ){
      $this->_errorMsg = 'wbDatabase:msg'.$msg;
      $this->_errorNum = $num;
      if($this->_errorNum < 500){
        echo '<div class="error_msg">'. $this->_errorMsg .'</div>';
        return;
      }
    } else if( $this->_driver == 'mysqli' ){
      $this->_errorMsg = mysqli_error( $this->_dbh );
      $this->_errorNum = mysqli_errno( $this->_dbh );
    } else {
      $this->_errorMsg = mysql_error( $this->_dbh );
      $this->_errorNum = mysql_errno( $this->_dbh );
    }
    die( 'wbDatabase fatal error<br>errno: '.$this->_errorNum.'<br>errmsg: '.$this->_errorMsg);
  } // ->_throwError

} // class-wbDatabase

/**********
  END
***********/
