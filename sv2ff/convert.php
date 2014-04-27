<?php
// Convert Savane database to FusionForge
// Copyright (C) 2014  Sylvain Beucler
//
// This file is part of Savane.
// 
// Savane is free software: you can redistribute it and/or modify
// it under the terms of the GNU Affero General Public License as
// published by the Free Software Foundation, either version 3 of the
// License, or (at your option) any later version.
// 
// Savane is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU Affero General Public License for more details.
// 
// You should have received a copy of the GNU Affero General Public License
// along with this program.  If not, see <http://www.gnu.org/licenses/>.

require_once(dirname(__FILE__).'/savane.conf.php');

/**
 * Convert a list of of arrays to a multi-INSERT database
 * $out: PostgreSQL database descriptor
 * $table: target table
 * $entries: list of arrays, 1 array -> 1 database row
 */
function insert($out, $table, &$entries) {
  if (count($entries) == 0)
    return;
  $fields = array_keys($entries[0]);
  $query  = "INSERT INTO $table (".join(',', $fields).') VALUES ';

  $first_entry = true;
  foreach ($entries as &$entry) {
    $query .= $first_entry ? '' : ',';
    $first_entry = false;
    $query .= '(';
    $first_value = true;
    foreach (array_values($entry) as $value) {
      $query .= $first_value ? '' : ',';
      $first_value = false;
      $query .= ($value === null) ? 'NULL' : pg_escape_literal($value);
    }
    $query .= ')';
  }

  pg_query($out, $query) or die(pg_last_error());
}

$last_time = -1;
function section($title) {
  global $last_time;
  $cur_time = time();
  if ($last_time > 0)
    print " (" . ($cur_time - $last_time) . "s)";
  print "\n";
  print "$title";
  $last_time = $cur_time;
}


//
// Init
//
section("Init");

$in = new mysqli('', $sys_dbuser, $sys_dbpasswd, $sys_dbname);
if ($in->connect_errno) {
  die($in->connect_error);
}
$in->set_charset('utf8');

$out = pg_connect("host=127.0.0.1 dbname=gforge user=gforge password=".file_get_contents('pass'))
  or die(pg_last_error());

// Constants
$res = pg_query_params('SELECT role_id FROM pfo_role JOIN pfo_role_class ON pfo_role.role_class = pfo_role_class.class_id WHERE class_name=$1',
		       array('PFO_RoleAnonymous')) or die(pg_last_error());
$anonymous_role = pg_fetch_result($res, 0,0);



//
// Users
//
section("- Users");

// Remove default users
// - remove references to existing (admin) users in the RBAC
// - keep user "None", references in some trackers default AFAICR
section("  Deleting existing users");
// Delete dependencies
pg_query($out, 'DELETE FROM group_join_request') or die(pg_last_error());
pg_query($out, 'DELETE FROM pfo_user_role') or die(pg_last_error());
// Reset users table (way faster than "DELETE FROM users WHERE user_id != 100")
pg_query($out, 'TRUNCATE users CASCADE') or die(pg_last_error());
pg_query($out, "INSERT INTO users VALUES (100, 'None', 'noreply@forge.internal', '*********34343', 'Nobody', 'A', '/bin/bash', '', 'N', 20100, 'shell', 0, NULL, 0, 0, NULL, NULL, 0, '', 'GMT', 1, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'Nobody', NULL, NULL, 'US', 24, 1, 20100, 1)") or die(pg_last_error());

section("  Loading Savane users");
$res = $in->query("SELECT * FROM user WHERE user_id != 100 AND status != 'SQD'") or die($in->error);
$users = array();
while ($row = $res->fetch_assoc()) {
  list($firstname, $lastname) = preg_split('/\s+|$/', $row['realname'], 2);
  $users[] = array(
    'user_id'            => $row['user_id'],
    'user_name'          => $row['user_name'], 
    'email'              => $row['email'],
    'user_pw'            => '!', // cf. unix_pw
    'realname'           => $row['realname'], // note: generated field (firstname+lastname) (ugly)
    'status'             => $row['status'],
    'shell'              => '/bin/sv_membersh',
    'unix_pw'            => $row['user_pw'],  // shell-compatible SHA-512
    //'unix_status'      => ?,
    'unix_uid'           => intval($row['uidNumber']),
    //'unix_box'         => default 'shell',
    'add_date'           => $row['add_date'],
    //'confirm_hash'     => default '',
    //'mail_siteupdates' => default 0,
    //'mail_va'          => default 0,
    //'authorized_keys'  => cf. sshkeys.*,
    'email_new'          => $row['email_new'],
    'people_view_skills' => $row['people_view_skills'],
    'people_resume'      => $row['people_resume'],
    'timezone'           => $row['timezone'],
    //'language'         => implement auto-detect from browser,
    //'block_rating'     => ?,
    //'jabber_address'   => default '',
    //'jabber_only'      => default '',
    //'address'          => default '',
    //'phone'            => default '',
    //'fax'              => default '',
    //'title'            => default '',
    'firstname'          => $firstname,
    'lastname'           => $lastname,
    //'address2'         => default '',
    'ccode'              => null,
    'theme_id'           => 24,
    //'type_id'          => ?,
    //'unix_gid'         => ?,
    //'tooltipcs'        => ?,
 );
}

section("  Creating FusionForge users");
insert($out, 'users', $users);



//Licenses
// TODO


//
// Groups
//
section("- Groups");

section("  Deleting existing groups");
// remove default tracker for 'siteadmin'
pg_query($out, 'DELETE FROM artifact_group_list') or die(pg_last_error());
// remove default tasks for 'siteadmin'
pg_query($out, 'DELETE FROM project_task') or die(pg_last_error());  // Remove empty task #1
pg_query($out, 'DELETE FROM project_group_list') or die(pg_last_error());
// remove default roles inclusions
pg_query       ($out, 'DELETE FROM pfo_role_setting WHERE role_id IN (SELECT role_id FROM pfo_role WHERE home_group_id IS NOT NULL)') or die(pg_last_error());
pg_query_params($out, 'DELETE FROM pfo_role_setting WHERE role_id=$1', array($anonymous_role)) or die(pg_last_error());
pg_query($out, 'DELETE FROM pfo_role WHERE home_group_id IS NOT NULL') or die(pg_last_error());
pg_query($out, 'DELETE FROM role_project_refs') or die(pg_last_error());
// remove group news & comments
pg_query($out, 'DELETE FROM forum_group_list');
pg_query($out, 'DELETE FROM forum');
// actually delete groups
pg_query($out, 'DELETE FROM groups') or die(pg_last_error());

section("  Loading Savane groups");
$res = $in->query("SELECT * FROM groups WHERE unix_group_name != 'svusers'") or die($in->error);
$groups = array();
$public_groups = array();
while ($row = $res->fetch_assoc()) {
  $groups[] = array(
    'group_id'            => $row['group_id'],
    'group_name'          => substr($row['group_name'], 0, 40),
    'homepage'            => $row['url_homepage'],
    'status'              => ($row['status'] == 'M') ? 'H' : $row['status'],
    'unix_group_name'     => $row['unix_group_name'],
    //unix_box            => default 'shell',
    //http_domain         => ?,
    'short_description'   => $row['long_description'],
    // TODO: short_description (search results) vs. long_description (project homepage)
    'register_purpose'    => $row['register_purpose'],
    'license_other'       => $row['license_other'],
    'register_time'       => $row['register_time'],
    //rand_hash           => default '',
    //use_mail => used?
    //use_survey => used?
    //use_forum => used?
    //use_pm => used?
    //use_scm => used?
    //use_news => used?
    //type_id => TODO
    //use_docman => used?
    //new_doc_address     => default '',
    //send_all_docs       => default 0,
    //use_pm_depend_box => used?
    //use_ftp => used?
    //use_tracker => used?
    //use_frs => used?
    //use_stats => used?
    //enable_pserver => used?
    'license'             => 126, // TODO
    //scm_box => ?
    //use_docman_search => used?
    //force_docman_reindex => used?
    //use_webdav => used?
    //use_docman_create_online => used?
    //is_template => ?
    //built_from_template => ?
    //use_activity => used?
  );
  if ($row['is_public'])
    $public_groups[] = $row['group_id'];
}

section("  Creating FusionForge groups");
insert($out, 'groups', $groups);


//
// Roles/permissions
//
section("- Roles and permissions");

//
// Site-wide admins
//
section("  Forge admins");
$res = $in->query("SELECT * FROM user_group WHERE group_id=(SELECT group_id FROM groups WHERE unix_group_name='$sys_unix_group_name') AND admin_flags='A'") or die($in->error);
while ($row = $res->fetch_assoc()) {
  pg_query_params($out, 'INSERT INTO pfo_user_role VALUES ($1, (SELECT role_id FROM pfo_role WHERE role_name=\'Forge administrators\'))', array($row['user_id']));
}


//
// Anonymous permissions
//

section("  Anonymous (public) permissions");
$role_project_refs = array();
foreach($public_groups as $group_id) {
  $role_project_refs[] = array('role_id' => $anonymous_role, 'group_id' => $group_id);
}
insert($out, 'role_project_refs', $role_project_refs);

$pfo_role_setting = array();
foreach($public_groups as $group_id) {
  $pfo_role_setting[] = array('role_id' => $anonymous_role, 'section_name' => 'project_read', 'ref_id' => $group_id, 'perm_val' => 1);
  $pfo_role_setting[] = array('role_id' => $anonymous_role, 'section_name' => 'frs',          'ref_id' => $group_id, 'perm_val' => 1);
  $pfo_role_setting[] = array('role_id' => $anonymous_role, 'section_name' => 'scm',          'ref_id' => $group_id, 'perm_val' => 1);
  $pfo_role_setting[] = array('role_id' => $anonymous_role, 'section_name' => 'docman',       'ref_id' => $group_id, 'perm_val' => 1);
}
insert($out, 'pfo_role_setting', $pfo_role_setting);


//
// User permissions -> user roles
//

section("  Loading Savane permissions");
// Group users permissions per project
$res = $in->query("SELECT user_group.user_id, user_group.group_id, user_group.admin_flags, user_group.privacy_flags,
    user_group.bugs_flags, user_group.task_flags, user_group.patch_flags, user_group.support_flags,
    groups_default_permissions.bugs_flags      AS gdp_bugs_flags,
      groups_default_permissions.task_flags    AS gdp_task_flags,
      groups_default_permissions.patch_flags   AS gdp_patch_flags,
      groups_default_permissions.support_flags AS gdp_support_flags,
    group_type.bugs_flags      AS gt_bugs_flags,
      group_type.task_flags    AS gt_task_flags,
      group_type.patch_flags   AS gt_patch_flags,
      group_type.support_flags AS gt_support_flags,
    user.realname
  FROM user_group
    JOIN groups USING (group_id)
    LEFT JOIN groups_default_permissions USING (group_id)
    JOIN group_type ON groups.type = group_type.type_id
    JOIN user USING (user_id)") or die($in->error);
$project_roles = array();
while ($row = $res->fetch_assoc()) {
  if ($row['admin_flags'] == 'A') {
    $project_roles[$row['group_id']]['Admin'][] = $row['user_id'];
    // no need for any other permission
  } else if ($row['admin_flags'] == 'SQD') {
    $project_roles[$row['group_id']][$row['realname']] = array();
    // TODO: import role users from user_squad
    // TODO: trackers permissions for the squad
    // TODO: user_name (e.g. savane-security) important?
  } else {
    if ($row['admin_flags'] == '')
      $project_roles[$row['group_id']]['Contributor'][] = $row['user_id'];
    else if ($row['admin_flags'] == 'P')
      continue; // TODO: P pending users -> group_join_request
    
    // flags: 1=tech, 2=tech+man, 3=man, 9=default
    foreach(array('bugs', 'task', 'patch', 'support') as $tracker) {
      $flag = $row["{$tracker}_flags"];
      if (!$flag or $flag == 9) $flag = $row["gdp_{$tracker}_flags"];
      if (!$flag or $flag == 9) $flag = $row["gt_{$tracker}_flags"];
      $Tracker = ucfirst($tracker);
      switch($flag) {
      case 1: $role_name = "$Tracker technician"; break;
      case 2: $role_name = "$Tracker techn. & manager"; break;
      case 3: $role_name = "$Tracker manager"; break;
      default: print_r($row); die("unknown flag $tracker $flag\n");
      }
      $project_roles[$row['group_id']][$role_name][] = $row['user_id'];
    }
  }
}

section("  Merging permissions");
// Attempt to merge all tracker roles in 'Contributor' if identical permissions
// TODO: alternative: put all common perms in 'Contributor', and keep other perms as separate roles
foreach($project_roles as $group_id => &$roles) {
  $user_roles = array();
  foreach($roles as $role_name => &$user_ids) {
    if ($role_name == 'Admin')
      continue;
    foreach($user_ids as $user_id) {
      $user_roles[$user_id][] = $role_name;
    }
  }
  $all_the_same = true;
  foreach($user_roles as &$user) {
    if ($user != $user_roles[array_keys($user_roles)[0]]) {
      $all_the_same = false;
    }
  }
  if ($all_the_same)
    foreach(array_keys($roles) as $role_name)
      if ($role_name != 'Admin' and $role_name != 'Contributor')
	unset($roles[$role_name]);
  // TODO: move tracker permissions to 'Contributor'
}


// Create roles for each project
section("  Converting permissions to roles");
$res = pg_query('SELECT MAX(role_id)+1 FROM pfo_role');
$cur_role = pg_fetch_result($res, 0,0);
$pfo_role = array();
$pfo_role_setting = array();
$pfo_user_role = array();
foreach($project_roles as $group_id => &$roles) {
  foreach($roles as $role_name => &$user_ids) {
    $pfo_role[] = array('role_id' => $cur_role, 'role_name' => $role_name, 'role_class' => 1, 'home_group_id' => $group_id);
    
    if ($role_name == 'Admin') {
      $pfo_role_setting[] = array('role_id' => $cur_role, 'section_name' => 'project_admin', 'ref_id' => $group_id, 'perm_val' => 1);
    } else if ($role_name == 'Contributor') {
      $pfo_role_setting[] = array('role_id' => $cur_role, 'section_name' => 'scm',           'ref_id' => $group_id, 'perm_val' => 2);
    } else {
      // TODO: create the trackers first
    }
    // TODO: privacy_flags (for private items)
    
    foreach($user_ids as $user_id) {
      $pfo_user_role[] = array('user_id' => $user_id, 'role_id' => $cur_role);
    }
    $cur_role++;
  }
  
  // Note: no specific permissions for news in FusionForge (== project admin)
}

section("  Creating FusionForge roles");
insert($out, 'pfo_role', $pfo_role);
insert($out, 'pfo_role_setting', $pfo_role_setting);
insert($out, 'pfo_user_role', $pfo_user_role);


//
// News
//
section("- News items");

section("  Deleting existing news");
pg_query($out, 'DELETE FROM news_bytes');


section("  Loading Savane news");
$res = $in->query("SELECT * FROM news_bytes") or die($in->error);
$news_bytes = array();
while ($row = $res->fetch_assoc()) {
  $news_bytes[] = array(
    'id'             => $row['id'],
    'group_id'       => $row['group_id'], 
    'submitted_by'   => $row['submitted_by'] ? $row['submitted_by'] : 100,
    'is_approved'    => $row['is_approved'] == 5 ? 4 : $row['is_approved'],
    // 0=approved, 1=frontpage, 4=deleted, 5=proposed
    // TODO?: 'proposed' not supported by FusionForge
    'post_date'      => $row['date'],
    // TODO:         => $row['date_last_edit'],
    'forum_id'       => $row['forum_id'],
    'summary'        => $row['summary'],
    'details'        => $row['details'],
 );
}

section("  Creating FusionForge news");
insert($out, 'news_bytes', $news_bytes);

section("  Loading Savane comments");
// Comments home
$res = $in->query("SELECT * FROM forum_group_list") or die($in->error);
$forum_group_list = array();
while ($row = $res->fetch_assoc()) {
  $forum_group_list[] = array(
    'group_forum_id' => $row['group_forum_id'],
    'group_id'       => $row['group_id'],
    'forum_name'     => $row['forum_name'],
    'description'    => $row['description'],
    // 'send_all_posts_to' => not supported in Savane, using default
    // $row['is_public']   => always '1' in Savane, not imported
 );
}
// Comments
// ignore orphan comments (3 occurrences at gna.org)
$res = $in->query("SELECT forum.* FROM forum LEFT JOIN forum_group_list USING (group_forum_id) WHERE forum_group_list.group_forum_id IS NOT NULL") or die($in->error);
$forum = array();
while ($row = $res->fetch_assoc()) {
  $forum[] = array(
    'msg_id'             => $row['msg_id'],
    'group_forum_id'     => $row['group_forum_id'],
    'posted_by'          => $row['posted_by'],
    'subject'            => $row['subject'],
    'body'               => $row['body'],
    'post_date'          => $row['date'],
    'is_followup_to'     => $row['is_followup_to'],
    'thread_id'          => $row['thread_id'],
    'has_followups'      => $row['has_followups'],
    //'most_recent_date' => ?, TODO
 );
}
// Comments read/unread status
// ignore status for deleted comments (6 occurrences at gna.org)
// not much data to expect since it's ununsed in Savane 3, but there may be data from prior
$res = $in->query("SELECT * FROM forum_saved_place LEFT JOIN forum ON forum_saved_place.forum_id = forum.msg_id WHERE forum.msg_id IS NOT NULL AND saved_place_id != 100") or die($in->error);
$forum_saved_place = array();
while ($row = $res->fetch_assoc()) {
  $forum_saved_place[] = array(
    'user_id'            => $row['user_id'],
    'forum_id'           => $row['forum_id'],
    'save_date'          => $row['save_date'],
    // $row['saved_place_id'] => unnecessary, not imported
 );
}
// Comments notifications
// ignore status for deleted forums (25 occurrences at gna.org)
// not much data to expect since it's ununsed in Savane 3, but there may be data from prior
$res = $in->query("SELECT * FROM forum_saved_place LEFT JOIN forum ON forum_saved_place.forum_id = forum.msg_id WHERE forum.msg_id IS NOT NULL AND saved_place_id != 100") or die($in->error);
$forum_saved_place = array();
while ($row = $res->fetch_assoc()) {
  $forum_saved_place[] = array(
    'user_id'            => $row['user_id'],
    'forum_id'           => $row['forum_id'],
    'save_date'          => $row['save_date'],
    // $row['saved_place_id'] => unnecessary, not imported
 );
}
// TODO: forum_thread_id?  Check that forum_thread_seq is OK after adding a new forum


section("  Creating FusionForge forums/comments");
insert($out, 'forum_group_list', $forum_group_list);
insert($out, 'forum', $forum);
insert($out, 'forum_saved_place', $forum_saved_place);



section("\n");
print "Memory usage: " . (memory_get_usage(true) / pow(1024, 2)) . "MB\n";
print "Memory peak : " . (memory_get_peak_usage(true) / pow(1024, 2)) . "MB\n";
