<?php

class wb_dacl {

  private $_dbh = null;
  private $_errorMsg  = null;
  private $_errorNum  = null;

  private $_keyTrx = '/[^a-z0-9\-\_\.]/';
  private $_treeStep      = 5000;
  private $_treeMaxCalc   = 0.10;
  private $_treeLftMin    = 0;
  private $_treeRgtMax    = PHP_INT_MAX;

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

    public function get_dacl( $type, $type_chain, $type_pid = null ){
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
            if( $row = $this->get_dacl( $type, $new_key, $new_pid ) ) {
              $new_pid      = $row[$type_prefix.'id'];
              $new_id_set[] = $new_pid;
            }
          // New Record
            else {
              // Prepare
                $new_lft   = 0;
                $new_rgt   = $this->_treeRgtMax;
                $new_chain = implode('.', array_slice($type_chain_set,0,$i+1));
              // Determine Boundaries
                $newRow = array(
                  $type_prefix.'pid'        => $new_pid,
                  $type_prefix.'rid'        => is_null($new_rid) ? $this->_dbh->getNextID($type_table) : $new_rid,
                  $type_prefix.'level'      => (int)$new_level,
                  $type_prefix.'children'   => 0,
                  $type_prefix.'lft'        => $new_lft,
                  $type_prefix.'rgt'        => $new_rgt,
                  $type_prefix.'key'        => $new_key,
                  $type_prefix.'chain'      => $new_chain,
                  $type_prefix.'label'      => ($i == count($type_chain_set)-1 ? $type_label : $new_key),
                  $type_prefix.'data'       => ($i == count($type_chain_set)-1 ? $type_data : null)
                  );
              // Merge Extra
                if( is_array($type_extra) ){
                  $newRow = array_merge( $newRow, $type_extra );
                }
              // Load / Verify Parent
                if( !is_null($new_pid) ){
                  // Pull Record
                    $this->_dbh->runQuery("
                      SELECT `{$type_prefix}id`
                        , `{$type_prefix}rid`
                        , `{$type_prefix}level`
                        , `{$type_prefix}key`
                        , `{$type_prefix}chain`
                        , `{$type_prefix}lft` AS `{$type_prefix}lft_min`
                        , (SELECT `{$type_prefix}lft` FROM `{$type_table}` WHERE `{$type_prefix}pid` = '". (int)$new_pid ."' ORDER BY `{$type_prefix}lft` ASC LIMIT 1) AS `{$type_prefix}lft_max`
                        , (SELECT `{$type_prefix}rgt` FROM `{$type_table}` WHERE `{$type_prefix}pid` = '". (int)$new_pid ."' ORDER BY `{$type_prefix}rgt` DESC LIMIT 1) AS `{$type_prefix}rgt_min`
                        , `{$type_prefix}rgt` AS `{$type_prefix}rgt_max`
                      FROM `{$type_table}`
                      WHERE `{$type_prefix}id` = '". (int)$new_pid ."'
                        ");
                    if( !$this->_dbh->getRowCount() ){
                      throw new Exception('ARO Parent Not Found');
                      return false;
                    }
                    $parentRow  = $this->_dbh->getRow();
                    $new_rid    = (int)$parentRow[$type_prefix.'rid'];
                    $new_level  = (int)$parentRow[$type_prefix.'level'] + 1;
                  // Error
                    if( $parentRow[$type_prefix.'lft_min'] >= $parentRow[$type_prefix.'rgt_max'] ){
                      $new_lft = 0;
                      $new_rgt = 0;
                    }
                  // No Children
                    else if( is_null($parentRow[$type_prefix.'lft_max']) && is_null($parentRow[$type_prefix.'rgt_min']) ){
                      $new_lft = (int)$parentRow[$type_prefix.'lft_min'] + 1;
                      $new_rgt = (int)$parentRow[$type_prefix.'lft_min'] + round(($parentRow[$type_prefix.'rgt_max'] - $parentRow[$type_prefix.'lft_min']) * $this->_treeMaxCalc);
                    }
                  // Next Child
                    else {
                      $new_lft = (int)$parentRow[$type_prefix.'rgt_min'] + 1;
                      $new_rgt = (int)$parentRow[$type_prefix.'rgt_min'] + round(($parentRow[$type_prefix.'rgt_max'] - $parentRow[$type_prefix.'rgt_min']) * $this->_treeMaxCalc);
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
                    UPDATE `#__aro`
                    SET `aro_children` = `aro_children` + 1
                    WHERE `aro_id` = '". (int)$new_pid ."'
                    ");
                }
              // Update PID for next key
                $new_pid = end($new_id_set);
              // Rebuild if Necessary
                if( $new_lft >= $new_rgt ){
                  inspect( 'rebuild...', $newRow );
                  $this->_rebuild_aro( $new_rid );
                }
            }
          // Fallback Root ID
            if( is_null($new_rid) ){
              $new_rid = $new_pid;
            }
        }
        return $count_created ? end($new_id_set) : false;
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

  public function create_aro( $aro_chain, $aro_label, $aro_pid = null, $aro_data = null ){
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
  public function get_aro_tree( $aro_key = null ){
    $tree = array();
    if( is_null($aro_key) ){
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
      $row = $this->get_aro( $aro_key );
    }
    $base_level = $row['aro_level'];
    $this->_dbh->runQuery("
      SELECT `aro_id`, `aro_rid`, `aro_lft`, `aro_rgt`, `aro_level`, `aro_key`, `aro_label`
      FROM `#__aro`
      WHERE `aro_rid` = '". (int)$row['aro_rid'] ."'
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
  public function rebuild_aro( $aro_key = null ){
    $aro_rid = null;
    if( !is_null($aro_key) ){
      $row = $this->get_aro( $aro_key );
      if( !$row ){
        throw new Exception('ARO Key Not Found');
        return false;
      }
      $aro_rid = $row['aro_rid'];
    }
    return $this->_rebuild_aro( $aro_rid );
  }

  /************************************************************************************************************************
  *
  *
  *
  ************************************************************************************************************************/
  private function _rebuild_aro( $aro_rid = null, $aro_id = null, $lft = null, $children = true, $rgt_max = null ){
    if( is_null($lft) ){
      if( !empty($aro_id) ){
        $this->_dbh->runQuery("
          SELECT `aro_lft`, `aro_rgt`
          FROM `#__aro`
          WHERE `aro_id` = '". intval($aro_id) ."'
          ");
        $lft = (int)$this->_dbh->getValue();
      }
      if( empty($lft) ){
        $lft = 0;
      }
    }
    if( is_null($rgt_max) ){
      $rgt_max = $this->_treeRgtMax;
    }
    $rgt = $lft;
    if( $children ){
      $this->_dbh->runQuery("
        SELECT `aro`.`aro_id`
          , `aro`.`aro_rid`
          , `aro`.`aro_children`
        FROM `#__aro` AS `aro`
        ". (
        is_null($aro_rid)
          ? "
            WHERE `aro`.`aro_id` = `aro_rid`
            "
          : "
            WHERE `aro`.`aro_rid` = '". (int)$aro_rid ."'
              AND `aro`.`aro_pid` ". (is_null($aro_id) ? "IS NULL" : "= '". intval($aro_id) ."'") ."
            ORDER BY `aro`.`aro_key`
          "
          ) ."
        ");
      $rows = $this->_dbh->getRows();
      foreach( $rows AS $row ){
        $rgt = $this->_rebuild_aro(
                  $row['aro_rid'],
                  $row['aro_id'],
                  is_null($aro_id) ? 0 : $rgt + 1,
                  $row['aro_children'],
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
    if( $aro_id ){
      $this->_dbh->runQuery("
        UPDATE `#__aro`
        SET `aro_lft` = '". intval($lft) ."'
          , `aro_rgt` = '". intval($rgt) ."'
        WHERE `aro_id` = '". intval($aro_id) ."'
        ");
    }
    return $rgt + 1;
  }

  /************************************************************************************************************************
  *
  * Attempt to predict boundaries and reduce calculations
  *
  ************************************************************************************************************************/
  public function rebuild_aro_alt( $aro_key = null ){
    $aro_rid = null;
    if( !is_null($aro_key) ){
      $row = $this->get_aro( $aro_key );
      if( !$row ){
        throw new Exception('ARO Key Not Found');
        return false;
      }
      $aro_rid = $row['aro_rid'];
    }
    return $this->_rebuild_aro_alt( $aro_rid );
  }

  /************************************************************************************************************************
  *
  * Attempt to predict boundaries and reduce calculations
  *
  ************************************************************************************************************************/
  private function _rebuild_aro_alt( $aro_rid = null, $aro_id = null, $aro_lft = null, $aro_rgt = null, $aro_children = true ){
    // Validate
      if( is_null($aro_lft) ){
        $aro_lft = $this->_treeLftMin;
      }
      if( is_null($aro_rgt) ){
        $aro_rgt = $this->_treeRgtMax;
      }
    // Loop Roots if None Defined
      if( is_null($aro_rid) ){
        $this->_dbh->runQuery("
          SELECT `aro`.`aro_id`
            , `aro`.`aro_rid`
            , `aro`.`aro_key`
            , `aro`.`aro_children`
            /*
            , (
              SELECT COUNT(*)
              FROM `#__aro` AS `aro2`
              WHERE `aro2`.`aro_rid` = `aro`.`aro_rid`
              ) AS `aro_rid_count`
            , (
              SELECT (SELECT COUNT(*) FROM `#__aro` WHERE `aro_level` = `aro2`.`aro_level` AND `aro_rid` = `aro2`.`aro_rid`) AS `aro_level_count`
              FROM `#__aro` AS `aro2`
              WHERE `aro2`.`aro_rid` = `aro`.`aro_rid`
              GROUP BY `aro2`.`aro_level`
              ORDER BY `aro_level_count` DESC
              LIMIT 1
              ) AS `aro_level_count_max`
            */
          FROM `#__aro` AS `aro`
          WHERE `aro`.`aro_id` = `aro`.`aro_rid`
          ORDER BY `aro`.`aro_key` ASC, `aro`.`aro_id` ASC
          ");
        $rows = $this->_dbh->getRows();
        foreach( $rows AS $row ){
          // inspect( 'Root Nodes', $row );
          $aro_rgt = $this->_rebuild_aro_alt( $row['aro_rid'], $row['aro_id'], $aro_lft, $aro_rgt );
          if( !$aro_rgt ){
            return false;
          }
        }
        return true;
      }
    // Required
      if( is_null($aro_id) ){
        throw new Exception('ARO ID Required');
        return false;
      }
    // Loop Nested
      $new_aro_lft  = $aro_lft;
      $new_aro_rgt  = $aro_rgt;
      $new_aro_step = round($aro_rgt * $this->_treeMaxCalc);
      if( $aro_children ){
        $this->_dbh->runQuery("
          SELECT `aro`.`aro_id`
            , `aro`.`aro_rid`
            , `aro`.`aro_children`
          FROM `#__aro` AS `aro`
          WHERE `aro`.`aro_pid` = '". (int)$aro_id ."'
          ORDER BY `aro`.`aro_key`
          ");
        $rows = $this->_dbh->getRows();
        $row_count = count($rows);
        $new_aro_size = ($new_aro_rgt - $new_aro_lft);
        if( $new_aro_size < $row_count ){
          die( __FILE__ . ' - ' . __LINE__ . ' ... exceeding rgt_max in rebuild ... ' );
        }
        if( $new_aro_size > $row_count * 10 ){
          $new_aro_step = round( ($new_aro_size * 0.4) / count($rows) );
        }
        else {
          $new_aro_step = round( $new_aro_size / count($rows) );
        }
        foreach( $rows AS $row ){
          $new_aro_rgt = $this->_rebuild_aro_alt( $row['aro_rid'], $row['aro_id'], $new_aro_lft + 1, $new_aro_lft + $new_aro_step, $row['aro_children'] );
          $new_aro_lft = $new_aro_rgt;
          if( $new_aro_rgt > $aro_rgt ){
            $aro_rgt = $new_aro_rgt;
            die( __FILE__ . ' - ' . __LINE__ . ' ... exceeding rgt_max in rebuild ... ' );
          }
        }
      }
    // Update Target
      $this->_dbh->runUpdate('#__aro', array(
        'aro_lft' => $aro_lft,
        'aro_rgt' => $aro_rgt
        ), array(
        "`aro_id` = '". (int)$aro_id ."'"
        ));
    // Return
      return $aro_rgt;
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
  public function create_aco( $keyStr, $label, $aco_pid = null, $status = 1, $data = null ){
    $created = 0;
    $aco_rid = null;
    $aco_fpid = $aco_pid;
    $aco_level = null;
    $chain = explode('.', preg_replace($this->_keyTrx,'',$keyStr));
    $idset = array();
    for( $i=0; $i<count($chain); $i++ ){
      // Lookup
        $key = $chain[$i];
        $this->_dbh->runQuery("
          SELECT `aco_id`, `aco_level`
          FROM `#__aco`
          WHERE `aco_pid` ". (is_null($aco_pid) ? "IS NULL" : "= '". intval($aco_pid) ."'") ."
            AND `aco_key` = '". $this->_dbh->getEscaped($key) ."'
            ");
      // Process
        if( !$this->_dbh->getRowCount() ){
          if( !is_null($aco_pid) ){
            // Verify Parent Exists
              $this->_dbh->runQuery("
                SELECT `aco_id`, `aco_rid`, `aco_level`
                FROM `#__aco`
                WHERE `aco_id` = '". intval($aco_pid) ."'
                  ");
              if( !$this->_dbh->getRowCount() )
                return false;
              $rows = $this->_dbh->getRows();
              $row = array_shift( $rows );
              $aco_rid = intval( $row['aco_rid'] );
              $aco_level = intval( $row['aco_level'] )+1;
          }
          // Insert Object
            $newRow = array(
              'aco_pid'     => $aco_pid,
              'aco_rid'     => is_null($aco_rid) ? $this->_dbh->getNextID('#__aco') : $aco_rid,
              'aco_level'   => intval($aco_level),
              'aco_key'     => $key,
              'aco_label'   => ($i == count($chain)-1 ? $label : $key),
              'aco_status'  => $status,
              'aco_data'    => ($i == count($chain)-1 ? $data : null)
              );
            $this->_dbh->runInsert('#__aco', $newRow);
            $idset[] = $aco_pid = $this->_dbh->getLastID();
            $created++;
        }
        else {
          $rows = $this->_dbh->getRows();
          $row = array_shift( $rows );
          $idset[] = $aco_pid = $row['aco_id'];
        }
      // Fallback Root ID
        if( is_null($aco_rid) ){
          $aco_rid = $aco_pid;
        }
      // Store First PID
        if( is_null($aco_fpid) ){
          $aco_fpid = $aco_pid;
        }
    }
    if( $created )
      $this->_rebuild_aco();
    return $created ? array_pop($idset) : false;
  }

  /************************************************************************************************************************
  *
  *
  *
  ************************************************************************************************************************/
  public function get_aco( $keyStr=null ){
    $chain = explode('.', $keyStr);
    $level = count($chain)-1;
    $key   = $chain[ $level ];
    $this->_dbh->runQuery("
      SELECT `aco`.*
      FROM `#__aco` AS `aco`
      LEFT JOIN `#__aco` AS `aco_root` ON `aco_root`.`aco_rid` = `aco`.`aco_rid`
      WHERE `aco_root`.`aco_key` = '". $this->_dbh->getEscaped($chain[0]) ."'
        AND `aco_root`.`aco_level` = '0'
        AND `aco`.`aco_key` = '". $this->_dbh->getEscaped($key) ."'
        AND `aco`.`aco_level` = '". $this->_dbh->getEscaped($level) ."'
        ");
    if( $this->_dbh->getRowCount() ){
      $rows = $this->_dbh->getRows();
      return array_shift( $rows );
    }
    if( !$level ){
      $this->_dbh->runQuery("
        SELECT `aco`.*
        FROM `#__aco` AS `aco`
        WHERE `aco`.`aco_key` = '". $this->_dbh->getEscaped($key) ."'
        ORDER BY `aco`.`aco_level` ASC, `aco`.`aco_lft` ASC
          ");
      if( $this->_dbh->getRowCount() ){
        $rows = $this->_dbh->getRows();
        return array_shift( $rows );
      }
    }
    return null;
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
  public function get_aco_tree( $keyStr=null ){
    $tree = array();
    if( is_null($keyStr) ){
      $this->_dbh->runQuery("
        SELECT
          MIN(`aco_level`) AS `aco_level`
          , MIN(`aco_lft`) AS `aco_lft`
          , MAX(`aco_rgt`) AS `aco_rgt`
        FROM `#__aco`
        ");
      $rows = $this->_dbh->getRows();
      $row = array_shift( $rows );
    } else {
      $row = $this->get_aco( $keyStr );
    }
    $base_level = $row['aco_level'];
    $this->_dbh->runQuery("
      SELECT `aco_id`, `aco_rid`, `aco_rgt`, `aco_level`, `aco_label`
      FROM `#__aco`
      WHERE `aco_lft`
      BETWEEN '". $row['aco_lft'] ."'
        AND '". $row['aco_rgt'] ."'
      ORDER BY `aco_lft` ASC
      ");
    return $this->_dbh->getRows();
  }

  /************************************************************************************************************************
  *
  *
  *
  ************************************************************************************************************************/
  private function _rebuild_aco( $aco_pid=null, $lft=0 ){
    $rgt = $lft + $this->_treeStep;
    $this->_dbh->runQuery("
      SELECT `aco_id`
      FROM `#__aco`
      WHERE `aco_pid` ". (is_null($aco_pid) ? "IS NULL" : "= '". intval($aco_pid) ."'") ."
      ORDER BY `aco_key`
      ");
    $rows = $this->_dbh->getRows();
    for($i=0; $i<count($rows); $i++)
      $rgt = $this->_rebuild_aco($rows[$i]['aco_id'], $rgt);
    if( $aco_pid ){
      $this->_dbh->runQuery("
        UPDATE `#__aco`
        SET `aco_lft` = '". intval($lft) ."'
          , `aco_rgt` = '". intval($rgt) ."'
        WHERE `aco_id` = '". intval($aco_pid) ."'
        ");
    }
    return $rgt + $this->_treeStep;
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
  public function create_acl( $aro_key, $aco_key, $acl_key, $acl_rule = 'allow', $acl_pid = null, $acl_status = 0, $acl_data = null ){

    // Lookup ARO
      $aro_record = $this->get_aro( $aro_key );
      if( empty($aro_record) ){
        throw new Exception('ARO Not Found');
        return false;
      }

    // Lookup ACO
      $aco_record = $this->get_aco( $aco_key );
      if( empty($aco_record) ){
        throw new Exception('ACO Not Found');
        return false;
      }

    // Prepare
      $acl_key    = preg_replace($this->_keyTrx,'',$acl_key);
      $acl_status = empty( $acl_status ) ? 0 : 1;
      $acl_data   = is_object( $acl_data ) || is_array( $acl_data ) ? serialize($acl_data) : $acl_data;

    // Insert Object(s)
      $created = 0;
      $acl_rid = null;
      $acl_level = null;
      $chain = explode('.', $acl_key);
      $idset = array();
      for( $i=0; $i<count($chain); $i++ ){
        // Lookup
          $key = $chain[$i];
          $this->_dbh->runQuery("
            SELECT `acl_id`, `acl_level`
            FROM `#__acl`
            WHERE ". (
              is_null($acl_pid)
                ? "
                `acl_pid` IS NULL
                AND `aro_id` = '". (int)$aro_record['aro_id'] ."'
                AND `aco_id` = '". (int)$aco_record['aco_id'] ."'
                "
                : "
                `acl_pid` = '". intval($acl_pid) ."'
                "
                ) ."
              AND `acl_key` = '". $this->_dbh->getEscaped($key) ."'
              ");
        // Process
          if( !$this->_dbh->getRowCount() ){
            if( !is_null($acl_pid) ){
              // Verify Parent Exists
                $this->_dbh->runQuery("
                  SELECT `acl_id`, `acl_rid`, `acl_level`
                  FROM `#__acl`
                  WHERE `acl_id` = '". intval($acl_pid) ."'
                  ");
                if( !$this->_dbh->getRowCount() )
                  return false;
                $rows = $this->_dbh->getRows();
                $row = array_shift( $rows );
                $acl_rid = intval( $row['acl_rid'] );
                $acl_level = intval( $row['acl_level'] )+1;
            }
            // Insert Object
              $newRow = array(
                'aro_id'      => $aro_record['aro_id'],
                'aco_id'      => $aco_record['aco_id'],
                'acl_pid'     => $acl_pid,
                'acl_rid'     => is_null($acl_rid) ? $this->_dbh->getNextID('#__acl') : $acl_rid,
                'acl_level'   => intval($acl_level),
                'acl_key'     => $key,
                'acl_rule'    => $acl_rule,
                'acl_data'    => ($i == count($chain)-1 ? $acl_data : null),
                'acl_status'  => $acl_status
                );
              $this->_dbh->runInsert('#__acl', $newRow);
              $idset[] = $acl_pid = $this->_dbh->getLastID();
              $created++;
          }
          else {
            $rows = $this->_dbh->getRows();
            $row = array_shift($rows);
            $idset[] = $acl_pid = $row['acl_id'];
          }
        // Fallback Root ID
          if( is_null($acl_rid) ){
            $acl_rid = $acl_pid;
          }
      }
      if( $created )
        $this->_rebuild_acl( $acl_rid );
      return $created ? array_pop($idset) : false;

  }

  /************************************************************************************************************************
  *
  *
  *
  ************************************************************************************************************************/
  public function check_acl( $aro_key, $aco_key, $acl_key, $acl_status = 0 ){

    // Lookup ARO
      $aro_record = $this->get_aro( $aro_key );
      if( empty($aro_record) ){
        throw new Exception('ARO Not Found');
        return false;
      }

    // Lookup ACO
      $aco_record = $this->get_aco( $aco_key );
      if( empty($aco_record) ){
        throw new Exception('ACO Not Found');
        return false;
      }

    // Prepare
      $acl_key    = preg_replace($this->_keyTrx,'',$acl_key);
      $acl_status = empty( $acl_status ) ? 0 : 1;

    // Query Object
      $this->_dbh->runQuery("
        SELECT `acl_status`
        FROM `#__acl`
        WHERE `aro_id` = '". (int)$aro_record['aro_id'] ."'
          AND `aco_id` = '". (int)$aco_record['aco_id'] ."'
          AND `acl_key` = '". $this->_dbh->getEscaped($acl_key) ."'
        ");
      $acl_status = $this->_dbh->getValue() || $acl_status;

    // Return
      return $acl_status;

  }

  /************************************************************************************************************************
  *
  *
  *
  ************************************************************************************************************************/
  private function _rebuild_acl( $acl_rid = null, $acl_pid = null, $lft=0 ){
    $rgt = $lft + $this->_treeStep;
    $this->_dbh->runQuery("
      SELECT `acl_id`, `acl_rid`
      FROM `#__acl`
      ". (
      is_null($acl_rid)
        ? "
          WHERE `acl_id` = `acl_rid`
          "
        : "
          WHERE `acl_rid` = '". (int)$acl_rid ."'
            AND `acl_pid` ". (is_null($acl_pid) ? "IS NULL" : "= '". intval($acl_pid) ."'") ."
        "
        ) ."
      ORDER BY `acl_key`
      ");
    die('123');
    while( $row = $this->_dbh->getRow() ){
      $rgt = $this->_rebuild_acl($row['acl_rid'], $row['acl_id'], $rgt);
    }
    if( $acl_pid ){
      $this->_dbh->runQuery("
        UPDATE `#__acl`
        SET `acl_lft` = '". intval($lft) ."'
          , `acl_rgt` = '". intval($rgt) ."'
        WHERE `acl_id` = '". intval($acl_pid) ."'
        ");
    }
    return $rgt + $this->_treeStep;
  }

}