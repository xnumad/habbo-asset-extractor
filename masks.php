<?php

ini_set("display_errors", 1);
set_time_limit(0);

define('OUTPUT_DIRECTORY', dirname(__FILE__) . '/download/');
define('OFFICIAL_RES_URL', "https://www.habbo.com");
define('FLASH_CLIENT_URL', "http://habboo-a.akamaihd.net/gordon/PRODUCTION-201905292208-407791804/");

?>
<!DOCTYPE html>
<html>
<head>
    <style>
        body {
            background-color: #000;
            color: #fff;
            font-family: monospace;
            font-size: 12px;
        }

        ul {
            padding: 0;
            margin: 0;
            display: block;
        }

        ul li {
            display: block;
            height: 1em;
            line-height: 1em;
            margin: 0;
            position: relative;
        }

        ul li.remove-previous-line {
            margin-top: -1em;
        }

        ul li.ta {
            height: auto;
        }

        ul li.ta textarea {
            width: 100em;
            height: 10em;
        }

        ul li p {
            padding: 0;
            margin: 0;
            width: 100%;
            height: 1em;
        }

        ul li p span {
            color: #f88;
        }

        ul li p:last-child {
            background-color: #000;
        }
    </style>
    <script>
        var complete = false;
        var flushingData = function () {
            if (document.body) {
                window.scrollTo(0, document.body.scrollHeight);
                removePreviousLine();
            }

            if (complete) return;

            if (requestAnimationFrame)
                requestAnimationFrame(flushingData);
        };
        flushingData();

        var removePreviousLine = function () {
            var lines = document.getElementById('console-lines').children;
            for (var i = 0; i < lines.length; i++) {
                if (lines[i].nextSibling != null && lines[i].className == 'remove-previous-line' && lines[i].nextSibling.className == 'remove-previous-line') {
                    lines[i].remove();
                }
            }
        };
        window.onload = removePreviousLine;
    </script>
</head>
<body>
<ul id="console-lines">
    <?php
    ob_end_flush();
    ob_start('mb_output_handler');

    function consoleLog($message, $prevRemove = false)
    {
        $sp = '';
        if ($prevRemove) $sp = ' class="remove-previous-line"';
        $message = str_replace("\r", '</p><p>', $message);
        $message = str_replace(" ", '&nbsp;', $message);
        echo '<li' . $sp . '><p>' . $message . '</p></li>';
        ob_flush();
        flush();
    }

    function consoleLogBlank()
    {
        echo '<li class="blank"></li>';
        ob_flush();
        flush();
    }

    function consoleLogProgressBar($current, $size, $unit = "kb")
    {
        $length = (int)(($current / $size) * 100);
        $str    = sprintf("\r[%-100s] %3d%% (%2d/%2d%s)", str_repeat("=", $length) . ($length == 100 ? "" : ">"), $length, ($current / ($unit == "kb" ? 1024 : 1)), $size / ($unit == "kb" ? 1024 : 1), " " . $unit);
        consoleLog($str, true);
    }

    function checkDIR($path)
    {
        if (!is_dir($path)) mkdir($path, 0777);
    }

    function file_get_contents_with_console($filename)
    {
        consoleLog("Download: " . $filename);

        $ctx = stream_context_create();
        stream_context_set_params($ctx, array("notification" => "stream_notification_callback"));

        $data = @file_get_contents($filename, false, $ctx);

        if ($data !== false) {
            $size = strlen($data);
            consoleLogProgressBar($size, $size);
            consoleLogBlank();
            consoleLogBlank();
            return $data;
        }

        $err = error_get_last();
        consoleLog("<span>Error:</span> " . $err["message"]);
        consoleLogBlank();
        return false;
    }

    function stream_notification_callback($notification_code, $severity, $message, $message_code, $bytes_transferred, $bytes_max)
    {
        static $filesize = null;

        switch ($notification_code) {
            case STREAM_NOTIFY_RESOLVE:
            case STREAM_NOTIFY_COMPLETED:
            case STREAM_NOTIFY_AUTH_REQUIRED:
            case STREAM_NOTIFY_FAILURE:
            case STREAM_NOTIFY_AUTH_RESULT:
                break;

            case STREAM_NOTIFY_REDIRECTED:
                consoleLog("Being redirected to: " . $message);
                break;

            case STREAM_NOTIFY_CONNECT:
                consoleLog("Connected...");
                break;

            case STREAM_NOTIFY_FILE_SIZE_IS:
                $filesize = $bytes_max;
                consoleLog("Filesize: " . $filesize);
                break;

            case STREAM_NOTIFY_MIME_TYPE_IS:
                consoleLog("Mime-type: " . $message);
                break;

            case STREAM_NOTIFY_PROGRESS:
                if ($bytes_transferred > 0) {
                    if ($filesize == 0) {
                        $str = sprintf("\rUnknown filesize.. %2d kb done..", $bytes_transferred / 1024);
                        consoleLog($str, true);
                    } else {
                        consoleLogProgressBar($bytes_transferred, $filesize);
                    }
                }
                break;

        }
    }


    function DownloadAll($file_name_swf)
    {
        consoleLogBlank();
        $data      = file_get_contents_with_console($file_name_swf);
        $file_name = OUTPUT_DIRECTORY . 'HabboRoomContent.swf';
        file_put_contents($file_name, $data);
        ExtractFlash($file_name);
        unlink($file_name);
    }


    function ExtractFlash($flash_file)
    {
        consoleLog("Analyzing SWF: $flash_file");
        $matches    = [];
        $swf_output = shell_exec("swfextract $flash_file");
        preg_match("/PNGs: ID\(s\) (.+)/", $swf_output, $matches);
        $ranges = explode(",", $matches[1]);

        $new_ranges = [];
        $count      = 0;
        foreach ($ranges as $index => $value):
            if (strpos($value, '-') !== false):
                $explode = explode('-', $value);
                for ($i = $explode[0]; $i <= $explode[1];)
                    $new_ranges[$count++] = $i++;
            else:
                $new_ranges[$count++] = $value;
            endif;
        endforeach;

        $kaka = [];
        $hehe = shell_exec("swfdump -s $flash_file");

        consoleLog("Extracting SWF: $flash_file");
        foreach ($new_ranges as $index => $value):
            $name = sprintf("%04d", $value);
            preg_match("/exports $name as \"(.+)\"/", $hehe, $kaka);
            $real_value = $kaka[1];
            $real_value = str_replace('HabboRoomContent_', '', $real_value);
            consoleLog("File Name: $real_value");
            $file_name = OUTPUT_DIRECTORY . 'masks/' . $real_value . '.png';
            shell_exec("swfextract $flash_file -p $value -o \"$file_name\"");
        endforeach;
    }

    consoleLog("<b>Habbo SWF Room Textures resource dump Tool</b>");
    consoleLogBlank();
    consoleLog("OFFICIAL RESOURCE URL : " . OFFICIAL_RES_URL);
    consoleLog("FLASH CLIENT URL      : " . FLASH_CLIENT_URL);
    consoleLog("OUTPUT PATH           : " . OUTPUT_DIRECTORY);
    consoleLogBlank();

    DownloadAll(FLASH_CLIENT_URL . '/HabboRoomContent.swf');

    consoleLog("update complete.");

    ?>
</ul>
<script> complete = true;
    removePreviousLine(); </script>
</body>
</html>
