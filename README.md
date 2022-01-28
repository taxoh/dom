# dom

HTML DOM parser / editor / beautifier / autocloser

Простой и быстрый HTML DOM парсер/редактор.
Совместим с PHP 7.3
	
Содержит класс DOM-узла (html), а также удобные функции:
	
		url_abs() - абсолютизация URL
		url_replace() - замена вашим колбеком голых доменов и URL в тексте, находящихся вне href-атрибутов
		cu_download() - скачивание страниц с приемом заголовка Content-Type
	
	Пример использования:
	
		$p = new html();
		$p->inner(file_get_contents('/tmp/somefile.html'));
		$p->iterate(function($node, &$c){
			if ($node->tag=='a')
			{echo $node->inner().'<br>';}
		});
		
	Описание:
	
		- умеет делать выборку по CSS3-селектору + возможности jQuery
		- есть полезные функции для поиска повторяющихся участков html-кода: forks(), search(), итд
		- "узел" - это объект типа html (или его наследник)
		- "документ" и "корневой узел" это одно и то же
		- корневой узел всегда один
		- у тегов типов '#text' и '#comment' не бывает дочерних узлов
		- узлы остальных типов не хранят какую-либо текстовую информацию
		- дерево можно редактировать в любой момент: модифицировать или переносить узлы
		- закрывающиеся теги, которые не были открыты (т.е. лишние закрывашки) парсер считает текстовыми узлами
		- если закрывашка пуста, это еще не значит что это тег незакрывающегося типа
		- html-entities не декодируются кроме как в атрибутах
		- умеет коррекно парсить XML и PHP файлы:
			- корректно воспринимает XML-прологи в любом участке документа (в т.ч. php-блоки кода), помечает их как комментарии (тип тега - #comment). Для пролога открывашкой служит "<" + "?", а закрывашкой "?" + ">".
			- XML-документы трактует как HTML-документы, в некоторых случаях (связанных с XML-особенностями) это может влиять на корректность разбора
		- блоки CDATA не замечает, читает их как и остальной (обычный) текст
		- "открепленный узел" свое состояние не меняет, с ним можно продолжать работу, но стоит понимать, что он помнит своего (прежнего) родителя и находится в невалидном состоянии (значение parent у него невалидно). Сделать его валидным снова можно передав его в качестве параметра любой из функций:
			- append()
			- prepend()
			- replace()
			- replace_inner()
		- поддерживается и корректно обрабатывается операция clone
		- поддерживает красивый var_dump() для узлов (без гигантских листингов, удобно)
