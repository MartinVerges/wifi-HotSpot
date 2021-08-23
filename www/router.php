<?php
// written in a few minutes, quite dirty but working
// MIT licensed (c) by Martin Verges

if (preg_match('/\.(?:css|js|png|jpg|jpeg|gif|map)$/', $_SERVER["REQUEST_URI"])) {
    // direct return file
    return false;
} elseif (preg_match('/^\/exec\/(.*)/', $_SERVER["REQUEST_URI"], $match)) {
    // execute xhr calls
    if ($match[1] == "ping") {
        system("ping -c 1 -W 1 8.8.8.8");#heise.de");
    } elseif ($match[1] == "reboot") {
        system('sleep 3 && shutdown -r now &');
    } elseif ($match[1] == "poweroff") {
        system('sleep 3 && shutdown -h now &');
    } elseif ($match[1] == "iproute") {
        system("ip route show");
    } elseif ($match[1] == "resolv") {
        system("cat /etc/resolv.conf");
    } elseif ($match[1] == "wifilist") {
        echo list2html(parse_nmcli_list(shell_exec("nmcli -m multiline dev wifi list")));
    } elseif ($match[1] == "conshow") {
        echo list2html(parse_nmcli_list(shell_exec("nmcli -m multiline con show")));
    } elseif ($match[1] == "devstatus") {
        echo list2html(parse_nmcli_list(shell_exec("nmcli -m multiline dev status")));
    } elseif ($match[1] == "wifi-connect") {
        system(sprintf("nmcli device wifi connect %s %s",
            escapeshellarg($_REQUEST["ssid"]),
            (empty($_REQUEST["password"]) ? "" : " password ".escapeshellarg($_REQUEST["password"]))
        ));
    } elseif (preg_match('/^con-(up|down|delete)\/\?con=.*/', $match[1], $m)) {
        echo $m[1]." ".htmlentities($_GET['con']).PHP_EOL;
        system("nmcli con ".$m[1]." ".escapeshellarg($_GET['con']));
    } elseif (preg_match('/^dev-(connect|disconnect)\/\?dev=.*/', $match[1], $m)) {
        echo $m[1]." ".htmlentities($_GET['dev']).PHP_EOL;
        system("nmcli device ".$m[1]." ".escapeshellarg($_GET['dev']));
    }

    return true;
}

function url(){
    if (isset($_SERVER['HTTPS']) && $_SERVER["HTTPS"] != "off")
        return "https://" . $_SERVER['HTTP_HOST'];
    else return "http://" . $_SERVER['HTTP_HOST'];
}

function parse_nmcli_list($cmdoutput, $separator=false) {
    define("BUTTON", '<a class="button" href="#" onclick="callAction(this)" data-url="%s">%s</a>');
    define("BUTTON_WIFI", '<a class="button" href="#" onclick="wifiForm(this)" data-ssid="%s">%s</a>');

    $list = array();
    $raw = explode(PHP_EOL, $cmdoutput);
    foreach($raw as $k=>$line) {
        $split = explode(":", $line);
        if (count($split) != 2) continue;
        if ($separator == false) $separator = $split[0];
        if ($split[0] == $separator) {
            if (isset($tmp)) array_push($list, $tmp);
            $tmp = array($split[0] => trim($split[1]));
        } else {
            if($split[0] != "UUID") $tmp[$split[0]] = trim($split[1]);
        }
    }
    if (isset($tmp)) array_push($list, $tmp);
    foreach($list as $k=>$tmp) {
        // Pimp the array
        if (isset($tmp["SSID"]) && isset($tmp["SECURITY"])) { // Wlan station list
            $tmp["SSID"] = sprintf(BUTTON_WIFI, htmlentities($tmp["SSID"]), htmlentities($tmp["SSID"]));
        }
        if (isset($tmp["DEVICE"]) && isset($tmp["STATE"])) { // device list
            if ($tmp["STATE"] == "connected")    $tmp["STATE"] = sprintf(BUTTON, "/exec/dev-disconnect/?dev=".urlencode($tmp["DEVICE"]), htmlentities($tmp["STATE"]));
            if ($tmp["STATE"] == "disconnected") $tmp["STATE"] = sprintf(BUTTON, "/exec/dev-connect/?dev=".urlencode($tmp["DEVICE"]), htmlentities($tmp["STATE"]));
        }
        if (isset($tmp["NAME"]) && isset($tmp["DEVICE"]) && isset($tmp["TYPE"])) { // connections
            if ($tmp["TYPE"] == "wifi") $tmp["FUN"] = sprintf(BUTTON, "/exec/con-delete/?con=".urlencode($tmp["NAME"]), "delete");
            else $tmp["FUN"] = "";

            if ($tmp["DEVICE"] != "--") $tmp[$separator] = sprintf(BUTTON, "/exec/con-down/?con=".urlencode($tmp["NAME"]), htmlentities($tmp["NAME"]));
            else $tmp[$separator] = sprintf(BUTTON, "/exec/con-up/?con=".urlencode($tmp["NAME"]), htmlentities($tmp["NAME"]));
        }
        $list[$k] = $tmp;
    }
    return $list; 
}
function list2html($list) {
    if (count($list) == 0) return "empty list";
    $tab = "<table>";
    $tab.= "<thead><tr><th>".implode('</th><th>', array_keys(current($list)))."</th></tr></thead>";
    $tab.= "<tbody>";
    foreach ($list as $row) {
        array_map('htmlentities', $row);
        $tab.= "<tr><td>".implode('</td><td>', $row)."</td></tr>";
    }
    $tab.= "</tbody></table>";
    return $tab;
}

function html($var, $content, $encode=false) {
    global $html;
    $html = preg_replace("/({{".$var."}})/", ($encode ? htmlentities($content) : $content), $html);
}

?>
<!DOCTYPE html>
<html>
 <head>
  <base href="<?php url(); ?>">
  <script src="jquery-3.6.0.min.js" type="text/javascript"></script>
  <link rel="stylesheet" type="text/css" href="milligram.min.css">
 </head>
 <body>
  <p>Hotspot enabled! (<?php echo $_SERVER['REQUEST_URI']; ?>)</p>
  <p>
   <a class="button" href="/">Show Hotspot status</a>
   <a class="button" href="http://192.168.8.1/html/content.html" target="_blank">LTE Modem status</a>
   <pre id="action-output" style="display: none;"></pre>
   <form id="wifipass" style="display: none;">
    <label for="wifissid">Wlan Name (SSID):</label>
    <input id="wifissid" type="text" name="ssid" value="">
    <label for="wifipass">Wlan Password:</label>
    <input id="wifipass" type="text" name="password" value="">
    <input id="wifisend" type="submit" name="submit" value="connect">
   </form>
   <pre id="load-conshow">Loading connection list ...</pre>
   <pre id="load-devstatus">Loading device status list ...</pre>
   <pre id="load-wifilist">Loading available WIFI Network list ...</pre>
   <pre id="load-iproute">Getting ip route information ...</pre>
   <pre id="load-resolv">Getting resolv.conf ...</pre>
   <pre id="load-ping">Ping probing ...</pre>
   <a id="reboot" class="button" href="#">Reboot the hotspot</a>
   <a id="poweroff" class="button" href="#">Power down the hotspot</a>
  </p>
  <script>
  $("#load-iproute").load("/exec/iproute");
  $("#load-resolv").load("/exec/resolv");
  $("#load-conshow").load("/exec/conshow");
  $("#load-devstatus").load("/exec/devstatus");
  $("#load-wifilist").load("/exec/wifilist");
  $("#load-ping").load("/exec/ping");
  $("#reboot").click(function() { $("#action-output").show().text("executing reboot").load("/exec/reboot"); });
  $("#poweroff").click(function() { $("#action-output").show().text("executing poweroff").load("/exec/poweroff"); });
  function callAction(b) {
    $("#action-output")
        .show().text("executing " + $(b).data("url") + ", please wait")
        .load($(b).data("url")); 
    $("#load-conshow").load("/exec/conshow");
    $("#load-devstatus").load("/exec/devstatus");
  };
  function wifiForm(b) {
    $("#wifissid").val($(b).data("ssid"));
    $("#wifipass")
        .show()
        .submit(function(event) {
            event.preventDefault();
            $("#action-output").show()
                .text("adding wifi " + $("#wifissid").val() + " with password " + $("#wifipass").val());
            $("#wifipass").hide();
            $.post("/exec/wifi-connect", $("#wifipass").serialize(), function(data) {
                $("#action-output").html(data);
                $("#load-conshow").load("/exec/conshow");
                $("#load-devstatus").load("/exec/devstatus");
            });
        });
  }
  </script>
 </body>
</html>
