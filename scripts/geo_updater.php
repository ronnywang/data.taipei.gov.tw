<?php

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
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            $content = curl_exec($curl);
            if (strtolower($encoding) == 'big5') {
                $content = str_replace('\\', '', iconv('Big5', 'UTF-8', $content));
            }
            if (!$ret = json_decode($content) or $ret->error) {
                file_put_contents('error', $content);
                throw new Exception("取得 {$file_url} shp {$shpfile->file} 失敗: " . $ret->message);
            }
            file_put_contents($target_dir . '/' . substr($shpfile->file, 0, -4) . '.json', json_encode($ret));
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
