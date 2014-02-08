<?php

$DB_NAME='test_ttly';
$DB_NAME='ttly';

$COL_NAME='ttly_links';
$GENERIC_STATS='stats';

if (!function_exists('http_build_url')) {
    function http_build_url($parts) {
        $out = "";
        if ($parts['scheme']) $out .= $parts['scheme'] . "://";
        if ($parts['user']) {
            $out .= $parts['user'];
            if ($parts['pass']) {
                $out .= ":" . $parts['pass'];
            }
            $out .= "@";
        }
        if ($parts['host']) $out .= $parts['host'];
        if ($parts['port']) $out .= ":" . $parts['port'];
        if ($parts['path']) $out .= $parts['path'];
        if ($parts['query']) $out .= '?' . $parts['query'];
        if ($parts['fragment']) $out .= '#' . $parts['fragment'];
        
        return $out;
    }
}

if (!function_exists('random_string')) {
    function random_string($l = 8){
        $c = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxwz0123456789";
        for(;$l > 0;$l--) $s .= $c{rand(0,strlen($c))};
        return str_shuffle($s);
    }
}

$created = false;
$mongo = new Mongo();
$db = $mongo->{$DB_NAME};
$links = $db->{$COL_NAME};
$stats = $db->{$GENERIC_STATS};

if ($_REQUEST && count($_REQUEST)) {

    $mDate = new MongoDate();

    if (isset($_REQUEST['l']) && !empty($_REQUEST['l'])) {
        $tag = trim($_REQUEST['l']);
        
        
        $link = $links->findOne(array('locators.tag' => $tag));
        if ($link) {
            $url = $link['url'];
            
            $links->update(array('_id' => $link['_id']), 
                                 array('$inc' => array('hits' => 1)));
                                 
                                 
            $links->update(array('locators.tag' => $tag), 
                                 array('$inc' => array('locators.$.hits' => 1)));
                                 
            if (isset($_SERVER['HTTP_REFERER'])) {
                $r = $_SERVER['HTTP_REFERER'];
                $rl = $links->findOne(array('_id' => $link['_id'], 'refs.url' => $r));
                if ($rl) {
                    $links->update(array('locators.tag' => $tag, 'refs.url' => $r), array('$inc' => array('refs.$.hits' => 1)));
                }
                else {
                    $links->update(array('_id' => $link['_id']), array('$push' => array('refs' => array('url' => $r, 'hits' => 1, 'firstAccessDate' => $mDate))));
                }
            }
            
            $link = $links->findOne(array('locators.tag' => $tag));
                        
            ob_clean();
            header_remove();
            header('HTTP/1.1 301 Moved Permanently');
            header("Location: $url");
            $html_url = htmlspecialchars($url);
            echo <<<EOF
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta http-equiv="refresh" content="0;url=$html_url">
  </head>
  <body>
  </body>
</html>
EOF;
            ob_end_flush();
            // header('Content-Type: text/plain');
            // $link = $links->findOne(array('locators.tag' => $tag));
            // echo("HTTP/1.1 301 Moved Permanently\n");
            // echo("Location: $url\n");
            // print_r($link);
            // print_r($_SERVER);
            // ob_end_flush();
            exit;
        }
        else {
            header('HTTP/1.1 404 Not Found');
            exit;
        }
        
    } elseif ((isset($_REQUEST['uri']) && trim($_REQUEST['uri']) != "") || (isset($_REQUEST['url']) && trim($_REQUEST['url']) != "")) {
        $success = false;
        $error = "unknown";
        $links->ensureIndex(array('url' => 1));
        $links->ensureIndex(array('locators.tag' => 1));
        
            
        $uri = trim($_REQUEST['uri']);
        if (empty($uri) && isset($_REQUEST['url'])) $uri = trim($_REQUEST['url']);
        $validUrl = false;
        if ($uri) {
            $up = parse_url($uri);
            $validUrl = isset($up['scheme']) && isset($up['host']) && strtolower($up['host']) != $_SERVER['HTTP_HOST'];
        }
        @$shortcut = trim($_REQUEST['d']);
        $autogen = false;
        if (empty($shortcut)) {
            $scexists = true; 
            //$ec = 15;
            do {
                $shortcut = random_string(5);
                $scexists = !!$links->findOne(array('locators.tag' => $shortcut));
            } while ($scexists);
            
            $autogen = true;
        }
        $ex = file_exists(dirname(__FILE__)."/$shortcut");
        $comp = preg_replace('/[^A-Za-z0-9\\-\\_]/', "", $shortcut);
        if ($comp != $shortcut || $ex) {
            $error = array (
                'description' => 'Invalid tag',
                'code' => 3,
                 'url' => $uri, 'd' => $shortcut, 'comp' => $comp
            );
        }
        elseif (!$validUrl) {
            $error = array (
                'description' => 'Invalid url given',
                'code' => 4,
                 'url' => $uri, 'd' => $shortcut
            );
        }
        else {
            $existing_uri = $links->findOne(array('url' => $uri));
            $existing_loc = $links->findOne(array('locators.tag' => $shortcut));
            if ($existing_uri) {
                if (!$existing_loc) {
                    // Shortcut doesn't exist anywhere, uri already registered. Add shortcut
                    $links->update(array('_id' => $existing_uri['_id']), array('$push' => array('locators' => array('tag' => $shortcut, 'hits' => 0, 'createDate' => $mDate))));
                    $entry = $links->findOne(array('locators.tag' => $shortcut));
                    $success = true;
                }
                else {
                    $loc = (string)$existing_loc['_id'];
                    $euri = (string)$existing_uri['_id'];
                    if ($loc == $euri) {
                        // Shortcut exists for given uri, just pass it thru
                        $success = true;
                        $entry = $existing_uri;
                    }
                    else {
                        // Shortcut taken
                        $error = array('description' => "Shortcut not available", 'code' => 1, 'url' => $uri, 'd' => $shortcut);
                    }
                }
            }
            elseif ($existing_loc) {
                // Locator already in use
                $error = array('description' => "Shortcut not available", 'code' => 2, 'url' => $uri, 'd' => $shortcut);
            }
            else {
                $entry = array('url' => $uri, 'locators' => array(array('tag' => $shortcut, 'hits' => 0, 'createDate' => $mDate)), 'hits' => 0, 'refs' => array());
                $links->insert($entry);
                $success = true;
            }
        }
        
        if ($success) {
            $url = "http://$_SERVER[HTTP_HOST]/$shortcut";
            $entry['requestedUrl'] = $url;
            if (isset($_REQUEST['action'])) {
                header('Content-Type: application/json');
                $json = json_encode((array)$entry);
                header('x-json: ' . $json);
                header('Connection: close');
                header('Content-Length: ' . strlen($json));
                echo $json;
                exit;
            }
            else {
                $created = true;
            }
        }
        else {
            if (isset($_REQUEST['action'])) {
                $json = json_encode(array('error' => $error));
                header('Content-Type: application/json');
                header('x-json: ' . $json);
                echo $json;
                exit;
            }
        }
    }
}


$totalLinks = 0;
$totalLocators = 0;
$totalHits = 0;
$totalRefs = 0;

$allLinks = $links->find(array(), array('hits' => 1, 'locators' => 1, 'refs' => 1));
foreach($allLinks as $al) {
    $totalLinks++;
    $totalLocators += count($al['locators']);
    $totalHits += $al['hits'];
    $totalRefs += count($al['refs']);
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <title>ttly.me url minimizer</title>
    <meta http-equiv="content-type" content="text/html; charset=UTF-8" />
    <!--[if IE]>
    	<script src="http://html5shiv.googlecode.com/svn/trunk/html5.js"></script>
    <![endif]-->
    <!--[if IE 7]>
    	<link rel="stylesheet" href="ie7.css" type="text/css" media="screen" />
    <![endif]-->
    <link rel="stylesheet" href="style.css" type="text/css" media="screen" />
    <script src="http://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js" type="text/javascript"></script>
    <script src="js/jquery.anchor.js" type="text/javascript"></script>
    <script src="js/ttly.js" text/javascript"></script>
</head>

<body>

    <header>
    
    	<div id="headercontainer">
    		<h1><a class="introlink" href="/">ttly.me url minimizer</a></h1>
    		<nav>
    			<ul>
                    <li>My other sites:</li>
                    <li><a href="/pictars" target="_blank">Pictars - Free Picture Hosting</a></li>
                    <li><a href="/beauscott" target="_blank">My Blog</a></li>
    			</ul>				
    		</nav>
    	</div>
    
    </header>

    <section id="contentcontainer">
    
    	<section id="home">
    	   <?php if (!isset($_REQUEST['action']) && $created) { ?>
               <h2>
                   <a href="<?=$entry['requestedUrl']?>" target="_blank"><?=$entry['requestedUrl']?></a>
                   <span class="sub">(right-click, copy link address)</span>
               </h2>
               <br/>
               <br/>
               <br/>
               <br/>
               <input type="submit" value="Make Another" onclick="location.href='http://ttly.me/'; return false;" />
            <?php } else { ?>
    		<h2>Shrink Your Link <span class="sub">Yeah, this is another one of those sites...</span></h2>
    		<div style="clear: both; margin-top: 15px; text-align:justify;">
        	    <form id="homeform"> 
        	        <input type="hidden" name="action" value="create" />
        	        
                    <p><label for="url">URL</label></p> 
                    <input type="url" id="uri" name="uri" placeholder="http://..." required tabindex="1" maxlength="256" autocomplete="off" /> 
                     
                    <p><label for="d">Desired Tag</label></p> 
                    <input type="text" id="d" name="d" placeholder="(optional)" tabindex="2" maxlength="32" autocomplete="off" 
                        pattern="[\w\-]{5,}" title="Minimum 5 characters, no spaces, alpha-numeric, case-sensitive"/> 
                    
                    <br />
                    <input name="submit" type="submit" id="submit" tabindex="3" value="Commence Shrinkage" /> 
                     
                </form><br/>
                <a href="javascript:void(window.open('http://ttly.me/?uri='+encodeURIComponent(location.href)))">ttly.me creator</a> &lt;-- Use this bookmark to make a ttly.me link automatically.
            </div>
            <?php } ?>
    	</section>
    	
        <section id="resultcontainer" style="display: none"></section>
        
    
        <footer>
          <small>
            &copy;<?=date('Y')?>, <a href="mailto:me@beauscott.com">Beau Scott</a> &nbsp; | &nbsp;
            <?=$totalLinks?> links, <?=$totalHits?> hits, <?=$totalLocators ?> locators, <?=$totalRefs?> referrers.
          </small> 
        </footer>
    </section>
    
</body>

</html>
