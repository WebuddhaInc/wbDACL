<div style="font-size:10px;">
<?php

// http://www.phpbuddy.com/article.php?id=6

set_time_limit( 60 * 5 );

require_once('wbDatabase.php');
require_once('wb_dacl.class.php');

/*


  Resource
    Work
      User
      Schedule
      Rates
    Material
      Schedule
      Rates

  User (ARO)
  User Groups (ARO)

  Task (ACO)

  User Group Permission (ARO -> ACO)
  User Permission (ARO -> ACO)

  ARO - Access Request Object
    Something from which control is being requested

  ACO - Access Control Object
    Something for which control is being managed

  Example
    dogs
    dogs.dog.1

  Example SQL Injection Lookup
    SELECT *
    FROM `wbdacl_acl`
    WHERE `aro_id` = (
        SELECT `c`.`aro_id`
        FROM `wbdacl_aro` AS `p`
        LEFT JOIN `wbdacl_aro` AS `c` ON (
          `c`.`aro_rid` = `p`.`aro_rid`
          AND `c`.`aro_lft` <= `p`.`aro_lft`
          AND `c`.`aro_rgt` >= `p`.`aro_rgt`
          AND `c`.`aro_status` = '1'
          )
        WHERE `p`.`aro_id` = (
          SELECT `aro_id`
          FROM `wbdacl_aro`
          WHERE `aro_status` = '1'
            AND (
              `aro_chain` = 'user'
              OR
              `aro_chain` = 'user.public'
              OR
              `aro_chain` = 'user.public.dhunt'
              )
          ORDER BY `aro_level` DESC
          LIMIT 1
          )
        ORDER BY `c`.`aro_level` DESC
        LIMIT 1
      )
      AND `aco_id` = (
        SELECT `c`.`aco_id`
        FROM `wbdacl_aco` AS `p`
        LEFT JOIN `wbdacl_aco` AS `c` ON (
          `c`.`aco_rid` = `p`.`aco_rid`
          AND `c`.`aco_lft` <= `p`.`aco_lft`
          AND `c`.`aco_rgt` >= `p`.`aco_rgt`
          AND `c`.`aco_status` = '1'
          )
        WHERE `p`.`aco_status` = '1'
          AND (
            `p`.`aco_chain` = 'system'
            OR
            `p`.`aco_chain` = 'system.admin'
            )
        ORDER BY `p`.`aco_level` DESC, `c`.`aco_level` DESC
        LIMIT 1
      )
      AND (
        `acl_chain` = 'edit'
        OR
        `acl_chain` = 'edit.name'
        )
      AND `acl_status` = '1'
    ORDER BY `acl_level` DESC
    LIMIT 1

 */

// ************************************************************************
// Connect to Database

  $wb_dbh = new wbDatabase(array(
    'host'   => 'localhost',
    'name'   => 'acl_wbdacl',
    'user'   => 'root',
    'pass'   => '',
    'hash'   => md5('MyPass'),
    'prefix' => 'wbdacl_',
    'debug'  => false
    ));

// ************************************************************************
// Initialize ACL
  $acl = new wb_dacl();

// ************************************************************************
// Flush Database

  $wb_dbh->runQuery("TRUNCATE TABLE `wbdacl_aro`");
  $wb_dbh->runQuery("TRUNCATE TABLE `wbdacl_aco`");
  $wb_dbh->runQuery("TRUNCATE TABLE `wbdacl_acl`");

// ************************************************************************
// Create a ARO Tree

  $acl->create_aro( 'company', 'Company ARO' );
  $acl->create_aro( 'company.vendors', 'Vendor Companies' );
  $acl->create_aro( 'company.vendors.att', 'AT&T' );
  $acl->create_aro( 'company.prospects', 'Prospect Companies' );

// ************************************************************************
// Create a ARO Tree

  $acl->create_aro( 'user', 'Users ARO' );
  $acl->create_aro( 'user.private', 'Private Users' );
  $acl->create_aro( 'user.public', 'Public Users' );
  $acl->create_aro( 'user.public.phunt', 'Peter Hunt' );
  $acl->create_aro( 'user.public.jhunt', 'Jane Hunt' );
  $acl->create_aro( 'user.public.dhunt', 'David Hunt' );
  if( $aro_id = $acl->create_aro( 'user.private.dhunt', 'David Hunt' ) )
    $acl->create_aro( 'friends', 'David Hunt Friends', $aro_id );

// ************************************************************************
// Draw ARO Tree
  $tree = $acl->get_aro_tree();
  draw_tree('aro', $tree);

// ************************************************************************
// Create a ACO Tree

  $acl->create_aco( 'system', 'System ACO' );
  $acl->create_aco( 'system.admin', 'Admin Area ACO' );
  $acl->create_aco( 'system.private', 'Private Area ACO' );

// ************************************************************************
// Create a ACO Tree

  for( $i_a=0; $i_a<2; $i_a++ ){
    $acl->create_aco( 'project.project-'.$i_a );
    for( $i_b=0; $i_b<2; $i_b++ ){
      $acl->create_aco( 'project.project-'.$i_a.'.action.action-'.$i_b );
      for( $i_c=0; $i_c<2; $i_c++ ){
        $acl->create_aco( 'project.project-'.$i_a.'.action.action-'.$i_b.'.note.note-'.$i_c );
        for( $i_d=0; $i_d<2; $i_d++ ){
          $acl->create_aco( 'project.project-'.$i_a.'.action.action-'.$i_b.'.note.note-'.$i_c.'.edit.edit-'.$i_d );
        }
      }
    }
  }

// ************************************************************************
// Draw ACO Tree
  $tree = $acl->get_aco_tree();
  draw_tree('aco', $tree);

// ************************************************************************
// Create ACL Rule

  $acl->create_acl( 'user', 'system', 'edit', false );
  $acl->create_acl( 'user.public', 'system', 'edit', true );
  $acl->create_acl( 'user.public.dhunt', 'system', 'edit.name', true );
  $acl->create_acl( 'user.public.dhunt', 'system.admin', 'edit.name', false );
  $acl->create_acl( 'user.public.dhunt', 'system.admin', 'edit.name.first', true );

// ************************************************************************
// Get ACL Status

  echo $acl->check_acl( 'user', 'system', 'edit' ) ? 1 : 0;
  echo $acl->check_acl( 'user.public', 'system', 'edit' ) ? 1 : 0;
  echo $acl->check_acl( 'user.public.dhunt', 'system', 'edit' ) ? 1 : 0;
  echo $acl->check_acl( 'user.public.dhunt', 'system', 'edit.name' ) ? 1 : 0;
  echo $acl->check_acl( 'user.public.dhunt', 'system.admin', 'edit' ) ? 1 : 0;
  echo $acl->check_acl( 'user.public.dhunt', 'system.admin', 'edit.name' ) ? 1 : 0;
  echo $acl->check_acl( 'user.public.dhunt', 'system.admin', 'edit.name.first' ) ? 1 : 0;

























// ************************************************************************
// Testing Functions

  function draw_tree($prefix,  &$tree){
    echo '******************************** '.strtoupper($prefix).' Tree <br>';
    if( count($tree) ){
      $base_level = $tree[0][$prefix.'_level'];
      for($i=0;$i<count($tree);$i++){
        if( !($tree[$i][$prefix.'_level'] - $base_level) )
          echo '<br>';
        echo $tree[$i][$prefix.'_id'].': '.$tree[$i][$prefix.'_chain'].' ('.$tree[$i][$prefix.'_lft'].'/'.$tree[$i][$prefix.'_rgt'].') '."<br>";
      }
    }
    echo '<br>';
  }

// Debug Functions
  function inspect(){
    echo '<pre>' . print_r( func_get_args(), true ) . '</pre>';
  }
