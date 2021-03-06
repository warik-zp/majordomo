<?php
/**
* Market 
*
* Market
*
* @package project
* @author Serge J. <jey@tut.by>
* @copyright http://www.atmatic.eu/ (c)
* @version 0.1 (wizard, 14:01:08 [Jan 11, 2014])
*/
//
//
class market extends module {
/**
* market
*
* Module class constructor
*
* @access private
*/
function market() {
  $this->name="market";
  $this->title="<#LANG_MODULE_MARKET#>";
  $this->module_category="<#LANG_SECTION_SYSTEM#>";
  $this->checkInstalled();
}
/**
* saveParams
*
* Saving module parameters
*
* @access public
*/
function saveParams($data=0) {
 $p=array();
 if (IsSet($this->id)) {
  $p["id"]=$this->id;
 }
 if (IsSet($this->view_mode)) {
  $p["view_mode"]=$this->view_mode;
 }
 if (IsSet($this->edit_mode)) {
  $p["edit_mode"]=$this->edit_mode;
 }
 if (IsSet($this->tab)) {
  $p["tab"]=$this->tab;
 }
 return parent::saveParams($p);
}
/**
* getParams
*
* Getting module parameters from query string
*
* @access public
*/
function getParams() {
  global $id;
  global $mode;
  global $view_mode;
  global $edit_mode;
  global $tab;
  if (isset($id)) {
   $this->id=$id;
  }
  if (isset($mode)) {
   $this->mode=$mode;
  }
  if (isset($view_mode)) {
   $this->view_mode=$view_mode;
  }
  if (isset($edit_mode)) {
   $this->edit_mode=$edit_mode;
  }
  if (isset($tab)) {
   $this->tab=$tab;
  }
}
/**
* Run
*
* Description
*
* @access public
*/
function run() {
 global $session;
  $out=array();
  if ($this->action=='admin') {
   $this->admin($out);
  } else {
   $this->usual($out);
  }
  if (IsSet($this->owner->action)) {
   $out['PARENT_ACTION']=$this->owner->action;
  }
  if (IsSet($this->owner->name)) {
   $out['PARENT_NAME']=$this->owner->name;
  }
  $out['VIEW_MODE']=$this->view_mode;
  $out['EDIT_MODE']=$this->edit_mode;
  $out['MODE']=$this->mode;
  $out['ACTION']=$this->action;
  $out['TAB']=$this->tab;
  if ($this->single_rec) {
   $out['SINGLE_REC']=1;
  }
  $this->data=$out;
  $p=new parser(DIR_TEMPLATES.$this->name."/".$this->name.".html", $this->data, $this);
  $this->result=$p->result;
}

/**
* Title
*
* Description
*
* @access public
*/
function checkPlugins(&$out) {
  
}

/**
* BackEnd
*
* Module backend
*
* @access public
*/
function admin(&$out) {

 global $name;

 global $mode;
 if (!$this->mode && $mode) {
  $this->mode=$mode;
 }


 $serial = gg('Serial');
    if (!$serial || $serial=='0') {
        $serial = '';
        if (IsWindowsOS()) {
            $data = exec('vol c:');
            if (preg_match('/[\w]+\-[\w]+/',$data,$m)) {
                $serial=strtolower($m[0]);
            }
        } else {
            $data=trim(exec("cat /proc/cpuinfo | grep Serial | cut -d '':'' -f 2"));
            $serial=ltrim($data,'0');
        }
        if (!$serial) {
            $serial = uniqid('uniq');
        }
        sg('Serial',$serial);
    }

    if (IsWindowsOS()) {
        $os = 'Windows';
    } else {
        $os=trim(exec("uname -a"));
        if (!$os) {
            $os = 'Linux';
        }
    }
    $locale = $_SERVER['HTTP_ACCEPT_LANGUAGE'];

 $data_url='http://connect.smartliving.ru/market/?lang='.SETTINGS_SITE_LANGUAGE."&serial=".urlencode($serial)."&locale=".urlencode($locale)."&os=".urlencode($os);

 global $err_msg;
 if ($err_msg) {
  $out['ERR_MSG']=$err_msg;
 }
 global $ok_msg;
 if ($ok_msg) {
  $out['OK_MSG']=$ok_msg;
 }

 if (is_dir(ROOT.'saverestore/temp')) {
  $out['CLEAR_FIRST']=1;
 }

 if ($this->mode=='iframe') {
  global $mode2;
  global $name;
  global $names;

  if (is_array($names)) {
   $out['NAMES']=urlencode(implode(',', $names));
  }
  $out['NAME']=urlencode($name);

  $out['MODE2']=$mode2;
  return;
 }

 $result=getURL($data_url, 120);
 if (!$result) {
  $result=getURL($data_url, 0);
 }
 $data=json_decode($result);
 if (!$data->PLUGINS) {
  $out['ERR']=1;
  return;
 }
 $total=count($data->PLUGINS);

 $old_category='';
 $can_be_updated=array();
 $selected_plugins=array();
 global $names;

 if (!is_array($names)) {
  $names=array();
 }
 $cat = array();
 $cat_id = -1;
 for($i=0;$i<$total;$i++) {
  $rec=(array)$data->PLUGINS[$i];
  if (is_dir(ROOT.'modules/'.$rec['MODULE_NAME'])) {
   $rec['EXISTS']=1;

   $plugin_rec=SQLSelectOne("SELECT * FROM plugins WHERE MODULE_NAME LIKE '".DBSafe($rec['MODULE_NAME'])."'");
   if ($plugin_rec['ID']) {
    $rec['INSTALLED_VERSION']=$plugin_rec['CURRENT_VERSION'];
   }

  }

   if ($rec['CATEGORY']!=$old_category) {
       $cat[] = array();
       ++$cat_id;
       $cat[$cat_id]['NAME'] = $rec['CATEGORY'];
       $cat[$cat_id]['CATEGORY_ID'] = $rec['CATEGORY_ID'];
       $old_category=$rec['CATEGORY'];
   }

  //if ($rec['MODULE_NAME']==$name) {
   //unset($rec['LATEST_VERSION']);

   if (!isset($rec['LATEST_VERSION_URL'])) {
    if (preg_match('/github\.com/is', $rec['REPOSITORY_URL']) && ($rec['EXISTS'] || $rec['MODULE_NAME']==$name)) {
     $git_url=str_replace('archive/master.tar.gz', 'commits/master.atom', $rec['REPOSITORY_URL']);
     $github_feed=getURL($git_url, 5*60);
     @$tmp=GetXMLTree($github_feed);
     @$items_data=XMLTreeToArray($tmp);
     @$items=$items_data['feed']['entry'];
     if (is_array($items)) {
      $latest_item=$items[0];
      //print_r($latest_item);exit;
      $updated=strtotime($latest_item['updated']['textvalue']);
      $rec['LATEST_VERSION']=date('Y-m-d H:i:s', $updated);
      $rec['LATEST_VERSION_COMMENT']=$latest_item['title']['textvalue'];
      $rec['LATEST_VERSION_URL']=$latest_item['link']['href'];
     }
    }
   }


   if ($rec['MODULE_NAME']==$name) {
    //$this->url=$rec['REPOSITORY_URL'];
    $this->url='http://connect.smartliving.ru/market/?op=download&name='.urlencode($rec['MODULE_NAME'])."&serial=".urlencode(gg('Serial'));
    $this->version=$rec['LATEST_VERSION'];
   }

  if ($rec['EXISTS']) {
   $this->can_be_updated[]=array('NAME'=>$rec['MODULE_NAME'], 'URL'=>$rec['REPOSITORY_URL'], 'VERSION'=>$rec['LATEST_VERSION']);
  }
  if (in_array($rec['MODULE_NAME'], $names)) {
   $this->selected_plugins[]=array('NAME'=>$rec['MODULE_NAME'], 'URL'=>$rec['REPOSITORY_URL'], 'VERSION'=>$rec['LATEST_VERSION']);
  }
  if ($rec['EXISTS'] && $rec['INSTALLED_VERSION']!=$rec['LATEST_VERSION']) {
      $cat[$cat_id]['NEW_VERSION'] = 1;
  }
  $cat[$cat_id]['PLUGINS'][]=$rec;
 }
 $out['CATEGORY'] = $cat;

 if ($this->mode=='install_multiple') {
  $this->updateAll($this->selected_plugins);
 }


 if ($this->mode=='update_all') {
  $this->updateAll($this->can_be_updated);
 }

 if ($this->mode=='install' && $this->url) {
  $this->getLatest($out, $this->url, $name, $this->version);
 }

 if ($this->mode=='upload') {
  $this->upload($out);
 }

 if ($this->mode=='uninstall' && $name) {
  $this->uninstallPlugin($name);
 }

 if ($this->mode=='clear') {
  $this->removeTree(ROOT.'saverestore/temp');
  @SaveFile(ROOT.'reboot', 'updated');
  $this->redirect("?err_msg=".urlencode($err_msg)."&ok_msg=".urlencode($ok_msg));
 }

}

/**
* Title
*
* Description
*
* @access public
*/
 function updateAll($can_be_updated, $frame=0) {

  //$this->redirect("?mode=install&name=".$can_be_updated[0]."&list=".urlencode(implode(',', $can_be_updated)));
  set_time_limit(0);
   if (!is_dir(ROOT.'saverestore')) {
    @umask(0);
    @mkdir(ROOT.'saverestore', 0777);
   }

   umask(0);
   @mkdir(ROOT.'saverestore/temp', 0777);

  if (is_array($can_be_updated)) {
   foreach($can_be_updated as $k=>$v) {
   //$this->getLatest($out, $v['URL'], $v['NAME'], $v['VERSION']);
    $name=$v['NAME'];
    $version=$v['VERSION'];
    $url=$v['URL'];

    $filename=ROOT.'saverestore/'.$name.'.tgz';
    @unlink(ROOT.'saverestore/'.$name.'.tgz');
    @unlink(ROOT.'saverestore/'.$name.'.tar');
    $f = fopen($filename, 'wb');
    if ($f == FALSE){
      $this->redirect("?err_msg=".urlencode("Cannot open ".$filename." for writing"));
    } 

    if ($frame) {
     $this->echonow("Downloading '$url' ... ");
    }

    DebMes("Downloading plugin $name ($version) from $url");
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_TIMEOUT, 600);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);     
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2); 
    curl_setopt($ch, CURLOPT_FILE, $f); 
    $incoming = curl_exec($ch);
    curl_close($ch);
    @fclose($f);

    if (file_exists($filename)) {

      if ($frame) {
       $this->echonow("OK<br/>", 'green');
      }


      $file = basename($filename);
      DebMes("Installing/updating plugin $name ($version)");

      chdir(ROOT.'saverestore/temp');

      if ($frame) {
       $this->echonow("Unpacking '$file' ..");
      }


      if (IsWindowsOS()) {
         //DebMes("Running ".DOC_ROOT.'/gunzip ../'.$file);
         exec(DOC_ROOT.'/gunzip ../'.$file, $output, $res);
         //DebMes("Running ".DOC_ROOT.'/tar xvf ../'.str_replace('.tgz', '.tar', $file));
         exec(DOC_ROOT.'/tar xvf ../'.str_replace('.tgz', '.tar', $file), $output, $res);
      } else {
         exec('tar xzvf ../' . $file, $output, $res);
      }

        $x = 0;
        $latest_dir='';
        $latest_file='';
        $dir=opendir('./');
        while (($filec = readdir($dir)) !== false) {
         if ($filec=='.' || $filec=='..') {
          continue;
         }
         if (is_Dir($filec)) {
          $latest_dir=$filec;
         } elseif (is_File($filec)) {
          $latest_file=$filec;
         }
         $x++;
        }

        if ($x==1 && $latest_dir) {
         $folder='/'.$latest_dir;
        }

        chdir('../../');

        DebMes("Latest folder: $latest_dir");

        if ($latest_dir=='') {
         if ($frame) {
          $this->echonow("ERROR<br/>", 'red');
         }
         DebMes("Error extracting $file");
         continue;
        }

        if ($frame) {
         $this->echonow("OK<br/>", 'green');
        }


        // UPDATING FILES DIRECTLY
        if ($frame) {
         $this->echonow("Updating files ...");
        }

        $this->copyTree(ROOT.'saverestore/temp'.$folder, ROOT, 1); // restore all files
        $this->removeTree(ROOT.'saverestore/temp'.$folder);

        if ($frame) {
         $this->echonow("OK<br/>", 'green');
        }



       $rec=SQLSelectOne("SELECT * FROM plugins WHERE MODULE_NAME LIKE '".DBSafe($name)."'");
       $rec['MODULE_NAME']=$name;
       $rec['CURRENT_VERSION']=$version;
       $rec['IS_INSTALLED']=1;
       $rec['LATEST_UPDATE']=date('Y-m-d H:i:s');
       if ($rec['ID']) {
        SQLUpdate('plugins', $rec);
       } else {
        SQLInsert('plugins', $rec);
       }

    }
  }
  }
        $this->removeTree(ROOT.'saverestore/temp', $frame);

        $source=ROOT.'modules';
        if ($dir = @opendir($source)) { 
          while (($file = readdir($dir)) !== false) { 
           if (Is_Dir($source."/".$file) && ($file!='.') && ($file!='..')) { // && !file_exists($source."/".$file."/installed")
            @unlink(ROOT."modules/".$file."/installed");
           }
          }
         }
         @unlink(ROOT."modules/control_modules/installed");

         if ($frame) {
          return ("Updates Installed!");
         } else {
          $this->redirect("?ok_msg=".urlencode("Updates Installed!"));
         }
  
 }

/**
* Title
*
* Description
*
* @access public
*/
 function uninstallPlugin($name, $frame=0) {

  if (!is_dir(ROOT.'modules/'.$name)) {
   $err_msg='Module not found';
   $this->redirect("?err_msg=".urlencode($err_msg)."&ok_msg=".urlencode($ok_msg));  
  }
  if ($frame) {
   $this->echonow("Removing module '$name' from database ... ");
  }

  include_once(ROOT.'modules/'.$name.'/'.$name.'.class.php');

  SQLExec("DELETE FROM plugins WHERE MODULE_NAME LIKE '".DBSafe($name)."'");
  SQLExec("DELETE FROM project_modules WHERE NAME LIKE '".DBSafe($name)."'");
  if ($frame) {
   $this->echonow(" OK<br/>", 'green');
  }
  $this->removeTree(ROOT.'modules/'.$name);
  $this->removeTree(ROOT.'templates/'.$name);
  if (file_exists(ROOT.'scripts/cycle_'.$name.'.php')) {
   @unlink(ROOT.'scripts/cycle_'.$name.'.php');
  }
  removeMissingSubscribers();

  $code='$plugin = new '.$name.';$plugin->uninstall();';
  eval($code);


  $ok_msg='Uninstalled';

  if ($frame) {
   $this->echonow(" Plugin uninstalled!<br/>", 'green');
  }

  if (!$frame) {
   $this->redirect("?err_msg=".urlencode($err_msg)."&ok_msg=".urlencode($ok_msg));  
  } else {
   return $ok_msg;
  }
 }

function getLatest(&$out, $url, $name, $version, $frame=0) {

   set_time_limit(0);

   if (!is_dir(ROOT.'saverestore')) {
    @umask(0);
    @mkdir(ROOT.'saverestore', 0777);
   }

    $filename=ROOT.'saverestore/'.$name.'.tgz';

    @unlink(ROOT.'saverestore/'.$name.'.tgz');
    @unlink(ROOT.'saverestore/'.$name.'.tar');

    $f = fopen($filename, 'wb');
    if ($f == FALSE){
      if ($frame) {
       $this->echonow("Cannot open ".$filename." for writing", "red");
       return 0;
      } else {
       $this->redirect("?err_msg=".urlencode("Cannot open ".$filename." for writing"));
      }
    } 


   if ($frame) {
    $this->echonow("Downloading '".$url."' ... ");
   }

   $ch = curl_init();
   curl_setopt($ch, CURLOPT_URL, $url);
   curl_setopt($ch, CURLOPT_TIMEOUT, 600);
   curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
   curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);     
   curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2); 
   curl_setopt($ch, CURLOPT_FILE, $f); 
   $incoming = curl_exec($ch);

   curl_close($ch);
   @fclose($f);

   if (file_exists($filename)) {

    if ($frame) {
     $this->echonow("OK<br/>", 'green');
    }


    $this->removeTree(ROOT.'saverestore/temp', $frame);

    if ($frame) {
     return 1;
    } else {
     global $list;
     $this->redirect("?mode=upload&restore=".urlencode($name.'.tgz')."&folder=".urlencode($name)."&name=".urlencode($name)."&version=".urlencode($version)."&list=".urlencode($list));
    }

   } else {
      if ($frame) {
       $this->echonow("Cannot download '".$url."'<br/>", "red");
       return 0;
      } else {
       $this->redirect("?err_msg=".urlencode("Cannot download ".$url));
      }
   }
  }

function upload(&$out, $frame=0)
{
   set_time_limit(0);
   global $restore;
   global $file;
   global $file_name;
   global $folder;

   if (!$folder)
      $folder = IsWindowsOS() ? '/.' : '/';
   else
      $folder = '/' . $folder;
   
   if ($restore != '')
   {
      $file = $restore;
   } 
   elseif ($file!='')
   {
      copy($file, ROOT.'saverestore/' . $file_name);
      $file = $file_name;
   }

   umask(0);
   @mkdir(ROOT.'saverestore/temp', 0777);

   if ($file != '') { // && mkdir(ROOT.'saverestore/temp', 0777)
      chdir(ROOT.'saverestore/temp');

      if ($frame) {
       $this->echonow("Unpacking '$file' ... ");
      }
      if (IsWindowsOS())
      {
         // for windows only
         exec(DOC_ROOT.'/gunzip ../'.$file, $output, $res);
         //echo DOC_ROOT.'/tar xvf ../'.str_replace('.tgz', '.tar', $file);exit;
         exec(DOC_ROOT.'/tar xvf ../'.str_replace('.tgz', '.tar', $file), $output, $res);
      } 
      else
      {
         exec('tar xzvf ../' . $file, $output, $res);
      }
      if ($frame) {
       $this->echonow(" OK <br/>", 'green');
      }

        $x = 0;
        $dir=opendir('./');
        while (($filec = readdir($dir)) !== false) {
         if ($filec=='.' || $filec=='..') {
          continue;
         }
         if (is_Dir($filec)) {
          $latest_dir=$filec;
         } elseif (is_File($filec)) {
          $latest_file=$filec;
         }
         $x++;
        }

        if ($x==1 && $latest_dir) {
         $folder='/'.$latest_dir;
        }
        @unlink(ROOT.'saverestore/temp'.$folder.'/config.php');
        @unlink(ROOT.'saverestore/temp'.$folder.'/README.md');


        chdir('../../');
        // UPDATING FILES DIRECTLY
        if ($frame) {
         $this->echonow("Updating files ... ");
        }

        $this->copyTree(ROOT.'saverestore/temp'.$folder, ROOT, 1); // restore all files
        $source=ROOT.'modules';
        if ($dir = @opendir($source)) { 
          while (($file = readdir($dir)) !== false) { 
           if (Is_Dir($source."/".$file) && ($file!='.') && ($file!='..')) { // && !file_exists($source."/".$file."/installed")
            @unlink(ROOT."modules/".$file."/installed");
           }
          }
         }
         @unlink(ROOT."modules/control_modules/installed");

       if ($frame) {
        $this->echonow(" OK <br/>", 'green');
       }

       global $name;
       global $version;

       DebMes("Installing/updating plugin $name ($version)");

       $rec=SQLSelectOne("SELECT * FROM plugins WHERE MODULE_NAME LIKE '".DBSafe($name)."'");
       $rec['MODULE_NAME']=$name;
       $rec['CURRENT_VERSION']=$version;
       $rec['IS_INSTALLED']=1;
       $rec['LATEST_UPDATE']=date('Y-m-d H:i:s');
       if ($rec['ID']) {
        SQLUpdate('plugins', $rec);
       } else {
        SQLInsert('plugins', $rec);
       }

       if ($frame) {
        $this->echonow("Plugin '$name' ($version) installed.<br/>", 'green');
        return "Plugin '$name' ($version) installed.";
       } else {
        $this->redirect("?mode=clear&ok_msg=".urlencode("Updates Installed!"));
       }
  }


 }

/**
* FrontEnd
*
* Module frontend
*
* @access public
*/
function usual(&$out) {
 $this->admin($out);
}

/**
* Install
*
* Module installation routine
*
* @access private
*/
 function install($data='') {
  parent::install();
 }

/**
* removeTree
*
* remove directory tree
*
* @access public
*/
 function removeTree($destination, $frame=0) {

  $res=1;

  if (!Is_Dir($destination)) {
    return 0; // cannot create destination path
  }

  if ($frame) {
     $this->echonow("Removing dir $destination ... ");
  }


 if ($dir = @opendir($destination)) { 
  while (($file = readdir($dir)) !== false) { 
    if (Is_Dir($destination."/".$file) && ($file!='.') && ($file!='..')) {
     $res=$this->removeTree($destination."/".$file);
    } elseif (Is_File($destination."/".$file)) {
     $res=@unlink($destination."/".$file);
    }
  }     
  closedir($dir); 
  $res=@rmdir($destination);
 }

  if ($frame) {
     $this->echonow("OK<br/>", "green");
  }


 return $res;
 }


/**
* copyTree
*
* Copy source directory tree to destination directory
*
* @access public
*/
 function copyTree($source, $destination, $over=0, $patterns=0) {


  $res=1;

  //Remove last slash '/' in source and destination - slash was added when copy
  $source = preg_replace("#/$#", "", $source);
  $destination = preg_replace("#/$#", "", $destination);

  if (!Is_Dir($source)) {
   return 0; // cannot create destination path
  }

  if (!Is_Dir($destination)) {
   if (!mkdir($destination)) {
    return 0; // cannot create destination path
   }
  }


  if ($dir = @opendir($source)) { 
  while (($file = readdir($dir)) !== false) { 
    if (Is_Dir($source."/".$file) && ($file!='.') && ($file!='..')) {
     $res=$this->copyTree($source."/".$file, $destination."/".$file, $over, $patterns);
    } elseif (Is_File($source."/".$file) && (!file_exists($destination."/".$file) || $over)) {
     if (!is_array($patterns)) {
      $ok_to_copy=1;
     } else {
      $ok_to_copy=0;
      $total=count($patterns);
      for($i=0;$i<$total;$i++) {
       if (preg_match('/'.$patterns[$i].'/is', $file)) {
        $ok_to_copy=1;
       }
      }
     }
     if ($ok_to_copy) {
      $res=copy($source."/".$file, $destination."/".$file);
     }
    }
  }   
  closedir($dir); 
 }
 return $res;

 }

 function copyFile($source, $destination) {
  $tmp=explode('/', $destination);
  $total=count($tmp);
  if ($total>0) {
   $d=$tmp[0];
   for($i=1;$i<($total-1);$i++) {
    $d.='/'.$tmp[$i];
    if (!is_dir($d)) {
     mkdir($d);
    }
   }
  }
  return copy($source, $destination);

 }

 function copyFiles($source, $destination, $over=0, $patterns=0) {

  $res=1;

  if (!Is_Dir($source)) {
   return 0; // cannot create destination path
  }

  if (!Is_Dir($destination)) {
   if (!mkdir($destination)) {
    return 0; // cannot create destination path
   }
  }


 if ($dir = @opendir($source)) { 
  while (($file = readdir($dir)) !== false) { 
    if (Is_Dir($source."/".$file) && ($file!='.') && ($file!='..')) {
     //$res=$this->copyTree($source."/".$file, $destination."/".$file, $over, $patterns);
    } elseif (Is_File($source."/".$file) && (!file_exists($destination."/".$file) || $over)) {
     if (!is_array($patterns)) {
      $ok_to_copy=1;
     } else {
      $ok_to_copy=0;
      $total=count($patterns);
      for($i=0;$i<$total;$i++) {
       if (preg_match('/'.$patterns[$i].'/is', $file)) {
        $ok_to_copy=1;
       }
      }
     }
     if ($ok_to_copy) {
      $res=copy($source."/".$file, $destination."/".$file);
     }
    }
  }   
  closedir($dir); 
 }
 return $res;
 }

 function echonow($msg, $color='') {
  if ($color) {
   echo '<font color="'.$color.'">';
  }
  echo $msg;
  if ($color) {
   echo '</font>';
  }
  echo "<script language='javascript'>window.scrollTo(0,document.body.scrollHeight);</script>";
  echo str_repeat(' ', 16*1024);
  flush();
  ob_flush();
 }


/**
* Uninstall
*
* Module uninstall routine
*
* @access public
*/
 function uninstall() {
  SQLExec('DROP TABLE IF EXISTS plugins');
  parent::uninstall();
 }
/**
* dbInstall
*
* Database installation routine
*
* @access private
*/
 function dbInstall($data) {
/*
plugins - Plugins
*/
  $data = <<<EOD
 plugins: ID int(10) unsigned NOT NULL auto_increment
 plugins: TITLE varchar(255) NOT NULL DEFAULT ''
 plugins: MODULE_NAME varchar(255) NOT NULL DEFAULT ''
 plugins: REPOSITORY_URL char(255) NOT NULL DEFAULT ''
 plugins: AUTHOR varchar(255) NOT NULL DEFAULT ''
 plugins: SUPPORT_URL char(255) NOT NULL DEFAULT ''
 plugins: DESCRIPTION_RU text
 plugins: DESCRIPTION_EN text
 plugins: CURRENT_VERSION varchar(255) NOT NULL DEFAULT ''
 plugins: LATEST_VERSION varchar(255) NOT NULL DEFAULT ''
 plugins: IS_INSTALLED int(3) NOT NULL DEFAULT '0'
 plugins: WHATSNEW text
 plugins: LATEST_UPDATE datetime
EOD;
  parent::dbInstall($data);
 }
// --------------------------------------------------------------------
}
/*
*
* TW9kdWxlIGNyZWF0ZWQgSmFuIDExLCAyMDE0IHVzaW5nIFNlcmdlIEouIHdpemFyZCAoQWN0aXZlVW5pdCBJbmMgd3d3LmFjdGl2ZXVuaXQuY29tKQ==
*
*/
