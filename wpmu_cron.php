<?php

# Round-robin
$www_servers = array('127.0.0.1', 'localhost');

define('DB_NAME', 'wordpress');
define('DB_USER', 'root');
define('DB_PASSWORD', '');
define('DB_HOST', '127.0.0.1');

DEFINE('DEBUG', false);

# only cli
if ( $argc != 1 )
  die('cli');

$conn = mysql_connect(DB_HOST, DB_USER, DB_PASSWORD);
if ( !$conn ) {
    die(mysql_error() . "\n");
}
mysql_select_db(DB_NAME);

$local_time = time();

// check all blogs
$rs = mysql_query("SELECT s.domain, b.path, b.blog_id FROM wp_site as s, wp_blogs as b where b.site_id = s.id;");
// echo $rs;
$c = 0;

while( ( $row = mysql_fetch_array($rs) ) != false ) {
  // print_r($row);
  if (DEBUG)
    echo "check:{$row['domain']}{$row['path']}\t\t";
  
  $blog_id = (int)$row['blog_id'];

  # load cron options
  $rs2 = mysql_query("SELECT option_value FROM wp_{$blog_id}_options WHERE option_name = 'cron'");
  $row2 = mysql_fetch_array($rs2);
  mysql_free_result($rs2);
  
  // skip non-publish_future_post tasks
  if ( !preg_match('/publish_future_post/', $row2['option_value']) ) {
      if (DEBUG) {
          echo "without 'publish_future_post' task\n";
      }
      continue;
  }
  
  $crons = unserialize($row2['option_value']);

  # isn't a array!
  if ( !is_array($crons) ) {
    if (DEBUG) {
      echo "not_array\n";
    }
    continue;
  }

  // remove non-publish_future_post scheadule
  foreach ( $crons as $key => $cron ) {
      if ( is_array($cron) &&
        !array_key_exists('publish_future_post', $cron) ) {
        unset($crons[$key]);
      }
  }
  
  $keys = array_keys( $crons );
  // echo "keys :" . date("c", $keys[0]) . "\n";
  // echo "local:" . date("c", $local_time) . "\n";
  if ( isset($keys[0]) && $keys[0] > $local_time ) {
    if (DEBUG)
      echo "skip\n";
    continue;
  }
  
  echo "cron: {$row['domain']}{$row['path']}\n";

  # try connect to apache at IP.
  $server = $www_servers[$c % count($www_servers)];
  $fp = fsockopen($server, 80);
  if (!$fp) {
    echo "error connecting at {$server} at port 80!";
    continue;
  }
  $request = "GET {$row['path']}wp-cron.php HTTP/1.0\r\nHost: {$row['domain']}\r\n\r\n";
  fputs($fp, $request);
  if ( DEBUG ) {
    echo "\n", $request;
    echo "request\n";
  }
  fclose($fp);
  
  $c++;
  if ( $c > 500 ) {
    # wait a while!.
    sleep(2);
    $total = 0;
    if ( DEBUG )
      echo "sleep\n";
  }
}
mysql_free_result($rs);
mysql_close($conn);

