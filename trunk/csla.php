<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN">
<html>
<head>
<title>Squid Log Analizer</title>
<meta name="Author" content="Tien D. Tran">
<style type="text/css">
<!--
*{font-family:arial,sans-serif;font-size:8pt}
table{border-collapse:collapse;border:1px solid #333}
th{text-align:center}
td,th{border:1px solid #333;padding:1px 3px}
.highlight{background-color:#faa}
.bold{font-weight:bold}
a{text-decoration:none}
.right, .right td{text-align:right}
-->
</style>
</head>
<body>
<?php

/****** BEGIN CONFIGURATIONS ******/
$logfile = 'access.log'; // path to squid log file
$db = 'history.db'; // path to cached file
$peakHours = array(0, 1, 2, 3, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23); // define peak hours, the rest will be off-peak
$lastupdate = mktime(17, 0, 0, 6, 25, 2009); // first date of the counter to read from the log
$billingCycleStartDate = 22; // the first day of your billing cycle, the end of billing cycle is x - 1
/****** END CONFIGURATIONS ******/

function isPeak($h) {
   global $peakHours;
   return in_array($h, $peakHours);
}

$data = array();
$cur_ip = $_GET['ip'] ? $_GET['ip'] : (($_SERVER['HTTP_X_FORWARDED_FOR'] && $_SERVER['HTTP_X_FORWARDED_FOR'] != '127.0.0.1') ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR']);

function loadData() {
   global $data,$db,$lastupdate;
   $h = fopen($db, 'r');
   $fs = filesize($db);
   if (!$fs) return;
   $hist = unserialize(fread($h, $fs));
   fclose($h);
   if ($hist) {
      $data = $hist[0];
      $lastupdate = $hist[1];
   }
}
function persistData() {
   global $data,$db,$lastupdate;
   $h = fopen($db, 'w');
   fwrite($h, serialize(array(0=>$data,1=>$lastupdate)));
   fclose($h);
}
function getData($d, $h) {
   return getMonthData(getYM(), $d, $h);
}
function getMonthData($m, $d, $h) {
   global $data, $cur_ip;
   if (isset($data[$cur_ip]) && isset($data[$cur_ip][$m]) && isset($data[$cur_ip][$m][$d]))
      return $data[$cur_ip][$m][$d][$h];
   return '';
}
function addData($ip, $t, $v) {
   global $data, $lastupdate;
   if ($t <= $lastupdate) return false;

   $m = getYM($t);
   $d = date('j', $t);
   $h = date('G', $t);

   if (!isset($data[$ip]))
      $data[$ip] = array();
   if (!isset($data[$ip][$m]))
      $data[$ip][$m] = array();
   if (!isset($data[$ip][$m][$d]))
      $data[$ip][$m][$d] = array();
   if (!isset($data[$ip][$m][$d][$h]))
      $data[$ip][$m][$d][$h] = $v;
   else
      $data[$ip][$m][$d][$h]+=$v;

   return true;
}
function getYM($time = false, $monthoffset = 0) {
   $m = $time ? date('n', $time) : date('n');
   $m += $monthoffset;
   $time = mktime(0, 0, 0, $m, 1);
   return intval(date('ym', $time));
}
function getTotalToday($ip = false, $peak = null) {
   global $data, $cur_ip;
   if (!$ip) $ip = $cur_ip;

   if (!isset($data[$ip])) return 0;
   $m = getYM();
   if (!isset($data[$ip][$m])) return 0;
   $d = date('j');
   if (!isset($data[$ip][$m][$d])) return 0;
   $val = 0;
   if ($peak === null) foreach ($data[$ip][$m][$d] as $hu) $val += $hu;
   if ($peak === true) foreach ($data[$ip][$m][$d] as $h => $hu) if (isPeak($h)) $val += $hu;
   if ($peak === false) foreach ($data[$ip][$m][$d] as $h => $hu) if (!isPeak($h)) $val += $hu;
   return format($val);
}
function getTotalMonth($ip = false, $monthoffset = 0, $peak = null) {
   global $data, $cur_ip, $billingCycleStartDate;
   if (!$ip) $ip = $cur_ip;

   if (!isset($data[$ip])) return 0;

   $d = date('j');
   if ($d < $billingCycleStartDate) $monthoffset--;

   $m1 = getYM(false, $monthoffset);
   $m2 = getYM(false, $monthoffset + 1);
   $val = 0;
   if (isset($data[$ip][$m1]))
      for ($du = $billingCycleStartDate; $du < 32; $du++) {
         if (!isset($data[$ip][$m1][$du])) continue;
         if ($peak === null) foreach ($data[$ip][$m1][$du] as $hu) $val += $hu;
         if ($peak === true) foreach ($data[$ip][$m1][$du] as $h => $hu) if (isPeak($h)) $val += $hu;
         if ($peak === false) foreach ($data[$ip][$m1][$du] as $h => $hu) if (!isPeak($h)) $val += $hu;
      }
   if (isset($data[$ip][$m2]))
      for ($du = 1; $du < $billingCycleStartDate; $du++) {
         if (!isset($data[$ip][$m2][$du])) continue;
         if ($peak === null) foreach ($data[$ip][$m2][$du] as $hu) $val += $hu;
         if ($peak === true) foreach ($data[$ip][$m2][$du] as $h => $hu) if (isPeak($h)) $val += $hu;
         if ($peak === false) foreach ($data[$ip][$m2][$du] as $h => $hu) if (!isPeak($h)) $val += $hu;
      }
   return format($val);
}
function format($v) {
   if (!$v) return '';
   if ($v > 1048676) return number_format($v/1048576, 2, '.', '') . ' MB';
   if ($v > 1024) return number_format($v/1024, 2, '.', '') . ' KB';
   return $v . ' bytes';
}

loadData();
$log = file($logfile);
$lines = count($log);
$count = 0;
$max = 10;
?>
<h4>Statistics</h4>
<?php
$newLatest = false;
$newData = false;
for ($line = $lines -1; $line >= 0; $line--) {
	list($time,$ip,$type,$size,$method,$url,$username,$server,$mime) = explode("\t", $log[$line]);
   if (!$newLatest) $newLatest = $time;
   if (stripos('TCP_HIT/UDP_HIT/TCP_DENIED/UDP_DENIED/TCP_MEM_HIT/', substr($type,0,-4)) !== false) continue;
   if (stripos($server, '/192.168.') !== false) continue;
   if (!addData($ip, $time, $size)) break;
   $newData = true;
}
$lastupdate = $newLatest;
if ($newData) persistData();
?>
<table class="right">
<tr><th rowspan="2">IP</th><th colspan="3">Today</th><th colspan="3">This month</th><th colspan="3">Last month</th></tr>
<tr><th>Peak</th><th>Off-peak</th><th>All</th><th>Peak</th><th>Off-peak</th><th>All</th><th>Peak</th><th>Off-peak</th><th>All</th></tr>
<?php
foreach($data as $ip => $values)
   echo '<tr',$ip==$cur_ip?' class="highlight"':'','><td><a href="?ip=',$ip,'">',$ip,'</a></td><td>',getTotalToday($ip, true),'</td><td>',getTotalToday($ip, false),'</td><td>',getTotalToday($ip),'</td><td>',getTotalMonth($ip, 0, true),'</td><td>',getTotalMonth($ip, 0, false),'</td><td>',getTotalMonth($ip),'</td><td>',getTotalMonth($ip, -1, true),'</td><td>',getTotalMonth($ip, -1, false),'</td><td>',getTotalMonth($ip, -1),'</td></tr>';
?>
</table>
<h4>Recent visited urls & usage [<?php echo $cur_ip ?>]</h4>
<table>
<tr><th>Time</th><th>URL</th><th>Mime</th><th>Usage</th></tr>
<?php
for ($line = $lines -1; $count < $max && $line > -1; $line--) {
	//%ts.%03tu	%>a	%Ss/%03Hs	%<st	%rm	%ru	%un	%Sh/%<A	%mt
	list($time,$ip,$type,$size,$method,$url,$username,$server,$mime) = explode("\t", $log[$line]);
	if ($ip != $cur_ip) continue;
   if (stripos($server, '/192.168.') !== false) continue;
	$count++;
	$time = date('d/m/Y g:i a', $time);
   $size = format($size);
	echo "<tr><td>$time</td><td>$url</td><td>$mime</td><td class=\"right\">$size</td></tr>";
}
?>
</table>
<h4>Hourly usage [<?php echo $cur_ip ?>]</h4>
<table>
<?php
$today = date('j');
$hour = date('G');
  echo '<tr><th></th>';
  for($h = 0; $h < 24; $h++) {
     echo '<th',$h==$hour?' class="highlight"':'','>',$h,':00</th>';
  }
  echo '<th>Total</th></tr>';
  $stop = getYM(mktime(0, 0, 0, date('n')-1, 1));
  $mon = intval(date('n'));
  $day = intval(date('j'));
  $time = mktime(0, 0, 0, $mon, $day);
  while(($ym = getYM($time)) >= $stop) {
     $m = date('M', $time);
     $d = date('j', $time);
     $dayStr = '<tr><td class="bold">'.$m.' '. $d. '</td>';
     $ht = 0;
     for($h = 0; $h < 24; $h++) {
        $dayStr .= '<td class="right'.(($h==$hour || isPeak($h)) ?' highlight':'').'">'.format($t = getMonthData($ym, $d, $h)).'</td>';
        $ht+=$t;
     }
     if ($ht > 0) echo $dayStr, '<td class="right bold">',format($ht),'</td></tr>';
     $time = mktime(0, 0, 0, $mon, --$day);
  }
?>
</table>
</body>
</html>