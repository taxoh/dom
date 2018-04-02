# dom

HTML DOM parser / editor / beautifier / autocloser

Простой и быстрый HTML DOM парсер/редактор.
	
	Пример использования:
	
		$p = new html();
		$p->inner(file_get_contents('/tmp/somefile.html'));
		$p->iterate(function($node, $level){
			if ($node->tag=='a')
			{echo $node->inner().'<br>';}
		});
	
	Описание:
	
		- "узел" - это объект типа html (или его наследник)
		- "документ" и "корневой узел" это одно и то же
		- корневой узел всегда один
		- у тегов типов '#text' и '#comment' не бывает дочерних узлов
		- узлы остальных типов не хранят какую-либо текстовую информацию
		- дерево можно редактировать в любой момент: модифицировать или переносить узлы
		- закрывающиеся теги, которые не были открыты (т.е. лишние закрывашки) парсер считает текстовыми узлами
		- если закрывашка пуста, это еще не значит что это тег незакрывающегося типа
		- html-entities не декодируются кроме как в атрибутах
		- может парсить XML файлы (но трактовать документ будет как HTML, в некоторых случаях это может влиять на корректность разбора)
		- класс html можно пронаследовать и что-то модифицировать или добавить свойства/методы
		- "открепленный узел" свое состояние не меняет, с ним можно продолжать работу, но стоит понимать, что он помнит своего (прежнего) родителя и находится в невалидном состоянии (значение parent у него невалидно). Сделать его валидным снова можно передав его в качестве параметра любой из функций:
			- append()
			- prepend()
			- replace()
			- replace_inner()
			
