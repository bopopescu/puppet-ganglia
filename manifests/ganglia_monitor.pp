# $Id$
# Writtenby: udo.waechter@uni-osnabrueck.de
#
# _Class:_ ganglia::monitor
# 
# Enables and installs the monitor daemond gmon.
#
# This module was tested with Debian (Etch/Lenny), Ubuntu (Hardy/Intrepid),
# Mac OS X Leopard and FreeBSD 7.
#
# _Parameters:_
#
# _Actions:_
#   Installs the ganglia-monitor package and configures it.
#
# _Requires:_
#   
# _Sample Usage:_
#   +include ganglia::monitor+
#
class ganglia::monitor ($ensure="present", $cluster="${domain}"){
  $ganglia_monitor_conf = "${ganglia_mconf_dir}/gmond.conf"
    $package = $kernel ? {
      "FreeBSD" => "ganglia-monitor-core",
	"Darwin" => "ganglia",
	default => "ganglia-monitor"
    }
    debug("${hostname} is: '${ensure}' and cluster: '${cluster}'")
  $pathprefix = $kernel ? {
    "FreeBSD" => "/usr/local",
      "Darwin" => "/opt/local",
      default => "/usr"
  } 
  $run_as = $kernel ? {
    "Darwin" => "nobody",
      default => "ganglia"
  }
  $pack_present = $ensure ? {
    "absent" => "absent",
      default => $kernel ? {
	"Linux" => $lsbdistcodename ? {
	  "Lenny" => "3.1.7-1+b1",
	  default => "latest",
	},
	default => $ensure
      },
  }
  package{"${package}":
    before => [ Service["${service}"], 
	   File["${ganglia_monitor_conf}"] ],
	   ensure => $ensure,
  }

  case $kernel {
    "Linux": {
      file{"/etc/init.d/ganglia-monitor":
	source => "puppet:///modules/ganglia/gmond-init",
	       notify => Service["${service}"],
	       before => Service["${service}"],
      ensure => $ensure,
      }  

      package{"libganglia1":
	ensure => $pack_present,
	       before => [ Service["${service}"], File["${ganglia_monitor_conf}"], Package["${package}"] ],
      }      

      package{"ganglia-module-iostat":
	ensure => $ensure,
	       notify => Service["${service}"],
	       require => Package["${package}"],
      }
      file {"${ganglia_mconf_dir}/conf.d/iostat.conf":
	source => "puppet:///modules/ganglia/mod_iostat.conf",
	       ensure => $ensure,
	       notify => Service["${service}"],
      }
    }      
    "Darwin": {
        #/Library/LaunchDaemons/de.ikw.uos.gmond.plist
              file{"/Library/LaunchDaemons/de.ikw.uos.gmond.plist":
            content => template("ganglia/de.ikw.uos.gmond.plist.erb")
              }        
      darwin_firewall{"any":
	port => "8649",
	     ensure => $ensure,
      }
    }
  }  
#### configure the service daemon
  $enabled = $ensure ? {
    "absent" => "false",
      default => "true"
  }

  service{"${service}":
    ensure => "${enabled}",
	   enable => "${enabled}",
	   pattern => "gmond",
	   subscribe => File["${ganglia_monitor_conf}"],
	   require => Package["${package}"],
  }

  file{"${ganglia_mconf_dir}":
    ensure => $ensure ? {
        "present" => "directory",
            default => "absent",
    },
  }
  file {"${ganglia_mconf_dir}/conf.d":
      ensure => $ensure ? {
              "present" => "directory",
                  default => "absent",
          },
	   require => File["${ganglia_mconf_dir}"]
  }
  debug("${fqdn} should ${package} have ${presence} / running: ${running} / enable: ${enabled} / conf: ${ganglia_monitor_conf}") 
    file{"${ganglia_monitor_conf}":
      content => template("ganglia/ganglia-monitor-conf.erb"),
	      require =>  [ File["${ganglia_mconf_dir}"],  
	      Package["${package}"] ],
    ensure => $ensure,
    }
  @@file{"${ganglia_metacollects}/meta-cluster-${fqdn}":
    tag => "ganglia_gmond_cluster_${ganglia_mcast_port}",
	ensure => $ensure,
	group => "root",
	notify => Exec["generate-metadconf"],
	content => template("ganglia/ganglia-datasource-cluster.erb"),
  }   

# metrics configuration
  file{"${ganglia_metrics}":
    ensure => $ensure ? {
      "present" => "directory",
          default => "absent",
  },
	   owner => "root",
	   mode => 0700
  }
  file{"${ganglia_metrics}/run-metrics.sh":
    source => "puppet:///modules/ganglia/run-metrics.sh",
	   mode => 0700,
	   owner => root,
	   require => File["${ganglia_metrics}"],
  }

## monitoring 
  monit::process{"gmond":
    start => "/etc/init.d/ganglia-monitor start",
	  stop => "/etc/init.d/ganglia-monitor stop",
	  ensure => $ensure,
  }
}
