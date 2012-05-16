<?php
ini_set('display_errors', 1);
ini_set('log_errors', 1); 
error_reporting(E_ALL);

$pages = array();
$functions = array();
$function_map = array('load_data'=>'load_data', 'save_data'=>'save_data', 'render_resource'=>'render_resource', 'render_top'=>'render_top', 'render_bottom'=>'render_bottom', 'render_manage_list'=>'render_manage_list');
$variables = array('page'=>'');
$variables['rf_url'] = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'];

// Probably breaks with windows and other things which dont use /
$variables['rf_file'] = array_pop(explode("/", $_SERVER["SCRIPT_NAME"]));

$variables['metadata_file'] = "rf_data.php";
$variables['plugin_dir'] = "rf_plugins";

// ensures that the metadata file exists
touch($variables['metadata_file']);

array_push($pages, 'resource');
call_back_list('resource', array( 'load_data', 'render_top','render_resource','render_bottom'));

array_push($pages, 'manage_resources');
call_back_list('manage_resources', array( 'authenticate','load_data', 'render_top','render_manage_list','render_bottom'));

array_push($pages, 'save_resources');
call_back_list('save_resources', array('authenticate','load_data','save_data'));

if(is_dir($variables["plugin_dir"]))
{
	if ($dh = opendir($variables["plugin_dir"])) 
	{
		while (($file = readdir($dh)) !== false) 
		{
			if(is_file($variables['plugin_dir'].'/'.$file) && preg_match('/\.php$/', $file))
			{
				include($variables['plugin_dir'].'/'.$file);
			}
		}
		closedir($dh);
	}

}

if(isset($_REQUEST['page']))
{
	call($_REQUEST['page']);
}
else
{
	call('resource');
}

print $variables['page'];


// FUNCTIONS FROM HERE ON DOWN
function call($function_name)
{
	global $functions, $function_map;
	foreach( $functions[$function_name] as $function )
	{
		call_user_func($function_map[$function]);
	}
}

function call_back_list($function_name, $list=Null)
{
	global $functions;
	if($list == Null)
	{
		if(isset($functions[$function_name]))
		{
			return $functions[$function_name];
		}
		
		return array();
	}

	$functions[$function_name] = $list;
	return True;
}

function get_licenses()
{
	$cc = array();
	$cc[''] = 'unspecified';
	$cc['by'] = 'Attribution';
	$cc['by-sa'] = 'Attribution-ShareAlike';
	$cc['by-nd'] = 'Attribution-NoDerivs';
	$cc['by-nc'] = 'Attribution-NonCommercial';
	$cc['by-nc-sa'] = 'Attribution-NonCommerical-ShareAlike';
	$cc['by-nc-nd'] = 'Attribution-NonCommerical-NoDerivs';
	return $cc;
}


function load_data()
{
	global $variables;
	$variables['data'] = unserialize(file_get_contents($variables['metadata_file']));
	if(!is_array($variables["data"]) )
	{
		$variables["data"]= array();
	}

}

function save_data()
{
	global $variables;
	$old_data = $variables["data"];
	$variables["data"] = array();
	for ($i = 0; $i < $_REQUEST['resource_count']; $i++)
	{
		$filename = $_REQUEST["filename$i"];
		if ($filename == null) continue;

		foreach ($_REQUEST as $key => $value)
			if (preg_match("/(.*)($i\$)/", $key, $matches))
				$variables["data"][$filename][$matches[1]] = $value;
	}

	if (isset($_REQUEST['missing']))
		foreach ($_REQUEST['missing'] as $missed)
			$variables['data'][$missed] = $old_data[$missed];

	$fh = fopen($variables["metadata_file"], "w");
	fwrite($fh,serialize($variables['data']));
	fclose($fh); 
	header('Location: redfeather.php?page=manage_resources');
}

function render_top()
{
	global $variables;
	$variables['page_title'] = 'RedFeather';
	$variables['page'] .= 
'<html><head>
	<title>'.$variables['page_title'].'</title>
	<script src="//ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js" type="text/javascript"></script>
	<link rel="stylesheet" href="http://meyerweb.com/eric/tools/css/reset/reset.css" type="text/css" />
	<link rel="stylesheet" href="style" type="text/css" />
</head><body>
<div class="rf_content">';
	$variables['page'] .=
'
<div id="rf_wrapper">
<div id="rf_header">
	<h1><a href="redfeather.php"><span class="rf_red">Red</span>Feather<img src="http://users.ecs.soton.ac.uk/pm5/redfeather/biddocs/small_logo.png"/></a></h1>
	<h2>Lightweight Resource Exhibition and Discovery</h2>
</div>
<div id="rf_content">';
}

function render_bottom()
{
	global $variables;
	$variables['page'] .= '</div><div id="rf_footer">&copy; Copyright 2012 | <a href="http://redfeather.ecs.soton.ac.uk">RedFeather Project</a> | <a target="_blank" href="http://blogs.ecs.soton.ac.uk/oneshare/tag/redfeather/">OneShare</a> | <a href="'.$_SERVER['SCRIPT_NAME'].'?page=manage_resources">Manage Resources</a></div></div>
</html>';
}

function render_resource()
{
	global $variables;
	$licenses = get_licenses();
	$data = $variables['data'][$_REQUEST['file']];	
	$this_url = $variables["rf_url"].'?page=resource&file='.$_REQUEST['file'];
	$bits = explode('/', $variables['rf_url']);
	array_pop($bits);
	$file_url = implode('/', $bits).'/'.$_REQUEST['file'];
	$variables['page'] .= '<h1>'.$data['title'].'</h1>';

	$variables['page'] .= '<div id="rf_resource_main">';
	
	$variables['page'] .= '<iframe id="preview" src="http://docs.google.com/viewer?embedded=true&url='.urlencode($file_url).'" width="600" height="600" style="border: none;"></iframe>';
	$variables['page'] .= '<div id="rf_resource_metadata">';

	$variables['page'] .= '<h2>Description</h2>';
	$variables['page'] .= '<p>'.$data['description'].'</p>';

	$variables['page'] .= '<h2>Resource details</h2>';
	$variables['page'] .= '<table><tbody>';

	$variables['page'] .= '<tr><td>Creator:</td><td>'.$data['creator'].' &lt;<a href="mailto:'.$data['email'].'">'.$data['email'].'</a>&gt;</td></tr>';
	$variables['page'] .= '<tr><td>Updated:</td><td>'.date ("d F Y H:i:s.", filemtime($_REQUEST['file'])).'</td></tr>';
	$variables['page'] .= '<tr><td>License:</td><td>'.$licenses[$data['license']].'</td></tr>';
	$variables['page'] .= '<tr><td>Download:</td><td><a target="_blank" href="'.$file_url.'">'.$file_url.'</a></td></tr>';
	$variables['page'] .= '</tbody></table>';

	$variables['page'] .= '<h2>Comments</h2>';

	$variables['page'] .= '<div id="fb-root"></div>
<script>(function(d, s, id) {
  var js, fjs = d.getElementsByTagName(s)[0];
  if (d.getElementById(id)) return;
  js = d.createElement(s); js.id = id;
  js.src = "//connect.facebook.net/en_GB/all.js#xfbml=1";
  fjs.parentNode.insertBefore(js, fjs);
}(document, "script", "facebook-jssdk"));</script>
<div class="fb-comments" data-href="'.$this_url.'" data-num-posts="2" data-width="450"></div>';

	$variables['page'] .= '</div>';

	$variables['page'] .= '</div><div class="clearer"></div></div>';

}


function render_managed($data, $num)
{
	global $variables;
	$item_html = "<table><tbody>";
	$item_html .= "<tr><th colspan='2'><a href='".$data['filename']."' target='_blank'>".$data['filename']."</th></tr><input type='hidden' name='filename$num' value='".$data['filename']."' />";
	$item_html .= "<tr><td>Title</td><td><input name='title$num' value='".$data['title']."' autocomplete='off' /></td></tr>";
	$item_html .= "<tr><td>Description</td><td><textarea name='description$num' autocomplete='off' rows='5'>".$data['description']."</textarea></td></tr>";
	$item_html .= "<tr><td>Creator</td><td><input name='creator$num' value='".$data['creator']."' autocomplete='off' /></td></tr>";
	$item_html .= "<tr><td>Email</td><td><input name='email$num' value='".$data['email']."' autocomplete='off' /></td></tr>";

	$license_options = "";
	foreach (get_licenses() as $key => $value)	
	{
		if ($data['license'] == $key)
			$selected = 'selected';
		else
			$selected = '';

		$license_options .= "<option value='$key' $selected autocomplete='off'>$value</option>";
	}

	$item_html .= "<tr><td class='rf_table_left'>Licence</td><td><select name='license$num' autocomplete='off'>$license_options</select></td></tr>";
	$item_html .= "</tbody></table>";

	return $item_html;
}


function render_manage_list()
{
	global $variables;
	$variables['page'] .= '<h1>Manage Resources</h1>';

	$dir = "./";

	$new_file_count = 0;
	$num = 0;
	$manage_resources_html = '';
	$new_resources_html = '';
	$files_found_list = array();
		
	$variables["page"] .= "<form action='".$variables["rf_file"]."?page=save_resources' method='POST'>\n";
	foreach (scandir($dir) as $file)
	{
		if(is_dir($dir.$file)){continue;}
		if($file == $variables["rf_file"]){continue;}
		if($file == $variables["metadata_file"]){continue;}
		if(preg_match("/^\./", $file)){continue;}

		if (isset($variables["data"]["$file"])) {
			$data = $variables["data"]["$file"];
			array_push($files_found_list, $file);
			$manage_resources_html .= "<div class='rf_manageable' id='resource$num'>".render_managed($data, $num)."</div>";
		}
		else
		{
			//the default data for the workflow
			$data = array('filename'=>$file,'title'=>'','description'=>'', 'creator'=>'','email'=>'', 'license'=>'');
			$new_resources_html .= "<div class='rf_manageable' id='resource$num'>".render_managed($data, $num)."</div>";
			$new_file_count++;
		}
		$num++;
	}
		
	
	// check whether any files are missing
	$missing_resources_html = '';
	$missing_num = 0;

	foreach ($variables['data'] as $key => $value) {
		if (! in_array($key, $files_found_list))
		{
			$missing_resources_html .= "<div class='rf_manageable' id='missing$missing_num'><p>Resource not found: $key <a href='#' onclick='javascript:$(\"#missing$missing_num\").remove();'>delete metadata</a></p><input type='hidden' name='missing[]' value='$key'/></div>";
			$missing_num++;
		}
	}
	
	$variables["page"] .= $missing_resources_html;
	if ($new_file_count) $variables["page"] .= "<div class='rf_new_resources'><p>$new_file_count new files found.</p>".$new_resources_html."</div>";


	$variables["page"] .= "<div>$manage_resources_html</div>";
	$variables["page"] .= "<input type='hidden' name='resource_count' value='$num'/>";
	$variables["page"] .= "<input type='submit' value='Save'/>";
	$variables["page"] .= "</form>";
}
