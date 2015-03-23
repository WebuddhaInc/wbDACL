<div style="font-size:10px;">
<?php

// http://www.phpbuddy.com/article.php?id=6

set_time_limit( 60 );

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
  $wb_dbh->runQuery("TRUNCATE TABLE `wbdacl_aro`");
  $wb_dbh->runQuery("TRUNCATE TABLE `wbdacl_aco`");
  $wb_dbh->runQuery("TRUNCATE TABLE `wbdacl_acl`");

// ************************************************************************
// Initialize ACL
  $acl = new wb_dacl();

// ************************************************************************
// Create a ARO Tree

  $count = 5;

  echo '<div style="height:100%;width:30%;float:left;">';

  echo microtime(true) . '<br>';
  $acl->create_aro( 'user', 'Users ARO' );
  $acl->create_aro( 'user.private', 'Private Users' );
  if( $aro_id = $acl->create_aro( 'user.private.dhunt', 'David Hunt' ) )
    $acl->create_aro( 'friends', 'David Hunt Friends', $aro_id );
  for( $i=0; $i<$count; $i++ ){
    $i_pid = $acl->create_aro( 'user.private.dhunt.friends.article_'.$i, 'Article #'.$i );
    for( $x=0; $x<$count; $x++ ){
      $x_pid = $acl->create_aro( 'edit_'.$x, 'Article #'.$i.' Edit #'.$x, $i_pid );
      for( $y=0; $y<$count; $y++ ){
        $y_pid = $acl->create_aro( 'revision_'.$y, 'Article #'.$i.' Edit #'.$x.' Revision #'.$y, $x_pid );
      }
    }
  }
  $acl->create_aro( 'user.public', 'Public Users' );
  $acl->create_aro( 'user.public.phunt', 'Peter Hunt' );
  $acl->create_aro( 'user.public.jhunt', 'Jane Hunt' );
  $acl->create_aro( 'user.public.dhunt', 'David Hunt' );
  for( $i=0; $i<$count; $i++ ){
    $acl->create_aro( 'user.public.dhunt.article_'.$i, 'Article #'.$i );
  }
  echo microtime(true) . '<br>';

  $tree = $acl->get_aro_tree();
  draw_tree('aro', $tree);
  echo '</div>';

  echo '<div style="height:100%;width:30%;float:left;">';

  echo microtime(true) . '<br>';
  $acl->rebuild_aro();
  echo microtime(true) . '<br>';

  $tree = $acl->get_aro_tree();
  draw_tree('aro', $tree);
  echo '</div>';

  echo '<div style="height:100%;width:30%;float:left;">';

  echo microtime(true) . '<br>';
  for( $i=$count; $i<$count*2; $i++ ){
    $i_pid = $acl->create_aro( 'user.private.dhunt.friends.article_'.$i, 'Article #'.$i );
    for( $x=$count; $x<$count*2; $x++ ){
      $x_pid = $acl->create_aro( 'edit_'.$x, 'Article #'.$i.' Edit #'.$x, $i_pid );
      for( $y=$count; $y<$count*2; $y++ ){
        $y_pid = $acl->create_aro( 'revision_'.$y, 'Article #'.$i.' Edit #'.$x.' Revision #'.$y, $x_pid );
      }
    }
  }
  echo microtime(true) . '<br>';

  $tree = $acl->get_aro_tree();
  draw_tree('aro', $tree);
  echo '</div>';

  die();

  echo microtime(true) . '<br>';
  for( $i=1; $i<2; $i++ ){
    $acl->create_aro( $i.'user', 'Users ARO' );
    $acl->create_aro( $i.'user.public', 'Public Users' );
    $acl->create_aro( $i.'user.public.anonymous', 'Anonymous' );
    $acl->create_aro( $i.'user.public.dhunt', 'David Hunt' );
    $acl->create_aro( $i.'user.private', 'Private Users' );
    if( $aro_id = $acl->create_aro( $i.'user.private.dhunt', 'David Hunt' ) )
      $acl->create_aro( $i.'friends', 'David Hunt Friends', $aro_id );
  }
  echo microtime(true) . '<br>';

// ************************************************************************
// Draw ARO Tree
  $tree = $acl->get_aro_tree();
  draw_tree('aro', $tree);

  $acl->rebuild_aro();
  $tree = $acl->get_aro_tree();
  draw_tree('aro', $tree);
  die();

// ************************************************************************
// Create a ARO Tree
  $acl->create_aro( 'company', 'Company ARO' );
  $acl->create_aro( 'company.vendors', 'Vendor Companies' );
  $acl->create_aro( 'company.vendors.att', 'AT&T' );
  $acl->create_aro( 'company.prospects', 'Prospect Companies' );

// ************************************************************************
// Draw ARO Tree
  $tree = $acl->get_aro_tree();
  draw_tree('aro', $tree);

// ************************************************************************
// Create a ACO Tree
  $acl->create_aco( 'system', 'System ACO' );
  $acl->create_aco( 'system.admin', 'Admin Area ACO' );
  $acl->create_aco( 'system.private', 'Private Area ACO' );
  $acl->create_aco( 'system.res', 'Resources ACO' );
  $acl->create_aco( 'system.res.articles', 'Articles ACO' );
  if( $aco_id = $acl->create_aco( 'system.project', 'Project' ) ){
    $acl->create_aco( '1', 'Project #1', $aco_id );
    $acl->create_aco( '1.action', 'Project #1 Action', $aco_id );
    $acl->create_aco( '1.action.1', 'Project #1 Action #1', $aco_id );
    $acl->create_aco( '1.action.2', 'Project #1 Action #2', $aco_id );
    $acl->create_aco( '1.action.3', 'Project #1 Action #3', $aco_id );
    $acl->create_aco( '1.action.4', 'Project #1 Action #4', $aco_id );
  }

// ************************************************************************
// Draw ACO Tree
  $tree = $acl->get_aco_tree();
  draw_tree('aco', $tree);

// ************************************************************************
// Create ARO / ACO Relationships
  $acl->create_acl( 'user', 'system', 'edit', false );
  $acl->create_acl( 'user.public', 'system', 'edit', false );
  $acl->create_acl( 'user.public.anonymous', 'system', 'edit', false );
  $acl->create_acl( 'user.public.dhunt', 'system', 'edit.name', true );

// ************************************************************************
// Get ACL Status
  echo $acl->check_acl( 'user', 'system', 'edit' ) ? 1 : 0;
  echo $acl->check_acl( 'user.public', 'system', 'edit' ) ? 1 : 0;
  echo $acl->check_acl( 'user.public.dhunt', 'system', 'edit' ) ? 1 : 0;
  echo $acl->check_acl( 'user.public.dhunt', 'system.admin', 'edit' ) ? 1 : 0;
  echo $acl->check_acl( 'user.public.dhunt', 'system.admin', 'edit.name' ) ? 1 : 0;

// ************************************************************************
// Draw ACO Tree
  $tree = $acl->get_acl_tree();
  draw_tree('acl', $tree);















// Testing Functions
  function draw_tree($prefix,  &$tree){
    echo '******************************** '.strtoupper($prefix).' Tree <br>';
    if( count($tree) ){
      $base_level = $tree[0][$prefix.'_level'];
      for($i=0;$i<count($tree);$i++){
        if( !($tree[$i][$prefix.'_level'] - $base_level) )
          echo '<br>';
        echo str_repeat('--',($tree[$i][$prefix.'_level'] - $base_level)).$tree[$i][$prefix.'_label'].' ('.$tree[$i][$prefix.'_lft'].'/'.$tree[$i][$prefix.'_rgt'].') '."<br>";
      }
    }
    echo '<br>';
  }

// Debug Functions
  function inspect(){
    echo '<pre>' . print_r( func_get_args(), true ) . '</pre>';
  }
