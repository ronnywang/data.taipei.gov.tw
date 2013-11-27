<?php

include(__DIR__ . '/Big52003.php');
ini_set('memory_limit', '2048m');

class Updater
{
    public function getInfoFromURL($url)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_URL, $url);
        $content = curl_exec($curl);
        curl_close($curl);

        $ret = new StdClass;
        if (!preg_match('#檔案下載：<a onclick="SaveDownloadLog\(\)" target="_blank" class="link" href="([^"]*)">([^<]*)</a></br>#', $content, $matches)) {
            throw new Exception("找不到 {$url} 的檔案下載連結");
        }
        $ret->link = $matches[1];

        if (!preg_match('#最後更新時間：([^<]*)#', $content, $matches)) {
            throw new Exception("找不到 {$url} 的最後更新時間");
        }
        $ret->updated_at = $matches[1];

        return $ret;
    }

    public function downloadFromURL($file_url, $folder, $source_srs, $encoding)
    {
        $url = 'http://shp2json.ronny.tw/api/downloadurl?url=' . urlencode($file_url);
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $ret = curl_exec($curl);
        if (!$ret = json_decode($ret) or $ret->error) {
            throw new Exception("下載 {$file_url} 失敗: " . $ret->message);
        }

        $url = $ret->getshp_api;
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $ret = curl_exec($curl);
        if (!$ret = json_decode($ret) or $ret->error) {
            throw new Exception("取得 {$file_url} shp 列表失敗: " . $ret->message);
        }

        $target_dir = __DIR__ . '/../geo/' . $folder;
        if (!file_exists($target_dir)) {
            mkdir($target_dir);
        }
        foreach ($ret->data as $shpfile) {
            $url = $shpfile->geojson_api . '&source_srs=' . urlencode($source_srs);
            error_log($url);
            $curl = curl_init($url);
            $download_fp = tmpfile();
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_FILE, $download_fp);
            curl_exec($curl);
            curl_close($curl);
            fflush($download_fp);

            $file_name = preg_replace_callback('/#U([0-9a-f]*)/', function($e){
                return mb_convert_encoding('&#' . hexdec($e[1]) . ';','UTF-8', 'HTML-ENTITIES');
            }, substr($shpfile->file, 0, -4));
            $target_file = $target_dir . '/' . $file_name . '.json';
            mkdir(dirname($target_file), 0777, true);

            if (strtolower($encoding) == 'big5') {
                $cmd = ("sed -i " . escapeshellarg('s/\\\\\\\\/\\\\/') . ' ' . escapeshellarg(stream_get_meta_data($download_fp)['uri']));
                exec($cmd);
                exec("piconv -f Big5 < " . escapeshellarg(stream_get_meta_data($download_fp)['uri']) . ' > ' . escapeshellarg($target_file));
            } else {
                rename(stream_get_meta_data($download_fp)['uri'], $target_file);
            }

            $cmd = "node " . escapeshellarg(__DIR__ . '/geojson_parse.js') . " get_type " . escapeshellarg($target_file);
            exec($cmd, $outputs, $ret);
            if ($ret) {
                throw new Exception("取得 {$file_url} JSON 格式錯誤: " . $ret->message);
            }
        }
    }

    public function main($argv)
    {
        $fp = fopen(__DIR__ . '/../geo.csv', 'r');
        $fp_new = fopen(__DIR__ . '/../geo_new.csv', 'w');
        $columns = fgetcsv($fp);
        fputcsv($fp_new, $columns);

        while ($row = fgetcsv($fp)) {
            list($folder, $name, $url, $srs, $origin_encoding, $updated_at) = $row;

            $info = $this->getInfoFromURL($url);
            if ($updated_at != $info->updated_at) {
                $this->downloadFromURL($info->link, $folder, $srs, $origin_encoding);
                $updated_at = $info->updated_at;
            }

            fputcsv($fp_new, array($folder, $name, $url, $srs, $origin_encoding, $updated_at));
        }
        fclose($fp_new);
        rename(__DIR__ . '/../geo_new.csv', __DIR__ . '/../geo.csv');
    }
}

$u = new Updater;
$u->main($_SERVER['argv']);
