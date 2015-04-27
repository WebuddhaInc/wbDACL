<div style="font-size:10px;">
<?php

// http://www.phpbuddy.com/article.php?id=6

set_time_limit( 60 * 5 );

require_once('wbDatabase.php');
require_once('wb_dacl.class.php');

/*

  ARO - Access Request Object
    Something from which control is being requested

  ACO - Access Control Object
    Something for which control is being managed

  ACL - Access Control Rule
    The rule to which a Resource and Control object will be governed

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
