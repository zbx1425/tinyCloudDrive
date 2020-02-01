<?php
//请编辑 CONFIG_THIS 所表示的字段。
$lang = json_decode(explode("'",file("index.php")[2])[1],true)["lang"]; //Ungraceful but used for performance.
$encryptMagic = array(0x12, 0x34, 0x56); //Replace these magic numbers for security measure! Use at least three of them!
$enableEmail = false; //CONFIG_THIS
if ($enableEmail){
	$smtpsevraddr = "smtp-mail.outlook.com";
	$smtpsevrport = 587;
	$smtpusername = "CONFIG_THIS@outlook.com";
	$smtppassword = "CONFIG_THIS";
	$smtpselfname = "CONFIG_THIS";
	$smtpselfaddr = "no-reply@CONFIG_THIS.domain";
	$smtprplyaddr = "CONFIG_THIS@outlook.com";
	$smtpmailsubject = "Email验证通知邮件";
	$smtpmailcontent = <<<EOF
<h1>CONFIG_THIS</h1><h2>Email验证通知邮件</h2><hr><br><p>{{USER_NAME}}: </p><p style='text-indent:2em'>欢迎您注册CONFIG_THIS！</p>
<p style='text-indent:2em'>您的Email验证链接为：</p>
<p style='margin-left:4em;font-family:monospace;word-break:break-all;border: 1px solid lightgray;padding:10px;border-radius:5px'><a href='{{TOKEN_URL}}'>{{TOKEN_URL}}</a></p>
<p style='margin-left:4em;'>(若您不能点击此链接，请复制到浏览器地址栏并打开)</p>
<p style='text-indent:2em'>如您并非主动进行此项验证，请忽略此邮件，不要点击链接，并立刻修改您的账号密码。</p>
<p>顺颂时祺</p>
<p align='right'>CONFIG_THIS</p><p align='right'>{{SEND_DATE}}</p>
EOF;
	require_once "phpmailer/PHPMailerAutoload.php"; //CONFIG_THIS
}

function readUsers(){
	return json_decode(file_get_contents("account.json"), true);
}

function getUserDirs(){
	$accdata = readUsers();
	foreach ($accdata as $key => $value) {
		if (strpos($key, "@") === false){
			$accdata[$key] = $_SERVER["DOCUMENT_ROOT"]; //This account is not registered via webpage, must be an admin
		} else {
			$accdata[$key] = dirname($_SERVER["SCRIPT_FILENAME"])."/".str_replace("@",".",$key);
		}
	}
	return $accdata;
}

function writeUsers($raw){
	file_put_contents("account.json",json_encode($raw,JSON_PRETTY_PRINT));
}

if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
	session_cache_limiter('');
	session_name("filemanager");
	session_start();
	if (isset($_GET["act"])){
		switch ($_GET["act"]){
			case "rtoken":
				if (!filter_var($_POST["fm_usr"], FILTER_VALIDATE_EMAIL)){
					header("location: account.php?web=signup&err=BadName"); exit;
				}
				if ($_POST["fm_pwd2"]!=$_POST["fm_pwd"]){
					header("location: account.php?web=signup&err=DifferentPassword"); exit;
				}
				if (strlen($_POST["fm_pwd"])<6){
					header("location: account.php?web=signup&err=ShortPassword"); exit;
				}
				$accdata = readUsers();
				if (isset($accdata[$_POST["fm_usr"]]) || is_dir(dirname(__FILE__)."/".str_replace("@",".",$_POST["fm_usr"]))){
					header("location: account.php?web=signup&err=ConflictName"); exit;
				}
				$data = $_POST["fm_usr"].chr(1).password_hash($_POST["fm_pwd"], PASSWORD_DEFAULT).chr(1).md5($_POST["fm_usr"]);
				foreach ($encryptMagic as $magic){
					$data = base64_encode(strrev(strxor($data, $magic)));
				}
				$token_url = "https://".$_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME']."?act=reg&token=".urlencode($data);
				if ($enableEmail){
					$contentdata = str_replace(array("{{USER_NAME}}", "{{TOKEN_URL}}", "{{SEND_DATE}}"),
						array($_POST["fm_usr"], $token_url, date("Y-m-d",time())), $smtpmailcontent);
					//exit;
					$mail = new PHPMailer;
					$mail->isSMTP();
					//Enable SMTP debugging
					// 0 = off (for production use)
					// 1 = client messages
					// 2 = client and server messages
					$mail->SMTPDebug = 0;
					//Ask for HTML-friendly debug output
					//$mail->Debugoutput = 'html';
					$mail->Host = $smtpsevraddr;
					$mail->Port = $smtpsevrport;
					$mail->SMTPAuth = true;
					$mail->SMTPSecure = 'tls';
					$mail->Username = $smtpusername;
					$mail->Password = $smtppassword;
					//$mail->setFrom('emailverify'.$smtpselfaddr, $smtpselfname);
					$mail->setFrom($smtpusername, $smtpselfname);
					$mail->addReplyTo($smtprplyaddr, $smtpselfname);
					$mail->addAddress($_POST["fm_usr"]);
					$mail->Subject = $smtpmailsubject;
					$mail->IsHTML(true);
					$mail->CharSet = "utf-8";
					$mail->Encoding = "base64";
					//$mail->msgHTML($contentdata); //部分邮箱对于Multipart的支持并不好。不过好在大部分邮箱都是支持HTML的，所以就这样吧……
					$mail->Body = $contentdata;
					//$mail->AltBody = 'This is a plain-text message body';
					
					if (!$mail->send()) {
						echo "发生错误！<br>Cannot send email：" . $mail->ErrorInfo;
						exit;
					}
				}
				if ($enableEmail){
					zbx_show_header_login();
	?>
					<div class="card fat">
						<div class="card-body">
							<div class="text-center">
								<h1 class="card-title"><?php echo zbx_lng("ConfirmEmail"); ?></h1>
							</div>
							<p><?php echo zbx_lng("CheckInbox"); ?></p>
						</div>
					</div>
	<?php		
					zbx_show_footer_login();
					exit;
				} else {
					header("location: ".$token_url);
					exit;
				}
				break;
			case "reg":
				if (!isset($_GET["token"])){
					header("location: account.php?web=signup&err=InvalidToken"); exit;
				}
				$data = $_GET["token"];
				foreach (array_reverse($encryptMagic) as $magic){
					$data = strxor(strrev(base64_decode($data)), $magic);
				}
				$data = explode(chr(1),$data);
				if (md5($data[0]) != $data[2]){
					header("location: account.php?web=signup&err=InvalidToken"); exit;
				}
				$accdata = readUsers();
				if (isset($accdata[$data[0]]) || is_dir(dirname(__FILE__)."/".str_replace("@",".",$data[0]))){
					header("location: account.php?web=signup&err=InvalidToken"); exit;
				}
				$accdata[$data[0]]=$data[1];
				writeUsers($accdata);
				mkdir(dirname(__FILE__)."/".str_replace("@",".",$data[0]));
				file_put_contents(dirname(__FILE__)."/".str_replace("@",".",$data[0])."/welcome.txt", "Welcome to CONFIG_THIS!");
				header("refresh: 3; url=index.php");
				zbx_show_header_login();
				zbx_show_success("SignUp");
				zbx_show_footer_login();
				exit;
				break;
			case "cpass":
				if ($_POST["fm_pwd2"]!=$_POST["fm_pwd"]){
					header("location: account.php?web=change&err=DifferentPassword"); exit;
				}
				if (strlen($_POST["fm_pwd"])<6){
					header("location: account.php?web=change&err=ShortPassword"); exit;
				}
				$accdata = readUsers();
				if (!isset($accdata[$_SESSION["filemanager"]['logged']])){
					header("location: account.php?web=change&err=InvalidToken"); exit;
				}
				$accdata[$_SESSION["filemanager"]['logged']]=password_hash($_POST["fm_pwd"], PASSWORD_DEFAULT);
				writeUsers($accdata);
				header("refresh: 3; url=index.php");
				zbx_show_header_login();
				zbx_show_success("ChangePassword");
				zbx_show_footer_login();
				exit;
				break;
			case "purge":
				$accdata = readUsers();
				if (!isset($accdata[$_SESSION["filemanager"]['logged']])){
					header("location: account.php?web=delete&err=InvalidToken"); exit;
				}
				if (!password_verify($_POST["fm_pwd"],$accdata[$_SESSION["filemanager"]['logged']])){
					header("location: account.php?web=delete&err=DifferentPassword"); exit;
				}
				deleteDir(dirname(__FILE__)."/".str_replace("@",".",$_SESSION["filemanager"]['logged']));
				unset($accdata[$_SESSION["filemanager"]['logged']]);
				writeUsers($accdata);
				header("refresh: 3; url=index.php");
				zbx_show_header_login();
				zbx_show_success("DeleteAccount");
				zbx_show_footer_login();
				exit;
				break;
			default:
				header("location: account.php?web=signup&err=InvalidToken"); exit; break;
		}
	}
				
	if (isset($_GET["web"])){
		switch ($_GET["web"]){
			case "signup":
				zbx_show_header_login();
				zbx_show_message();
			?>
			<div class="card fat">
				<div class="card-body">
					<script>
						function openmodal(){
							$("#loadingModal").modal("show");
						}
					</script>
					<form class="form-signin" action="account.php?act=rtoken" method="post" autocomplete="off" onsubmit="openmodal()">
						<div class="form-group">
							<a href="index.php"><i class="fa fa-chevron-circle-left go-back"></i> <?php echo zbx_lng('Back'); ?></a>
						</div>
						<div class="form-group">
							<label for="fm_usr"><?php echo zbx_lng('Username'); ?></label>
							<input type="email" class="form-control" id="fm_usr" name="fm_usr" required autofocus>
						</div>

						<div class="form-group">
							<label for="fm_pwd"><?php echo zbx_lng('Password'); ?></label>
							<input type="password" class="form-control" id="fm_pwd" name="fm_pwd" required>
						</div>

						<div class="form-group">
							<label for="fm_pwd"><?php echo zbx_lng('PasswordConfirm'); ?></label>
							<input type="password" class="form-control" id="fm_pwd2" name="fm_pwd2" required>
						</div>

						<div class="form-group">
							<button type="submit" class="btn btn-primary btn-block" role="button">
								<?php echo zbx_lng('SignUp'); ?>
							</button>
						</div>
					</form>
				</div>
			</div>
			<div class="modal fade" id="loadingModal" tabindex="-1" role="dialog" aria-labelledby="loadingModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
			<div class="modal-dialog">
				<div class="modal-content">
					<div class="modal-header">
						<h4 class="modal-title" id="loadingModalLabel">Email</h4>
					</div>
					<div class="modal-body">
					<table style="margin:auto"><tr>
					<td><div class="spinner-border text-primary" role="status">
					  <span class="sr-only">Sending...</span>
					</div></td>
					<td style="padding-left:4em"><h3><?php echo zbx_lng("PleaseWait"); ?></h3></td></tr></table>
					</div>
				</div><!-- /.modal-content -->
			</div><!-- /.modal -->
			</div>
	<?php 
				break;
			case "change":
				zbx_show_header_login();
				zbx_show_message();
			?>
			<div class="card fat">
				<div class="card-body">
					<form class="form-signin" action="account.php?act=cpass" method="post" autocomplete="off" autocomplete="new-password">
						<div class="form-group">
							<a href="index.php"><i class="fa fa-chevron-circle-left go-back"></i> <?php echo zbx_lng('Back'); ?></a>
						</div>
						<div class="form-group">
							<h1 class="text-center card-title"><?php echo zbx_lng('ChangePassword'); ?></h1>
						</div>

						<div class="form-group">
							<label for="fm_pwd"><?php echo zbx_lng('Password'); ?></label>
							<input type="password" class="form-control" id="fm_pwd" name="fm_pwd" required>
						</div>

						<div class="form-group">
							<label for="fm_pwd"><?php echo zbx_lng('PasswordConfirm'); ?></label>
							<input type="password" class="form-control" id="fm_pwd2" name="fm_pwd2" required>
						</div>

						<div class="form-group">
							<button type="submit" class="btn btn-warning btn-block" role="button">
								<?php echo zbx_lng('Change'); ?>
							</button>
						</div>
					</form>
				</div>
			</div>
	<?php
				break;
			case "delete":
				zbx_show_header_login();
				zbx_show_message();
?>
			<div class="card fat">
				<div class="card-body">
					<form class="form-signin" action="account.php?act=purge" method="post" autocomplete="off" autocomplete="new-password">
						<div class="form-group">
							<a href="index.php"><i class="fa fa-chevron-circle-left go-back"></i> <?php echo zbx_lng('Back'); ?></a>
						</div>
						<div class="form-group">
							<h1 class="text-center card-title"><?php echo zbx_lng("DeleteAccount"); ?></h1>
						</div>
						<div class="form-group">
							<label style="color:red"><?php echo zbx_lng("DangerDeleteAccount"); ?></label>
						</div>


						<div class="form-group">
							<label for="fm_pwd"><?php echo zbx_lng('PasswordConfirm'); ?></label>
							<input type="password" class="form-control" id="fm_pwd" name="fm_pwd" required>
						</div>

						<div class="form-group">
							<button type="submit" class="btn btn-danger btn-block" role="button">
								<?php echo zbx_lng('DeleteAccount'); ?>
							</button>
						</div>
					</form>
				</div>
			</div>
	<?php
				break;
			default:
				header("location: index.php"); exit; break;
		}
		zbx_show_footer_login();
	}
}

function deleteDir($dirPath) {
    if (! is_dir($dirPath)) {
        throw new InvalidArgumentException("$dirPath must be a directory");
    }
    if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') {
        $dirPath .= '/';
    }
    $files = glob($dirPath . '*', GLOB_MARK);
    foreach ($files as $file) {
        if (is_dir($file)) {
            deleteDir($file);
        } else {
            unlink($file);
        }
    }
    rmdir($dirPath);
}

function strxor($str, $key){
	$out="";
	for($i=0;$i<strlen($str);$i++){
		$out.=$str[$i]^chr($key);
	}
	return $out;
}

function zbx_show_message()
{
	if (isset($_GET['err'])) {
		echo '<p class="message error">' . zbx_lng($_GET['err']) . '</p>';
	}
}

function zbx_show_success($operation)
{
?>
	<div class="card fat">
		<div class="card-body">
			<div class="text-center">
				<h1 class="card-title"><?php echo zbx_lng($operation); echo zbx_lng("Success");?> </h1>
			</div>
			<a href="index.php"><?php echo zbx_lng("AutoRefresh");?></a>
		</div>
	</div>
<?php
}

function zbx_show_header_login()
{
$sprites_ver = '20160315';
header("Content-Type: text/html; charset=utf-8");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
header("Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0");
header("Pragma: no-cache");

global $lang, $root_url, $favicon_path;
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<meta name="description" content="Web based File Manager in PHP, Manage your files efficiently and easily with Tiny File Manager">
	<meta name="author" content="CCP Programmers">
	<meta name="robots" content="noindex, nofollow">
	<meta name="googlebot" content="noindex">
	<link rel="icon" href="<?php echo zbx_enc($favicon_path) ?>" type="image/png">
	<title><?php echo zbx_enc("Tiny File Manager") ?></title>
	<link rel="stylesheet" href="https://cdn.staticfile.org/twitter-bootstrap/4.4.1/css/bootstrap.min.css">
	<link rel="stylesheet" href="https://cdn.staticfile.org/font-awesome/4.7.0/css/font-awesome.min.css">
	<style>
		body.fm-login-page{background-color:#f7f9fb;font-size:14px}
		.fm-login-page .brand{width:121px;overflow:hidden;margin:0 auto;margin:40px auto;margin-bottom:0;position:relative;z-index:1}
		.fm-login-page .brand img{width:100%}
		.fm-login-page .card-wrapper{width:360px}
		.fm-login-page .card{border-color:transparent;box-shadow:0 4px 8px rgba(0,0,0,.05)}
		.fm-login-page .card-title{margin-bottom:1.5rem;font-size:24px;font-weight:300;letter-spacing:-.5px}
		.fm-login-page .form-control{border-width:2.3px}
		.fm-login-page .form-group label{width:100%}
		.fm-login-page .btn.btn-block{padding:12px 10px}
		.fm-login-page .footer{margin:40px 0;color:#888;text-align:center}
		@media screen and (max-width: 425px) {
			.fm-login-page .card-wrapper{width:90%;margin:0 auto}
		}
		@media screen and (max-width: 320px) {
			.fm-login-page .card.fat{padding:0}
			.fm-login-page .card.fat .card-body{padding:15px}
		}
		.message{padding:4px 7px;border:1px solid #ddd;background-color:#fff}
		.message.ok{border-color:green;color:green}
		.message.error{border-color:red;color:red}
		.message.alert{border-color:orange;color:orange}
	</style>
</head>
<body class="fm-login-page">
	<div id="wrapper" class="container-fluid">
		<section class="h-100">
			<div class="container h-100">
				<div class="row justify-content-md-center h-100">
					<div class="card-wrapper">
						<div class="brand">
							<svg version="1.0" xmlns="http://www.w3.org/2000/svg" M1008 width="100%" height="121px" viewBox="0 0 512 512" aria-label="H3K Tiny File Manager">
								<g>
									<path d="M489.901,214.376c-11.994-16.059-28.37-28.736-46.802-36.384c-3.773-21.386-14.386-40.977-30.477-55.958
										c-18.173-16.919-41.727-26.423-66.51-26.879c-12.252-22.052-29.759-40.783-51.083-54.557
										c-24.797-16.018-53.572-24.483-83.214-24.483c-84.682,0-153.575,68.894-153.575,153.575c0,4.346,0.187,8.712,0.556,13.064
										C22.733,202.008,0,239.414,0,280.981c0,61.427,49.974,111.4,111.4,111.4h34.58l0.002,36.782c0,12.11,4.989,23.891,13.688,32.318
										l22.423,21.723c8.44,8.177,19.562,12.68,31.314,12.68l92.947-0.005c12.02-0.001,23.319-4.682,31.819-13.182
										c8.499-8.5,13.18-19.8,13.179-31.82l-0.003-58.496h49.25c61.427,0,111.4-49.974,111.4-111.4
										C512,256.763,504.358,233.731,489.901,214.376z M203.469,462.099c-0.167-0.148-0.34-0.285-0.501-0.441l-22.423-21.723
										c-0.018-0.017-0.032-0.037-0.05-0.054h22.974V462.099z M316.96,461.485c-2.833,2.834-6.601,4.395-10.607,4.395l-72.884,0.004
										v-41.002c0-8.284-6.716-15-15-15h-42.487l-0.007-128.772l145.369-0.006l0.009,169.776
										C321.353,454.886,319.793,458.652,316.96,461.485z M400.6,362.381h-49.252l-0.005-81.345c7.67-0.664,13.691-7.092,13.691-14.935
										c0-8.284-6.717-14.999-15.001-14.999l-202.752,0.009c-8.284,0-14.999,6.717-14.999,15.001c0,7.842,6.022,14.269,13.692,14.933
										l0.004,81.337H111.4c-44.885,0-81.4-36.516-81.4-81.4c0-33.234,19.885-62.827,50.659-75.391
										c6.453-2.636,10.241-9.369,9.143-16.252c-1.035-6.487-1.561-13.098-1.561-19.648c0-68.14,55.436-123.575,123.575-123.575
										c47.528,0,91.392,27.775,111.747,70.761c2.673,5.646,8.571,9.041,14.793,8.529c2.175-0.18,4.1-0.268,5.883-0.268
										c36.567,0,67.396,28.534,70.187,64.961c0.469,6.122,4.622,11.341,10.483,13.172C459.057,213.939,482,245.169,482,280.981
										C482,325.866,445.484,362.381,400.6,362.381z"/>
									<path d="M289.41,304.908h-81.491c-8.284,0-15,6.716-15,15s6.716,15,15,15h81.491c8.284,0,15-6.716,15-15
										S297.694,304.908,289.41,304.908z"/>
									<path d="M289.41,352.425h-81.491c-8.284,0-15,6.716-15,15s6.716,15,15,15h81.491c8.284,0,15-6.716,15-15
										S297.694,352.425,289.41,352.425z"/>
								</g>
							</svg>
						</div>
						<div class="text-center">
							<h1 class="card-title"><?php echo "Tiny Cloud Drive"; ?></h1>
						</div>
	<?php
	}

	/**
	 * Show page footer in Login Form
	 */
	function zbx_show_footer_login()
	{
	?>
							<div class="footer text-center">
							&mdash;&mdash; &copy;
							<a href="https://zbx1425.tk" target="_blank" class="text-muted" data-version="<?php echo VERSION; ?>">zbx1425</a> &mdash;&mdash;
						</div>
					</div>
				</div>
			</div>
		</section>
</div>
<script src="https://cdn.staticfile.org/jquery/3.4.1/jquery.slim.min.js"></script>
<script src="https://cdn.staticfile.org/twitter-bootstrap/4.4.1/js/bootstrap.min.js"></script>
</body>
</html>
<?php
}
function zbx_enc($text)
{
	return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function zbx_get_translations($tr) {
	try {
		$content = @file_get_contents('translation.json');
		if($content !== FALSE) {
			$zbx_lng = json_decode($content, TRUE);
			global $lang_list;
			foreach ($zbx_lng["language"] as $key => $value)
			{
				$code = $value["code"];
				$lang_list[$code] = $value["name"];
				if ($tr)
					$tr[$code] = $value["translation"];
			}
			return $tr;
		}

	}
	catch (Exception $e) {
		echo $e;
	}
}

function zbx_lng($txt) {
	global $lang;
	// English Language
	$tr['en']['AppName']        = 'Tiny Cloud Drive';      $tr['en']['AppTitle']           = 'File Manager';
	$tr['en']['Login']          = 'Sign in';                $tr['en']['Username']           = 'Username';
	$tr['en']['Password']       = 'Password';               $tr['en']['Logout']             = 'Sign Out';
	$tr['en']['Move']           = 'Move';                   $tr['en']['Copy']               = 'Copy';
	$tr['en']['Save']           = 'Save';                   $tr['en']['SelectAll']          = 'Select all';
	$tr['en']['UnSelectAll']    = 'Unselect all';           $tr['en']['File']               = 'File';
	$tr['en']['Back']           = 'Back';                   $tr['en']['Size']               = 'Size';
	$tr['en']['Perms']          = 'Perms';                  $tr['en']['Modified']           = 'Modified';
	$tr['en']['Owner']          = 'Owner';                  $tr['en']['Search']             = 'Search';
	$tr['en']['NewItem']        = 'New Item';               $tr['en']['Folder']             = 'Folder';
	$tr['en']['Delete']         = 'Delete';                 $tr['en']['Rename']             = 'Rename';
	$tr['en']['CopyTo']         = 'Copy to';                $tr['en']['DirectLink']         = 'Direct link';
	$tr['en']['UploadingFiles'] = 'Upload Files';           $tr['en']['ChangePermissions']  = 'Change Permissions';
	$tr['en']['Copying']        = 'Copying';                $tr['en']['CreateNewItem']      = 'Create New Item';
	$tr['en']['Name']           = 'Name';                   $tr['en']['AdvancedEditor']     = 'Advanced Editor';
	$tr['en']['RememberMe']     = 'Remember Me';            $tr['en']['Actions']            = 'Actions';
	$tr['en']['Upload']         = 'Upload';                 $tr['en']['Cancel']             = 'Cancel';
	$tr['en']['InvertSelection']= 'Invert Selection';       $tr['en']['DestinationFolder']  = 'Destination Folder';
	$tr['en']['ItemType']       = 'Item Type';              $tr['en']['ItemName']           = 'Item Name';
	$tr['en']['CreateNow']      = 'Create Now';             $tr['en']['Download']           = 'Download';
	$tr['en']['Open']           = 'Open';                   $tr['en']['UnZip']              = 'UnZip';
	$tr['en']['UnZipToFolder']  = 'UnZip to folder';        $tr['en']['Edit']               = 'Edit';
	$tr['en']['NormalEditor']   = 'Normal Editor';          $tr['en']['BackUp']             = 'Back Up';
	$tr['en']['SourceFolder']   = 'Source Folder';          $tr['en']['Files']              = 'Files';
	$tr['en']['Move']           = 'Move';                   $tr['en']['Change']             = 'Change';
	$tr['en']['Settings']       = 'Settings';               $tr['en']['Language']           = 'Language';
	$tr['en']['MemoryUsed']     = 'Memory used';            $tr['en']['PartitionSize']      = 'Partition size';
	$tr['en']['ErrorReporting'] = 'Error Reporting';        $tr['en']['ShowHiddenFiles']    = 'Show Hidden Files';
	$tr['en']['Full size'] 		= 'Full size';				$tr['en']['Help'] 				= 'Help';
	$tr['en']['Free of'] 		= 'Free of';				$tr['en']['Preview'] 			= 'Preview';
	$tr['en']['Help Documents'] = 'Help Documents';			$tr['en']['Report Issue']		= 'Report Issue';
	$tr['en']['Generate'] 		= 'Generate';				$tr['en']['FullSize']           = 'Full Size';
	$tr['en']['FreeOf']         = 'free of';                $tr['en']['CalculateFolderSize']= 'Calculate folder size';
	$tr['en']['Check Latest Version']= 'Check Latest Version';
	$tr['en']['Generate new password hash'] = 'Generate new password hash';
	$tr['en']['HideColumns'] = 'Hide Perms/Owner columns';
	$i18n = zbx_get_translations($tr);
	$tr = $i18n ? $i18n : $tr;

	if (!strlen($lang)) $lang = 'en';
	if (isset($tr[$lang][$txt])) return zbx_enc($tr[$lang][$txt]);
	else if (isset($tr['en'][$txt])) return zbx_enc($tr['en'][$txt]);
	else return "$txt";
}
?>