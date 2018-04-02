<?php
/*	Фронтенд к "dom.php".
	Возможности: 
	
		- искать по CSS-селектору в документе
		- автозакрытие тегов
		- beautify
		- minify
		- умеет сохранять результат
		
*/

include 'dom/dom.php';

if ($_GET['api'])
{
	ob_start();
	
	if (preg_match('#^https?://#i', $_POST['url']))
	{$s = shell_exec('curl -A "Firefox" --silent '.escapeshellarg($_POST['url']));}
		else
	{$s = file_get_contents($_POST['url']);}
	$time = microtime(1);
	$x = new html();
	$x->inner($s);
	
	if ($_POST['mm_autoclose']) $x->autoclose();
	if ($_POST['mm_minify']) $x->minify();
	if ($_POST['mm_beautify']) $x->beautify();
	
	if (strlen($_POST['sel']))
	{$z = $x->css($_POST['sel']);}
		else
	{$z = [$x];}
	$time = sprintf('%.3f', microtime(1)-$time);
	
	if ($_POST['sav'])
	{
		$s = '';
		foreach ($z as $zz) $s .= $zz->outer()."\n";
		file_put_contents($_POST['sav'], $s);
	}
	
	foreach ($z as $zz)
	{echo htmlspecialchars($zz->outer()).'<br><br>';}
	
	$content = ob_get_clean();
	header('Content-Type: application/javascript');
	echo json_encode(compact('content', 'time'));
	die();
}

header('Content-Type: text/html;charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
<title>HTML Tools</title>
<script src="js/jquery-1.12.4.min.js"></script>
<script>
$(document).ready(function(){
	$("#sel").keypress(function(e) {
		if (e.which == 13) {
			$(".btn_css").click();
		}
	});
	$(".btn_css").click(function(){
		var a = {};
		$(".var").each(function(){
			var x = $(this).attr("id");
			var typ = $(this).attr("type");
			if ((typ=='radio') && !$(this).prop("checked")) return;
			a[x] = ((typ=='checkbox')?+$(this).prop("checked"):$(this).val());
		});
		$.post("?api=1", a, function(data){
			$("#time").html(data.time+' с.');
			if (a['mm_beautify']==1 || a['mm_minify']==1) data.content = data.content.replace(/\n/g, "<br>").replace(/\t/g, "&nbsp;&nbsp;&nbsp;&nbsp;");
			$('#res').html(data.content);
		}, "json");
	});
});
</script>
<style>
	table {border:1px dotted grey;}
	input[type=text] {width:800px;}
</style>
</head>
<body>
	<table width="100%">
		<tr>
			<td width=200>URL или локальный файл: </td>
			<td>
				<input class="var" id="url" value="test.html" type=text />
			</td>
		</tr>
		<tr>
			<td>CSS селектор (или пусто): </td>
			<td>
				<input class="var" id="sel" value=".indent p:lt(-2)" type=text />
			</td>
		</tr>
		<tr>
			<td>Сохранить (или пусто): </td>
			<td>
				<input class="var" id="sav" value="html_tools.txt" type=text placeholder="e.g. result.html" />
			</td>
		</tr>
		<tr>
			<td>Опции: </td>
			<td>
				<input type=checkbox class="var" id="mm_autoclose" />
				<label for=mm_autoclose>autoclose tags</label>
				<input type=radio class="var" id="mm_none" name=mm_action value="" checked />
				<label for=mm_none>(нет)</label>
				<input type=radio class="var" id="mm_minify" name=mm_action value="1" />
				<label for=mm_minify>minify</label>
				<input type=radio class="var" id="mm_beautify" name=mm_action value="1" />
				<label for=mm_beautify>beautify</label>
			</td>
		</tr>
		<tr>
			<td>Время обработки: </td>
			<td id="time"></td>
		</tr>
		<tr style="height:50px;">
			<td colspan=2>
				<input type=button class="btn_css" value="Запуск" />
			</td>
		</tr>
		<tr>
			<td id="res" colspan=2></td>
		</tr>
	</table>
</body>
</html>
