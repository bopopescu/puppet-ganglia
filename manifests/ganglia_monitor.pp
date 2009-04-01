# $Id$

class ganglia::monitor {
  $ganglia_monitor_conf = $kernel ? {
    "FreeBSD" => "/usr/local/etc/gmond.conf",
      default => "/etc/ganglia/gmond.conf"
  }
  $package = $kernel ? {
    "FreeBSD" => "ganglia-monitor-core",
      default => "ganglia-monitor",
  }
  $service = $kernel ? {
    "FreeBSD" => "gmond",
      "Darwin" => "com.42quarks.gmond",
      default => "ganglia-monitor",
  }
  $pathprefix = $kernel ? {
    "FreeBSD" => "/usr/local",
      default => "/usr",
  } 
  case $kernel {
    "Linux": {
      package{["libganglia1", "${package}"]:
	ensure => "3.1.2-ikw-1",
	       before => [ Service["${service}"], File["${ganglia_monitor_conf}"] ],
      }      
    }
    "Darwin": {
      pkg_deploy{"ganglia_3.0.7.pkg.dmg": 
	before => [ Service["${service}"], File["${ganglia_monitor_conf}"] ],
      }
    }
    default: {
	       package{"${package}":
		 before => [ Service["${service}"], File["${ganglia_monitor_conf}"] ],
	       }
	     }
  }   
  service{"${service}":
    ensure => "running",
	   enable => "true",
	   pattern => "gmond",
	   subscribe => File["${ganglia_monitor_conf}"],
	   require => Package["${package}"],
  }
  file{"${ganglia_monitor_conf}":
    content => template("ganglia/ganglia-monitor-conf.erb"),
	    require => Package["${package}"],
  }
  @@file{"${ganglia_metacollects}/meta-cluster-${fqdn}":
    tag => "ganglia_gmond_cluster_${ganglia_mcast_port}",
	ensure => "present",
	notify => Exec["generate-metadconf"],
	content => template("ganglia/ganglia-datasource-cluster.erb"),
  }   

### metrics configuration
  file{"${ganglia_metrics}":
    ensure => "directory",
	   owner => "root",
	   mode => 0700
  }
  file{"${ganglia_metrics_cron}":
    ensure => "directory",
	   owner => "root",
	   mode => 0700,
	   require => File["${ganglia_metrics}"],
  }
  file{"${ganglia_metrics}/run-metrics.sh":
    source => "puppet:///ganglia/run-metrics.sh",
	   mode => 0700,
	   owner => root,
	   require => File["${ganglia_metrics}"],
  }
  cron{"ganglia-runmetrics":
    require => File["${ganglia_metrics}/run-metrics.sh"],
	    command => "${ganglia_metrics}/run-metrics.sh ${ganglia_metrics_cron}",
	    user => root,
	    minute => "*",
	    hour => "*",
  }
}
