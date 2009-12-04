<?php


define('APACHE_IP', '127.0.0.1');

define('DB_NAME', 'wordpress');
define('DB_USER', 'root');
define('DB_PASSWORD', 'root');
define('DB_HOST', '127.0.0.1');

DEFINE('DEBUG', false);

# only cli
if ( $argc != 1 )
  die('');

$conn = mysql_connect(DB_HOST, DB_USER, DB_PASSWORD);
mysql_select_db(DB_NAME);

$local_time = time();

// check all blogs
$rs = mysql_query("SELECT s.domain, b.path, b.blog_id FROM wp_site as s, wp_blogs as b where b.site_id = s.id;");

$total = 0;
while( ( $row = mysql_fetch_array($rs) ) != false ) {
  // print_r($row);
  if (DEBUG)
    echo "check:{$row['domain']}{$row['path']}\t\t";
  
  $blog_id = (int)$row['blog_id'];

  # load cron options
  $rs2 = mysql_query("SELECT option_value FROM wp_{$blog_id}_options WHERE option_name = 'cron'");
  $row2 = mysql_fetch_array($rs2);
  $crons = unserialize($row2['option_value']);
  mysql_free_result($rs2);
  
  # isn't a array!
  if ( !is_array($crons) ) {
    if (DEBUG)
      echo "not_array\n";
    continue;
  }

  # equals to wp-cron.php check
  $keys = array_keys( $crons );
  if ( isset($keys[0]) && $keys[0] > $local_time ) {
    if (DEBUG)
      echo "skip\n";
    continue;
  }

  echo "cron: {$row['domain']}{$row['path']}\n";

  # try connect to apache at IP.
  $fp = fsockopen(APACHE_IP, 80);
  if (!$fp) {
    echo "error connecting at {APACHE_IP}!";
    continue;
  }
  $request = "GET {$row['path']}wp-cron.php HTTP/1.0\r\nHost: {$row['domain']}\r\n\r\n";
  fputs($fp, $request);
  if ( DEBUG ) {
    echo "\n", $request;
    echo "request\n";
  }
  fclose($fp);
  
  $total++;
  if ( $total > 100 ) {
    # wait a while!.
    sleep(2);
    $total = 0;
    if ( DEBUG )
      echo "sleep\n";
  }
}
mysql_free_result($rs);
mysql_close($conn);

