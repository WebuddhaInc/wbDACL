<?php

class wb_dacl {

  private $_dbh         = null;
  private $_errorMsg    = null;
  private $_errorNum    = null;
  private $_keyTrx      = '/[^a-z0-9\-\_\.]/';
  private $_treeStep    = 5000;
  private $_treeMaxCalc = 0.01;
  private $_treeLftMin  = 0;
  private $_treeRgtMax  = 18446744073709551615; // PHP_INT_MAX;

  /************************************************************************************************************************
  *
  *
  *
  ************************************************************************************************************************/
  public function __construct(){
    $this->_dbh = wbDatabase::getInstance();
  }

  /************************************************************************************************************************
  *
  *
  *
  ************************************************************************************************************************/
  public function __destruct(){
    $this->_dbh->close_dbh();
  }

  /************************************************************************************************************************
  *
  *
  *
  ************************************************************************************************************************/
  private static function &getInstance(){
    if( isset($GLOBALS['wb_dacl_instance']) )
      return $GLOBALS['wb_dacl_instance'];
    $GLOBALS['wb_dacl_instance'] = new self();
    return $obj;
  }

  /*
   *
   *
   * Shared Functions
   *
   *
   */

  /************************************************************************************************************************
  *
  *
  *
  ************************************************************************************************************************/

    public function get_dacl( $type, $type_chain, $type_pid = null, $type_extra = null ){
      // Validate Type
        if( !in_array($type, explode(',','aro,aco,acl')) ){
          die('Invalid Object Type: '.$type);
        }
        $type_prefix      = $type.'_';
        $type_table       = '#__'.$type;
      // Prepare Where
        $where = array();
        if( !empty($type_pid) ){
          $where[] = "`{$type_prefix}pid` = '". (int)$type_pid ."'";
          $where[] = "`{$type_prefix}key` = '". $this->_dbh->getEscaped($type_chain) ."'";
        }
        else {
          $where[] = "`{$type_prefix}chain` = '". $this->_dbh->getEscaped($type_chain) ."'";
        }
      // Specific Fields
        if( is_array($type_extra) ){
          switch( $type ){
            case 'acl':
              $where[] = "`aro_id` = '". (int)$type_extra['aro_id'] ."'";
              $where[] = "`aco_id` = '". (int)$type_extra['aco_id'] ."'";
              break;
            default:
              break;
          }
        }
      // Finalize
        $type_chain = preg_replace($this->_keyTrx,'',$type_chain);
        $this->_dbh->runQuery("
          SELECT *
          FROM `{$type_table}`
          WHERE ". implode(' AND ', $where) ."
          LIMIT 1
          ");
        return $this->_dbh->getRow();
    }

  /************************************************************************************************************************
  *
  *
  *
  ************************************************************************************************************************/

    public function get_dacl_closest( $type, $type_chain, $type_extra = null ){
      // Validate Type
        if( !in_array($type, explode(',','aro,aco,acl')) ){
          die('Invalid Object Type: '.$type);
        }
        $type_prefix      = $type.'_';
        $type_table       = '#__'.$type;
      // Prepare Where
        $where = array();
        if( !empty($type_pid) ){
          $where[] = "`{$type_prefix}pid` = '". (int)$type_pid ."'";
          $where[] = "`{$type_prefix}key` = '". $this->_dbh->getEscaped($type_chain) ."'";
        }
        else {
          $chain_set  = explode('.', $type_chain);
          $lookup_set = array();
          $tmp_where  = array();
          while( count($chain_set) ){
            $lookup_set = implode('.', $chain_set);
            $tmp_where[] = "`{$type_prefix}chain` = '". $this->_dbh->getEscaped($lookup_set) ."'";
            array_pop( $chain_set );
          }
          $where[] = '(' . implode(' OR ', $tmp_where) . ')';
        }
      // Specific Fields
        if( is_array($type_extra) ){
          switch( $type ){
            case 'acl':
              $where[] = "`aro_id` = '". (int)$type_extra['aro_id'] ."'";
              $where[] = "`aco_id` = '". (int)$type_extra['aco_id'] ."'";
              break;
            default:
              break;
          }
        }
      // Finalize
        $type_chain = preg_replace($this->_keyTrx,'',$type_chain);
        $this->_dbh->runQuery("
          SELECT *
          FROM `{$type_table}`
          WHERE ". implode(' AND ', $where) ."
          ORDER BY `{$type_prefix}level` DESC
          LIMIT 1
          ");
        return $this->_dbh->getRow();
    }

  /************************************************************************************************************************
  *
  *
  *
  ************************************************************************************************************************/

    public function store_dacl( $type, $type_chain, $type_label, $type_data = null, $type_extra = null, $type_pid = null ){
      // Validate Type
        if( !in_array($type, explode(',','aro,aco,acl')) ){
          die('Invalid Object Type: '.$type);
        }
        $type_prefix      = $type.'_';
        $type_table       = '#__'.$type;
      // Create Object
        $new_pid          = $type_pid;
        $new_rid          = null;
        $new_level        = 0;
        $new_id_set       = array();
        $type_chain_set   = explode('.', preg_replace($this->_keyTrx,'',$type_chain));
        $count_created    = 0;
        for( $i=0; $i<count($type_chain_set); $i++ ){
          // Lookup
            $new_key = $type_chain_set[$i];
          // Record Exists / Update
            if( $row = $this->get_dacl( $type, $new_key, $new_pid, $type_extra ) ) {
              $new_pid      = $row[$type_prefix.'id'];
              $new_id_set[] = $new_pid;
            }
          // New Record
            else {
              // Prepare
                $new_lft    = 0;
                $new_rgt    = $this->_treeRgtMax;
                $new_chain  = implode('.', array_slice($type_chain_set,0,$i+1));
                if( empty($type_label) ){
                  $type_label = $new_key;
                }
              // Core Fields
                $newRow = array(
                  $type_prefix.'pid'        => $new_pid,
                  $type_prefix.'rid'        => is_null($new_rid) ? $this->_dbh->getNextID($type_table) : $new_rid,
                  $type_prefix.'level'      => $this->_bigInt($new_level),
                  $type_prefix.'children'   => 0,
                  $type_prefix.'lft'        => $new_lft,
                  $type_prefix.'rgt'        => $new_rgt,
                  $type_prefix.'key'        => $new_key,
                  $type_prefix.'chain'      => $new_chain,
                  $type_prefix.'data'       => ($i == count($type_chain_set)-1 ? $type_data : null)
                  );
              // Specific Fields
                switch( $type ){
                  case 'aro':
                  case 'aco':
                    $newRow[$type_prefix.'label'] = ($i == count($type_chain_set)-1 ? $type_label : $new_key);
                    break;
                  case 'acl':
                    $newRow['aro_id']   = null;
                    $newRow['aco_id']   = null;
                    $newRow['acl_rule'] = 'allow';
                    break;
                }
              // Merge Extra
                if( is_array($type_extra) ){
                  $newRow = array_merge( $newRow, $type_extra );
                }
              // Load / Verify Parent
                if( !is_null($new_pid) ){
                  // Pull Records
                    $this->_dbh->runQuery("
                      SELECT `{$type_prefix}id`
                        , `{$type_prefix}rid`
                        , `{$type_prefix}level`
                        , `{$type_prefix}key`
                        , `{$type_prefix}chain`
                        , `{$type_prefix}lft` AS `{$type_prefix}lft_min`
                        , (SELECT `{$type_prefix}lft` FROM `{$type_table}` WHERE `{$type_prefix}pid` = '". $this->_bigInt($new_pid) ."' ORDER BY `{$type_prefix}lft` ASC LIMIT 1) AS `{$type_prefix}lft_max`
                        , (SELECT `{$type_prefix}rgt` FROM `{$type_table}` WHERE `{$type_prefix}pid` = '". $this->_bigInt($new_pid) ."' ORDER BY `{$type_prefix}rgt` DESC LIMIT 1) AS `{$type_prefix}rgt_min`
                        , `{$type_prefix}rgt` AS `{$type_prefix}rgt_max`
                      FROM `{$type_table}`
                      WHERE `{$type_prefix}id` = '". $this->_bigInt($new_pid) ."'
                      ");
                    if( !$this->_dbh->getRowCount() ){
                      throw new Exception(strtoupper($type).' Parent Not Found: ' . $new_pid);
                      return false;
                    }
                    $parentRow  = $this->_dbh->getRow();
                    $new_rid    = $this->_bigInt($parentRow[$type_prefix.'rid']);
                    $new_level  = $this->_bigInt($parentRow[$type_prefix.'level'] + 1);
                  // Error
                    if( $parentRow[$type_prefix.'lft_min'] >= $parentRow[$type_prefix.'rgt_max'] ){
                      $new_lft = 0;
                      $new_rgt = 0;
                    }
                  // No Children
                    else if( is_null($parentRow[$type_prefix.'lft_max']) && is_null($parentRow[$type_prefix.'rgt_min']) ){
                      $new_lft = $this->_bigInt($parentRow[$type_prefix.'lft_min'] + 1);
                      $new_rgt = $this->_bigInt($parentRow[$type_prefix.'lft_min'] + round(($parentRow[$type_prefix.'rgt_max'] - $parentRow[$type_prefix.'lft_min']) * ($new_level * $this->_treeMaxCalc)));
                    }
                  // Next Child
                    else {
                      $new_lft = $this->_bigInt($parentRow[$type_prefix.'rgt_min'] + 1);
                      $new_rgt = $this->_bigInt($parentRow[$type_prefix.'rgt_min'] + round(($parentRow[$type_prefix.'rgt_max'] - $parentRow[$type_prefix.'rgt_min']) * ($new_level * $this->_treeMaxCalc)));
                    }
                  // Next Chain
                    $new_chain = $parentRow[$type_prefix.'chain'].'.'.$new_key;
                  // Merge
                    $newRow = array_merge( $newRow, array(
                      $type_prefix.'rid'    => $new_rid,
                      $type_prefix.'lft'    => $new_lft,
                      $type_prefix.'rgt'    => $new_rgt,
                      $type_prefix.'level'  => $new_level,
                      $type_prefix.'chain'  => $new_chain
                      ) );
                }
              // Insert Object
                $this->_dbh->runInsert($type_table, $newRow);
                $new_id_set[] = $this->_dbh->getLastID();
                $count_created++;
              // Update Parent Child Count
                if( !is_null($new_pid) ){
                  $this->_dbh->runQuery("
                    UPDATE `{$type_table}`
                    SET `{$type_prefix}children` = `{$type_prefix}children` + 1
                    WHERE `{$type_prefix}id` = '". $this->_bigInt($new_pid) ."'
                    ");
                }
              // Update PID for next key
                $new_pid = end($new_id_set);
              // Rebuild if Necessary
                if( $new_lft >= $new_rgt ){
                  inspect( 'rebuild...', $newRow ); die();
                  $this->_rebuild_dacl( $type, $new_rid );
                  die();
                }
            }
          // Fallback Root ID
            if( is_null($new_rid) ){
              $new_rid = $new_pid;
            }
        }
        return $count_created ? end($new_id_set) : false;
    }

    /************************************************************************************************************************
    *
    *
    *
    ************************************************************************************************************************/
    public function rebuild_dacl( $type, $type_key = null ){
      $type_rid = null;
      if( !is_null($type_key) ){
        $row = call_user_method_array('get_'.$type, $this, array($type_key));
        if( !$row ){
          throw new Exception(strtoupper($type) . ' Key Not Found: ' . $type_key);
          return false;
        }
        $type_rid = $row[$type . '_rid'];
      }
      return $this->_rebuild_dacl( $type, $type_rid );
    }

    /************************************************************************************************************************
    *
    *
    *
    ************************************************************************************************************************/
    private function _rebuild_dacl( $type, $type_rid = null, $type_id = null, $lft = null, $children = true, $rgt_max = null ){
      // Validate Type
        if( !in_array($type, explode(',','aro,aco,acl')) ){
          die('Invalid Object Type: '.$type);
        }
        $type_prefix      = $type.'_';
        $type_table       = '#__'.$type;
      // Determine Left
        if( is_null($lft) ){
          if( !empty($type_id) ){
            $this->_dbh->runQuery("
              SELECT `{$type_prefix}lft`
                , `{$type_prefix}rgt`
              FROM `{$type_table}`
              WHERE `{$type_prefix}id` = '". intval($type_id) ."'
              ");
            $lft = $this->_bigInt($this->_dbh->getValue());
          }
          if( empty($lft) ){
            $lft = 0;
          }
        }
      // Determine Right
        if( is_null($rgt_max) ){
          $rgt_max = $this->_treeRgtMax;
        }
        $rgt = $lft;
        if( $children ){
          $this->_dbh->runQuery("
            SELECT `{$type_prefix}id`
              , `{$type_prefix}rid`
              , `{$type_prefix}children`
            FROM `{$type_table}`
            ". (
            is_null($type_rid)
              ? "
                WHERE `{$type_prefix}id` = `{$type_prefix}rid`
                "
              : "
                WHERE `{$type_prefix}rid` = '". $this->_bigInt($type_rid) ."'
                  AND `{$type_prefix}pid` ". (is_null($type_id) ? "IS NULL" : "= '". intval($type_id) ."'") ."
                ORDER BY `{$type_prefix}key`
              "
              ) ."
            ");
          $rows = $this->_dbh->getRows();
          foreach( $rows AS $row ){
            $rgt = $this->_rebuild_dacl(
                      $type,
                      $row[$type_prefix . 'rid'],
                      $row[$type_prefix . 'id'],
                      is_null($type_id) ? 0 : $rgt + 1,
                      $row[$type_prefix . 'children'],
                      $rgt_max
                      );
            $rgt += $this->_treeStep - 1;
          }
          $rgt += $this->_treeStep * count($rows);
        }
        if( $lft == $rgt ){
          $rgt += $this->_treeStep;
        }
        if( $rgt > $rgt_max ){
          die( __FILE__ . ' - ' . __LINE__ . ' ... exceeding rgt_max in rebuild ... ' );
        }
      // Update Record
        if( $type_id ){
          $this->_dbh->runQuery("
            UPDATE `{$type_table}`
            SET `{$type_prefix}lft` = '". intval($lft) ."'
              , `{$type_prefix}rgt` = '". intval($rgt) ."'
            WHERE `{$type_prefix}id` = '". intval($type_id) ."'
            ");
        }
      // Return
        return $rgt + 1;
    }


  /*
   *
   *
   * ARO Functions
   *
   *
   */

  /************************************************************************************************************************
  *
  *
  *
  ************************************************************************************************************************/

  public function create_aro( $aro_chain, $aro_label = null, $aro_pid = null, $aro_data = null ){
    return $this->store_dacl( 'aro', $aro_chain, $aro_label, $aro_data, array(), $aro_pid );
  }

  /************************************************************************************************************************
  *
  *
  *
  ************************************************************************************************************************/
  public function get_aro( $aro_chain ){
    return $this->get_dacl( 'aro', $aro_chain );
  }

  /************************************************************************************************************************
  *
  *
  *
  ************************************************************************************************************************/
  public function get_aro_closest( $aro_chain ){
    return $this->get_dacl_closest( 'aro', $aro_chain );
  }

  /************************************************************************************************************************
  *
  *
  *
  ************************************************************************************************************************/
  public function get_aro_val( $aro_chain, $colKey ){
    $row = $this->get_aro( $aro_chain );
    if( $row && array_key_exists($colKey, $row) )
      return $row[ $colKey ];
    return null;
  }

  /************************************************************************************************************************
  *
  *
  *
  ************************************************************************************************************************/
  public function get_aro_tree( $aro_chain = null ){
    $tree = array();
    if( is_null($aro_chain) ){
      $this->_dbh->runQuery("
        SELECT `aro_key`
        FROM `#__aro`
        WHERE `aro_id` = `aro_rid`
        ");
      $rows = $this->_dbh->getRows();
      $treeRows = array();
      foreach( $rows AS $row ){
        $treeRows = array_merge( $treeRows, $this->get_aro_tree( $row['aro_key'] ) );
      }
      return $treeRows;
    }
    else {
      $row = $this->get_aro( $aro_chain );
    }
    $base_level = $row['aro_level'];
    $this->_dbh->runQuery("
      SELECT *
      FROM `#__aro`
      WHERE `aro_rid` = '". $this->_bigInt($row['aro_rid']) ."'
        AND `aro_lft` BETWEEN '". $row['aro_lft'] ."' AND '". $row['aro_rgt'] ."'
      ORDER BY `aro_lft` ASC
      ");
    return $this->_dbh->getRows();
  }

  /************************************************************************************************************************
  *
  *
  *
  ************************************************************************************************************************/
  public function rebuild_aro( $aro_chain = null ){
    $aro_rid = null;
    if( !is_null($aro_chain) ){
      $row = $this->get_aro( $aro_chain );
      if( !$row ){
        throw new Exception('ARO Key Not Found');
        return false;
      }
      $aro_rid = $row['aro_rid'];
    }
    return $this->_rebuild_dacl( 'aro', $aro_rid );
  }

  /************************************************************************************************************************
  *
  * Attempt to predict boundaries and reduce calculations
  *
  ************************************************************************************************************************/
  public function rebuild_aro_alt( $aro_chain = null ){
    $aro_rid = null;
    if( !is_null($aro_chain) ){
      $row = $this->get_aro( $aro_chain );
      if( !$row ){
        throw new Exception('ARO Key Not Found');
        return false;
      }
      $aro_rid = $row['aro_rid'];
    }
    return $this->_rebuild_aro_alt( $aro_rid );
  }

  /*
   *
   *
   * ACO Functions
   *
   *
   */

  /************************************************************************************************************************
  *
  *
  *
  ************************************************************************************************************************/
  public function create_aco( $aco_chain, $aco_label = null, $aco_pid = null, $aco_data = 1 ){
    return $this->store_dacl( 'aco', $aco_chain, $aco_label, $aco_data, array(), $aco_pid );
  }

  /************************************************************************************************************************
  *
  *
  *
  ************************************************************************************************************************/
  public function get_aco( $aco_chain = null ){
    return $this->get_dacl( 'aco', $aco_chain );
  }

  /************************************************************************************************************************
  *
  *
  *
  ************************************************************************************************************************/
  public function get_aco_closest( $aco_chain ){
    return $this->get_dacl_closest( 'aco', $aco_chain );
  }

  /************************************************************************************************************************
  *
  *
  *
  ************************************************************************************************************************/
  public function get_aco_val( $keyStr, $rowKey ){
    $row = $this->get_aco( $keyStr );
    if( $row && array_key_exists($rowKey,$row) )
      return $row[$rowKey];
    return null;
  }

  /************************************************************************************************************************
  *
  *
  *
  ************************************************************************************************************************/
  public function get_aco_tree( $aco_chain = null ){
    $tree = array();
    if( is_null($aco_chain) ){
      $this->_dbh->runQuery("
        SELECT `aco_key`
        FROM `#__aco`
        WHERE `aco_id` = `aco_rid`
        ");
      $rows = $this->_dbh->getRows();
      $treeRows = array();
      foreach( $rows AS $row ){
        $treeRows = array_merge( $treeRows, $this->get_aco_tree( $row['aco_key'] ) );
      }
      return $treeRows;
    }
    else {
      $row = $this->get_aco( $aco_chain );
    }
    $base_level = $row['aco_level'];
    $this->_dbh->runQuery("
      SELECT *
      FROM `#__aco`
      WHERE `aco_rid` = '". $this->_bigInt($row['aco_rid']) ."'
        AND `aco_lft` BETWEEN '". $row['aco_lft'] ."' AND '". $row['aco_rgt'] ."'
      ORDER BY `aco_lft` ASC
      ");
    return $this->_dbh->getRows();
  }

  /************************************************************************************************************************
  *
  *
  *
  ************************************************************************************************************************/
  public function rebuild_aco( $aco_chain = null ){
    $aco_rid = null;
    if( !is_null($aco_chain) ){
      $row = $this->get_aco( $aco_chain );
      if( !$row ){
        throw new Exception('ARO Key Not Found');
        return false;
      }
      $aco_rid = $row['aco_rid'];
    }
    return $this->_rebuild_dacl( 'aco', $aco_rid );
  }

  /*
   *
   *
   * ACL Functions
   *
   *
   */

  /************************************************************************************************************************
  *
  *
  *
  ************************************************************************************************************************/
  public function create_acl( $aro_chain, $aco_chain, $acl_chain, $acl_rule = 'allow', $acl_pid = null, $acl_status = 0, $acl_data = null ){

    // Lookup ARO
      $aro_record = $this->get_aro( $aro_chain );
      if( empty($aro_record) ){
        throw new Exception('ARO Not Found: ' . $aro_chain);
        return false;
      }

    // Lookup ACO
      $aco_record = $this->get_aco( $aco_chain );
      if( empty($aco_record) ){
        throw new Exception('ACO Not Found: ' . $aco_chain);
        return false;
      }
    // Prepare
      return $this->store_dacl( 'acl', $acl_chain, null, $acl_data, array(
        'aro_id'    => $aro_record['aro_id'],
        'aco_id'    => $aco_record['aco_id'],
        'acl_rule'  => ((is_bool($acl_rule) && $acl_rule === false) || (is_string($acl_rule) && $acl_rule == 'deny') ? 'deny' : 'allow')
        ), $acl_pid );

  }

  /************************************************************************************************************************
  *
  *
  *
  ************************************************************************************************************************/
  public function get_acl( $aro_chain, $aco_chain, $acl_chain ){

    // Lookup ARO
      $aro_record = $this->get_aro( $aro_chain );
      if( empty($aro_record) ){
        throw new Exception('ARO Not Found: ' . $aro_chain);
        return false;
      }

    // Lookup ACO
      $aco_record = $this->get_aco( $aco_chain );
      if( empty($aco_record) ){
        throw new Exception('ACO Not Found: ' . $aco_chain);
        return false;
      }

    // Return
      return $this->get_dacl( 'acl', $acl_chain, null, array(
        'aro_id' => $aro_record['aro_id'],
        'aco_id' => $aco_record['aco_id']
        ) );

  }

  /************************************************************************************************************************
  *
  *
  *
  ************************************************************************************************************************/
  public function get_acl_closest( $aro_chain, $aco_chain, $acl_chain ){

    // Lookup ARO
      $aro_record = $this->get_aro_closest( $aro_chain );
      if( empty($aro_record) ){
        throw new Exception('ARO Not Found: ' . $aro_chain);
        return false;
      }

    // Lookup ACO
      $aco_record = $this->get_aco_closest( $aco_chain );
      if( empty($aco_record) ){
        throw new Exception('ACO Not Found: ' . $aco_chain);
        return false;
      }

    // Prepare
      $acl_chain  = preg_replace($this->_keyTrx,'',$acl_chain);
      $chain_set  = explode('.', $acl_chain);
      $lookup_set = array();
      $where_set  = array();
      while( count($chain_set) ){
        $lookup_set = implode('.', $chain_set);
        $where_set[] = "`acl_chain` = '". $this->_dbh->getEscaped($lookup_set) ."'";
        array_pop( $chain_set );
      }

    // Query Object
      $this->_dbh->runQuery("
        SELECT *
        FROM `#__acl`
        WHERE `aro_id` = (
            SELECT `c`.`aro_id`
            FROM `#__aro` AS `p`
            LEFT JOIN `#__aro` AS `c` ON (
              `c`.`aro_rid` = `p`.`aro_rid`
              AND `c`.`aro_lft` <= `p`.`aro_lft`
              AND `c`.`aro_rgt` >= `p`.`aro_rgt`
              AND `c`.`aro_status` = '1'
              )
            WHERE `p`.`aro_id` = '". $this->_bigInt($aro_record['aro_id']) ."'
              AND `p`.`aro_status` = '1'
            ORDER BY `c`.`aro_level` DESC
            LIMIT 1
          )
          AND `aco_id` = (
            SELECT `c`.`aco_id`
            FROM `#__aco` AS `p`
            LEFT JOIN `#__aco` AS `c` ON (
              `c`.`aco_rid` = `p`.`aco_rid`
              AND `c`.`aco_lft` <= `p`.`aco_lft`
              AND `c`.`aco_rgt` >= `p`.`aco_rgt`
              AND `c`.`aco_status` = '1'
              )
            WHERE `p`.`aco_id` = '". $this->_bigInt($aco_record['aco_id']) ."'
              AND `p`.`aco_status` = '1'
            ORDER BY `c`.`aco_level` DESC
            LIMIT 1
          )
          AND (". implode(' OR ', $where_set) .")
          AND `acl_status` = '1'
        ORDER BY `acl_level` DESC
        LIMIT 1
        ");

    // Return
      return $this->_dbh->getRow();

  }

  /************************************************************************************************************************
  *
  *
  *
  ************************************************************************************************************************/
  public function check_acl( $aro_chain, $aco_chain, $acl_chain, $default_rule = 'deny' ){

    // Lookup ACL
      $acl_record = $this->get_acl_closest( $aro_chain, $aco_chain, $acl_chain );

    // Return
      return (empty($acl_record) ? $default_rule : $acl_record['acl_rule']) == 'allow';

  }

  /************************************************************************************************************************
  *
  *
  *
  ************************************************************************************************************************/
  public function rebuild_acl( $aro_chain = null, $aco_chain = null, $acl_chain = null ){
    $acl_rid = null;
    if( !is_null($acl_chain) ){
      $row = $this->get_acl( $acl_chain );
      if( !$row ){
        throw new Exception('ACL Key Not Found');
        return false;
      }
      $acl_rid = $row['acl_rid'];
    }
    return $this->_rebuild_dacl( 'acl', $acl_rid );
  }

  /************************************************************************************************************************
  *
  *
  *
  ************************************************************************************************************************/

  /**
   * [_bigInt description]
   * @param  [type] $bigInt [description]
   * @return [type]         [description]
   */
  private function _bigInt( $bigInt ){
    return number_format($bigInt,0,'','');
  }

}