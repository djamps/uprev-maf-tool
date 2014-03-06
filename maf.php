<?php
error_reporting(E_ALL);
ini_set("display_errors", 1); 
?>
<html>
<head>
<style>
table {
	text-align: left;
  font-size: 1.0em;
   }
th {
	font-weight: bold;
	background-color: #acf;
	border-bottom: 1px solid #cef; 
	font-size: 1.0em;
}
td {
	padding: 0px 0px; 
  width:75px;
  height:30px;
  font-size: 0.8em;
}
.odd {
	background-color: #def; 
}
.odd td {
	border-bottom: 1px solid #cef; 
}
ul
{
    list-style-type: none;
}
</style>
</head>
<body>
<h3>You can browse/download the <a href="https://github.com/djamps/uprev-maf-tool">source code</a> at github!</h3>
<h3>Logging params (* = required)</h3>
<ul>
<li>* MAF (v) (mas-v)</li>
<li>* A/F Ratio (bank 1 and 2)</li>
<li>* Corrections (bank 1 and 2)</li>
<li>TPS (v) (throttle position)</li>
<li>APP (v) (pedal position)</li>
<li>RPM</li>
<li>Speed</li>
<li>Timing</li>
<li>BFS (mS)</li>
</ul> 
<form enctype="multipart/form-data" action="maf.php" method="POST">
<!--<input type="hidden" name="MAX_FILE_SIZE" value="50000000" />-->
Choose a file to upload: <input name="uploadedfile" type="file" /><br />
<input type="submit" name="submit" value="Upload File" /><br/>

<?php
ini_set('display_errors',0);
ini_set('error_reporting',E_NONE);
$file = '';
$target_path = "uploads/";
if ( isset($_REQUEST['submit']) && $_REQUEST['submit'] == 'Upload File' ) {
  $file = basename( $_FILES['uploadedfile']['name']);
  $target_path = $target_path . $file; 
  if(move_uploaded_file($_FILES['uploadedfile']['tmp_name'], $target_path)) {
      echo "<br/>\nThe file ".  $file. 
      " has been uploaded...<br/>";
          do_maf($file);
  } else {
    echo "<br/>\nNo file uploaded<br/>\n";
  }

} elseif ( isset($_REQUEST['submit']) && $_REQUEST['submit'] == 'Recalculate'  ) {
  do_maf($_REQUEST['file']);
  
} else {
  //echo "There was an error uploading the file, please try again!<br/>\n".var_export($_FILES)."<br/>\n";
}

?>
</form>
</body>
</html>
<?php
function do_maf($file) {
  require_once('includes/class.uprev.php');
  $mode = 'maf';
  if ( isset($_REQUEST['mode']) && $_REQUEST['mode'] == 'fuel' ) { $mode = 'fuel'; }
  $o = new uprev();
  $o->file = $file;
  $o->mode = $mode;
  $o->scale = ((isset($_REQUEST['scale']) && $_REQUEST['scale'] > 0)?$_REQUEST['scale']:'23');
  $o->redline = ((isset($_REQUEST['redline']) && $_REQUEST['redline'] > 0)?$_REQUEST['redline']:'6400');
  $o->min_hits = ((isset($_REQUEST['min_hits']) && $_REQUEST['min_hits'] > 0)?$_REQUEST['min_hits']:'10');
        $o->calc_table();

          echo 'File: <input type="text" size="48" readonly name="file" value="'.($file?$file:$_REQUEST['file']).'"  />';
            if ( array_key_exists('rpm',$o->cols) ) {
            echo '    <p>RPM Min: <input type="text" name="rpm_min" size="5" value="'.(isset($_REQUEST['rpm_min'])?$_REQUEST['rpm_min']:'').'" /> ('.$o->ranges['rpm']['min'].') | 
                        RPM Max: <input type="text" name="rpm_max" size="5" value="'.(isset($_REQUEST['rpm_max'])?$_REQUEST['rpm_max']:'').'"  /> ('.$o->ranges['rpm']['max'].') </p>';
            }
            if ( array_key_exists('app-v',$o->cols) ) {
               echo '    <p>Accel(v) Min: <input type="text" name="appv_min" size="5" value="'.(isset($_REQUEST['appv_min'])?$_REQUEST['appv_min']:'').'"  /> ('.$o->ranges['app-v']['min'].') | 
                        Accel(v) Max: <input type="text" name="appv_max" size="5" value="'.(isset($_REQUEST['appv_max'])?$_REQUEST['appv_max']:'').'"  /> ('.$o->ranges['app-v']['max'].') </p>';
            }
            if ( array_key_exists('sched-ms',$o->cols) ) {
               echo '    <p>Sched(mS) Min: <input type="text" name="sched_min" size="5" value="'.(isset($_REQUEST['sched_min'])?$_REQUEST['sched_min']:'').'"  /> ('.$o->ranges['sched-ms']['min'].') | 
                        Sched(mS) Max: <input type="text" name="sched_max" size="5" value="'.(isset($_REQUEST['sched_max'])?$_REQUEST['sched_max']:'').'"  /> ('.$o->ranges['sched-ms']['max'].') </p>';
            }
            if ( array_key_exists('tps-v',$o->cols) ) {
               echo '    <p>TPS(v) Min: <input type="text" name="tpsv_min" size="5" value="'.(isset($_REQUEST['tpsv_min'])?$_REQUEST['tpsv_min']:'').'"  /> ('.$o->ranges['tps-v']['min'].') | 
                        TPS(v) Max: <input type="text" name="tpsv_max" size="5" value="'.(isset($_REQUEST['tpsv_max'])?$_REQUEST['tpsv_max']:'').'"  /> ('.$o->ranges['tps-v']['max'].') </p>';
            }
            if ( array_key_exists('speed',$o->cols) ) {
               echo '    <p>Speed Min: <input type="text" name="speed_min" size="5" value="'.(isset($_REQUEST['speed_min'])?$_REQUEST['speed_min']:'').'"  /> ('.$o->ranges['speed']['min'].') | 
                        Speed Max: <input type="text" name="speed_max" size="5" value="'.(isset($_REQUEST['speed_max'])?$_REQUEST['speed_max']:'').'"  /> ('.$o->ranges['speed']['max'].') </p>';
            }
            echo '<p>Min. Hits: <input type="text" name="min_hits" size="5" value="'.$o->min_hits.'"  /></p>';
          echo '<input type="radio" name="mode" value="maf" '.($mode=='maf'?'checked':'').' /> MAF Table    
                <input type="radio" name="mode" value="fuel" '.($mode=='fuel'?'checked':'').' /> Fuel Table [ Scale mS: <input type="text" name="scale" size="3" value="'.$o->scale.'"  /> ] [ Redline: <input type="text" name="redline" size="3" value="'.$o->redline.'"  /> ]<br>';
          echo '<p><input type="submit" name="submit" value="Recalculate" /></p>';
          $o->show_table();
  }

?>
