<?php if (!defined('PmWiki')) exit();
/*  Copyright 2007-2023 Patrick R. Michaud (pmichaud@pobox.com)
    This file is part of PmWiki; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published
    by the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.  See pmwiki.php for full details.

    Script maintained by Petko YOTOV www.pmwiki.org/petko
*/

$RecipeInfo['Captcha']['Version'] = '20230227';

SDV($CaptchaValue, mt_rand(1000, 9999));
SDV($CaptchaName, 'response');
SDV($EnableCaptchaImage, (int)function_exists('imagecreatetruecolor'));

SDV($FmtPV['$CaptchaKey'], "\$GLOBALS['CaptchaKey']");
SDV($FmtPV['$CaptchaValue'], 'CaptchaValue()');
SDV($FmtPV['$Captcha'], 'CaptchaFn($pn)');

SDV($Conditions['captcha'], '(boolean)IsCaptcha()');

SDVA($InputTags['captcha'], array(
  ':fn' => 'InputCaptcha',
  ':html' => "<input type='hidden' name='captchakey' value='\$CaptchaKey' /><input type='text' \$InputFormArgs />",
  ':args' => array('value', 'placeholder', 'required'),
  'size' => 5,
  'autocomplete' => 'off',
  'name' => $CaptchaName,
  'class' => 'inputbox'
  ));
SDVA($InputTags['captcha1'], $InputTags['captcha']);
$InputTags['captcha1'][':fn'] = 'InputCaptcha1';

array_unshift($EditFunctions, 'RequireCaptcha');
SDV($HandleActions['captchaimage'], 'HandleCaptchaImage');
SDV($HandleAuth['captchaimage'], 'read');

if (!function_exists('pm_session_start')) {
  function pm_session_start() {
    return @session_start();
  }
}

function RequireCaptcha($pagename, $page, $new) {
  global $EnablePostCaptchaRequired, $MessagesFmt, 
    $CaptchaRequiredFmt, $EnablePost;
  if (!IsEnabled($EnablePostCaptchaRequired, 0)) return;
  if (IsCaptcha()) return;
  SDV($CaptchaRequiredFmt, 
    "<div class='wikimessage'>$[Must enter valid code]</div>");
  $MessagesFmt[] = $CaptchaRequiredFmt;
  $EnablePost = 0;
}

function IsCaptcha() {
  global $IsCaptcha, $CaptchaName, $EnableCaptchaSession;
  if (isset($IsCaptcha)) return $IsCaptcha;
  $key = @$_POST['captchakey'];
  $resp = @$_POST[$CaptchaName];
  $sid = session_id();
  pm_session_start();
  if ($key && $resp && @$_SESSION['captcha-challenges'][$key] == $resp)
    $IsCaptcha = 1;
  if (IsEnabled($EnableCaptchaSession, 0)) {
    $IsCaptcha |= @$_SESSION['iscaptcha'];
    @$_SESSION['iscaptcha'] = $IsCaptcha;
  }
  $IsCaptcha = (int)@$IsCaptcha;
  if (!$sid) session_write_close();
  return $IsCaptcha;
}

function InputCaptcha($pagename, $type, $args) {
  CaptchaValue();
  return Keep(InputToHTML($pagename, $type, $args, $opt));
}

function InputCaptcha1($pagename, $type, $args) {
  if(@$_SESSION['iscaptcha']) return '';
  CaptchaValue();
  $chall = CaptchaFn($pagename);
  return Keep($chall.InputToHTML($pagename, $type, $args, $opt));
}

function CaptchaValue() {
  global $CaptchaKey, $CaptchaValue;
  if ($CaptchaKey > '' &&
      @$_SESSION['captcha-challenges'][$CaptchaKey] == $CaptchaValue) 
    return $CaptchaValue;
  $sid = session_id();
  pm_session_start();
  if ($CaptchaKey == '') {
    if(isset($_SESSION['captcha-challenges']))
      $CaptchaKey = count($_SESSION['captcha-challenges']);
    else $CaptchaKey = 0;
  }
  $_SESSION['captcha-challenges'][$CaptchaKey] = $CaptchaValue;
  if (!$sid) session_write_close();
  return $CaptchaValue;
}


function CaptchaFn($pagename) {
  global $CaptchaChallenge, $EnableCaptchaImage;
  if (@$CaptchaChallenge) return $CaptchaChallenge;
  if ($EnableCaptchaImage) return CaptchaImage($pagename);
  return CaptchaValue();
}


function CaptchaImage($pagename) {
  global $CaptchaImageFmt, $CaptchaImageEmbedFmt, $CaptchaImageCSS, $EnableCaptchaImageDataURI;
  $value = CaptchaValue();
  SDV($CaptchaImageCSS, "border: none; vertical-align:top;");
  SDV($CaptchaImageFmt, "<img src='{\$PageUrl}?action=captchaimage&amp;captchakey={\$CaptchaKey}' style='$CaptchaImageCSS' alt='Captcha' />");
  SDV($CaptchaImageEmbedFmt, "<img src='data:image/jpeg;base64,%s' style='$CaptchaImageCSS' alt='Captcha' />");
  if (! IsEnabled($EnableCaptchaImageDataURI, 0)) 
    return Keep(FmtPageName($CaptchaImageFmt, $pagename));

  ob_start();
  CreateCaptchaImage($value);
  $i = base64_encode(ob_get_clean());
  return Keep(sprintf($CaptchaImageEmbedFmt, $i));
}

function HandleCaptchaImage($pagename, $auth = 'read') {
  global $CaptchaImage;
  $key = @$_REQUEST['captchakey'];
  if ($key == '') return '';
  pm_session_start();
  $value = @$_SESSION['captcha-challenges'][$key];
  if (!$value) {
    $value = 'Error';
  }

  header('Content-type: image/jpeg');
  header('Cache-Control: no-store, no-cache, must-revalidate');
  header('Expires: Tue, 01 Jan 2002 00:00:00 GMT');
  
  CreateCaptchaImage($value);
  
  return;
}

function CreateCaptchaImage($value) {
  $width = 60;
  $height = 22;
  $fontwidth = 10;
  $fontheight = 14;
  $img = imagecreatetruecolor($width, $height);
  $white = imagecolorallocate($img, 240, 240, 240);
  imagefilledrectangle($img, 0, 0, $width, $height, $white);
  imagealphablending($img, 1);
  imagecolortransparent($img);
  for($i=0; $i < 100; $i++) {
    $r = mt_rand(200, 255); $g = mt_rand(200, 255); $b = mt_rand(200, 255);
    $color = imagecolorallocate($img, $r, $g, $b);
    imagefilledellipse($img, round(mt_rand(0, $width)), round(mt_rand(0, $height)),
        @round(mt_rand(0, $width/8)), @round(mt_rand(0, $height/4)), $color);
  }
  $vlen = strlen($value);
  $x = mt_rand(2, ($width)/($vlen+1));
  for($i=0; $i < $vlen; $i++) {
    $y = mt_rand(2, $height - $fontheight - 2);
    $r = mt_rand(0, 150); $g = mt_rand(0, 150); $b = mt_rand(0, 150);
    $fg = imagecolorallocatealpha($img, $r, $g, $b, 30);
    $c = substr($value, $i, 1);
    imagechar($img, 5, $x, $y, $c, $fg);
    $min = min($fontwidth + 2, ($width-$x)/($vlen-$i));
    $max = max($fontwidth + 2, ($width-$x)/($vlen-$i));
    $x += @mt_rand($min, $max);
  }
  imagejpeg($img);
  imagedestroy($img); 
  return;
}

