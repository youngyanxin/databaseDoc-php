<?php

/**
 * 生成mysql数据字典
 */

//数据库配置
$config = [
    'host'     => '127.0.0.1',
    'user'     => 'root',
    'password' => '',
];

function characet($data){
    if( !empty($data) ){
        $fileType = mb_detect_encoding($data , array('UTF-8','GBK','LATIN1','BIG5')) ;
        if( $fileType != 'UTF-8'){
            $data = mb_convert_encoding($data ,'utf-8' , $fileType);
        }
    }
    return $data;
}

function export_dict($dbname, $config) {
    $title = $dbname.' 数据字典';
    $dsn = 'mysql:dbname='.$dbname.';host='.$config['host'];
    //数据库连接
    try {
        $con = new PDO($dsn, $config['user'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    } catch (PDOException $e) {
        die('Connection failed: ' . $e->getMessage());
    }

    $con->query('SET NAMES utf8');
    $tables = $con->query('SHOW tables')->fetchAll(PDO::FETCH_COLUMN);

    //取得所有的表名
    foreach ($tables as $table) {
        $_tables[]['TABLE_NAME'] = $table;
    }

    //循环取得所有表的备注及表中列消息
    foreach ($_tables as $k => $v) {

        $sql = 'SELECT * FROM ';
        $sql .= 'INFORMATION_SCHEMA.TABLES ';
        $sql .= 'WHERE ';
        $sql .= "table_name = '{$v['TABLE_NAME']}' AND table_schema = '{$dbname}'";
        $tr = $con->query($sql)->fetch(PDO::FETCH_ASSOC);
        $_tables[$k]['TABLE_COMMENT'] = $tr['TABLE_COMMENT'];

        $sql = 'SELECT * FROM ';
        $sql .= 'INFORMATION_SCHEMA.COLUMNS ';
        $sql .= 'WHERE ';
        $sql .= "table_name = '{$v['TABLE_NAME']}' AND table_schema = '{$dbname}'";
        $fields = [];
        $field_result = $con->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        foreach ($field_result as $fr)
        {
            $fields[] = $fr;
        }
        $_tables[$k]['COLUMN'] = $fields;
    }
    unset($con);

    $mark = '';

    //循环所有表
    foreach ($_tables as $k => $v) {

        $mark .= '## '.$v['TABLE_NAME'].'  '. characet($v['TABLE_COMMENT']) .PHP_EOL;
        $mark .= ''.PHP_EOL;
        $mark .= '|  字段名  |  数据类型  |  默认值  |  允许非空  |  自动递增  |  备注  |'.PHP_EOL;
        $mark .= '| ------ | ------ | ------ | ------ | ------ | ------ |'.PHP_EOL;
        foreach ($v['COLUMN'] as $f) {
            $mark .= '| '.$f['COLUMN_NAME'].' | '.$f['COLUMN_TYPE'].' | '.$f['COLUMN_DEFAULT'].' | '.$f['IS_NULLABLE'].' | '.($f['EXTRA'] == 'auto_increment' ? '是' : '').' | '.(empty($f['COLUMN_COMMENT']) ? '-' : str_replace('|', '/', $f['COLUMN_COMMENT'])).' |'.PHP_EOL;
        }
        $mark .= ''.PHP_EOL;

    }

    //markdown输出
    $md_tplt = <<<EOT
# {$title}
>   本数据字典由PHP脚本自动导出,字典的备注来自数据库表及其字段的注释(`comment`).开发者在增改库表及其字段时,请在 `migration` 时写明注释,以备后来者查阅.

{$mark}
EOT;

    //html输出
    $marked_text = htmlentities($md_tplt);
    $html_tplt = <<<EOT
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>{$title} - Powered By Markdown Viewer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="stylesheet" type="text/css" href="http://s1.ystatic.cn/41345695beaa9b2e/css/github-markdown.css">
    <script src="http://s1.ystatic.cn/lib/marked/marked.js"></script>
    <script src="http://s1.ystatic.cn/lib/highlight.js/highlight.pack.js?v=9.6.0"></script>
    <link href="http://s1.ystatic.cn/lib/highlight.js/styles/github.css?v=9.6.0" rel="stylesheet">
</head>
<body>
<div class="markdown-body" id="content" style="margin:auto; width: 1024px;">

</div>
<div id="marked_text" style="display:none;">
{$marked_text}
</div>
<script>
var marked_text = document.getElementById('marked_text').innerText;
var renderer = new marked.Renderer();
renderer.table = function(header, body) {
    return '<table class="table table-bordered table-striped">\\n'
            + '<thead>\\n'
            + header
            + '</thead>\\n'
            + '<tbody>\\n'
            + body
            + '</tbody>\\n'
            + '</table>\\n';
};
marked.setOptions({
    renderer: renderer,
    gfm: true,
    tables: true,
    breaks: false,
    pedantic: false,
    sanitize: true,
    smartLists: true,
    smartypants: false,
    langPrefix: 'language-',
    //这里使用了highlight对代码进行高亮显示
    highlight: function (code) {
        return hljs.highlightAuto(code).value;
    }
});
document.getElementById('content').innerHTML = marked(marked_text);
  </script>
</body>
</html>
EOT;

    file_put_contents($dbname.'.md', $md_tplt);
    file_put_contents($dbname.'.html', $html_tplt);
}

$dbs = ['smart_museum'];
foreach ($dbs as $db) {
    export_dict($db, $config);
}
?>