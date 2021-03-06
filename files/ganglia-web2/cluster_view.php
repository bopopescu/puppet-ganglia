 <?php
 /* $Id$ */
$tpl = new Dwoo_Template_File( template("cluster_view.tpl") );
$data = new Dwoo_Data();
$data->assign("extra", template("cluster_extra.tpl"));

$data->assign("images","./templates/${conf['template_name']}/images");

$data->assign("user_may_edit", checkAccess( $clustername, GangliaAcl::EDIT, $conf ) );
$data->assign("graph_engine", $conf['graph_engine']);

$cpu_num = !$showhosts ? $metrics["cpu_num"]['SUM'] : cluster_sum("cpu_num", $metrics);
$load_one_sum = !$showhosts ? $metrics["load_one"]['SUM'] : cluster_sum("load_one", $metrics);
$load_five_sum = !$showhosts ? $metrics["load_five"]['SUM'] : cluster_sum("load_five", $metrics);
$load_fifteen_sum = !$showhosts ? $metrics["load_fifteen"]['SUM'] : cluster_sum("load_fifteen", $metrics);
#
# Correct handling of *_report metrics
#
if (!$showhosts) {
  if(array_key_exists($metricname, $metrics))
     $units = $metrics[$metricname]['UNITS'];
  }
else {
  if(array_key_exists($metricname, $metrics[key($metrics)]))
     if (isset($metrics[key($metrics)][$metricname]['UNITS']))
        $units = $metrics[key($metrics)][$metricname]['UNITS'];
     else
        $units = '';
  }

if(isset($cluster['HOSTS_UP'])) {
    $data->assign("num_nodes", intval($cluster['HOSTS_UP']));
} else {
    $data->assign("num_nodes", 0);
}
if(isset($cluster['HOSTS_DOWN'])) {
    $data->assign("num_dead_nodes", intval($cluster['HOSTS_DOWN']));
} else {
    $data->assign("num_dead_nodes", 0);
}
$data->assign("cpu_num", $cpu_num);
$data->assign("localtime", date("Y-m-d H:i", $cluster['LOCALTIME']));

if (!$cpu_num) $cpu_num = 1;
$cluster_load15 = sprintf("%.0f", ((double) $load_fifteen_sum / $cpu_num) * 100);
$cluster_load5 = sprintf("%.0f", ((double) $load_five_sum / $cpu_num) * 100);
$cluster_load1 = sprintf("%.0f", ((double) $load_one_sum / $cpu_num) * 100);
$data->assign("cluster_load", "$cluster_load15%, $cluster_load5%, $cluster_load1%");

$avg_cpu_num = find_avg($clustername, "", "cpu_num");
if ($avg_cpu_num == 0) $avg_cpu_num = 1;
$cluster_util = sprintf("%.0f", ((double) find_avg($clustername, "", "load_one") / $avg_cpu_num ) * 100);
$data->assign("cluster_util", "$cluster_util%");

$cluster_url=rawurlencode($clustername);

// If we want zoomable support on graphs we need to add correct zoomable class to every image
$additional_cluster_img_html_args = "";
$additional_host_img_html_args = "";
if ( isset($conf['zoom_support']) && $conf['zoom_support'] === true )
   $additional_cluster_img_html_args = "class=cluster_zoomable";

$data->assign("additional_cluster_img_html_args", $additional_cluster_img_html_args);

$data->assign("cluster", $clustername);

$graph_args = "c=$cluster_url&amp;$get_metric_string&amp;st=$cluster[LOCALTIME]";

$optional_reports = "";

####################################################################################
# Let's find out what optional reports are included
# First we find out what the default (site-wide) reports are then look
# for host specific included or excluded reports
####################################################################################
$default_reports = array("included_reports" => array(), "excluded_reports" => array());
if ( is_file($conf['conf_dir'] . "/default.json") ) {
  $default_reports = array_merge($default_reports,json_decode(file_get_contents($conf['conf_dir'] . "/default.json"), TRUE));
}
$cluster_file = $conf['conf_dir'] . "/cluster_" . str_replace(" ", "_", $clustername) . ".json";
$override_reports = array("included_reports" => array(), "excluded_reports" => array());
if ( is_file($cluster_file) ) {
  $override_reports = array_merge($override_reports, json_decode(file_get_contents($cluster_file), TRUE));
}

# Merge arrays
$reports["included_reports"] = array_merge( $default_reports["included_reports"] , $override_reports["included_reports"]);
$reports["excluded_reports"] = array_merge($default_reports["excluded_reports"] , $override_reports["excluded_reports"]);

# Remove duplicates
$reports["included_reports"] = array_unique($reports["included_reports"]);
$reports["excluded_reports"] = array_unique($reports["excluded_reports"]);

foreach ( $reports["included_reports"] as $index => $report_name ) {
  if ( ! in_array( $report_name, $reports["excluded_reports"] ) ) {
    $optional_reports .= "<a name=metric_" . $report_name . ">
    <A HREF=\"./graph_all_periods.php?$graph_args&amp;g=" . $report_name . "&amp;z=large&amp;c=$cluster_url\">
    <IMG BORDER=0 style=\"padding:2px;\" $additional_cluster_img_html_args title=\"$cluster_url\" SRC=\"./graph.php?$graph_args&amp;g=" . $report_name ."&amp;z=medium&amp;c=$cluster_url\"></A>
";
  }

}

$data->assign("optional_reports", $optional_reports);


#
# Summary graphs
#
$data->assign("graph_args", $graph_args);
if (!isset($conf['optional_graphs']))
  $conf['optional_graphs'] = array();
$optional_graphs_data = array();
foreach ($conf['optional_graphs'] as $g) {
  $optional_graphs_data[$g]['name'] = $g;
#  $data->assign("name", $optional_graphs_data[$g]['name']);
  $optional_graphs_data[$g]['graph_args'] = $graph_args;
}

$data->assign('optional_graphs_data', $optional_graphs_data);

#
# Correctly handle *_report cases and blank (" ") units
#
if (isset($units)) {
  $vlabel = $units;
  if ($units == " ")
    $units = "";
  else
    $units=$units ? "($units)" : "";
}
else {
  $units = "";
}
$data->assign("metric","$metricname $units");
$data->assign("metric_name","$metricname");
$data->assign("sort", $sort);
$data->assign("range", $range);
#$data->assign("checked$showhosts", "checked");

$showhosts_levels = array(
   2 => array('checked'=>'', 'name'=>'Auto'),
   1 => array('checked'=>'', 'name'=>'Same'),
   0 => array('checked'=>'', 'name'=>'None'),
);
$showhosts_levels[$showhosts]['checked'] = 'checked';
$data->assign("showhosts_levels", $showhosts_levels);


$sorted_hosts = array();
$down_hosts = array();
$percent_hosts = array();
if ($showhosts)
   {
      foreach ($hosts_up as $host => $val)
         {

	  // If host_regex is defined
	  if ( isset($user['host_regex']) && ! preg_match("/" .$user['host_regex'] . "/", $host  ) )
	    continue;
            if ( isset($metrics[$host]["cpu_num"]['VAL']) and $metrics[$host]["cpu_num"]['VAL'] != 0 ){
               $cpus = $metrics[$host]["cpu_num"]['VAL'];
            } else {
               $cpus = 1;
            }
            if ( isset($metrics[$host]["load_one"]['VAL']) ){
               $load_one = $metrics[$host]["load_one"]['VAL'];
            } else {
               $load_one = 0;
            }
            $load = ((float) $load_one)/$cpus;
            $host_load[$host] = $load;
            if(isset($percent_hosts[load_color($load)])) { 
                $percent_hosts[load_color($load)] += 1;
            } else {
                $percent_hosts[load_color($load)] = 1;
            }
            if ($metricname=="load_one")
               $sorted_hosts[$host] = $load;
            else if (isset($metrics[$host][$metricname]))
               $sorted_hosts[$host] = $metrics[$host][$metricname]['VAL'];
            else
               $sorted_hosts[$host] = "";

         }
         
      foreach ($hosts_down as $host => $val)
         {
            $load = -1.0;
            $down_hosts[$host] = $load;
            if(isset($percent_hosts[load_color($load)])) {
                $percent_hosts[load_color($load)] += 1;
            } else {
                $percent_hosts[load_color($load)] = 1;
            }
         }
      
      # Show pie chart of loads
      $pie_args = "title=" . rawurlencode("Cluster Load Percentages");
      $pie_args .= "&amp;size=250x150";
      foreach($conf['load_colors'] as $name=>$color)
         {
            if (!array_key_exists($color, $percent_hosts))
               continue;
            $n = $percent_hosts[$color];
            $name_url = rawurlencode($name);
            $pie_args .= "&$name_url=$n,$color";
         }
      $data->assign("pie_args", $pie_args);

      # Host columns menu defined in header.php
      $data->assign("columns_size_dropdown", 1);
      $data->assign("cols_menu", $cols_menu);
      $data->assign("size_menu", $size_menu);
      $data->assign("node_legend", 1);
   }
else
   {
      # Show pie chart of hosts up/down
      $pie_args = "title=" . rawurlencode("Host Status");
      $pie_args .= "&amp;size=250x150";
      $up_color = $conf['load_colors']["25-50"];
      $down_color = $conf['load_colors']["down"];
      $pie_args .= "&amp;Up=$cluster[HOSTS_UP],$up_color";
      $pie_args .= "&amp;Down=$cluster[HOSTS_DOWN],$down_color";
      $data->assign("pie_args", $pie_args);
   }

# No reason to go on if we have no up hosts.
if (!is_array($hosts_up) or !$showhosts) {
   $dwoo->output($tpl, $data);
   return;
}

switch ($sort)
{
   case "descending":
      arsort($sorted_hosts);
      break;
   case "by name":
      uksort($sorted_hosts, "strnatcmp");
      break;
   default:
   case "ascending":
      asort($sorted_hosts);
      break;
}

$sorted_hosts = array_merge($down_hosts, $sorted_hosts);

if ( isset($user['max_graphs']) )
  $max_graphs = $user['max_graphs'];
else
  $max_graphs = $conf['max_graphs'];

# First pass to find the max value in all graphs for this
# metric. The $start,$end variables comes from get_context.php, 
# included in index.php.
# Do this only if person has not selected a maximum set of graphs to display
if ( $max_graphs == 0 ) {
  list($min, $max) = find_limits($sorted_hosts, $metricname);
}

# Second pass to output the graphs or metrics.
$i = 1;


# Initialize overflow list
$overflow_list = array();
$overflow_counter = 1;

foreach ( $sorted_hosts as $host => $value )
   {
      $host_url = rawurlencode($host);

      $host_link="\"?c=$cluster_url&amp;h=$host_url&amp;$get_metric_string\"";
      $textval = "";

      #echo "$host: $value, ";

      if (isset($hosts_down[$host]) and $hosts_down[$host])
         {
            $last_heartbeat = $cluster['LOCALTIME'] - $hosts_down[$host]['REPORTED'];
            $age = $last_heartbeat > 3600 ? uptime($last_heartbeat) : "${last_heartbeat}s";

            $class = "down";
            $textval = "down <br>&nbsp;<font size=\"-2\">Last heartbeat $age ago</font>";
         }
      else
         {
            if(isset($metrics[$host][$metricname]))
                $val = $metrics[$host][$metricname];
            else
                $val = NULL;
            $class = "metric";

            if ($val['TYPE']=="timestamp" or 
                (isset($always_timestamp[$metricname]) and
                 $always_timestamp[$metricname]))
               {
                  $textval = date("r", $val['VAL']);
               }
            elseif ($val['TYPE']=="string" or $val['SLOPE']=="zero" or
                    (isset($always_constant[$metricname]) and
                    $always_constant[$metricname] or
                    ($max_graphs > 0 and $i > $max_graphs )))
               {
                  if (isset($reports[$metricname]) and $reports[$metricname])
                     // No "current" values available for reports
                     $textval = "N/A";
                  else
                     $textval = "$val[VAL]";
                     if (isset($val['UNITS']))
                        $textval .= " $val[UNITS]";
               }
         }

      $size = isset($clustergraphsize) ? $clustergraphsize : 'small';

      if ($conf['hostcols'] == 0) # enforce small size in multi-host report
         $size = 'small';

      // set host zoom class based on the size of the graph shown
      if ( isset($conf['zoom_support']) && $conf['zoom_support'] === true )
         $additional_host_img_html_args = "class=host_${size}_zoomable";

      $data->assign("additional_host_img_html_args", $additional_host_img_html_args);

      $graphargs = "z=$size&amp;c=$cluster_url&amp;h=$host_url";

      if (isset($host_load[$host])) {
         $load_color = load_color($host_load[$host]);
         $graphargs .= "&amp;l=$load_color&amp;v=$val[VAL]";
      }
      $graphargs .= "&amp;r=$range&amp;su=1&amp;st=$cluster[LOCALTIME]";
      if ($cs)
         $graphargs .= "&amp;cs=" . rawurlencode($cs);
      if ($ce)
         $graphargs .= "&amp;ce=" . rawurlencode($ce);

      if ($showhosts == 1 && $max_graphs == 0 )
         $graphargs .= "&amp;x=$max&amp;n=$min";

      if (isset($vlabel))
         $graphargs .= "&amp;vl=" . urlencode($vlabel);

      if ($textval)
         {
            $cell="<td class=$class>".
               "<b><a href=$host_link>$host</a></b><br>".
               "<i>$metricname:</i> <b>$textval</b></td>";
         }
      else
         {
            $cell="<td><div><font style='font-size: 8px'>$host</font><br><a href=$host_link><img $additional_host_img_html_args src=\"./graph.php?";
            $cell .= (isset($reports[$metricname]) and $reports[$metricname])
               ? "g=$metricname" : "m=$metricname";
            $cell .= "&amp;$graphargs\" title=\"$host\" border=0 style=\"padding:2px;\"></a></div></td>";
         }

      if ($conf['hostcols'] == 0) {
         $pre = "<td><a href=$host_link><img src=\"./graph.php?g=";
         $post = "&amp;$graphargs\" $additional_host_img_html_args title=\"$host\" border=0 style=\"padding:2px;\"></a></td>";
         $cell .= $pre . "load_report" . $post;
         $cell .= $pre . "mem_report" . $post;
         $cell .= $pre . "cpu_report" . $post;
         $cell .= $pre . "network_report" . $post;
      }

      // Check if max_graphs is set. If it put cells in an overflow list since that one is hidden
      // by default
      if ($max_graphs > 0 and $i > $max_graphs ) {
	$overflow_list[$host]["metric_image"] = $cell;
	if (! ($overflow_counter++ % $conf['hostcols']) ) {
	  $overflow_list[$host]["br"] = "</tr><tr>";
	} else {
	  $overflow_list[$host]["br"] = "";
	}

      } else {
	$sorted_list[$host]["metric_image"] = $cell;
	if (! ($i++ % $conf['hostcols']) ) {
	  $sorted_list[$host]["br"] = "</tr><tr>";
	} else {
	  $sorted_list[$host]["br"] = "";
	}

      } // end of if ($max_graphs > 0 and $i > $max_graphs ) {

   }

$data->assign("sorted_list", $sorted_list);

# If there is an overflow list
if ( sizeof($overflow_list) > 0 ) {
  $data->assign("overflow_list_header", '<p><table width=80%><tr><td align=center class=metric>
  <a href="#" id="overflow_list_button"onclick="$(\'#overflow_list\').toggle();" class="button ui-state-default ui-corner-all" title="Toggle overflow list">Show more hosts (' 
  . ($overflow_counter - 1) .')</a>
  </td></tr></table>
  <div style="display: none;" id="overflow_list"><table>
  <tr>
     ');
  $data->assign("overflow_list_footer", "</div></tr></table></div>");
} else {
  $data->assign("overflow_list_header", "");
  $data->assign("overflow_list_footer", "");
}
$data->assign("overflow_list", $overflow_list);

$dwoo->output($tpl, $data);
?>
