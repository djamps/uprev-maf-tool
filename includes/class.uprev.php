<?php
//include_once("includes/class.db.php");
ini_set('display_errors',0);

class uprev {
  //var $db;
  var $file;
  var $mode = 'maf';
  var $scale = 23;
  var $redline = 6400;
  var $rpm_min;
  var $rpm_max;
  var $appv_min;
  var $appv_max;
  var $tpsv_min;
  var $tpsv_max;
  var $sched_min;
  var $sched_max;
  var $cols;
  var $min_hits;
  var $fuel_maxhits;
  var $ranges = array();
  var $col_regex = array (
                    'time'=>'/^time$/i',
                    'corr1'=>'/corr-b1.+\(%\)/i',
                    'corr2'=>'/corr-b2.+\(%\)/i',
                    'afr1'=>'/(wb-b1|lc-1|\(afr\)).+(wb-b1|lc-1|\(afr\))/i',
                    'afr2'=>'/(wb-b2|lc-1|\(afr\)).+(wb-b2|lc-1|\(afr\))/i',
                    'sched-ms'=>'/(Base Fuel Schedule)|(b-fuel.+\(ms\))/i',
                    'rpm'=>'/rpm.+\(rpm\)/i',
                    'timing'=>'/ign timing.+\(btdc\)/i',
                    'mas-v'=>'/mas a\/f.+\(v\)/i',
                    'tps-v'=>'/throttle (pos|sen).+\((v-throttle|v)\)/i',
                    'app-v'=>'/accel.+\((v-accel|v)\)/i',
                    'timing'=>'/ign timing.+\(btdc\)/i',
                    'speed'=>'/vehicle speed/i',
                    'temp'=>'/coolant temp/i',
                  );
                  
   var $maftable = array();
   var $fueltable = array();
           
  var $masv_ranges = array();
  var $rpm_ranges = array();
  var $sched_ranges = array();
              
  function __construct() {

    $this->rpm_min = isset($_REQUEST['rpm_min'])?$_REQUEST['rpm_min']:'';
    $this->rpm_max = isset($_REQUEST['rpm_max'])?$_REQUEST['rpm_max']:'';
    $this->appv_min = isset($_REQUEST['appv_min'])?$_REQUEST['appv_min']:'';
    $this->appv_max = isset($_REQUEST['appv_max'])?$_REQUEST['appv_max']:'';
    $this->sched_min = isset($_REQUEST['sched_min'])?$_REQUEST['sched_min']:'';
    $this->sched_max = isset($_REQUEST['sched_max'])?$_REQUEST['sched_max']:'';
    $this->tpsv_min = isset($_REQUEST['tpsv_min'])?$_REQUEST['tpsv_min']:'';
    $this->tpsv_max = isset($_REQUEST['tpsv_max'])?$_REQUEST['tpsv_max']:'';
    $this->speed_min = isset($_REQUEST['speed_min'])?$_REQUEST['speed_min']:'';
    $this->speed_max = isset($_REQUEST['speed_max'])?$_REQUEST['speed_max']:'';
    //echo var_export($this->masv_ranges);
  }
  
  function __destruct() {
    //unset($this->db);
  }
  
  function _parse_head ($head="") {
    if ( !is_array($head) ) {
      return;
    }
    $n = 0;
    $colarray = array();
    foreach ( $head as $key=>$val ) {
      foreach ( $this->col_regex as $column => $regex ) { if ( preg_match($regex,$val) ) { $colarray[$column]=$n; } }
      $n++;
    }
    return $colarray;
  }
  
  function gen_masv_ranges() {
    $tmp = array();
    $step = 0.078;
    for ($i = 0.08; $i <= 5.00; $i=$i+$step) {
      $tmp[] = array( 'name' => number_format($i,2) , 'min' => $i-($step/2) , 'max' => $i+($step/2) );
    }
    //echo var_export($tmp);
    //exit;
    return $tmp;
  }
  
  function gen_rpm_ranges() {
    $tmp = array();
    $step = ($this->redline-400)/15;
    for ($i = 400; $i <= $this->redline+100; $i=$i+$step) {
      $tmp[] = array( 'name' => round($i/10)*10 , 'min' => $i-($step/2) , 'max' => $i==$this->redline?9999:$i+($step/2)-1 );
    }
    //echo var_export($tmp);
    //exit;
    return $tmp;
  }
  
  function gen_sched_ranges() {
    $tmp = array();
    $step = $this->scale / 16;
    for ($i = 1; $i <= 16; $i++) {
      $tmp[] = array( 'name' => number_format($step*$i-$step,1) , 'min' => $step*$i-($step) , 'max' => $step*$i );
    }
    //echo var_export($tmp);
    //exit;
    return $tmp;
  }
  
  function calc_table () { 
    if ( !$this->file ) { trigger_error('No file',E_USER_WARNING); return; }
    $m = 0;
    $file = $this->file;
    $tmp = array();
    $i = 0;
    $y = 0;
    $tot = array();
    $cor = array();
    $afr1 = array();
    $afr2 = array();
    $this->masv_ranges = $this->gen_masv_ranges();
    $this->rpm_ranges = $this->gen_rpm_ranges();
    $this->sched_ranges = $this->gen_sched_ranges();
    //echo "Opening $file<br/>\n";
    if (($handle = fopen('uploads/'.basename($file), "r")) !== FALSE) {
      echo "Parsing samples";
      while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $y++;
        $m++;
        if ( $y > 1000 ) { echo "."; $y = 0; }
        //$num = count($data);
        if ( $m == 1 ) {
          $this->cols = $this->_parse_head($row);
          if ( !$this->cols ) { trigger_error('Unable to parse csv header',E_USER_WARNING); return; }
          //if ( !$this->cols['corr1'] || !$this->cols['corr2'] || !$this->cols['mas-v'] ) { echo "Missing params (required = mas(v), corr1, corr2).<br/>\n"; return; }
//           if ( ($this->rpm_min || $this->rpm_max) && !$this->cols['rpm'] ) { echo "RPM limit specified but no data in log.<br/>"; return; }
//           if ( ($this->appv_min || $this->appv_max) && !$this->cols['app-v'] ) { echo "APP(v) limit specified but no data in log.<br/>"; return; }
//           if ( ($this->sched_min || $this->sched_max) && !$this->cols['sched-ms'] ) { echo "Fuel schedule (mS) limit specified but no data in log.<br/>"; return; }
//           if ( ($this->tpsv_min || $this->tpsv_max) && !$this->cols['tps-v'] ) { echo "Throttle position(v) limit specified but no data in log.<br/>"; return; }
          //echo var_export($this->cols);
          //exit;
        } else {
          foreach ( $this->cols as $col => $z ) {
            if ( !isset($this->ranges[$col]['max']) ) { $this->ranges[$col]['max'] = $row[$z]; };
            if ( !isset($this->ranges[$col]['min']) ) { $this->ranges[$col]['min'] = $row[$z]; };
            if ( $row[$z] >= $this->ranges[$col]['max'] ) { $this->ranges[$col]['max'] = $row[$z]; };
            if ( $row[$z] <= $this->ranges[$col]['min'] ) { $this->ranges[$col]['min'] = $row[$z]; };
          }
          //Process the data
          if ( $this->rpm_min && isset($this->cols['rpm']) && $row[$this->cols['rpm']] < $this->rpm_min )  { continue; };
          if ( $this->rpm_max && isset($this->cols['rpm']) && $row[$this->cols['rpm']] > $this->rpm_max )  { continue; };
          if ( $this->appv_min && ( !isset($this->cols['app-v']) || $row[$this->cols['app-v']] < $this->appv_min ))  { continue; };
          if ( $this->appv_max && ( !isset($this->cols['app-v']) || $row[$this->cols['mas-v']] > $this->appv_max ))  { continue; };
          if ( $this->sched_min && ( !isset($this->cols['sched-ms']) || $row[$this->cols['sched-ms']] < $this->sched_min ))  { continue; };
          if ( $this->sched_max && ( !isset($this->cols['sched-ms']) || $row[$this->cols['sched-ms']] > $this->sched_max ))  { continue; };
          if ( $this->tpsv_min && ( !isset($this->cols['tps-v']) || $row[$this->cols['tps-v']] < $this->tpsv_min ))  { continue; };
          if ( $this->tpsv_max && ( !isset($this->cols['tps-v']) || $row[$this->cols['tps-v']] > $this->tpsv_max ))  { continue; };
          
          if ( $this->mode == 'maf' ) {
            foreach ( $this->masv_ranges as $n => $range ) {
              if ( !isset($this->maftable[$n]['tot']) ) { $this->maftable[$n]['tot'] = 0; }
              if ( $row[$this->cols['mas-v']] > $range['min'] && $row[$this->cols['mas-v']] < $range['max']  ) {
                if ( !isset($this->maftable[$n]['cor']) ) { $this->maftable[$n]['cor'] = (($row[$this->cols['corr1']] + $row[$this->cols['corr2']])/200); }
                if ( !isset($this->maftable[$n]['cor1']) ) { $this->maftable[$n]['cor1'] = (($row[$this->cols['corr1']])/100); }
                if ( !isset($this->maftable[$n]['cor2']) ) { $this->maftable[$n]['cor2'] = (($row[$this->cols['corr2']])/100); }
                $this->maftable[$n]['cor'] = ($this->maftable[$n]['cor'] + (($row[$this->cols['corr1']] + $row[$this->cols['corr2']])/200))/2;
                $this->maftable[$n]['cor1'] = ($this->maftable[$n]['cor1'] + (($row[$this->cols['corr1']] )/100))/2;
              	$this->maftable[$n]['cor2'] = ($this->maftable[$n]['cor2'] + (($row[$this->cols['corr2']] )/100))/2;

                if ( isset($this->cols['afr1']) && isset($this->cols['afr2']) ) {
                  if ( !isset($this->maftable[$n]['afr1']) ) { $this->maftable[$n]['afr1'] = $row[$this->cols['afr1']]; }
                  if ( !isset($this->maftable[$n]['afr2']) ) { $this->maftable[$n]['afr2'] = $row[$this->cols['afr2']]; }
                  $this->maftable[$n]['afr1'] = ($this->maftable[$n]['afr1'] + $row[$this->cols['afr1']])/2;
                  $this->maftable[$n]['afr2'] = ($this->maftable[$n]['afr2'] + $row[$this->cols['afr2']])/2;
                }
                $this->maftable[$n]['tot']++;
                $i++;
              } // valid values
            } // masv range
          } elseif ($this->mode == 'fuel') {
            foreach ( $this->rpm_ranges as $index_y => $rpm_range ) {
              foreach ( $this->sched_ranges as $index_x => $sched_range ) {
                if ( !isset($this->fueltable[$index_y][$index_x]['tot']) ) { $this->fueltable[$index_y][$index_x]['tot'] = 0; }

                //if ( !isset($this->fueltable[$x][$y]['cor']) ) { $this->fueltable[$x][$y]['cor'] = 0; }
                //if ( !isset($this->fueltable[$x][$y]['timing']) ) { $this->fueltable[$x][$y]['timing'] = 0; }
                //echo var_export($row)."<br/>\n";
                //if ( $row[$this->cols['mas-v']] > $range['min'] && $row[$this->cols['mas-v']] < $range['max'] && !($row[$this->cols['corr1']] == 100 && $row[$this->cols['corr2']] == 100 ) ) {
                if ( ($row[$this->cols['sched-ms']] >= $sched_range['min'] && $row[$this->cols['sched-ms']] < $sched_range['max']) &&  ($row[$this->cols['rpm']] >= $rpm_range['min'] && $row[$this->cols['rpm']] < $rpm_range['max']) ) {
                  if ( !isset($this->fueltable[$index_y][$index_x]['cor']) ) { $this->fueltable[$index_y][$index_x]['cor'] = (($row[$this->cols['corr1']] + $row[$this->cols['corr2']])/200); }
                  $this->fueltable[$index_y][$index_x]['cor'] = ($this->fueltable[$index_y][$index_x]['cor'] + (($row[$this->cols['corr1']] + $row[$this->cols['corr2']])/200))/2;
                  if ( isset($this->cols['afr1']) && isset($this->cols['afr2']) ) {
                    if ( !isset($this->fueltable[$index_y][$index_x]['afr1']) ) { $this->fueltable[$index_y][$index_x]['afr1'] = $row[$this->cols['afr1']]; }
                    if ( !isset($this->fueltable[$index_y][$index_x]['afr2']) ) { $this->fueltable[$index_y][$index_x]['afr2'] = $row[$this->cols['afr2']]; }
                    $this->fueltable[$index_y][$index_x]['afr1'] = ($this->fueltable[$index_y][$index_x]['afr1'] + $row[$this->cols['afr1']])/2;
                    $this->fueltable[$index_y][$index_x]['afr2'] = ($this->fueltable[$index_y][$index_x]['afr2'] + $row[$this->cols['afr2']])/2;
                  }
                  if ( isset($this->cols['timing']) ) {
                    if ( !isset($this->fueltable[$index_y][$index_x]['timing']) ) { $this->fueltable[$index_y][$index_x]['timing'] = $row[$this->cols['timing']]; }
                    $this->fueltable[$index_y][$index_x]['timing'] = ($this->fueltable[$index_y][$index_x]['timing'] + $row[$this->cols['timing']])/2;
                  }
                  $this->fueltable[$index_y][$index_x]['tot']++;
                  if ( $this->fueltable[$index_y][$index_x]['tot'] > $this->fuel_maxhits) { $this->fuel_maxhits = $this->fueltable[$index_y][$index_x]['tot'];}
                  $i++;
                }
              } // valid values
            } // masv range            
          }
        } // process values

      } // getcsv loop
      //echo var_export($this->fueltable);
      echo "done<br/>\n";
      return $i;
      // Calculate averages for each correction cell
//       if ( $this->mode == 'maf' ) {
//         unset($this->maf_corrections);
//         foreach ($this->fueltable[$x] as $x => $sched_range) {
//           if ( $sched_range['tot'] > 1 ) {
//             $this->maf_corrections[$n]['afr1'] = round(($afr1[$n]/$p),2);
//             $this->maf_corrections[$n]['afr2'] = round(($afr2[$n]/$p),2);
//             $this->maf_corrections[$n]['avg'] = round(($cor[$n]/$p)*100,1);
//             $this->maf_corrections[$n]['adj'] = round(($cor[$n]/$p),2);
//             $this->maf_corrections[$n]['tot'] = $p;
//           }
//         }
//         echo $i." of ".($m-1)." samples used in calcs<br/>\n";
//         return $i;
//       } elseif ($this->mode == 'fuel') {
//         //nothing;
//       }
    }
  }
  
  function show_table() {
    if ( $this->mode == 'maf' ) { return $this->show_maf_table(); }
    if ( $this->mode == 'fuel' ) { return $this->show_fuel_table(); }
  }
  
  function show_maf_table () {
    //$n = 0;
    //$p = 0;
    //$tot = 0;
    if ( !isset($this->maftable) ) { trigger_error('No data to show!',E_USER_WARNING); return; }
    echo '<table cellpadding="0" cellspacing="0" style="width: 320px"><thead><tr>
      <th>MAF(v)</th>
      <th>AFR 1/2 (Cor.)</th>
      <th>Hits</th>
    </tr></thead><tbody>';
    foreach ( $this->maftable as $n => $cor ) {
      if ( $cor['tot'] > 0 ) {
      echo '<tr'.($n%2?' class="odd"':'').'>
        <th>'.$this->masv_ranges[$n]['name'].'</th>
        <td>'.(($cor['tot']>$this->min_hits)?(round(($cor['afr1']),2).'/'.round(($cor['afr2']),2).' ('.number_format($cor['cor1'],2).'/'.number_format($cor['cor2'],2).')'):'--').'</td>
        <td>'.(($cor['tot']>$this->min_hits)?$cor['tot']:'--').'</td>
      </tr>';
      $n++;
      //if ( $cor['tot']>200 ) { $tot = $tot + $cor['adj']; $p++; }
      }
    }
    echo '</tbody></table>';
    //echo var_export($this->gen_sched_ranges());

  }
  
  function show_fuel_table () {
    //$n = 0;
    //$p = 0;
    //$tot = 0;
    if ( !isset($this->fueltable) ) { trigger_error('No data to show!',E_USER_WARNING); return; }
    echo '<table><thead><tr><th></th>';
    foreach ( $this->sched_ranges as $x_index => $sched_range ) {
      echo '<th>'.$sched_range['name'].'</th>';
    }
    echo '</tr></thead><tbody>';
    $n = 0;
    foreach ( $this->rpm_ranges as $y_index => $rpm_range ) {
      echo '<tr><th>'.$rpm_range['name'].'</th>';
      foreach ( $this->fueltable[$y_index] as $x_index => $val ) {
        $hex = $val['tot']>$this->min_hits?dechex(220-(ceil(($val['tot']*96)/$this->fuel_maxhits))):'FF';
        echo '<td style="background-color: #'.$hex.$hex.'FF;">'.(($val['tot']>$this->min_hits)?('h:'.$val['tot'].'  a:'.round((($val['afr1']+$val['afr2'])/2),1).'<br/>t:'.round($val['timing'],1).'  c:'.number_format($val['cor'],2)):'--').'</td>';
      }
      echo '</tr>';
      $n++;
    }
    echo '</tbody></table>';
    

  }
   

  
  function parse () {
    $row = 1;
    $file = $this->file;
    $tmp = array();
    echo "Processing $file<br/>\n";
    if (($handle = fopen($file, "r")) !== FALSE) {
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
          //$num = count($data);
          if ( $row == 1 ) {
            $this->cols = $this->_parse_head($data);
            if ( !$this->cols ) { trigger_error('Unable to parse csv header'); return; }
          } else {
//              $values = array();
//              foreach ( $this->cols as $col => $n ) {
//                 $values[$col] = $data[$n];
//              }
             //array_push($this->data,$data);
             $tmp[] = $data;
//              unset($values);
          }
          $row++;
        }
        fclose($handle);
    } else {
      trigger_error('Error processing csv file',E_USER_WARNING);
      return;
    }
    echo "Found ".($row-1)." samples<br/>\n";
    return 1;
  }
}
?>
