<?php

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>臺北市開放資料整理</title>
<script src="//ajax.googleapis.com/ajax/libs/jquery/1.8.3/jquery.min.js"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/2.2.2/bootstrap.min.js"></script>
<link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/2.2.2/css/bootstrap.css">
</head>
<body>
<h1>地理相關資料</h1>
<?php
$fp = fopen(__DIR__ . '/../geo.csv', 'r');
$columns = fgetcsv($fp);
?>
<table class="table">
    <thead>
        <tr>
            <td>資料名稱</td>
            <td>原始網址</td>
            <td>GitHub預覽</td>
            <td>最後更新時間</td>
        </tr>
    </thead>
    <tbody>
    <?php while ($row = fgetcsv($fp)) { ?>
    <tr>
        <td><?= htmlspecialchars($row[1]) ?></td>
        <td><a href="<?= htmlspecialchars($row[2]) ?>">data.taipei.gov.tw</a></td>
        <td><a href="https://github.com/ronnywang/data.taipei.gov.tw/tree/master/geo/<?= urlencode($row[0]) ?>">GitHub</a></td>
        <td><?= htmlspecialchars($row[5]) ?></td>
    </tr>
    <?php } ?>
    </tbody>
</table>
</body>
</html>
<?php 
file_put_contents(__DIR__ . '/../index.html', ob_get_clean());
