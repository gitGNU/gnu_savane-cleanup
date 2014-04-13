<?php
// aptitude install php5-cli php5-mysql php5-pgsql
require_once(dirname(__FILE__).'/savane.conf.php');

$in = new mysqli('', 'root', 'root', 'savane');
if ($in->connect_errno) {
  die($in->connect_error);
}
$in->set_charset('utf8');

$out = pg_connect("host=127.0.0.1 dbname=gforge user=gforge password=".file_get_contents('pass'))
  or die(pg_last_error());



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

// Remove default users
// - remove references to existing (admin) users in the RBAC
// - keep user "None", references in some trackers default AFAICR
pg_query($out, 'DELETE FROM pfo_user_role');
pg_query($out, 'DELETE FROM users WHERE user_id != 100');

function insert($out, $table, $entries) {
  $fields = array_keys($entries[0]);
  $query  = 'INSERT INTO users ('.join(',', $fields).') VALUES ';

  $first_entry = true;
  foreach ($entries as $entry) {
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

  pg_query($out, $query);
}

insert($out, 'users', $users);

$res = $in->query("SELECT * FROM user_group WHERE group_id=(SELECT group_id FROM groups WHERE unix_group_name='$sys_unix_group_name') AND admin_flags='A'") or die($in->error);
while ($row = $res->fetch_assoc()) {
  pg_query_params($out, 'INSERT INTO pfo_user_role VALUES ($1, (SELECT role_id FROM pfo_role WHERE role_name=\'Forge administrators\'))', array($row['user_id']));
}

// Remove empty task #1
pg_query($out, 'DELETE FROM project_task');
