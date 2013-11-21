<?php

require_once('libs/deathbycaptcha/deathbycaptcha.php');

define("DBC_USERNAME", "");
define("DBC_PASSWORD", "");
define("DBC_TIMEOUT", 60);
define("URL", "http://www.bitvisitor.com/");
define("CURL_TIMEOUT", 60);

//Help
if(@$argv[1]=="--help" || @$argv[1]=="-h" || @$argv[1]=="help" || !@$argv[1]){
	die("\n    Usage: ".$argv[0]." LTC_adress [-p]\n\n");
}

define("WALLET", $argv[1]);

goNext();

/**
 Next
*/
function goNext($res=""){
	global $proxy;
	if(!$res){
		echo colorize("Grabbing the valitadion form...", "title");
		if(isset($argv[2])){
			$proxy = getProxy();
		}
		$post = array(
			"ref" => "", 
			"addr" => WALLET
		);
		$res = curl(URL."next.php", $post);
	}
	//Banned IP?
	if(strstr($res, "Abuse")){
		echo colorize("IP banned.", "warning");
		$proxy = getProxy();
	//Invalid Wallet Adress?
	}elseif(strstr($res, "Invalid Address")){
		die(colorize("Invalid adress.", "failure"));
	//Invalid Visit?
	}elseif(strstr($res, "Invalid Visit")){
		echo colorize("Invalid Visit. Please try again in 24 hours.", "failure");
		$proxy = getProxy();
	//Something not ok...
	}elseif(!strstr($res, "for visiting the next site")){
		file_put_contents("tmp/log.txt", curl($res));
		echo colorize("Something is not ok...", "failure");
		echo colorize("Result logged in tmp/log.txt", "debug");
		$proxy = getProxy();
	//All ok
	}else{
		//BTC's
		$btcs = get_between($res, "<small> Earn </small>", "<small>");
		echo colorize("Next earnings: ".$btcs, "debug");
		//Graphic captcha?
		if(strstr($res, "/securimage_show.php")){
			echo colorize("Image captcha", "debug");
			//Saving the image...
			$url = URL.get_between($res, 'margin-right: 15px" src="./', '" alt="CAPTCHA ');
			$img = 'tmp/captcha.png';
			file_put_contents($img, curl($url));
			echo colorize("Solving captcha...", "debug");
			//Deathbycaptcha resolver
			$solvedCaptcha = deathbycaptchaResolver($img);
		//Text captcha?
		}else{
			echo colorize("Text captcha", "debug");
			echo $res;
			exit;
		}
		//Got solved captcha?
		if($solvedCaptcha){
			echo colorize("Captcha solved: ".$solvedCaptcha, "debug");
			goVisit($solvedCaptcha);
		}
	}
}

/**
 Visit
*/
function goVisit($solvedCaptcha){
	global $proxy;
	echo colorize("Visiting the ad...", "title");
	$post = array(
		"ct_captcha" => $solvedCaptcha,
		"addr" => WALLET
	);
	$res = curl(URL."next.php", $post);
	//Grabbing the minutes
	$cuted = get_between($res, "function cd() {", "}");
	$minutes = (int)get_between($cuted, "mins = ", " * m");
	$seconds = (int)get_between($cuted, "secs = ", " + s");
	echo colorize("Waiting ".$minutes." minutes and ".$seconds." seconds...", "debug");
	sleep(($minutes*60)+$seconds);
	//Grabbing the inputs
	$a = get_between($res, 'name="a" value="', '"');
	$t = get_between($res, 'name="t" value="', '"');
	$s = get_between($res, 'name="s" value="', '"');
	echo colorize("Sending the form... GIMME MA LILCOINZ!", "debug");
	echo colorize("Grabbing the next valitadion form...", "title");
	$post = array(
		"a" => $a,
		"t" => $t,
		"s" => $s,
		"addr" => WALLET
	);
	$res = curl(URL."next.php", $post);
	//repeating!
	goNext($res);
}

/**
 deathbycaptchaResolver
*/
function deathbycaptchaResolver($img){
	// Put your DBC credentials here.
	// Use DeathByCaptcha_HttpClient class if you want to use HTTP API.
	$client = new DeathByCaptcha_SocketClient(DBC_USERNAME, DBC_PASSWORD);
	// Put the CAPTCHA file name or handler, and desired timeout (in seconds) here:
	if ($captcha = $client->decode($img, DBC_TIMEOUT)) {
		return $captcha['text'];
	}
}

/**
 Helpers
*/
function get_between_help($end,$r){
   $r = explode($end,$r);
   return $r[0];   
}
function get_between($content,$start,$end){
   $r = explode($start, $content);
   if (isset($r[1])){
       array_shift($r);
       $end = array_fill(0,count($r),$end);
       $r = array_map('get_between_help',$end,$r);
       if(count($r)>1)
           return $r;
       else
           return $r[0];
   } else {
       return array();
   }
}

function curl($url, $post=""){
	global $proxy;
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; Trident/4.0; .NET CLR 2.0.50727; .NET CLR 3.0.4506.2152; .NET CLR 3.5.30729)");
	curl_setopt($ch, CURLOPT_AUTOREFERER, true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POST , true);
	curl_setopt($ch, CURLOPT_POSTFIELDS , $post);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, CURL_TIMEOUT);
	curl_setopt($ch, CURLOPT_TIMEOUT, CURL_TIMEOUT);
	curl_setopt($ch, CURLOPT_COOKIEJAR, "tmp/cookies.txt");
	curl_setopt($ch, CURLOPT_COOKIEFILE, "tmp/cookies.txt");
	if($proxy){
		curl_setopt($ch, CURLOPT_PROXY, $proxy);
	}
	$exec = curl_exec($ch);
	curl_close($ch);
	return $exec;
}

function colorize($text, $status) {
	global $config;
	//http://softkube.com/blog/generating-command-line-colors-with-php/
	$out = "";
 	switch(strtolower($status)){
  		case "banner":
   			$out = "[0;32m"; //Green
  		break;
  		case "title":
  			$text = "\n[+] ".$text;
   			$out = "[0;32m"; //Green
  		break;
  		case "debug":
  			$text = " * ".$text;
   		break;
  		case "success":
  			$text = " - ".$text;
   			$out = "[0;32m"; //Green
   		break;
  		case "failure":
   			$text = " ! ".$text;
   			$out = "[0;31m"; //Red
   		break;
  		case "warning":
  			$text = " X ".$text;
   			$out = "[1;33m"; //Yellow
   		break;
  		case "notice":
  			$text = " - ".$text;
   			$out = "[0;34m"; //Blue
   		break;
   		default:
   			$text = " | ".$text;
   		break;
   	}
	if($text){
		if($out)
	 		return chr(27).$out.$text.chr(27)."[0m \n";
	 	else
	 		return $text."\n";
	 }
}

function getProxy(){
	echo colorize("Grabbing a proxy...", "debug");
	$list = array();
	//Post data
	$post = "ac=on&c%5B%5D=United+States&c%5B%5D=Indonesia&c%5B%5D=China&c%5B%5D=Brazil&c%5B%5D=Russian+Federation&c%5B%5D=Iran";
	$post = "&c%5B%5D=Colombia&c%5B%5D=Thailand&c%5B%5D=India&c%5B%5D=Egypt&c%5B%5D=Ukraine&c%5B%5D=Korea%2C+Republic+of";
	$post = "&c%5B%5D=Germany&c%5B%5D=Turkey&c%5B%5D=Poland&c%5B%5D=Argentina&c%5B%5D=Mongolia&c%5B%5D=Peru&c%5B%5D=Latvia";
	$post = "&c%5B%5D=Ecuador&c%5B%5D=Canada&c%5B%5D=South+Africa&c%5B%5D=Taiwan%2C+Republic+of+China&c%5B%5D=France";
	$post = "&c%5B%5D=Venezuela&c%5B%5D=Hong+Kong&c%5B%5D=Spain&c%5B%5D=United+Kingdom&c%5B%5D=Chile&c%5B%5D=Australia";
	$post = "&c%5B%5D=Kazakhstan&c%5B%5D=Italy&c%5B%5D=Czech+Republic&c%5B%5D=Philippines&c%5B%5D=Netherlands&c%5B%5D=Japan";
	$post = "&c%5B%5D=Nigeria&c%5B%5D=Viet+Nam&c%5B%5D=Romania&c%5B%5D=Bulgaria&c%5B%5D=Bangladesh&c%5B%5D=Kenya";
	$post = "&c%5B%5D=Cambodia&c%5B%5D=Malaysia&c%5B%5D=Switzerland&c%5B%5D=Mexico&c%5B%5D=United+Arab+Emirates";
	$post = "&c%5B%5D=Hungary&c%5B%5D=Portugal&c%5B%5D=Albania&c%5B%5D=Lithuania&c%5B%5D=Kuwait&c%5B%5D=Slovakia";
	$post = "&c%5B%5D=Iraq&c%5B%5D=Sri+Lanka&c%5B%5D=Pakistan&c%5B%5D=Serbia&c%5B%5D=Paraguay&c%5B%5D=Bosnia+and+Herzegovina";
	$post = "&c%5B%5D=Singapore&c%5B%5D=Macedonia&c%5B%5D=Malta&c%5B%5D=Saudi+Arabia&c%5B%5D=Denmark&c%5B%5D=Norway";
	$post = "&c%5B%5D=Palestinian+Territory%2C+Occupied&c%5B%5D=Dominican+Republic&c%5B%5D=Costa+Rica&c%5B%5D=Ghana";
	$post = "&c%5B%5D=Mozambique&c%5B%5D=Belgium&c%5B%5D=Gibraltar&c%5B%5D=Lao+PDR&c%5B%5D=Uganda&c%5B%5D=Luxembourg";
	$post = "&c%5B%5D=Cote+D%27Ivoire&c%5B%5D=Benin&c%5B%5D=Puerto+Rico&c%5B%5D=Israel&c%5B%5D=Ireland&c%5B%5D=Austria";
	$post = "&c%5B%5D=Croatia&c%5B%5D=Greece&c%5B%5D=Zimbabwe&c%5B%5D=Brunei+Darussalam&c%5B%5D=Georgia&c%5B%5D=Azerbaijan";
	$post = "&c%5B%5D=Belarus&c%5B%5D=Moldova%2C+Republic+of&p=8080&pr%5B%5D=0&pr%5B%5D=1&a%5B%5D=0&a%5B%5D=1&a%5B%5D=2";
	$post = "&a%5B%5D=3&a%5B%5D=4&pl=on&sp%5B%5D=3&ct%5B%5D=3&s=0&o=0&pp=2&sortBy=date";
	//Curl
	$content = curl("http://www.hidemyass.com/proxy-list/search-227289", $post);
	//Table
	$table = get_between($content, '<table id="listtable"', "</table>");
	if($table){
	    //Rows
	    $trs = get_between($table, "<tr", "</tr>");
	    //Unset thead
	    unset($trs[0]);
	    if(count($trs)){
	        foreach($trs as $tr){
	            //Cols
	            $tds = get_between($tr, "<td", "</td>");
	            if(count($tds)){
	                //Last Update
	                $current['lastUpdate'] = date("Y-m-d H:i:s", @trim(get_between($tr, 'timestamp" rel="', '"')));
	                //Ip
	                    //Style Obfs
	                    $style = get_between($tds[1], "<style>", "</style>");
	                    if($style){
	                        $classes = get_between($style, ".", "{display:none}");
	                        $classes[] = "display:none";
	                    }
	                    //Ip
	                    $current['ip'] = "";
	                    $elements = get_between($tds[1], "<", "</");
	                    if(count($elements)){
		                    foreach($elements as $element){
		                        if(!strstr_array($element, $classes)){
		                            $part = @trim(substr($element, strpos($element, ">")+1));
		                            if($part){
		                                $current['ip'] .= $part;
		                            }
		                        }
		                    }
		                }
	                //Port
	                $current['port'] = @trim(substr($tds[2], strpos($tds[2], ">")+1));
	                //ignore sheeeeit
	                if($current['ip']){
	                    $list[] = $current;
	                    
	                }
	            }
	        }
	    }
	}
	$r = rand(0, count($list));
	$proxy = $list[$r]['ip'].":".$list[$r]['port'];
	echo colorize("Proxy: ".$proxy, "debug");
	return $proxy;
}

function strstr_array($haystack, $needle){
    if(!is_array($needle)){
        return false;
    }
    foreach($needle as $n){
        if(strstr($haystack, $n)){
            return true;
        }
    }
    return false;
}

?>