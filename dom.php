<?php
/*	Простой и быстрый HTML DOM парсер/редактор.	
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
		- может парсить XML файлы (но трактовать документ будет как HTML, в некоторых случаях это может влиять на корректность разбора)
		- блоки CDATA не замечает, читает их как и остальной (обычный) текст
		- "открепленный узел" свое состояние не меняет, с ним можно продолжать работу, но стоит понимать, что он помнит своего (прежнего) родителя и находится в невалидном состоянии (значение parent у него невалидно). Сделать его валидным снова можно передав его в качестве параметра любой из функций:
			- append()
			- prepend()
			- replace()
			- replace_inner()
		- поддерживается и корректно обрабатывается операция clone
		- поддерживает красивый var_dump() для узлов (без гигантских листингов, удобно)
	
*/


// списки HTML-элементов, разделенные на группы.

// все элементы: cобраны все теги всех стандартов вплоть до HTML5 включительно, а также устаревшие и (почти все) нестандартные теги
define('HTML_ELEMENTS_ALL', ['a', 'abbr', 'acronym', 'address', 'applet', 'area', 'article', 'aside', 'audio', 'b', 'base', 'basefont', 'bdi', 'bdo', 'bgsound', 'big', 'blink', 'blockquote', 'body', 'br', 'button', 'canvas', 'caption', 'center', 'cite', 'code', 'col', 'colgroup', 'command', 'data', 'datalist', 'dd', 'del', 'details', 'dfn', 'dialog', 'dir', 'div', 'dl', 'dt', 'em', 'embed', 'fieldset', 'figcaption', 'figure', 'font', 'footer', 'form', 'frame', 'frameset', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'head', 'header', 'hgroup', 'hr', 'html', 'i', 'iframe', 'img', 'input', 'ins', 'isindex', 'kbd', 'keygen', 'label', 'legend', 'li', 'link', 'main', 'map', 'mark', 'marquee', 'menu', 'menuitem', 'meta', 'meter', 'nav', 'nobr', 'noembed', 'noframes', 'noscript', 'object', 'ol', 'optgroup', 'option', 'output', 'p', 'param', 'picture', 'plaintext', 'pre', 'progress', 'q', 'rp', 'rt', 'ruby', 's', 'samp', 'noindex', 'script', 'section', 'select', 'small', 'source', 'span', 'strike', 'strong', 'style', 'sub', 'summary', 'sup', 'table', 'tbody', 'td', 'textarea', 'tfoot', 'th', 'thead', 'time', 'title', 'tr', 'track', 'tt', 'u', 'ul', 'var', 'video', 'wbr', 'xmp', ]);
// блочные элементы
define('HTML_ELEMENTS_BLOCK', ['address', 'article', 'aside', 'blockquote', 'center', 'dd', 'details', 'dir', 'div', 'dl', 'dt', 'embed', 'fieldset', 'figcaption', 'figure', 'footer', 'form', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hgroup', 'hr', 'isindex', 'li', 'main', 'marquee', 'nav', 'ol', 'p', 'pre', 'rt', 'section', 'summary', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr', 'ul', 'xmp', ]);
// строчные элементы
define('HTML_ELEMENTS_SPAN', ['a', 'abbr', 'acronym', 'applet', 'audio', 'b', 'bdi', 'bdo', 'big', 'blink', 'br', 'button', 'canvas', 'cite', 'code', 'command', 'data', 'del', 'dfn', 'dialog', 'em', 'figcaption', 'figure', 'font', 'i', 'iframe', 'img', 'input', 'ins', 'kbd', 'label', 'mark', 'meter', 'nobr', 'object', 'output', 'picture', 'plaintext', 'pre', 'progress', 'q', 'rp', 'ruby', 's', 'samp', 'select', 'small', 'span', 'strike', 'strong', 'sub', 'sup', 'textarea', 'time', 'tt', 'u', 'var', 'video', ]);
// информационные и логические элементы, которые однозначно нельзя отнести к строчным, либо блочным (часто невидимые).
define('HTML_ELEMENTS_INFO', ['area', 'base', 'basefont', 'bgsound', 'body', 'caption', 'col', 'colgroup', 'datalist', 'frame', 'frameset', 'head', 'html', 'keygen', 'legend', 'link', 'map', 'menu', 'menuitem', 'meta', 'noembed', 'noframes', 'noscript', 'optgroup', 'option', 'param', 'script', 'source', 'style', 'title', 'track', 'wbr']);
// "выделители" (phrase tags): жирный, курсив и прочие косметические выделялки для текста
define('HTML_ELEMENTS_MARKS', ['abbr', 'acronym', 'address', 'b', 'big', 'cite', 'code', 'del', 'dfn', 'em', 'font', 'i', 'ins', 'kbd', 'mark', 's', 'samp', 'small', 'span', 'strike', 'strong', 'sub', 'sup', 'tt', 'u', 'q', 'var', ]);
// элементы, используемые внутри (полезного) контента. Без тега "span".
define('HTML_CONTENT_TAGS', ['a', 'abbr', 'acronym', 'audio', 'b', 'blockquote', 'br', 'cite', 'code', 'dd', 'del', 'dfn', 'dl', 'dt', 'em', 'embed', 'font', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'i', 'iframe', 'img', 'ins', 'kbd', 'li', 'mark', 'object', 'ol', 'p', 'param', 'picture', 'pre', 'q', 'rp', 'rt', 'ruby', 's', 'samp', 'small', 'source', 'strike', 'strong', 'sub', 'sup', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'time', 'tr', 'track', 'tt', 'u', 'ul', 'var', 'video', ]);
// элементы, которые всегда подразумевают перенос строки (т.е. не могут собой разделять слова одного предложения)
define('HTML_LINEBREAK_TAGS', ['audio', 'blockquote', 'br', 'cite', 'code', 'dd', 'dl', 'dt', 'embed', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'iframe', 'img', 'li', 'object', 'ol', 'p', 'picture', 'pre', 'ruby', 'table', 'td', 'th', 'ul', 'video', ]);
// элементы, разрешенные спецификацией внутри параграфа (<p>)
define('HTML_ELEMENTS_PHRASING', ['a', 'abbr', 'area', 'audio', 'b', 'bdi', 'bdo', 'br', 'button', 'canvas', 'cite', 'code', 'command', 'datalist', 'del', 'dfn', 'em', 'embed', 'i', 'iframe', 'img', 'input', 'ins', 'kbd', 'keygen', 'label', 'map', 'mark', 'math', 'meter', 'noscript', 'object', 'output', 'progress', 'q', 'ruby', 's', 'samp', 'script', 'select', 'small', 'span', 'strong', 'sub', 'sup', 'svg', 'textarea', 'time', 'u', 'var', 'video', 'wbr', ]);
// микроразметка: элементы, дающие конкретную классифицирующую информацию об определенных частях документа
define('HTML_ELEMENTS_MICRO', ['abbr', 'acronym', 'address', 'article', 'aside', 'button', 'cite', 'code', 'dd', 'dfn', 'dt', 'footer', 'header', 'main', 'meta', 'nav', 'time', 'q', ]);
// формы: элементы, связанные с веб-формами
define('HTML_ELEMENTS_FORMS', ['datalist', 'fieldset', 'form', 'input', 'button', 'label', 'legend', 'optgroup', 'option', 'select', 'textarea', 'keygen', ]);
// таблицы: элементы, связанные с таблицами
define('HTML_ELEMENTS_TABLES', ['table', 'caption', 'colgroup', 'col', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th', ]);
// картинки: элементы, связанные с изображениями
define('HTML_ELEMENTS_IMAGES', ['area', 'img', 'map', 'picture', 'canvas', 'figure', 'figcaption', ]);
// head: элементы, разрешенные к размещению внутри <head>
define('HTML_ELEMENTS_HEAD',  ['base', 'basefont', 'bgsound', 'link', 'meta', 'script', 'style', 'title', ]);
// устаревшие и нестандартные элементы (не поддерживаются в HTML5)
define('HTML_ELEMENTS_OBSOLETE', ['acronym', 'applet', 'basefont', 'bgsound', 'big', 'blink', 'center', 'command', 'data', 'dir', 'font', 'frame', 'frameset', 'hgroup', 'isindex', 'listing', 'marquee', 'nobr', 'noembed', 'noframes', 'plaintext', 'shadow', 'spacer', 'strike', 'tt', 'xmp', ]);
// элементы, добавленные в HTML5
define('HTML_ELEMENTS_HTML5', ['article', 'aside', 'bdi', 'details', 'dialog', 'figcaption', 'figure', 'footer', 'header', 'main', 'mark', 'menuitem', 'meter', 'nav', 'progress', 'rp', 'rt', 'ruby', 'section', 'summary', 'time', 'wbr', 'datalist', 'keygen', 'output', ]);
// !!Не трогать!! Теги, не имеющие закрывающих. В режиме XML этот список не учитывается.
define('HTML_ELEMENTS_VOID', ['!doctype', '?xml', 'area', 'base', 'basefont', 'bgsound', 'br', 'col', 'command', 'embed', 'frame', 'hr', 'img', 'input', 'isindex', 'keygen', 'link', 'meta', 'nextid', 'param', 'source', 'track', 'wbr', ]);
// !!Не трогать!! Элементы, которые нельзя располагать внутри элементов такого же типа. Т.е. DOM-парсеру предписывается при встрече такого элемента закрыть предыдущий элемент такого же типа. Проверено на последней версии blink + HTML5.
define('HTML_ELEMENTS_NON_NESTED', ['a', 'body', 'button', 'dd', 'dt', 'form', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'head', 'html', 'iframe', 'select', 'li', 'nobr', 'noembed', 'noframes', 'noindex', 'noscript', 'optgroup', 'option', 'p', 'script', 'style', 'textarea', 'title', 'xmp', ]);
// !!Не трогать!! Элементы, которые нельзя располагать внутри параграфа. Т.е. DOM-парсеру предписывается при встрече такого элемента закрыть открытый параграф. Проверено на последней версии blink + HTML5.
define('HTML_ELEMENTS_NON_PARAGRAPH', ['address', 'article', 'aside', 'blockquote', 'center', 'dd', 'details', 'dialog', 'dir', 'div', 'dl', 'dt', 'fieldset', 'figcaption', 'figure', 'footer', 'form', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hgroup', 'hr', 'li', 'main', 'menu', 'nav', 'ol', 'p', 'plaintext', 'pre', 'section', 'summary', 'table', 'ul', 'xmp', ]);
// !!Не трогать!! Элементы, которые нельзя располагать внутри заголовков (h1-h6). Т.е. DOM-парсеру предписывается при встрече такого элемента закрыть открытый заголовок. Проверено на последней версии blink + HTML5.
define('HTML_ELEMENTS_NON_HEADER', ['body', 'caption', 'col', 'colgroup', 'frame', 'frameset', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'head', 'html', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr', ]);
// !!Не трогать!! Теги, которые будучи открытыми не воспринимают других тегов, в том числе комментарии. В режиме XML этот список не учитывается.
define('HTML_ELEMENTS_SPECIAL', ['script', 'style']);


// класс HTML-узла
class html {
	
	// свойства закомментированы ради уменьшения расхода памяти.
	// тем не менее, они могут присутствовать, когда это необходимо. Здесь даны описания для них.
	
	// public $tag;				// тип тега (например, 'div'). Всегда в нижнем регистре. Текстовый узел - '#text', комментарий - '#comment', корневой - NULL.
	// public $tag_block;		// открывашка от тега, например '<a href="http://..." someattr="123">'. Для узлов типа '#text' и '#comment' в этом поле хранится сам текст (либо HTML-комментарий целиком). У корневого узла здесь NULL.
	// public $closer;			// закрывашка от тега, например '</a>'. Может быть пустым, когда закрывашка отсутствует (по тем или иным причинам). У корневого узла здесь NULL.
	// public $parent;			// ссылка на родительский узел. У корневого - NULL.
	// public $offset;			// числовое строковое смещение до тега в получившемся документе. Заполняется при вызове calc_offsets().
	public $children = [];		// массив вложенных узлов. Может быть пустым.
	
	public function __clone()
	{foreach ($this->children as &$v) $v = clone $v;}
	
	public function __debugInfo()
	{
		$v = get_object_vars($this);
		foreach ($v['children'] as $vv) $vv = $vv->__debugInfo();
		$x = $v['parent'];
		if ($x->tag===NULL) $v['parent'] = ($x?'(root)':NULL);
		else $v['parent'] = 'object('.get_class($v['parent']) .') / '.$x->tag;
		return $v;
	}
	
	/*	Найти узлы внутри текущего элемента, соответствующие заданному CSS-селектору.
		Поддерживает (почти) все возможности из спецификации CSS3. 
		
		Также имеются дополнительные нестандартные расширения CSS, а именно:
		(псевдоклассы применяются в отрыве от комбинаторов)
		
			:contains("любой текст") - регистронезависимая проверка наличия текста внутри тега (проверяется outerHTML)
			:notcontains("любой текст") - регистронезависимая проверка отсутствия текста внутри тега (проверяется outerHTML)
			:icontains("любой текст") - регистронезависимая проверка наличия текста внутри тега (проверяется innerHMTL)
			:inotcontains("любой текст") - регистронезависимая проверка отсутствия текста внутри тега (проверяется innerHMTL)
			:rcontains("#регулярка#") - проверка на совпадение с регуляркой (проверяется outerHTML)
			:rnotcontains("#регулярка#") - проверка на НЕсовпадение с регуляркой (проверяется outerHTML)
			:ricontains("#регулярка#") - проверка на совпадение с регуляркой (проверяется innerHMTL)
			:rinotcontains("#регулярка#") - проверка на НЕсовпадение с регуляркой (проверяется innerHMTL)
			:ritcontains("#регулярка#") - проверка на совпадение с регуляркой (проверяется innerText, т.е. используется strip_tags() от innerHTML)
			:ritnotcontains("#регулярка#") - проверка на НЕсовпадение с регуляркой (проверяется innerText, т.е. используется strip_tags() от innerHTML)
			:rtbcontains("#регулярка#") - проверка на совпадение с регуляркой (проверяется только открывашка от тега, исключая содержимое тега)
			:rtbnotcontains("#регулярка#") - проверка на НЕсовпадение с регуляркой (проверяется только открывашка от тега, исключая содержимое тега)
			:ritbcontains("#регулярка#") - проверка на совпадение с регуляркой (проверяется виртуальная суммарная строчка открывающих тегов элементов innerHTML)
			:ritbnotcontains("#регулярка#") - проверка на НЕсовпадение с регуляркой (проверяется виртуальная суммарная строчка открывающих тегов элементов innerHTML)
			:rptbcontains("#регулярка#") - проверка на совпадение с регуляркой (проверяется виртуальная суммарная строчка открывающих тегов родительских элементов, в порядке подъема вверх, исключая тег самого элемента)
			:rptbnotcontains("#регулярка#") - проверка на НЕсовпадение с регуляркой (проверяется виртуальная суммарная строчка открывающих тегов родительских элементов, в порядке подъема вверх, исключая тег самого элемента)
			:header - собрать все заголовки (h1, h2, h3, ...)
			:hidden - собирает элементы, имеющие в атрибуте "style" текст "display:none" либо "visibility:hidden" (ищет по регулярке, регистронезависимо)
			:first - взять первый элемент среди найденных
			:last - взять последний элемент среди найденных
			:odd - взять нечетные элементы среди найденных
			:even - взять четные элементы среди найденных
			:lt(4) - ("less") взять все элементы, индекс которых меньше заданного значения (индексы начинаются с 0, как и в jQuery). Т.е. возьмет первые 4 элемента с начала.
			:lt(-2) - ("less") взять все найденные элементы, индекс которых меньше заданного значения (индексы начинаются с 0, как и в jQuery). Т.е. возьмет все элементы кроме 2 последних.
			:gt(4) - ("greater") взять все элементы, индекс которых больше заданного значения (индексы начинаются с 0, как и в jQuery)
			:gt(-2) - ("greater") взять последний элемент среди найденных
			:eq(0) - взять конкретный элемент по индексу (индексы начинаются с 0, как и в jQuery)
			:eq(-2) - взять 2ой с конца
			:not-parent(.someclass) - аналог ":not", но в отличие от него проверяется не сам тег, а его родитель. Поддерживается ограниченно. Позволяет у выбранных элементов проверить условие, чтобы прямой родитель НЕ обладал определенным классом, либо id, либо именем тега. Нельзя использовать сложные селекторы, но можно перечислить селекторы через запятые. Пример:
				:not-parent(.myclass,#someid,anytag,.otherclass,.bar,#foo)
		
		А также:
			
			"!=" - (для атрибутов) возможность выборки атрибута "не равного" заданному значению
			[!...] - (для атрибутов) инверсия требования для атрибута. Т.е. срабатывать будет в прямо противоположных случаях.
			[!myattr] - (для атрибутов) как частный пример случая выше: требование отсутствия атрибута с именем "myattr".
			[!myattr~=flower] - (для атрибутов) еще один частный пример случая выше: атрибут "myattr" не должен содержать в значении фразу "flower".
			@text - (в качестве имени тега) выборка текстовых узлов
			@comment - (в качестве имени тега) выборка узлов-комментариев
			@attr_href - (в качестве имени тега, а также можно применять в связке с ".class" и "#id" суффиксами) собрать атрибуты с заданным именем. В данном случае это "href". Вставлять нужно раньше атрибутных требований (это которые в квадратных скобках). Внимание! Результат будет содержать открепленные текстовые узлы, представляющие из себя значения найденных атрибутов в декодированном виде (т.к. атрибуты сами по себе узлами не являются). Пример парсинга значения мета-тега:
			
				meta@attr_content[name=description]
		
		Также имеются псевдоклассы и псевдоэлементы, которые в принципе невозможно прочитать/изменить без js: в таких случаях поведение будет словно соответствующая часть селектора отсутствует. Селекторы читаются согласно стандарту, поэтому можно подавать взятые прямо со стилей HTML-страниц из сети. Чтение и выполнение хорошо оттестированы и прекрасно работают во всех возможных режимах.
		
		Есть некоторые ограничения:
		
			- имена атрибутов в css-выражении (в секции требований к атрибутам) могут состоять только из символов: [\w\-]
			- псевдокласс ":not" (из стандартной css-спецификации), а также псевдокласс ":not-parent" (наше расширение) поддерживаются ограниченно: можно только одиночное имя тега, либо одиночный класс, либо одиночный id, или же можно перечислить несколько селекторов такого типа через запятую. Примеры: 
		
				:not(span)
				:not(.fancy)
				:not(.crazy, .fancy)
				
			- при использовании регулярок некоторые символы внутри регулярки могут мешать разбору всего селектора (возникает PHP-ошибка). В таких случаях используйте HEX-нотацию для необходимых символов (\xAB например). Наиболее очевидным таким символом является ":" (двоеточие). Но в случае двоеточия достаточно перед ним добавить бэкслеш ("\:") и сработает как надо. HEX-нотация же пригодится для разного рода скобок (круглых, квадратных) и возможно каких-то еще символов.
			
		Параметры:
		
			$selector - селектор любой сложности
			$allow_extensions - включить обработку (наших) нестандартных расширений CSS
		
		Если передать неправильный селектор - функция выбросит исключение.
		Вернет массив узлов.
	*/
	public function css($selector, $allow_extensions = true)
	{
		$res = [];
		$s = trim($selector);
		// часть регулярки: отдельный тег с классом или ID, либо отдельный класс или ID, либо отдельный тег 
		// + опц. псевдоклассы во всех случаях, + опц. атрибут во всех случаях
		$nth = '\(\s*(?:[\d\-\+n]+|odd|even)\s*\)';
		if ($allow_extensions)
		{$ext = '|odd|even|hidden|header|first|last|(?:eq|lt|gt)\(\s*\-?\d+\s*\)|(?:contains|notcontains|icontains|inotcontains|rcontains|rnotcontains|ricontains|ritcontains|rinotcontains|ritnotcontains|rtbcontains|rtbnotcontains|ritbcontains|ritbnotcontains|rptbcontains|rptbnotcontains)\((?:"[^"]+"|\'[^\']+\'|[^\)]+)\)';}
		$gr = '(?P<tag>(?:@?[\w\-]*|\*)(?:[#\.@][\w\-]+)*)'.
			'(?P<attrs>(?:\[.*?\])*)'.
			'(?P<pseudo>(?:\s*(?:(?<!\\\\):(?:active|checked|disabled|empty|enabled|first-child|first-of-type|last-of-type|focus|hover|in-range|invalid|last-child|link|not(?:-parent)?\([\w\-#\.,\s]+\)|only-child|target|valid|visited|root|read-write|lang\([\w\-]+\)|read-only|optional|out-of-range|only-of-type'.$ext.'|nth-of-type'.$nth.'|nth-last-of-type'.$nth.'|nth-last-child'.$nth.'|nth-child'.$nth.')|(?<!\\\\)::(?:after|before|first-letter|first-line|selection)))*)';
		$off = 0;
		$buf = []; // текущая собираемая группа
		$list = []; // список групп, которые нужно найти
		if (!preg_match_all('~((?P<combinator>\s*(\+|\~|\>|,)\s*|\s+)|'.$gr.')~si', $s, $m, PREG_SET_ORDER))
		{throw new Exception('Invalid css selector!');}
		foreach ($m as $mm)
		{
			$off += strlen($mm[0]);
			foreach ($mm as $k=>$v)
			{if (is_int($k) || $v==='') unset($mm[$k]);}
			if (!$mm) continue;
			if (isset($mm['combinator']))
			{$mm['combinator'] = trim($mm['combinator']);}
			if (isset($mm['combinator']) && !$mm['combinator']) $mm['combinator'] = ' ';
			if ($mm['combinator']==',')
			{
				$list[] = $buf;
				$buf = [];
			}
				else
			{
				if (preg_match_all('#\[(?P<inv>!)?(?P<attr>[\w\-]+)(?:(?P<eq>=|\~=|\|=|\^=|!=|\$=|\*=)(?P<attr_v>.*?))?\]#si', $mm['attrs'], $m4, PREG_SET_ORDER))
				{
					foreach ($m4 as &$v)
					{
						if (isset($v['attr_v']))
						{$v['attr_v'] = preg_replace('#^(["\'])(.*)\1$#s', '\2', $v['attr_v']);}
					}
					unset($v);
					$mm['attrs'] = $m4;
				}
					else
				{$mm['attrs'] = [];}
				$buf[] = $mm;
			}
		}
		$list[] = $buf;
		$list = array_filter($list);
		if ($off < strlen($s)) throw new Exception('Invalid css selector!');
		foreach ($list as $group)
		{
			// $group - цепочка описателей, соединенных комбинаторами (цепочка также может состоять из одного элемента-описателя).
			// описатель - это тег, либо тег+класс, либо тег+ID, либо класс, либо ID, либо "звездочка".
			$prev_combinator = '';
			$last_found = [];
			foreach ($group as $xv)
			{
				$need = $found = $req = [];
				/*	$xv может содержать поля:
						'combinator' - когда это поле непустое, то остальные поля отсутствуют. Это комбинатор. Возможные значения: " ", ">", "+", "~"
						'tag' - когда пуст или отсутствует, то означает - "любой тег". Искомый тег (опционально в комбинации с множеством классов и/или ID). Также может быть @text, @attr_href, @comment (для текстовых узлов, узлов-комментариев, сбора атрибутов).
							Примеры: "*", "@text", "@comment", "@attr_href", "*.tag", "tag", "#some", ".class", "tag.class", "tag#some", "tag.class#some.other.shit"
						'texcom' - будет true, если ищется узел-коммент или текстовый узел
						'get_attr' - (массив) будет непуст, если ищутся непосредственно атрибуты
						'attrs' - список массивов требований к атрибутам, каждый вида:
							'attr' - имя атрибута, который будет проверен
							'eq' - присутствует, когда есть требование к значению атрибута. Возможные значения: "=", "!=", "~=", "|=", "^=", "$=", "*="
							'attr_v' - присутствует, когда есть требование к значению атрибута. Содержит требуемое значение (без кавычек по бокам).
							'inv' - отрицание условия. Если присутствует, то результат проверки (bool) должен быть инвертирован.
						'pseudo' - присутствует, когда у описателя имеются псевдоклассы и/или псевдоэлементы. Может иметь сразу несколько псевдоклассов, которые могут быть разделены пробелами (или не разделены). 
							Примеры: ":active", "::after", " :checked :enabled::after"
					Всегда присутствует как минимум одно поле из перечисленных.
				*/
				if ($xv['combinator'])
				{
					$prev_combinator = $xv['combinator'];
					continue;
				}
				$req['attrs'] = $xv['attrs'];
				if (preg_match_all('~[@#\.]?[\w\-]+~', $xv['tag'], $m2))
				{
					foreach ($m2[0] as $v)
					{
						switch ($v[0])
						{
							case '#':
								$req['id'] = substr($v, 1);
							break;
							case '.':
								$tn = substr($v, 1);
								$req['attrs'][] = ['attr'=>'class', 'eq'=>'~=', 'attr_v'=>$tn];
							break;
							case '@':
								if ($v=='@text' || $v=='@comment')
								{
									$v[0] = '#';
									$req['texcom'] = $v;
								}
								elseif (preg_match('#^@attr_([\w\-]+)$#', $v, $m3))
								{$req['get_attr'][] = $m3[1];}
							break;
							default:
								$req['tag'] = $v;
							break;
						}
					}
				}
				if ($xv['pseudo'])
				{
					foreach (preg_split('#(?<!\\\\)::?#', $xv['pseudo']) as $v)
					{$req['pseudos'][] = trim($v);}
					$req['pseudos'] = array_filter($req['pseudos']);
				}
				
				if (!$prev_combinator) $last_found = [$this];
				foreach ($last_found as $iter_c)
				{
					$allow_recurse = false;
					$i_node = $iter_c;
					switch ($prev_combinator)
					{
						case '':
						case ' ':
							$allow_recurse = true;
						break;
						case '>':
							// no action
						break;
						case '+':
						case '~':
							$i_node = $iter_c->parent;
						break;
					}
					
					$fnc = function($e)use($allow_recurse, &$found, $iter_c, &$req, &$need, $prev_combinator, $i_node){
						// пустой цикл - из него будем выскакивать когда не найдено
						do {
							if ($e->tag!==NULL)
							{
								if ($req['tag'] && $e->tag!=$req['tag']) continue;
								if (!$req['texcom'] && $e->tag[0]=='#') continue;
								if ($req['texcom'] && $e->tag!=$req['texcom']) continue;
								$a = $e->attrs();
								if ($req['id']!==NULL && $a['id']!==$req['id']) continue;
								$conti = false;
								foreach ($req['attrs'] as $va)
								{
									$def = false;
									$a_v = $a[$va['attr']];
									// не забываем, $conti - условие выхода! (т.е. когда элемент НЕ найден и/или не совпал)
									switch ($va['eq'])
									{
										case '=':
											$conti = ($va['attr_v'] !== $a_v);
										break;
										case '!=':
											$conti = ($va['attr_v'] === $a_v);
										break;
										case '~=':
											$conti = (!preg_match('#(^|\s)'.preg_quote($va['attr_v'], '#').'($|\s)#', $a_v));
										break;
										case '|=':
											$conti = (!preg_match('#^'.preg_quote($va['attr_v'], '#').'($|\s|\-)#', $a_v));
										break;
										case '^=':
											$conti = (substr($a_v, 0, strlen($va['attr_v'])) !== $va['attr_v']);
										break;
										case '$=':
											$conti = (substr($a_v, -strlen($va['attr_v'])) !== $va['attr_v']);
										break;
										case '*=':
											$conti = (strpos($a_v, $va['attr_v'])===FALSE);
										break;
										default:
											// когда 'eq' пуст
											$conti = ($a_v===NULL);
											$def = true;
										break;
									}
									if (($def || $allow_extensions) && $va['inv']) $conti = (!$conti);
									if ($conti) continue(2);
								}
								if (!$req['pseudos']) $req['pseudos'] = [];
								foreach ($req['pseudos'] as $p)
								{
									$z = explode('(', $p, 2);
									list($p, $ps) = $z;
									$p = strtolower($p);
									if ($ps)
									{
										$ps = trim(rtrim($ps, ')'));
										$ps = preg_replace('#^(["\'])(.*)\1$#s', '\2', $ps);
									}
									// напоминаю, что $conti2 - это условие выхода из цикла, т.е. это инверсия "найденности"
									$conti2 = false;
									switch ($p)
									{
										case 'first-child':
										case 'last-child':
											$z = $e->parent->children;
											if ($p=='last-child') $z = array_reverse($z);
											foreach ($z as $cc)
											{
												if ($cc->tag[0]!='#') 
												{
													if ($cc === $e)
													{break(2);}
														else
													{
														$conti2 = true;
														break;
													}
												}
											}
										break;
										case 'checked':
											$conti2 = (!
												(
													($e->tag=='input' && isset($a['checked'])) ||
													($e->tag=='option' && isset($a['selected']))
												)
											);
										break;
										case 'root':
											$conti2 = ($e->parent->tag!==NULL);
										break;
										case 'required':
											$conti2 = (!isset($a['required']));
										break;
										case 'optional':
											$conti2 = isset($a['required']);
										break;
										case 'read-only':
											$conti2 = (!isset($a['readonly']));
										break;
										case 'read-write':
											$conti2 = isset($a['readonly']);
										break;
										case 'contains':
											$conti2 = (mb_stripos($e->outer(), $ps)===FALSE);
										break;
										case 'notcontains':
											$conti2 = (mb_stripos($e->outer(), $ps)!==FALSE);
										break;
										case 'icontains':
											$conti2 = (mb_stripos($e->inner(), $ps)===FALSE);
										break;
										case 'inotcontains':
											$conti2 = (mb_stripos($e->inner(), $ps)!==FALSE);
										break;
										case 'rcontains':
											$conti2 = !preg_match($ps, $e->outer());
										break;
										case 'rnotcontains':
											$conti2 = (bool)preg_match($ps, $e->outer());
										break;
										case 'ricontains':
											$conti2 = !preg_match($ps, $e->inner());
										break;
										case 'rinotcontains':
											$conti2 = (bool)preg_match($ps, $e->inner());
										break;
										case 'ritcontains':
											$qwe = [];
											html::render_texts($e->children, $qwe);
											$conti2 = !preg_match($ps, implode($qwe));
											unset($qwe);
										break;
										case 'ritnotcontains':
											$qwe = [];
											html::render_texts($e->children, $qwe);
											$conti2 = (bool)preg_match($ps, implode($qwe));
											unset($qwe);
										break;
										case 'rtbcontains':
											$conti2 = !(!in_array($e->tag, ['#text', '#comment']) && preg_match($ps, $e->tag_block));
										break;
										case 'rtbnotcontains':
											$conti2 = (!in_array($e->tag, ['#text', '#comment']) && preg_match($ps, $e->tag_block));
										break;
										case 'ritbcontains':
											$qwe = [];
											html::render_tag_blocks($e->children, $qwe);
											$qwe = implode($qwe);
											$conti2 = !preg_match($ps, $qwe);
											unset($qwe);
										break;
										case 'ritbnotcontains':
											$qwe = [];
											html::render_tag_blocks($e->children, $qwe);
											$qwe = implode($qwe);
											$conti2 = preg_match($ps, $qwe);
											unset($qwe);
										break;
										case 'rptbcontains':
											$conti2 = !preg_match($ps, implode($e->parent_tag_blocks()));
										break;
										case 'rptbnotcontains':
											$conti2 = (bool)preg_match($ps, implode($e->parent_tag_blocks()));
										break;
										case 'first': $need['first'] = true; break;
										case 'last': $need['last'] = true; break;
										case 'odd': $need['odd'] = true; break;
										case 'even': $need['even'] = true; break;
										case 'eq':
										case 'lt':
										case 'gt':
											$need[$p] = (int)trim($ps);
										break;
										case 'header':
											$conti2 = (!in_array($e->tag, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6']));
										break;
										case 'hidden':
											$conti2 = (!preg_match('#display\s*:\s*none|visibility\s*:\s*hidden#i', $a['style']));
										break;
										case 'enabled':
											$conti2 = isset($a['disabled']);
										break;
										case 'disabled':
											$conti2 = (!isset($a['disabled']));
										break;
										case 'empty':
											$conti2 = (bool)$e->children;
										break;
										case 'first-of-type':
										case 'last-of-type':
											$z = $e->parent->children;
											if ($p=='last-of-type') $z = array_reverse($z);
											foreach ($z as $cc)
											{
												if ($cc->tag[0]=='#' || $cc->tag != $e->tag) continue;
												$conti2 = ($cc!==$e);
												break;
											}
										break;
										case 'only-of-type':
											foreach ($e->parent->children as $cc)
											{
												if ($cc!==$e && $cc->tag == $e->tag)
												{
													$conti2 = true;
													break;
												}
											}
										break;
										case 'only-child':
											$conti2 = (count($e->parent->children) > 1);
										break;
										case 'nth-child':
										case 'nth-last-child':
										case 'nth-of-type':
										case 'nth-last-of-type':
											$calc = [];
											$ps = strtolower($ps);
											$z = $e->parent->children;
											if (in_array($p, ['nth-last-of-type', 'nth-last-child']))
											{$z = array_reverse($z);}
											if (is_numeric($ps))
											{
												$ps2 = floor($ps);
												if ($ps2 >= 1) $calc[$ps2] = true;
											}
												else
											{
												if ($ps=='even') {$ps = '2n';}
												elseif ($ps=='odd') {$ps = '2n+1';}
												$ps2 = preg_replace('#\s+#', '', $ps);
												if (!preg_match('#^(?P<mul>[\+\-\d\.]*n)(?P<plus>[\+\-]*[\d\.]+)?$#', $ps2, $m2))
												{preg_match('#^(?P<plus>[\+\-]*[\d\.]+)$#', $ps2, $m2);}
												$ml = &$m2['mul'];
												if ($ml=='')
												{$ml = 0;}
													else
												{
													$ml = str_ireplace('n', '', $ml);
													switch ($ml)
													{
														case '':
														case '+': 
															$ml = 1;
														break;
														case '-': $ml = -1; break;
													}
												}
												foreach (range(0, count($z)) as $q)
												{
													$ps3 = floor($ml*$q + $m2['plus']);
													if ($ps3 >= 1) $calc[$ps3] = true;
												}
												unset($ml);
											}
											$of_type = in_array($p, ['nth-of-type', 'nth-last-of-type']);
											$nn = 1;
											$e_found = false;
											foreach ($z as $cc)
											{
												if ($cc->tag[0]=='#') continue;
												if ($cc===$e)
												{
													$e_found = $calc[$nn];
													break;
												}
												if (!$of_type || $cc->tag == $e->tag)
												{$nn++;}
											}
											// не нашли
											$conti2 = (!$e_found);
										break;
										case 'lang':
											$ps = strtolower($ps);
											$conti2 = (strtolower(substr($a['lang'], 0, strlen($ps))) != $ps);
										break;
										case 'not':
										case 'not-parent':
											if ($p=='not-parent')
											{
												$e2 = $e->parent;
												$a2 = ($e2?$e2->attrs():[]);
												$e2 = ($e2?$e2->tag:'-');
											}
												else
											{
												$e2 = $e->tag;
												$a2 = $a;
											}
											unset($e_classes);
											$ps = explode(',', $ps);
											foreach ($ps as $v)
											{
												$v = trim($v);
												$tn = substr($v, 1);
												$conti2 = ($e2==$v || ($v[0]=='#' && $a2['id']==$tn));
												if (!$conti2 && $v[0]=='.')
												{
													if ($e_classes===NULL)
													{$e_classes = (isset($a2['class'])?preg_split('#\s+#', $a2['class']):[]);}
													$conti2 = in_array($tn, $e_classes);
												}
												if ($conti2) break;
											}
										break;
									}
									if ($conti2) continue(2);
								}
								
								// проверить условия, связанные через комбинатор с предыдущим уровнем
								$conti3 = false;
								switch ($prev_combinator)
								{
									case '>':
										$conti3 = ($e->parent!==$iter_c);
									break;
									case '+':
									case '~':
										$start = false;
										// по-умолчанию не нашли
										$conti3 = true;
										foreach ($iter_c->parent->children as $cc)
										{
											if ($cc===$iter_c)
											{$start = true;}
												else
											{
												if ($start && $cc===$e) 
												{
													// нашли!
													$conti3 = false;
													break;
												}
												if ($prev_combinator=='+' && $cc->tag[0]!='#') $start = false;
											}
										}
									break;
								}
								if ($conti3) continue;
								// нашли!
								if ($req['get_attr'])
								{
									foreach ($req['get_attr'] as $srch)
									{
										if (isset($a[$srch]))
										{$found[] = html::node($a[$srch]);}
									}
								}
									else
								{$found[] = $e;}
							}
						} while (false);
					};
					if ($allow_recurse)
					{$i_node->iterate($fnc);}
						else
					{
						// когда рекурсивный обход не требуется
						foreach ($i_node->children as $e) ($fnc)($e);
					}
				}
				foreach ($need as $k=>$v)
				{
					switch ($k)
					{
						case 'first':
							if ($v) $found = array_slice($found, 0, 1);
						break;
						case 'last':
							if ($v) $found = array_slice($found, -1);
						break;
						case 'odd':
							$n = 1;
							foreach ($found as $kk=>$vv)
							{if (!($n++ % 2)) unset($found[$kk]);}
						break;
						case 'even':
							$n = 1;
							foreach ($found as $kk=>$vv)
							{if ($n++ % 2) unset($found[$kk]);}
						break;
						case 'eq':
							if ($v!==NULL) {$found = array_slice($found, $v, 1);}
						break;
						case 'lt':
							$found = array_slice($found, 0, $v);
						break;
						case 'gt':
							$found = array_slice($found, $v+1);
						break;
					}
				}
				$last_found = $found;
			}
			foreach ($last_found as $e)
			{$res[] = $e;}
		}
		return $res;
	}
	
	/*	Добавить закрывающие теги тем тегам, которые их не имеют (но должны).
		Вернет кол-во добавленных закрывашек.
	*/
	public function autoclose()
	{
		$res = 0;
		$qkey = 0;
		$queue = array_values($this->children);
		while ($e = $queue[$qkey++])
		{
			if (!$e->closer && $e->tag[0]!='#' && !in_array($e->tag, HTML_ELEMENTS_VOID))
			{
				$e->closer = '</'.$e->tag.'>';
				$res++;
			}
			foreach ($e->children as $ee)
			{$queue[] = $ee;}
		}
		$this->invalidate();
		return $res;
	}
	
	/*	Убрать пробелы по бокам всем текстовым узлам.
	*/
	public function minify()
	{
		$qkey = 0;
		$queue = array_values($this->children);
		while ($e = $queue[$qkey++])
		{
			if ($e->tag==='#text')
			{
				$e->tag_block = trim($e->tag_block);
				if (!strlen($e->tag_block))
				{
					$e->remove();
					continue;
				}
			}
			foreach ($e->children as $ee)
			{$queue[] = $ee;}
		}
		$this->invalidate();
	}
	
	/*	Обновить узлы внутри текущего узла так, чтобы они были отравняны табуляциями и переносами строки.
		Это стандартизирует, а также позволяет человеку читать документ, в случаях когда он минифицирован или неудобен.
	*/
	public function beautify()
	{
		$this->minify();
		$qkey = 0;
		$this->children = array_values($this->children);
		$queue = $this->children;
		while ($e = $queue[$qkey++])
		{
			if ($e->tag[0]!='#' || strlen(trim($e->tag_block)))
			{
				$level = $level2 = count($e->parents())-1;
				if (end($e->parent->children)===$e) $level2--;
				$s = "\n".str_pad('', max(0, $level), "\t");
				$s2 = "\n".str_pad('', max(0, $level2), "\t");
				if (reset($e->parent->children)!==$e) $s = '';
				
				if (strlen($s))
				{$e->replace([html::node($s), $e]);}
				if (strlen($s2))
				{$e->replace([$e, html::node($s2)]);}
			}
			foreach ($e->children as $ee)
			{$queue[] = $ee;}
		}
		$this->invalidate();
	}
	
	// удалить специальные теги ('script', 'style', 'noscript', 'noframes', 'noembed') среди потомков текущего элемента.
	public function remove_specials()
	{
		$qkey = 0;
		$queue = [$this];
		while ($e = $queue[$qkey++])
		{
			if (in_array($e->tag, ['script', 'style', 'noscript', 'noframes', 'noembed', ]))
			{
				$e->remove();
				continue;
			}
			foreach ($e->children as $ee) $queue[] = $ee;
		}
	}
	
	// для текущего элемента (и всех элементов рекурсивно внутри) удалить атрибуты, отвечающие за HTML-события, т.е. имеющие в названии приставку "on*".
	public function remove_events()
	{
		$qkey = 0;
		$queue = [$this];
		while ($e = $queue[$qkey++])
		{
			if ($e->tag[0]!='#')
			{
				$a = $e->attrs();
				$a_keys = array_keys($a);
				$a_keys2 = preg_grep('#^on#', $a_keys, PREG_GREP_INVERT);
				if (count($a_keys)!=count($a_keys2))
				{
					$a = array_intersect_key($a, array_flip($a_keys2));
					$e->attrs($a);
				}
			}
			foreach ($e->children as $ee)
			{$queue[] = $ee;}
		}
	}
	
	/*	Подсчитать и заполнить параметр $offset у текущего узла и всех вложенных узлов.
			$start_offset - смещение, которое будет считаться нулевым (т.к. считается только с текущего узла и ниже).
	*/
	public function calc_offsets($start_offset = 0)
	{
		$offset = $start_offset;
		$res = '';
		$arr = ($this->parent?[$this]:$this->children);
		foreach ($arr as $elem)
		{$this->calc_offsets_recurs($elem, $offset, $res);}
	}
	
	/*	Обработать колбеком все суб-узлы внутри текущего узла (включая вложенные, исключая сам тег).
		Самый обычный и последовательный обход. Идем словно курсором, спускаясь на уровень вниз, когда это возможно.
		Внимание! Замечания по поводу работы с узлами *во время* обработки, т.е. изнутри колбека:
			Терминология: 
				- "базовый узел" - это тот узел, от которого была вызвана функция.
				- "текущий обрабатываемый узел" (или "текущий узел") - это то, что передается в колбек в виде переменной $node.
				- "состав" - это состав узлов либо порядок их следования
			Менять параметры любых узлов (исключая свойство children) всегда можно без предосторожностей, это безопасно. 
			Как работает функция: перед обработкой детей очередного узла все его дети копируются в отдельный массив (итератор), по которому идёт обход. 
			Поэтому! 
				Нельзя менять составы детей *любых* предков текущего обрабываемого узла ($node). 
				Нельзя менять состав *соседей* текущего обрабатываемого узла ($node). 
				В том числе нельзя это делать через replace(), pull_up() и подобные функции. 
				Но вы можете что угодно делать с потомками текущего обрабатываемого узла ($node->children или $node->children[...]...->children...) или с ним самим ($node).
			Если удаляете текущий обрабатываемый узел ($node->remove()), то обязательно установите $ctrl['skip'] в true!
			А если всё таки требуется изменение предков, то изменить их можно, но обработку нужно полностью отменить и начать заново, т.е.: 
				- либо колбек должен вернуть true
				- либо установите $ctrl['rewind_level'] в правильное значение. До того уровня, которого коснулись изменения. Например, если изменения коснулись состава детей базового узла ($this), то $ctrl['rewind_level'] нужно поставить в 0.
			Во любом случае нужно полностью понимать, что делаешь! Иначе результаты будут непредсказуемыми.
		
		Параметры:
		
			$callback - замыкание или имя функции. Имеет формат:
				function($node, &$ctrl){
					// ...
				}
				, где:
					$node - очередной узел
					$ctrl - массив контроля за обработкой. Ключи:
					
						'level' - (int) (только для чтения) уровень текущей вложенности. Целое число >= 0. Номер уровня считается начиная от базового узла.
						'skip' - (bool) если поставить в true, то обход потомков текущего обрабатываемого узла ($node) произведен не будет.
						'rewind_level' - (int) (cтрогая проверка на int!) уровень вложенности, до которого будет осуществлена перемотка (сброс) процесса обработки.
							Если занести сюда значение, равное значению $ctrl['level'], то обработка начнется с первого ребенка родителя текущего обрабатываемого узла (т.е., попросту говоря, с первого "брата").
							Если занести ($ctrl['level']-1), то обработка начнется с первого ребенка *родителя* *родителя* текущего обрабатываемого узла.
							и так далее...
							Если передать 0, то обработка начнется полностью заново.
							Значения больше $ctrl['level'], либо ниже 0 передавать бессмысленно.
							Если занести NULL - перемотка не будет осуществляться (так по-умолчанию).
							С 'rewind_level' будьте осторожны, т.к. возможен бесконечный цикл!
						'stack' - (массив) (только для чтения) стек родителей. По сути это список родителей текущего обрабатываемого узла, начиная от самого дальнего (!) родителя (т.е. от базового узла) и заканчивая самым ближним родителем. Для различных проверок стека удобны функции с префиксом stack_*, такие как html::stack_have().
			
			Если колбек вернет TRUE, то обход будет немедленно полностью прекращен. При этом возвращенное колбеком значение будет передано как результат вызова функции.
		
		Если важна скорость и не важен порядок обработки, то лучше делать обход полностью вручную.
		Пример ручной обработки:
		(внимание! порядок перебора узлов будет другой!)
		
			$qkey = 0;
			// здесь аккуратнее: если передать $node->children, то его узлы могут иметь номера не по порядку, а это чревато! 
			// (поэтому сделайте array_values())
			$queue = [$start_node];
			while ($e = $queue[$qkey++])
			{
				// обработка
				// ...
				// добавляем следующие узлы в очередь. Можно по желанию пропускать целые ветви
				foreach ($e->children as $ee) $queue[] = $ee;
			}
	*/
	public function iterate($callback)
	{
		$stack = [$this, ];
		$rewind = NULL;
		$this->iterate_recurs($stack, $rewind, $callback);
	}
	
	/*	Функция предназначена для вызова изнутри колбека iterate().
		Позволяет проверить, нет ли среди родителей текущего узла тегов заданного типа.
			$tags - массив имен тегов, например: ['strong', 'span', ]
			$c - массив контроля за обработкой
		Вернет узел (либо NULL, если не найдено).
		Возвращает наиболее дальнего родителя среди найденных.
	*/
	static public function stack_have($tags, $c)
	{
		foreach ($c['stack'] as $v)
		{if (in_array($v->tag, $tags)) return $v;}
	}
	
	/*	Получить список родителей текущего узла, начиная от самого близкого и вверх, исключая корневой.
			$self - добавить также в возвращаемый массив ссылку на себя.
	*/
	public function parents($self = false)
	{
		$res = [];
		if ($self && $this->tag!==NULL) $res[] = $this;
		$c = $this;
		while (($c = $c->parent) && ($c->tag!==NULL))
		{$res[] = $c;}
		return $res;
	}
	
	/*	Получить или установить атрибуты тега.
			$values - массив вида 'ключ'=>'значение'. Если не задан, то функция вернет текущий массив атрибутов тега.
		Атрибуты возвращаются в декодированном виде, имена атрибутов переводятся в нижний регистр.
	*/
	public function attrs($values = NULL)
	{
		// эта переменная общая на весь скрипт (работает вне конкретных экземпляров объекта)
		static $attrs_cache = [];
		if ($values===NULL)
		{
			if ($this->tag[0]=='#') return [];
			$res = [];
			if (($v = ($attrs_cache[$this->tag_block]))!==NULL)
			{return $v;}
			if (preg_match_all('#([\w\-]+)(?:\s*=\s*("[^"<>]*"|\'[^\'<>]*\'|[^\s<>=]*))?#si', preg_replace('#^<[^\s<>]+#', '', $this->tag_block), $m, PREG_SET_ORDER))
			{
				foreach ($m as $mm)
				{
					if ($mm[2]{0}=='"' || $mm[2]{0}=='\'')
					{$mm[2] = trim($mm[2], $mm[2]{0});}
					$a = strtolower($mm[1]);
					// согласно стандарту атрибуты, дублирующие уже существующие, должны игнорироваться.
					// > if there is already an attribute on the token with the exact same name, then this is a parse error and the new attribute must be dropped
					if (!isset($res[$a]))
					{
						// раскодировать *любые* HTML-entities в строке. В том числе: "<", ">", '"' и "'"
						$x = $mm[2];
						$x = html_entity_decode($x, ENT_HTML5 + ENT_QUOTES, 'utf-8');
						// возникают невидимые неразрывные пробелы ("nbsp" после декодирования)
						$x = preg_replace('#\x{a0}#u', ' ', $x);
						$res[$a] = $x;
					}
				}
				if (count($attrs_cache)>20000)
				{$attrs_cache = array_slice($attrs_cache, 10000, NULL, true);}
				$attrs_cache[$this->tag_block] = $res;
			}
			return $res;
		}
			else
		{
			if ($this->tag[0]=='#' || !preg_match('#^(<[^\s<>]+\s*).*?([\s/]*>)$#s', $this->tag_block, $m))
			{return;}
			foreach ($values as $k=>&$v) $v = $k.'="'.htmlspecialchars($v).'"';
			unset($v);
			$this->tag_block = ($values?$m[1]:rtrim($m[1])).(($values && !preg_match('#\s$#', $m[1]))?' ':'').implode(' ', $values).$m[2];
			$this->invalidate();
		}
	}
	
	/*	Очистить кеш тега.
		Автоматически вызывается классом при модификациях.
		Нужно вызывать это вручную когда меняете какие-либо базовые поля тега.
	*/
	public function invalidate()
	{
		$c = $this;
		do {unset($c->c_inner, $c->c_outer, $c->c_strip);}
		while ($c = $c->parent);
	}
	
	/*	Удалить текущий тег, заменив его его содержимым.
		(!) Текущий узел становится открепленным.
	*/
	public function pull_up()
	{$this->replace($this->children);}
	
	/*	Обернуть текущий тег в отдельный тег.
		Текущий тег сменит родителя.
			$tag_name - имя тега, например 'span'
		Внимание! Иизнутри iterate() всегда используйте установку rewind_level при вызове данной функции.
	*/
	public function encapsulate($tag_name)
	{
		$x = new html();
		$x->tag = $tag_name;
		$x->tag_block = '<'.$tag_name.'>';
		$x->closer = '</'.$tag_name.'>';
		$this->replace($x);
		$x->children = [$this, ];
		$this->parent = $x;
	}
	
	/*	Удалить текущий узел.
		В случае корневого узла это очистит его содержимое.
		Удаляет текущий элемент из списка детей у родителя текущего элемента.
		(!) Текущий узел становится открепленным.
	*/
	public function remove()
	{
		if ($this->parent)
		{
			if (($num = array_search($this, $this->parent->children, true))===false)
			{return;}
			unset($this->parent->children[$num]);
		}
			else
		{$this->children = [];}
		$this->invalidate();
	}
	
	/*	Добавить узел или список узлов в содержимое *текущего* тега (в начало).
		Параметры:
	
			$nodes - может быть узлом или массивом узлов. (!) Корневой узел нельзя передавать среди $nodes.
		
		Выполняются следующие операции:
			- меняет родителя всем узлам в $nodes
			- добавляет узлы из $nodes к детям текущего узла
	*/
	public function prepend($nodes)
	{
		if (!is_array($nodes)) $nodes = [$nodes];
		foreach ($this->children as $cc)
		{$nodes[] = $cc;}
		foreach ($nodes as $cc)
		{$cc->parent = $this;}
		$this->children = $nodes;
		$this->invalidate();
	}
	
	/*	Добавить узел или список узлов в содержимое *текущего* тега (в конец).
			$nodes - может быть узлом или массивом узлов. (!) Корневой узел нельзя передавать среди $nodes.
		Выполняются следующие операции:
			- меняет родителя всем узлам в $nodes
			- добавляет узлы из $nodes к детям текущего узла
	*/
	public function append($nodes)
	{
		if (!is_array($nodes)) $nodes = [$nodes];
		foreach ($nodes as $cc)
		{
			$this->children[] = $cc;
			$cc->parent = $this;
		}
		$this->invalidate();
	}
	
	/*	Поместить заданный узел перед текущим элементом, либо после него.
		Для корневых тегов вызов этой функции поместит узел в начало, либо в конец документа.
			$node - узел
			$before - (bool) добавить перед текущим узлом (true) или после него (false)
	*/
	public function insert($node, $before)
	{
		if ($p = $this->parent)
		{
			$ch = [];
			if ($before)
			{
				foreach ($p->children as $v)
				{
					if ($v===$this) $ch[] = $node;
					$ch[] = $v;
				}
			}
				else
			{
				foreach ($p->children as $v)
				{
					$ch[] = $v;
					if ($v===$this) $ch[] = $node;
				}
			}
			$p->children = $ch;
		}
			else
		{
			$p = $this;
			if ($before)
			{array_unshift($p->children, $node);}
				else
			{$p->children[] = $node;}
		}
		$node->parent = $p;
		$p->invalidate();
	}
	
	/*	Создать *текстовый* узел с заданным содержимым перед текущим элементом, либо после него.
		Для корневых тегов вызов этой функции добавит текстовый узел в начало, либо в конец документа.
			$text - текстовое содержимое для нового узла
			$before - (bool) добавить перед тегом (true) или после него (false)
		При желании в качестве $text можно передать любую HTML-разметку, но она останется текстовым узлом (!).
		Чтобы она стала активной документ нужно пересобрать заново:
			$x->insert_before('<p>some <strong>letters</strong></p>');
			$r = $x->root();
			$r->outer(html::render($r));
	*/
	public function insert_text($text, $before = true)
	{
		$func = function($text, $p){
			$x = new html();
			$x->tag = '#text';
			$x->tag_block = $text;
			$x->parent = $p;
			return $x;
		};
		if ($p = $this->parent)
		{
			$ch = [];
			if ($before)
			{
				foreach ($p->children as $v)
				{
					if ($v===$this) $ch[] = $func($text, $p);
					$ch[] = $v;
				}
			}
				else
			{
				foreach ($p->children as $v)
				{
					$ch[] = $v;
					if ($v===$this) $ch[] = $func($text, $p);
				}
			}
			$p->children = $ch;
		}
			else
		{
			$x = $func($text, $this);
			if ($before)
			{array_unshift($this->children, $x);}
				else
			{$this->children[] = $x;}
		}
	}
	
	/*	"Всплыть" узел пузырьком. 
		Текущий узел закроет все теги, внутри которых расположен и откроет их заново (через создание новых узлов), встав между двумя возникшими частями.
		Т.е. текущий узел будет среди детей корневого узла.
		Если узел уже находится среди детей корневого узла (либо является корневым), то никаких операций выполнено не будет.
		Если в результате деления на границах возникнут пустые теги, то они будут удалены.
	*/
	public function pop()
	{
		$this->invalidate();
		$prev = $this;
		$rem = $buf = [];
		foreach ($this->parents() as $v)
		{
			$k = array_search($prev, $v->children, true);
			$buf[] = [$v, array_slice($v->children, $k+1), ];
			$v->children = array_slice($v->children, 0, (($prev===$this)?$k:$k+1));
			if (!$v->children) $rem[] = $v;
			$prev = $v;
		}
		if ($buf)
		{
			list($base) = end($buf);
			$base->insert($this, false);
			$prev = NULL;
			foreach ($buf as $v)
			{
				list($n, $ch) = $v;
				$new = new html();
				$new->tag = $n->tag;
				$new->tag_block = $n->tag_block;
				$new->closer = $n->closer;
				if ($prev && $prev->children) $new->append($prev);
				$new->append($ch);
				$prev = $new;
			}
			if ($new->children)
			{$this->insert($new, false);}
		}
		foreach ($rem as $v) $v->remove();
	}
	
	/*	Очистить содержимое тега. Сам тег остается на месте.
		
		Выполняются следующие операции:
			- очищает список детей текущего узла
			
		(!) Дочерние узлы становятся открепленными.
	*/
	public function clear()
	{
		if ($this->tag[0]=='#')
		{$this->tag_block = '';}
			else
		{$this->children = [];}
		$this->invalidate();
	}
	
	/*	Заменить *текущий* узел другим узлом либо списком узлов.
		Параметры:
	
			$nodes - может быть узлом или массивом узлов. (!) Корневой узел нельзя передавать среди $nodes.
			
		Выполняются следующие операции:
			- меняет родителя всем узлам в $nodes
			- меняет список детей у родителя текущего элемента, заменяя в нем текущий на новые
			
		(!) Обратите внимание, что текущий узел может стать открепленным (если никуда больше не вставлен и не использован).
	*/
	public function replace($nodes)
	{
		if (!is_array($nodes)) $nodes = [$nodes];
		if ($this->parent)
		{
			$new = [];
			foreach ($this->parent->children as $v)
			{
				if ($v===$this)
				{
					foreach ($nodes as $cc)
					{
						$cc->parent = $this->parent;
						$new[] = $cc;
					}
				}
					else
				{$new[] = $v;}
			}
			$this->parent->children = $new;
		}
			else
		{
			$this->children = $nodes;
			foreach ($this->children as $cc)
			{$cc->parent = $this;}
		}
		$this->invalidate();
	}
	
	/*	Заменить содержимое текущего узла. Cам узел остается на месте.
		Параметры:
	
			$nodes - может быть узлом или массивом узлов. (!) Корневой узел нельзя передавать среди $nodes.
			
		Выполняются следующие операции:
			- меняет родителя всем узлам в $nodes
			- меняет список детей у текущего элемента, заменяя его на $nodes
	*/
	public function replace_inner($nodes)
	{
		if (!is_array($nodes)) $nodes = [$nodes];
		$this->children = $nodes;
		foreach ($nodes as $cc)
		{$cc->parent = $this;}
		$this->invalidate();
	}
	
	/*	Получить или установить innerHTML для текущего элемента.
			$html - HTML-код. Если не задан, то функция вернет текущий внутренний HTML.
		С кешированием.
	*/
	public function inner($html = NULL)
	{
		if ($html===NULL)
		{
			if (isset($this->c_inner))
			{return $this->c_inner;}
			// рекурсией выходит на 30-40% быстрее, чем если от нее избавиться
			if (!$this->parent || $this->tag[0]!='#')
			{$this->c_inner = html::render($this->children);}
				else
			{$this->c_inner = html::render($this);}
			return $this->c_inner;
		}
			else
		{$this->set($html, true);}
	}
	
	/*	Получить или установить outerHTML для текущего элемента.
			$html - HTML-код. Если не задан, то функция вернет текущий внешний HTML.
		С кешированием.
	*/
	public function outer($html = NULL)
	{
		if ($html===NULL)
		{
			if (isset($this->c_outer))
			{return $this->c_outer;}
			// рекурсией выходит на 30-40% быстрее, чем если от нее избавиться
			if (!$this->parent)
			{$this->c_outer = html::render($this->children);}
				else
			{$this->c_outer = html::render($this);}
			return $this->c_outer;
		}
			else
		{$this->set($html, false);}
	}
	
	/*	Получить outerHTML для любого массива узлов или конкретного узла.
		Узлы могут быть в т.ч. из разных документов.
			$nodes - массив узлов либо отдельный узел
		Возвращает строку.
		Функция статическая - может быть вызвана без создания класса.
	*/
	static public function render($nodes)
	{
		$res = [];
		if (is_object($nodes))
		{$res[] = $nodes->tag_block . html::render($nodes->children) . $nodes->closer;}
			else
		{
			// с рекурсией очень быстро работает, не нужно от нее избавляться
			foreach ($nodes as $elem)
			{$res[] = $elem->tag_block . html::render($elem->children) . $elem->closer;}
		}
		return implode($res);
	}
	
	/*	Получить только открывашки тегов внутри указанных узлов и добавить строки в массив $res.
		Текстовые узлы и комментарии исключаются.
	*/
	static protected function render_tag_blocks($nodes, &$res)
	{
		// с рекурсией очень быстро работает, не нужно от нее избавляться
		foreach ($nodes as $elem)
		{
			if (!in_array($elem->tag, ['#text', '#comment']))
			{
				$res[] = $elem->tag_block;
				html::render_tag_blocks($elem->children, $res);
			}
		}
	}
	
	
	/*	Получить только текстовые узлые внутри указанных узлов и добавить строки в массив $res.
		Комментарии исключаются.
	*/
	static protected function render_texts($nodes, &$res)
	{
		// с рекурсией очень быстро работает, не нужно от нее избавляться
		foreach ($nodes as $elem)
		{
			if ($elem->tag=='#text')
			{$res[] = $elem->tag_block;}
				else
			{html::render_texts($elem->children, $res);}
		}
	}
	
	/*	Получить только открывашки тегов родителей текущего узла. Вернет массив строк.
		Текстовые узлы и комментарии исключаются.
	*/
	static protected function parent_tag_blocks()
	{
		$res = [];
		$c = $this;
		while (($c = $c->parent) && ($c->tag!==NULL) && !in_array($c->tag, ['#text', '#comment', ]))
		{$res[] = $c->tag_block;}
		return $res;
	}
	
	/*	Упаковать список узлов в общий (корневой) тег.
			$nodes - массив узлов
		Вернет узел (т.е. новый документ).
	*/
	static public function pack($nodes)
	{
		$x = new html();
		$x->replace($nodes);
		return $x;
	}
	
	/*	Создать *текстовый* узел (либо узел-комментарий) на основе заданного текста и вернуть его.
		Он не будет корневым, так что его сразу можно использовать.
		(!) Внимание: возвращается только первый узел! Т.е. нельзя пихать разметку. Функция предназначена только для текстовых или одиночных узлов! (или html-комментариев, опять же - одиночных).
		Отлично подойдёт для того, чтобы создать узел для добавления некоего текстового маркера в дерево (и к примеру, дальнейшей его обработки регулярками).
		Вернет объект узла.
	*/
	static public function node($s)
	{
		$h = new html();
		$h->outer((string)$s);
		return $h->children[0];
	}
	
	/*	Составить CSS-селектор для поиска текущего DOM-узла.
			$ex_attrs - (массив) имена атрибутов, которые не будут использоваться для идентификации
		Поднимается вверх по родителям и использует имеющиеся в них атрибуты, исключая заданные ($ex_attrs).
	*/
	public function generate_selector($ex_attrs = ['id', 'class', ])
	{
		$css = [];
		$node = $this;
		$ex = array_flip($ex_attrs);
		do {
			$a = $node->attrs();
			if (!($c = $node->tag)) continue;
			if ($ex) $a = array_diff_key($a, $ex);
			foreach ($a as $k=>$v) $c .= '['.$k.']';
			$css[] = $c;
		} while ($node = $node->parent);
		$css = array_reverse($css);
		return implode(' > ', $css);
	}
	
	// с кешированием, содержимое без разметки, без пробелов по бокам
	public function strip()
	{
		if (isset($this->c_strip))
		{return $this->c_strip;}
		$res = [];
		$this->get_stripped_content($this, $res);
		return ($this->c_strip = trim(strip_tags(implode($res)))); // strip_tags() всё равно нужен
	}
	
	/*	Изменить тип тега.
		Внимание: тип НЕ может быть изменен на '#text' или '#comment', а также на "не-имеющий-закрывающего" тега тип. Также тип нельзя менять корневому узлу.
			$save_attrs - сохранить ли атрибуты от прежнего тега
	*/
	public function change($tag, $save_attrs = false)
	{
		$this->tag = $tag;
		$this->tag_block = ($save_attrs?preg_replace('#\w+#', $tag, $this->tag_block, 1):'<'.$tag.'>');
		$this->closer = '</'.$tag.'>';
		$this->invalidate();
	}
	
	// получить корневой узел
	public function root()
	{
		$c = $this;
		do {$c = $c->parent;}
		while ($c->tag!==NULL);
		return $c;
	}
	
	/*	Найти узлы, находящиеся между двумя узлами, при условии что оба узла имеют общего прямого родителя и расположены в прямом порядке.
		Вернет массив узлов. Если условие не соблюдено, либо узлов нет, то массив будет пустым.
	*/
	static public function between($node1, $node2)
	{
		$res = [];
		foreach ($node1->parent->children as $c)
		{
			if ($c===$node2) return $res;
			if ($start) $res[] = $c;
			if ($c===$node1) $start = true;
		}
		return [];
	}
	
	/*	Найти отличия между 2 узлами.
			$c1 - один узел
			$c2 - второй узел
			$res - (массив) куда поместить результат
		В массив $res будет добавлен список узлов (потомков $c1), которые явно отличаются от соответствующих потомков $c2.
		Как правило, это и есть основной контент страницы.
	*/
	static public function compare($c1, $c2, &$res)
	{
		$arr = [[], [], ];
		$ex = ['#comment', '#text'];
		foreach ([$c1, $c2] as $k=>$cc)
		{
			foreach ($cc->children as $v)
			{
				if (!in_array($v->tag, $ex))
				{$arr[$k][$v->strip()] = [$k, $v];}
			}
		}
		$arr3 = array_diff_key($arr[0], $arr[1]);
		$arr4 = array_diff_key($arr[1], $arr[0]);
		$arr5 = array_merge(array_values($arr3), array_values($arr4));
		$z = [[], [], ];
		foreach ($arr5 as $v2)
		{
			list($k, $v) = $v2;
			$z[$k][] = $v;
		}
		list($arr1, $arr2) = $z;
		if (count($arr1)!=count($arr2))
		{
			if ($arr1)
			{
				$x = reset($arr1);
				$res[] = $x->parent;
			}
			return;
		}
		// здесь $arr1 и $arr2 имеют одинаковый размер
		reset($arr2);
		foreach ($arr1 as $v1)
		{
			$v2 = current($arr2);
			next($arr2);
			html::compare($v1, $v2, $res);
		}
	}
	
	/*	Найти узел, который является (максимально глубоким) общим родителем для переданного списка узлов.
			$nodes - список узлов
		Вернет узел или NULL, если такого узла не существует.
	*/
	static public function common_parent($nodes)
	{
		$stolbiki = [];
		foreach ($nodes as $node)
		{
			$x = $node->parents();
			$stolbiki[] = array_reverse($x);
			$max = max($max, count($x));
		}
		if (!$stolbiki) return;
		for ($i=0;$i<$max;$i++)
		{
			foreach ($stolbiki as $k=>$stolbik)
			{
				$node = $stolbik[$i];
				if ($k)
				{
					if ($node!==$prev)
					{break(2);}
				}
				$prev = $node;
			}
			$last = $node;
		}
		return $last;
	}
	
	/*	Найти узел внутри текущего узла по его строковому смещению.
			$offset - смещение, относительно текущего узла
			$recalc - если true, то будет вызван calc_offsets() и смещения будут пересчитаны относительно текущего узла.
		Вернет узел или NULL, если не найден.
	*/
	public function search_by_offset($offset, $recalc = true)
	{
		if ($offset<0 || $offset>=strlen($this->outer()))
		{return;}
		if ($recalc)
		{$this->calc_offsets();}
		$res = $this;
		while (true)
		{
			foreach ($res->children as $ee)
			{
				if (($offset >= $ee->offset) && ($offset < ($ee->offset+strlen($ee->outer()))))
				{
					$res = $ee;
					continue(2);
				}
			}
			break;
		}
		return $res;
	}
	
	/*	Поиск узлов минимального размера, содержащих данную регулярку (т.е. последний узел в ветви перед тем как регулярка перестанет срабатывать при переходе к более глубоким узлам).
			$reg - регулярка, либо узел. В случае узла просто проверит - имеется ли данный узел среди потомков текущего узла (включая текущий).
			$strip - искать ли только в текстах (т.е. по результату strip_tags())
		Вернет массив узлов.
		Примечание: в роли $reg можно передать узел, тогда вернет массив из одного элемента, если он присутствует где-либо внутри заданного или является им (или пустой массив, если нет).
	*/
	public function search($reg, $strip = false)
	{
		$result = [];
		if (is_a($reg, 'html'))
		{
			if ($reg===$this) return [$this];
			$this->iterate(function($e)use($reg,&$result){
				if ($e===$reg)
				{
					$result[] = $e;
					return true;
				}
			});
			return $result;
		}
		$this->search_recurse($reg, $result, ($strip?'strip':'outer'));
		$pp = [];
		foreach ($result as $node)
		{
			foreach ($node->parents() as $p)
			{$pp[] = $p;}
		}
		// здесь нельзя выпонить array_unique($pp), т.к. nesting ошибка возникает
		$res2 = [];
		foreach ($result as $k=>$v)
		{if (!in_array($v, $pp, true)) $res2[] = $v;}
		return $res2;
	}
	
	/*	Парный поиск узлов. Ищутся пары узлов с общим прямым родителем, содержащих совпадения с указанными регулярками (каждый - отдельную).
		Одна из регулярок НЕ должна быть полным подмножеством второй регулярки (иначе ничего не найдёт вообще).
			- искомые регулярками участки могут находиться в произвольных местах искомых блоков
			- искомые блоки могут находиться на любом расстоянии
			- алгоритм:
				- ищем среди детей текущего узла искомую строку
				- рекурсивно спускаемся в те теги, где обе регулярки содержатся в одном и том же узле
				- как только найдены каждая в отдельном блоке - пара найдена
				- между парой найденных узлов может находиться еще одна или несколько других пар
		Возвращает массив пар узлов, каждый элемент в нем всегда имеет вид:
			[узел1, узел2]
		Узлы в каждой паре имеют прямого общего родителя (т.е. являются сиблингами) и не участвуют в каких-либо других парах.
		Возвращаемый массив не имеет какой-либо сортировки.
		Параметры:
			$reg1 - первая регулярка
			$reg2 - вторая
			$strict_order - требовать строгий порядок следования узлов в оригинале (сначала $reg1, потом $reg2). Т.е. в строгом режиме в результатах не будут встречаться узлы, находившиеся в оригинале в обратном порядке ($reg2/$reg1).
			$strip - искать ли только в текстах (т.е. по результату strip_tags())
		Примечание: вместо регулярок ($reg1, $reg2) можно задать узлы/узел (либо вперемешку).
	*/
	public function forks($reg1, $reg2, $strict_order = false, $strip = false)
	{
		$result = [];
		$x1 = is_a($reg1, 'html');
		$x2 = is_a($reg2, 'html');
		$x3 = (!$x1 || !$x2);
		$this->forks_recurse($reg1, $reg2, $result, $strict_order, ($strip?'strip':'outer'), $x1, $x2, $x3);
		return $result;
	}
	
	/*	Разбить текущий HTML-документ на контекстные группы.
		(!) Внимание! Текущий узел должен быть корневым (т.е. хранить документ целиком).
		Документ должен быть заранее приведен к UTF-8.
		При $preserve_tags==false некоторые узлы будут удалены из документа.
		Схема называется article_extractor.

		Общая идея:
	
			- ищем узел, содержащий "столбик параграфов": это такой узел DOM, где больше всего "предложных параграфов"
			- в роли "параграфа" может выступать любой элемент, не только <p>
			- "предложный параграф" - это значит параграф, который содержит в своем прямом контенте (без "стриптеггинга") хотя бы одно "предложение" текста
			- "предложением" при этом считается строчка с большой буквы и как минимум с 4 словами после этого, идущими через пробел
			- тяпаем всё из этого узла и обрабатываем
		
		Результаты:
	
			- очень чистые статьи в удобно отформатированном виде, не требуют дополнительной чистки в 99% случаев
			- комментарии к статье не смешаны со статьей в 99% случаев
			- может парсить статьи и отдельно комменты, или и то и другое
			- может рипать шаблон (заменять статейный контейнер произвольным контентом)
		
		Параметры:
		
			$preserve_tags - (bool) предохранить ли абсолютно все теги изначального документа. 
				Когда включено, то html::article_filter() использовать нельзя! Но можно заменять части документа на какие-то свои и получим авто-рипалку шаблонов.
				Когда отключено, то удаляются скрипты, стили, итд. Тогда можно вызывать в дальнейшем html::article_filter().
			$allow_noscript - (bool) оставлять ли тег noscript. 
				Если равно true, то некоторые (редкие) сайты станет парсить лучше (такие как Blogger). Но некоторые сайты станет парсить хуже. Может возникать чуть больше мусора, например разновидности надписей "включите javascript".
		Вернет массив вида:
			[$ghash_stats, $grps]		
		, где:
			$ghash_stats - массив статистики. Имеет вид:
				'zzz' => ['cnt'=>xxx, 'sum'=>yyy, ]
			, где:
				zzz - хеш группы (ghash)
				xxx - количество блоков в группе
				yyy - сумма параграфных баллов
			$grps - список массивов dom узлов.
				Каждый из таких массивов это т.н. список "параграфов". Т.е. если взять его родителя, то получим "контейнер параграфов".
				Первый массив ($grps[0]) как правило является статьей, второй ($grps[1]) - комментариями пользователей, третий и последующие - бесполезным мусором.
				В очень редких случаях (~1%) возможна перемена мест между первыми двумя группами.
				Если взять первый узел (т.е. $grps[0][0]), в нем содержится поле $par_digest. Это строка, в ней через запятую перечислены теги, которые были в роли параграфов. Эту строку можно разбить по запятым и передать в html::article_filter() в качестве 'par_tags'.
		При неудаче вернет NULL, либо $grps будет пуст.
		Каждую полученную группу ($grps[0], $grps[1], ...) нужно дополнительно обработать (см. html::article_filter()), т.к. там сырые узлы и они представляют из себя почти нетронутые участки оригинального документа, содержащие соответствующий контент. Т.е. делаем html::render() и передаем результат в html::article_filter().
	*/
	public function context_groups($preserve_tags = false, $allow_noscript = false)
	{
		$bad_tags = ['#comment', 'script', 'head', 'style', 'noindex', 'noframes', 'noembed', ];
		if (!$allow_noscript)
		{
			// иногда контент содержится именно здесь! да-да, Blogger так делает.
			$bad_tags[] = 'noscript';
		}
		
		// собрать информацию о "параграфах" для обнаружения "контейнеров параграфов"
		$this->iterate(function($node, &$ctrl)use($preserve_tags, $bad_tags){
			
			// удалить или пометить чувствительные теги
			if (in_array($node->tag, $bad_tags))
			{
				if (!$preserve_tags)
				{$node->remove();}
				$ctrl['skip'] = true;
				return;
			}
			
			// контейнер параграфов
			$parent = $node->parent;
			
			// $node - претендент на "параграф"
			
			// здесь очень тонко!
			
			// перечислены теги, которые могут быть общим контейнером ("контейнером параграфов")
			if (!in_array($parent->tag, [
				// иногда контент содержится именно здесь! да-да, Blogger так делает.
				// это позволяет его парсить, но добавляет мусора, в особенности массу разновидестей надписей "включите javascript"
				'noscript',
				'body', 'div', 'td', 'main', 'section', 'article', 'aside', 'header', 'details', 'summary',
			]))
			{return;}
			
			// перечислены теги, которые могут выступать в роли "параграфов"
			// вот эти еще с натяжкой можно заюзать, но это бред: 'main', 'nav', 'blockquote', 'center', 'meta', 'pre'
			if (!in_array($node->tag, ['p', 'div', 'span', 'section', 'details', 'summary', 'ruby', ]))
			{return;}
			
			$pt = $node->inner();
			
			// регулярка для поиска внутри "параграфа" тегов, которых в нем быть не может в принципе.
			// эта штука на ~20% ускоряет обработку, но в некотором очень малом проценте случаев ( < 3%) снижает качество.
			// 'div', // div должен быть закомменчен! строго!
			$reg4 = '#<(applet|area|article|aside|base|basefont|bgsound|blink|body|button|canvas|caption|col|colgroup|datalist|details|dialog|fieldset|footer|form|frame|frameset|head|header|html|input|isindex|label|legend|link|main|map|marquee|menu|menuitem|meter|nav|optgroup|option|param|plaintext|progress|section|select|summary|svg|table|tbody|td|textarea|tfoot|th|thead|time|title|tr)\b#i';
			if (preg_match($reg4, $pt))
			{return;}
			
			// перед оценкой "параграфности" удаляются некоторые теги
			$regs2 = [
				// перечислены все phrase-теги (т.е. различные выделители: strong, em, span, итд),
				// а также те, которые часто используются в роли них (напр.: center, big, data, meta, итд).
				// Они создают липовую вложенность в условиях параграфа.
				// удаляя их мы выравниваем "поверность" параграфа перед анализом, делая её более плоской.
				'#</?(abbr|acronym|address|b|bdi|bdo|big|center|cite|code|data|del|dfn|em|font|i|ins|kbd|mark|meta|q|s|samp|small|span|strike|strong|sub|sup|tt|u|var)\b[^>]*>#si',
				// ссылки удаляются полностью, т.к. лишь вносят шум
				'#<a\s.*?</a>#si',
			];
			$pt = preg_replace($regs2, '', $pt);
			
			
			// разбор нужен чтобы обойти только прямых текстовых детей
			$ht = new html();
			$ht->outer($pt);

			// чекер на предложения
			// вот это хорошо работает:
			// $predl_reg = '#(?<!\w)(?=\p{Lu})\w+(\s+\w+){3}(?=\s|$)#u';
			// вот это потестить (вроде тоже норм):
			$predl_reg = '#(?<!\w)(?=\p{Lu})[\w\-]+(\s+\w[\w\-]*){3}(?=\s|$)#u';
			// нормально, но с нареканиями:
			// $predl_reg = '#(?<!\w)\w+(\s+\w+){3}(?=\s|$)#u';
			// плохая! учитывает только предложения, спотыкается на сайтах о моде, где нет предложений, а только подписи к картинкам
			// $predl_reg = '#\w\)?[\.\?!]+(?!\w)#u';
			
			foreach ($ht->children as $c)
			{
				if ($c->tag=='#text' && 
					trim($c->tag_block) &&
					($n = preg_match_all($predl_reg, $c->tag_block))
				)
				{
					// тестим доп. слагаемое, помогающее в случаях длинных копипаст без заглавных букв
					$predl_reg2 = '#(?<!\w)[\w\-]+(\s+\w[\w\-]*){5}(?=\s|$)#u';
					$n2 = (int)floor(preg_match_all($predl_reg2, $c->tag_block) / 3);
					
					$parent->found += $n + $n2;
					$ok = true;
				}
			}
			
			if ($ok)
			{$parent->par_digest[$node->tag]++;}
		});

		// собрать все найденные "контейнеры параграфов"
		$arr = [];
		$this->iterate(function($node, &$ctrl)use(&$arr){
			if ($node->found)
			{
				// сохранить их изначальный порядок
				$node->orig_order = count($arr);
				$arr[] = $node;
			}
		});

		// отсортировать "контейнеры параграфов" по убыванию "параграфности"
		usort($arr, function($a, $b){
			return $b->found <=> $a->found;
		});

		// отфильтровать "контейнеры параграфов"
		$blocks = $dupes = [];
		foreach ($arr as $k=>$node)
		{
			// чекнуть на концентрические дубли
			$pr = $node->parents();
			for ($j=0;$j<$k;$j++)
			{
				$aj = $arr[$j];
				if (($in1 = in_array($aj, $pr, true)) || 
					($in2 = in_array($node, $aj->parents(), true))
				)
				{continue 2;}
			}
			
			$s = $node->outer();
			
			// удалить полные дубли блоков
			if ($dupes[$s]) continue;
			$dupes[$s] = true;
			
			$blocks[$node->orig_order] = $node;
		}

		// отсортировать в изначальный порядок
		ksort($blocks);

		$ghash_stats = [];
		foreach ($blocks as $node)
		{
			$node->par_digest = implode(',', array_keys($node->par_digest));
			$ghash = $node->ghash = substr(md5(implode('|', [$node->par_digest, $node->tag_block, ])), 0, 8);
			$ghash_stats[$ghash]['cnt']++;
			$ghash_stats[$ghash]['sum'] += $node->found;
		}
		unset($v);
		
		// отладка $blocks
		// $n = 0;
		// foreach ($blocks as $node)
		// {
			// echo '=='.(++$n).'('.
				// 'found: '.$node->found.', '.
				// 'par_digest: '.$node->par_digest.', '.
				// 'ghash: '.$node->ghash.', '.
				// 'ghash_cnt: '.$ghash_stats[$node->ghash]['cnt'].', '.
				// 'ghash_sum: '.$ghash_stats[$node->ghash]['sum'].
			// ')================================================================='."\n".
			// $node->outer()."\n\n";
		// }
		// echo 'ghash_stats: '."\n";
		// foreach ($ghash_stats as $ghash=>$v)
		// {echo $ghash.' (cnt: '.$v['cnt'].', sum: '.$v['sum'].')'."\n";}

		
		/*	Найти группы среди претендентов на группы.
			Сортируем группы по убыванию ghashsum. Берем шапку от этого списка, где ghashsum больше >=5. 
			Выбираем из них одну - идущую первой по (изначальному) порядку. 
			Но желательно брать не конкретно группы, а числа sum и с ними сравнивать found-значения блоков, проходя от начала.
			Первый же найденный блок таким способом будет статейным ($grps[0]). Второй блок - комментарии ($grps[1]). Третий и последующие ($grps[2]...) - любой сторонний треш, их лучше не использовать (это разного рода тексты из футеров, и это редко бывает частью контента).
			Возвращает список массивов узлов.
		*/
		if (!$ghash_stats) return;
	
		// Ищем первый же блок, соответствующий своей суммой found'ов одной из сумм в топовых группах.
		// И собираем все блоки с таким ghash, как у него.
		// Первый такой сбор даст статью целиком. Второй - комменты, итд.
		// $blocks хранит оригинальный порядок узлов и сами узлы.
		uasort($ghash_stats, function($a, $b){
			return $b['sum'] <=> $a['sum'];
		});
		// если самая топовая группа накоплена <= 3 блоками, то берем в первую очередь её
		$v = reset($ghash_stats);
		$ghash = key($ghash_stats);
		$grps = [];
		if ($v['cnt'] <= 3)
		{
			$new_grp = [];
			foreach ($blocks as $k=>$node)
			{
				if ($node->ghash===$ghash)
				{
					$new_grp[] = $node;
					unset($blocks[$k]);
				}
			}
			if ($new_grp)
			{$grps[] = $new_grp;}
		}
		
		// теперь следуем по порядку и находим первый же хеш, который состоит в крупных группах и выхватываем всё с этим хешем.
		// и повторяем этот процесс многократно, пока возникают результаты.
		do {
			$new_grp = [];
			foreach ($blocks as $k=>$node)
			{
				if ($ghash_stats[$node->ghash]['sum'] >= 5)
				{
					// нашли такой $node, который идет первым по порядку, и при этом удовлетворяет условиям.
					// теперь собираем все ноды с таким же хешем, как у него, и также удаляем их из массива, хранящего порядок.
					foreach ($blocks as $kk=>$node2)
					{
						if ($node2->ghash===$node->ghash)
						{
							$new_grp[] = $node2;
							unset($blocks[$kk]);
						}
					}
					if ($new_grp) $grps[] = $new_grp;
					break;
				}
			}
		} while ($new_grp);
		return [$ghash_stats, $grps, ];
	}
	
	/*	Обработать контекстную группу, превратив ее в причесанную статью.
		Контекстные группы документа можно выделить через context_groups().
			$params - массив параметров. Ключи:
				'content_text' - (строка) HTML-разметка. Это должно быть содержимое контентного узла (а не документ целиком). Получить его можно массой способов, применяя различные техники анализа. Одной из них является context_groups().
				'url' - URL, откуда был скачан документ
				'par_tags' - (массив) имена тегов, которые были использованы в роли параграфов (т.е. содержали основной текст), если у вас есть достоверная информация об этом. Пример: ['div', 'span', ]. В общем, здесь можно перечислить наиболее "текстосодержащие" типы тегов. Как используется: если теги перечисленных типов вылезут в "топ", то их имена будут заменены на параграф ("p"). Очевидно, тег "p" указывать в массиве нет смысла.
				'remove_p_if_not_punct' - (bool) (по-умолчанию true) удалять параграфы, на конце которых отсутствует стандартная оканчивающая пунктуация (.?!:;).
					Если равно true, то гарантируется чистота текстов, но иногда (относительно редко) теряет полезные параграфы.
					Если равно false, то потерь будет меньше, но будет чуть больше мусора. Статьи будут гарантированно "полными копипастами" и картиночные посты с подписями будут парситься лучше.
				'allow_p_in_tables' - (bool) (по-умолчанию true) разрешить параграфы внутри табличек.
					Если равно true, то табличка не будет удаляться из-за наличия в ней параграфа.
				'do_debug' - (bool) включить режим отладки
		
		Вернет один корневой dom-узел (новый документ), содержащий параграфы, списки, таблицы, итд.
		Он готов к использованию ($x->outer()), но вы можете также произвести какую-то дополнительную фильтрацию или обработку узлов.
		Оригинальные атрибуты тегов доступны в каждом узле результата в виде массива $node->ae_attrs.
		Реальные же атрибуты сильно зачищены и почти отсутствуют, исключая некоторые атрибуты некоторых тегов (img, iframe).
		Рекомендации по пост-обработке:
			- картинки сразу скачиваем и проверяем на валидность (пока это еще узлы!), неподходящие удаляем.
			- если осталось мало текста ( < 500 симв.) и при этом нет минимум 5 картинок, то можно удалять статью - это надежно защитит от треша
			- ссылки (<a>) при желании можно полностью удалить, оставив от них анкорный текст
			- рекомендуется отклонять статьи, которые полностью дублируют ранее полученные. Такое возможно со страницами cloudflare-ошибок (они могут все загадить).
			- рекомендуется удалять параграфы, текст которых повторяется 2 и более раз в рамках *всего парсинга*.
			- рекомендуется удалять картинки, которые повторяются по URLам в рамках *всего парсинга*
			- рекомендуется фильтровать параграфы по своим спискам стоп-слов, такие как: "All rights reserved", "No Relared Posts", итд. Они и так чистые, но иногда дичь проскальзывает полиморфная (одна и та же приписка на разных сайтах может иметь разный вид).
			- спаны (<span>) при желании можно полностью удалить (или заменить на strong/em), оставив от них анкорный текст
			- блокквоты вставляем сами, вынося в одном из параграфов последнее предложение в отдельный тег <blockquote> вне параграфа.

	*/
	static public function article_filter($params)
	{
		extract($params);
		if ($remove_p_if_not_punct===NULL) $remove_p_if_not_punct = true;
		if ($allow_p_in_tables===NULL) $allow_p_in_tables = true;
		$start = time();
		/*
			[ok] заранее делаем это: 
				- наиболее классические phrase-теги заменяем на span.
				- все остальные теги заменяем на параграф. параграфы не смогут быть вложенными и тем самым упростят общую структуру, предохранив информацию о делении пространства.
				- пересобираем документ, чтобы вставленные параграфы применились
				- "br" заменяем на "p"
				- "th" заменяем на "td"
				- "h1" заменяем на "h2"
				- тег "meta" удаляем
				- windows переносы строки изменяем на unix-стиль
			Далее:
				[ok] удаляем: form, h4, h5, h6
				[ok] оставляем: img, ul, ol, li, h1, h2, h3, p, table, tr, td, th, thead, tbody, tfoot, em, strong, span.
				[ok] изнутри h2, h3 удаляем всё кроме ссылок и текстов.
				[ok] в любой табличке должно быть как минимум 2 строки и 4 ячейки, а также табличка должна содержать от 8 слов, не считая цифр.
				[ok] внутри таблички не может быть тегов: table, iframe, h1, h2, h3, h4, h5, h6, ul, ol, li (иначе табличку удаляем).
				[ok] внутри списков (ul, ol) можно: li, em, strong, span, a. Остальное нельзя.
				[ok] список (ul, ol) удаляем, если после удаления ссылок (<a>) из него в нём не остается как минимум 10 слов, не считая цифр.
				[ok] <a> удаляем полностью, если он содержит "&raquo;", "Read More" или подобные признаки.
				[ok] всем тегам удаляем все атрибуты, кроме: <img src>, <iframe src>, <a href>
			[ok] если вне таблицы встречаются теги: thead, tbody, tfoot, tr, td, th, то выворачиваем их.
			[ok] картинки и фреймы, которые находятся вне таблиц нужно попросту "всплыть" наверх и оформить в параграф.
			[ok] если какой-то тег доплыл до топа и это не параграф, и внутри нет параграфов, то заменить его на параграф (исключая некоторые).
			[ok] URLы в картинках абсолютизируем, проверяем чтобы src был заполнен.
			[ok] URLы во фреймах проверяем чтобы были абсолютные, иначе удаляем фрейм.
			[ok] если какая-то картинка повторилась несколько раз (по URL), то все картинки с таким URL удаляем.
			[ok] span, содержащий дату или время (2019.04.09 или 18:47).
			[ok] ссылки <a>, находящиеся вне h1,h2,h3, и у которых атрибут href=#, либо есть атрибуты title, alt, class, id, name - удаляем полностью, включая ссылочный текст.
			[ok] вложенные span/em/strong выворачиваем.
			[ok] всё это нужно делать через многократные повторы, до тех пор, пока хоть что-то модифицируется.
			[ok] множественные пробелы в текстовых узлах заменяем на одиночные.
			[ok] оформляем в параграфы голые тексты, оказавшиеся в топовом узле, включая в такие группы также phrase-теги (a, em, strong, span).
			[ok] слишком длинные (по тексту) a/span/strong/em выворачиваем.
			[ok] удаляем относительно короткие "span" в конце параграфа
			[ok] удаляем серии идущих подряд заголовков, исключая последний заголовок в такой серии.
			[ok] выворачиваем span/em/strong когда они почти полностью занимают собой параграф. Если еще при этом он достаточно короткий и является span'ом, то удаляем весь параграф.
			[ok] удаляем параграф, если в нем ссылочного текста 80%.
			[ok] удаляем параграф, расположенный на конце документа, если он заканчивается на двоеточие.
			[ok] удаляем параграф, если в нем есть буквы, но их меньше 10.
			[ok] удаляем параграф, если на конце него нет одного из основных знаков препинания (.?!:;).
			[ok] удаляем параграфы, которые начинаются с маленькой буквы.
			[ok] если в самом конце статьи ссылка (<a>) или <span>, то этот элемент удаляем.
			[ok] если в самом конце статьи заголовок, то удаляем его.
			[ok] после завершающих тегов </h1>, </h2>, </h3>, </p>, </ul>, </li>, </blockquote>, </table>, </thead>, </tbody>, </tfoot>, </tr>, </td>  ставится перенос строки.
			[ok] удаляем параграфы, текст которых повторяется 2 и более раз в рамках статьи
			
			(...)
			здесь перечислен не полный список из того, что реально делается.
			
		*/
		
		if (!$par_tags) $par_tags = [];
		$par_tags = array_diff($par_tags, ['p', ]);
		
		$s = $content_text;
		
		/*	заменяем:
				- "br" на параграф (это делается отдельно, т.к. br - это тег незакрывающегося типа)
				- "h1" на "h2"
				- "th" на "td"
				- мета-теги (микроразметка) удаляем
				- переносы строки в windows-стиле удаляем
				- нестандартные пробелы заменяем на простой пробел
		*/
		
		$s = preg_replace(
			[
				'#<br(?=[\s\>/])[^>]*>#i', 
				'#<h1(?=\s|\>)#i', 
				'#</h1>#i', 
				'#<th(?=\s|\>)#i', 
				'#</th>#i', 
				'#<b(?=\s|\>)#i', 
				'#</b>#i', 
				'#<i(?=\s|\>)#i', 
				'#</i>#i', 
				'#\r#',
				'#&nbsp;?|&\#160;?|&\#xA0;?|\x{a0}#siu',
			],
			[
				'<p>', 
				'<h2', 
				'</h2>', 
				'<td',  
				'</td>', 
				'<strong',  
				'</strong>', 
				'<em',  
				'</em>', 
				'',
				' ',
			],
		$s);
		
		// наиболее классические phrase-теги заменяем на "span"
		$reg1 = implode('|', ['abbr', 'acronym', /*'address', 'b', 'bdi', 'bdo', 'big', 'center',*/ 'cite', 'code', 'data', 'del', 'dfn', /*'em',*/ 'font', /*'i',*/ 'ins', 'kbd', 'mark', 'q', 's', 'samp', 'small', /*'span',*/ 'strike', /*'strong',*/ 'sub', 'sup', 'tt', 'u', 'var', ]);
		$s = preg_replace(
			['#<(?:'.$reg1.')(?=\>|\s)#i', '#</(?:'.$reg1.')>#i', ],
			['<span', '</span>', ],
		$s);
		
		// все теги кроме перечисленных здесь ("остальное") заменяем на параграф
		$reg2 = implode('|', ['form', 'p', 'a', 'span', 'iframe', 'table', 'tr', 'td', 'th', 'thead', 'tbody', 'tfoot', 'em', 'strong', 'img', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', ]);
		$s = preg_replace(
			'#</?(?!(?:'.$reg2.')(?=\>|\s))\w+(?=\>|\s)#i', 
			// (!) именно открывающий тег! 
			// т.е. даже в случае закрывашек создается новая открывашка для параграфа (которая, в свою очередь, закроет предыдущий параграф).
			'<p',
		$s);
		
		// $s = preg_replace('#<p>(\s*<p>)+#', '<p>', $s);
		
		$x = new html();
		$x->inner($s);
		$x->autoclose();
		
		$debug = [];
		$cycle = 0;
		$src_stats = [];
		do {
			$total = 0;
			$cycle++;
			$debug_func = function($n, $descr)use(&$debug, &$total){
				$debug['#1: '.$descr][] = $n->outer();
			};
			$x->iterate(function($n, &$c)use($url, $par_tags, $remove_p_if_not_punct, $allow_p_in_tables, &$src_stats, $cycle, $debug_func, &$total, $do_debug){
						
				// [уже убраны, см. выше] убираем пробелы в текстовых узлах, представленные в виде html-entity
				// if ($cycle > 1 && $n->tag=='#text' && !$n->nbsp_removed)
				// {
					// $n->nbsp_removed = true;
					// $n->tag_block = preg_replace('#(&nbsp;?)+#i', ' ', $n->tag_block, -1, $repl_c);
					// if ($repl_c) $n->invalidate();
				// }
				
				$reasons = [];
				if (
					($reasons['html-комментарии, а также: form, h4, h5, h6'] = (
						in_array($n->tag, ['#comment', 'form', 'h4', 'h5', 'h6', ])
					)) ||
					($reasons['абсолютно пустые текстовые элементы (без текста)'] = (
						$n->tag=='#text' && 
						$n->tag_block===''
					)) ||
					($reasons['пустые элементы'] = (
						!in_array($n->tag, ['#text', 'img', 'iframe', 'td', 'br', ]) &&
						(
							$n->children===[] ||
							// т.к. может состоять из нескольких текстовых узлов
							trim($n->inner())===''
						)
					)) ||
					($reasons['потерянные закрывающие теги'] = (
						$n->tag=='#text' && 
						preg_match('#^</\w+>$#', $n->tag_block)
					)) || 
					($reasons['текстовый элемент содержит escaped-теги (вероятно он служебный)'] = (
						$n->tag=='#text' && 
						preg_match('#&lt;\w|&gt;?&lt#i', $n->tag_block)
					)) || 
					($reasons['ссылки (<a>), имеющие пустой href, либо ajax-подобный href'] = (
						$n->tag=='a' && 
						preg_match('#\shref=(\#|["\']\#?["\'\s]|["\']?javascript:)#i', $n->tag_block)
					)) ||
					($reasons['если "a" или "span" похожи на кнопку "читать далее"'] = (
						in_array($n->tag, ['a', 'span', ]) && 
						// есть характерный ссылочный текст
						preg_match('#\[|&raquo;|\b(read|see|explore|learn|show|view)\s+more\b|»|&gt;\s*&gt;#ui', $n->inner()) && 
						// и внутри нет тегов, а также открывающих кавычек
						!preg_match('#&laquo;|«|\<#ui', $n->inner()) &&
						// и букв относительно немного
						preg_match_all('#(?!\d)\w#u', $n->strip()) < 60
					)) ||
					// на википедии не робит из-за классов и тайтлов :-/
					// ($reasons['нестандартные ("нестатейные") ссылки удаляем полностью'] = (
						// $n->tag=='a' &&
						// ($attrs = $n->attrs()) &&
						// ($attrs['href']=='#' || array_intersect_key($attrs, ['class'=>'', 'id'=>'', 'title'=>'', 'name'=>'', ])) &&
						// !html::stack_have(['h1', 'h2', 'h3', 'h4', 'h5', 'h6', ], $c)
					// )) ||
					($reasons['гавеные фреймы'] = (
						$n->tag=='iframe' &&
						($attrs = $n->attrs()) &&
						!preg_match('#^https?://#i', $attrs['src'])
					)) ||
					// таблички...
					(
						$n->tag=='table' &&
						(
							// (!) если убрать из регулярки параграф ("p"), то сможет парсить "расширенные таблички", часто встречающиеся на торрент-сайтах.
							($reasons['внутри табличек не может быть других табличек, а также фреймов, списков, заголовков, параграфов'] = 
								preg_match('#<(table|iframe|h\d|ul|ol|li'.($allow_p_in_tables?'':'|p').')\b#i', $n->inner())) || 
							($reasons['в табличке должно быть хотя бы несколько слов'] = 
								(preg_match_all('#(?!\d)\w+#u', $n->strip()) < 8)
							) ||
							($reasons['в табличке должно быть как минимум 2 строки и 4 ячейки'] = 
								(preg_match_all('#<tr\b#i', $n->inner()) < 2) ||
								(preg_match_all('#<td\b#i', $n->inner()) < 4)
							) || 
							false
						)
					) ||
					// списки...
					(
						(
							in_array($n->tag, ['ul', 'ol', ]) &&
							(
								// параграфы должны быть разрешены внутри списков, т.к. мы заменяем <br> на параграф,
								// и они у нас возникают даже там, где их нет изначально.
								($reasons['внутри списков можно только определенные элементы (li/em/strong/span/a/p)'] = 
									(preg_match('#<(?!(?:li|em|strong|span|a|p)\b)\w+\b#i', $n->inner()))) ||
								($reasons['кол-во слов в списке (без тегов) за полным вычетом ссылок должно быть >= 7'] = 
									(preg_match_all('#(?!\d)\w+#u', strip_tags(preg_replace('#<a\s.*?</a>#si', '', $n->inner()))) < 7)) ||
								false
							)
						) ||
						(
							$n->tag=='li' &&
							(
								($reasons['внутри элемента списка не должно быть более 2 параграфов, каждый из которых содержит текст после него'] = 
									(preg_match_all('#(<p\b[^>]*>\s*)+\w#ui', $n->inner()) >= 3)) ||
								false
							)
						)
					) ||
					($reasons['элементы списков, находящиеся вне списков'] = (
						($n->tag=='li' && !html::stack_have(['ul', 'ol', ], $c)) ||
						(in_array($n->tag, ['dt', 'dd', ]) && !html::stack_have(['dl', ], $c)) ||
						false
					)) ||
					($reasons['определенные теги, содержащие хотя бы одну цифру в своем strip-контенте и когда при этом в нем содержится мало буквенных символов'] = (
						in_array($n->tag, ['p', 'span', 'em', 'strong', 'ul', 'ol', /*'li',*/ ]) &&
						(($q = $n->strip())!==NULL) &&
						preg_match('#\d#', $q) &&
						preg_match_all('#(?!\d)\w#u', $q) < 15
					)) || 
					($reasons['теги <a>, содержащие даты либо время'] = (
						in_array($n->tag, ['a', ]) &&
						(($q = $n->strip())!==NULL) &&
						preg_match('#\b\d{1,2}\b\s+\w{3}\s+20\d\d\b|\b\d\d?:\d\d\b|\b(?<!\.)(\d{2}|\d{4})[\./\\\\]\d{1,2}[\./\\\\]\d{1,2}(?!\.)\b#u', $q)
					)) || 
					($reasons['почти любые теги внутри заголовков удаляются'] = (
						!in_array($n->tag, ['#text', 'a', ]) &&
						($hdr = html::stack_have(['h1', 'h2', 'h3', 'h4', 'h5', 'h6', ], $c)) &&
						// но только если удаление тега не приведет к полному опустошению заголовка
						str_replace($n->strip(), '', $hdr->strip()) > 25
					)) ||
					($reasons['ссылки, содержащие в себе phrase-теги удаляем'] = (
						$n->tag=='a' &&
						preg_match('#<(strong|span|em)\b#i', $n->inner())
					)) ||
					false
				)
				{
					if ($do_debug)
					{$debug_func($n, key(array_filter($reasons)));}
					$total++;
					$n->remove();
					$c['skip'] = true;
					return;
				}
				
				if ($c['level']==0 && in_array($n->tag, $par_tags))
				{
					// то заменяем ему имя на "p"
					if ($do_debug)
					{$debug_func($n, 'в топовые узлы вылез тег, который был в оригинале "параграфом"');}
					$total++;
					$n->change('p');
				}
				
				if (in_array($n->tag, ['img', 'iframe', ]) &&
					!$n->popped &&
					!html::stack_have(['table', ], $c)
				)
				{
					if ($do_debug)
					{$debug_func($n, 'img и iframe "всплываем" наверх и оформляем в параграфы, но это не относится к тегам, находящимся внутри табличек');}
					$total++;
					$n->popped = true;
					$n->pop();
					$n->encapsulate('p');
					$c['rewind_level'] = 0;
					return;
				}
				
				$reasons = [];
				if (
					($reasons['если у табличного тега среди родителей нет таблички, то такой тег выворачиваем'] = 
					(
						in_array($n->tag, ['tr', 'td', 'th', 'thead', 'tbody', 'tfoot', ]) &&
						!html::stack_have(['table', ], $c)
					)) ||
					($reasons['вложенные span/strong/em'] = (
						(in_array($n->tag, ['span', 'strong', 'em', ]) && html::stack_have(['strong', 'em', ], $c)) ||
						(in_array($n->tag, ['span', ]) && html::stack_have(['span', ], $c))
					)) ||
					($reasons['слишком длинные a/span/strong/em выворачиваем'] = (
						(
							in_array($n->tag, ['a', 'span', 'strong', 'em', ]) &&
							preg_match_all('#\w#u', $n->strip()) > 60
						)
					)) ||
					false
				)
				{
					if ($do_debug)
					{$debug_func($n, key(array_filter($reasons)));}
					$total++;
					$n->pull_up();
					$c['rewind_level'] = $c['level'];
					return;
				}
				
				// множественные пробелы заменяем на одиночные
				if ($n->tag=='#text' && !$n->multispaces)
				{
					$n->multispaces = true;
					if (!in_array($n->tag_block, [' ', ''], true))
					{$n->tag_block = preg_replace('#^\s+|\s+$|\s{2,}#', ' ', $n->tag_block);}
				}
				
				// атрибуты всем тегам удаляем, кроме некоторых
				if ($n->tag!='#text' && $n->ae_attrs===NULL)
				{
					$n->ae_attrs = $n->attrs();
					$a = [];
					$a2 = $n->ae_attrs;
					switch ($n->tag)
					{
						case 'a':
							if ($a2['href'])
							{
								$a = array_intersect_key($a2, array_flip(['href', ]));
								$a['href'] = url_abs($a['href'], $url);
							}
						break;
						case 'img':
							if ($a2['data-lazy-src']) $a2['src'] = $a2['data-lazy-src'];
							elseif ($a2['data-src']) $a2['src'] = $a2['data-src'];
							elseif ($a2['srcset'])
							{
								$z = [];
								foreach (explode(',', $a2['srcset']) as $e)
								{
									list($s, $num) = preg_split('#\s+#', trim($e));
									if ($num = (float)preg_replace('#\D#', '', $num))
									{$z[$num] = $s;}
								}
								krsort($z);
								if ($srcset = reset($z))
								{$a2['src'] = $srcset;}
							}
							if ($a2['src'])
							{
								$a = array_intersect_key($a2, array_flip(['src', ]));
								$a['src'] = url_abs($a['src'], $url);
								$src_stats[$a['src']][] = $n;
							}
								else
							{
								if ($do_debug)
								{$debug_func($n, 'удаляем изображения без атрибута src');}
								$total++;
								$n->remove();
								$c['skip'] = true;
								return;
							}
						break;
						case 'iframe':
							// фреймы уже проверены на валидность
							$a = array_intersect_key($a2, array_flip(['src', 'width', 'height', 'border', 'frameborder', 'allowfullscreen', ]));
						break;
					}
					// в общем случае все атрибуты стираются, но остаются доступными через $node->ae_attrs
					$n->attrs($a);
				}
			});
			
			if ((time()-$start) > 10) return new html();
			
			// если какая-то картинка повторилась несколько раз, то ее удаляем
			foreach ($src_stats as $v)
			{
				if (count($v)>1)
				{
					foreach ($v as $vv) $vv->remove();
					if ($do_debug)
					{$debug_func($vv, 'картинка удалена из-за её повторов');}
					$total++;
				}
			}
			$src_stats = [];
			
			// нерекурсивные проверки на топовом левеле
			do {
				$total2 = 0;
				$debug_func = function($text, $descr)use(&$debug){
					$debug['#2: '.$descr][] = $text;
				};
				// сгруппировать "голые" теги, вылезшие в топовый узел без обертки параграфом
				$buf2 = $bufs = [];
				$have_tsse = false;
				foreach ($x->children as $v)
				{
					if (
						in_array($v->tag, ['a', 'strong', 'span', 'em']) ||
						// чтобы первый элемент не был пробельным текстовым
						($v->tag=='#text' && ($buf2 || trim($v->tag_block)!==''))
					)
					{
						$have_tsse = true;
						$buf2[] = $v;
					}
						else
					{
						if ($buf2)
						{
							$bufs[] = $buf2;
							$buf2 = [];
						}
						$bufs[] = $v;
					}
				}
				if ($buf2) $bufs[] = $buf2; // слить возможный буфер
				if ($have_tsse)
				{
					$new = [];
					foreach ($bufs as $v)
					{
						if (is_array($v))
						{
							// пробельные текстовые элементы на начале и на конце размещаем вне параграфа.
							// это важно, т.к. иначе на некоторых стыках пробелы исчезнут полностью, слепив до этого разделенные между собой теги.
							$add_after = [];
							while (($e = reset($v)) && $e->tag=='#text' && trim($e->tag_block)==='')
							{$new[] = array_shift($v);}
							while (($e = end($v)) && $e->tag=='#text' && trim($e->tag_block)==='')
							{$add_after[] = array_pop($v);}
							
							$prg = new html();
							$prg->tag = 'p';
							$prg->tag_block = '<p>';
							$prg->closer = '</p>';
							$prg->parent = $x;
							$prg->append($v);
							$new[] = $prg;
							foreach ($add_after as $e) $new[] = $e;
							if ($do_debug)
							{$debug_func(html::render($v), 'голые группы тегов #text/a/strong/span/em в топовом узле упаковываются в параграф');}
							$total2++;
						}
							else
						{$new[] = $v;}
					}
					$x->children = $new;
					$x->invalidate();
				}
				
				// проверки на недопустимые элементы в самом конце статьи
				foreach (array_reverse($x->children) as $v)
				{
					// тексты пропускаем до первого нетекстового элемента
					if ($v->tag=='#text')
					{continue;}
					if (in_array($v->tag, ['a', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', ]))
					{
						$v->remove();
						if ($do_debug)
						{$debug_func($v->outer(), 'если на конце документа заголовок или ссылка, то удаляем такой тег');}
						$total2++;
					}
						else
					{
						// $v - первый с конца контейнер документа
						foreach (array_reverse($v->children) as $vv)
						{
							// тексты, не содержащие букв пропускаем до первого нетекстового элемента.
							// Это позволит обработать ситуации вида: текст текст <a>ссылка<a>[точка].
							if ($vv->tag=='#text' && !preg_match('#\p{L}#u', $vv->tag_block))
							{continue;}
							if (in_array($vv->tag, ['a', 'span', ]))
							{
								$vv->remove();
								if ($do_debug)
								{$debug_func($vv->outer(), 'если внутри последнего контейнера документа на конце "a" или "span", то удаляем это');}
								$total2++;
							}
							break;
						}
						if ($v->tag=='p')
						{
							if (
								preg_match('#(\.\.\.|&hellip;|…)$#u', $v->strip()) &&
								preg_match_all('#\w#u', $v->strip()) < 30
							)
							{
								$v->remove();
								if ($do_debug)
								{$debug_func($v->outer(), 'если текст в параграфе на конце документа достаточно короткий и содержит на конце многоточие');}
								$total2++;
							}
							elseif (preg_match('#(:)$#u', $v->strip()))
							{
								$v->remove();
								if ($do_debug)
								{$debug_func($v->outer(), 'если текст в параграфе на конце документа содержит на конце двоеточие');}
								$total2++;
							}
							elseif (
								!preg_match('#\p{Ll}#u', $v->strip()) &&
								preg_match('#\p{Lu}#u', $v->strip())
							)
							{
								$v->remove();
								if ($do_debug)
								{$debug_func($v->outer(), 'если текст в параграфе на конце документа написан капсом, то он удаляется');}
								$total2++;
							}
						}
					}
					break;
				}
				
				// проверки всех топовых элементов
				$prev_h = $last_p = NULL;
				$p_hashes = [];
				foreach ($x->children as $v)
				{if ($v->tag=='p') $p_hashes[$v->strip()]++;}
				foreach ($x->children as $v)
				{
					if (in_array($v->tag, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', ]))
					{
						if (preg_match('#[\[\]\|@\{\};]#', $v->strip()))
						{
							$v->remove();
							if ($do_debug)
							{$debug_func($v->outer(), 'заголовки, содержащие нехарактерные символы');}
							$total2++;
							continue;
						}
						if (strpos($v->inner(), '<')!==false)
						{
							$v->inner($v->strip());
							if ($do_debug)
							{$debug_func($v->outer(), 'любые теги внутри заголовков исчезают, оставляя свой анкорный текст');}
							$total2++;
						}
						// удаляет серии из заголовков, идущих подряд, исключая последний
						if ($prev_h)
						{
							$prev_h->remove();
							if ($do_debug)
							{$debug_func($prev_h->outer(), 'серийные заголовки удаляем кроме последнего заголовка в такой серии');}
							$total2++;
						}
						$prev_h = $v;
					}
						else
					{
						// серию заголовков не прерываем, если между ними находятся текстовые элементы, состоящие из пробелов
						if (!($v->tag=='#text' && !trim($v->tag_block)))
						{$prev_h = NULL;}
						
						if ($v->tag=='p')
						{
							$last_p = $v;
							$s = $v->strip();
							if ($s && $p_hashes[$s] >= 2)
							{
								$v->remove();
								if ($do_debug)
								{$debug_func($v->outer(), 'удаляем все параграфы, которые повторялись по тексту');}
								$total2++;
								continue;
							}
							
							// кол-во букв в параграфе
							$c_count = preg_match_all('#(?!\d)\w#u', $s);
							// кол-во слов в параграфе
							$w_count = preg_match_all('#\b(?!\d)\w+#u', $s);
							// кол-во ссылок в параграфе
							$a_count = preg_match_all('#<a\s#i', $v->inner());
							
							if ($c_count) // чтобы не стерло картинки и фреймы
							{
								if ($remove_p_if_not_punct)
								{
									if (!preg_match('#[\.\?!:;]$#u', $v->strip()))
									{
										$v->remove();
										if ($do_debug)
										{$debug_func($v->outer(), 'удаляем параграфы, где на конце нет знаков препинания (.?!:;)');}
										$total2++;
										continue;
									}
								}
								if (preg_match('#^\p{Ll}#u', $v->strip()))
								{
									$v->remove();
									if ($do_debug)
									{$debug_func($v->outer(), 'удаляем параграфы, которые начинаются с маленькой буквы');}
									$total2++;
									continue;
								}
								if ($c_count < 10)
								{
									$v->remove();
									if ($do_debug)
									{$debug_func($v->outer(), 'удаляем параграфы, в которых есть буквы, но их меньше 10');}
									$total2++;
									continue;
								}
							}
							
							if ($a_count>1 && $w_count/$a_count < 2)
							{
								$v->remove();
								if ($do_debug)
								{$debug_func($v->outer(), 'удаляем параграфы, в которых минимум 2 ссылки и при этом соотношение количества слов к количеству ссылок слишком большое. Например 5 слов и 3 ссылки.');}
								$total2++;
								continue;
							}
							// тег => минимальное_количество_букв, которое должно остаться после его удаления, чтобы не был удален весь параграф
							$min_sizes = ['span' => 1, 'a' => 6, ];
							foreach ($min_sizes as $tag=>$symb_count)
							{
								$s = preg_replace('#<'.$tag.'\b.*?</'.$tag.'>#si', '', $v->inner(), -1, $repl_sz);
								if ($repl_sz && preg_match_all('#(?!\d)\w#u', strip_tags($s)) < $symb_count)
								{
									$v->remove();
									if ($do_debug)
									{$debug_func($v->outer(), 'удаляем параграф, который полностью (либо почти полностью) состоит из '.$tag.'\'ов. Использован порог из '.$symb_count.' букв');}
									$total2++;
									continue 2;
								}
							}
						
							// это нельзя делать раньше (внутри iterate())
							do {
								foreach ($v->children as $vv)
								{
									if (in_array($vv->tag, ['span', 'strong', 'em', ]) &&
										preg_match_all('#\w#u', str_replace($vv->strip(), '', $v->strip())) <= 1
									)
									{
										if ($vv->tag=='span' && preg_match_all('#\w#u', $v->strip()) < 25)
										{
											$v->remove();
											if ($do_debug)
											{$debug_func($v->outer(), 'удаляем параграф, который очень короткий и на 99% состоит из span');}
											$total2++;
											continue 3;
										}
									
										$vv->pull_up();
										if ($do_debug)
										{$debug_func($vv->outer(), 'тег span/em/strong выворачиваем, если он почти полностью занимает собой параграф');}
										$total2++;
										continue 2;
									}
								}
								break;
							} while (true);
							
							// ищем элемент с конца
							foreach (array_reverse($v->children) as $vv)
							{
								// тексты пропускаем до первого нетекстового элемента.
								// но не все, а лишь состоящие из пробелов, чисел и нестандартной пунктуации.
								// Это позволит обработать ситуации вида: текст текст <a>ссылка<a>[требуха].
								if ($vv->tag=='#text' && preg_match('#^(\s|\d|_|[^\.,\?!:;\w])*$#u', $vv->tag_block))
								{continue;}
								// $vv - последний нетекстовый элемент внутри параграфа, без пропуска букв
								if ($vv->tag=='span')
								{
									if (!preg_match('#<#', $vv->inner()) &&
										preg_match_all('#(?!\d)\w#u', str_replace($vv->strip(), '', $vv->parent->strip())) > 10
									)
									{
										$vv->remove();
										if ($do_debug)
										{$debug_func($vv->outer(), 'span\'ы на конце параграфов удаляем, если внутри них нет тегов и после них нет ничего либо есть всего лишь ".?!" с пробелами, и если при этом span не занимает параграф целиком');}
										$total2++;
									}
								}
								break;
							}
						}
					}
				}
				
				if ($last_p && preg_match('#©#u', $last_p->strip()))
				{
					$last_p->remove();
					if ($do_debug)
					{$debug_func($last_p->outer(), 'удаляем последний параграф, если в нем содержится значок ©');}
					$total2++;
				}
				
				if ((time()-$start) > 10) return new html();
				
			} while ($total2);
			$total += $total2;
		} while ($total > 0);
		
		if ($do_debug)
		{var_dump($debug);}
		
		return $x;
	}
	
	protected function search_recurse($reg, &$result, $func)
	{
		foreach ($this->children as $node)
		{
			$ss = $node->$func();
			if (preg_match($reg, $ss))
			{
				$result[] = $node;
				$node->search_recurse($reg, $result, $func);
			}
		}
	}
	
	protected function forks_recurse($reg1, $reg2, &$result, $strict_order, $func, $x1, $x2, $x3)
	{
		$list = [];
		foreach ($this->children as $node)
		{
			if ($x3)
			{$s = $node->$func();}
			$s1 = ($x1?$node->search($reg1):preg_match($reg1, $s));
			$s2 = ($x2?$node->search($reg2):preg_match($reg2, $s));
			if ($s1 && $s2) {$code = 0;}
			elseif ($s1) {$code = 1;}
			elseif ($s2) {$code = 2;}
			else {$code = -1;}
			if ($code>=0)
			{$list[] = [$code, $node];}
		}
		do {
			foreach ($list as $id=>$data)
			{
				list($code, $node) = $data;
				if ($code==0)
				{$node->forks_recurse($reg1, $reg2, $result, $strict_order, $func, $x1, $x2, $x3);}
				else
				{
					if ($prev_code>0 && 
						(
							($strict_order && $code>$prev_code) ||
							(!$strict_order && $prev_code!=$code)
						)
					)
					{
						$result[] = [$prev_node, $node];
						$prev_code = 0;
						unset($list[$prev_id], $list[$id]);
						continue(2);
					}
						else
					{$prev_code = $code;}
					$prev_node = $node;
					$prev_id = $id;
				}
			}
			break;
		} while (true);
	}
	
	protected static function get_stripped_content($tag, &$res)
	{
		if ($tag->tag==='#text')
		{$res[] = $tag->tag_block;}
			else
		{
			foreach ($tag->children as $tag2)
			{html::get_stripped_content($tag2, $res);}
		}
	}
	
	protected function set($html, $only_inner = true)
	{
		// вырожденная ситуация: когда корневому ставят пустой контент, то нужно добавить хотя бы один пустой текстовый узел
		if ($this->tag===NULL && ((string)$html)==='')
		{
			$x_obj = new html();
			$x_obj->tag = '#text';
			$x_obj->tag_block = '';
			$x_obj->parent = $this;
			$x_obj->parent->children = [$x_obj];
			$this->invalidate();
			return;
		}
		$is_xml = false;
		
		$curr_parent = $this;
		if ($this->tag[0]=='#')
		{$only_inner = false;}
		$curr_parent->children = [];
		$parent_stack = [];
		$offset = $last_opened_or_closed_tag_offset = 0;
		if (!preg_match_all('#<!--|-->|</?(?:[a-z][\w:]*|!doctype|\?xml)\b[^<>]*(?<!--)>#si', $html, $m, PREG_OFFSET_CAPTURE))
		{$m = [[]];}
		foreach ($m[0] as $mm)
		{
			$tag_block = $mm[0];
			$offset = $mm[1];
			preg_match('#[!\?\w:]+#s', $tag_block, $m2);
			$name = strtolower($m2[0]);
			$is_opened = ($tag_block[1]!='/');
			
			if ($special_opened && ($is_opened || $name!=$special_opened))
			{continue;}
			
			if ($comment)
			{
				if ($tag_block=='-->')
				{
					$comment->tag_block = substr($html, $comment_started_at, $offset+strlen($tag_block)-$comment_started_at);
					$last_opened_or_closed_tag_offset = $offset+strlen($tag_block);
					$comment = false;
				}
				continue;
			}
			elseif ($tag_block=='<!--')
			{
				$this->try_add_text_node($html, $last_opened_or_closed_tag_offset, $offset, $curr_parent);
				$comment = new html();
				$comment->tag = '#comment';
				$comment_started_at = $offset;
				$comment->parent = $curr_parent;
				$comment->parent->children[] = $comment;
				continue;
			}
			
			$this->try_add_text_node($html, $last_opened_or_closed_tag_offset, $offset, $curr_parent);
			$last_opened_or_closed_tag_offset = $offset+strlen($tag_block);
			if ($is_opened)
			{
				// тег открылся.
				
				// список специальных тегов, которые игнорируют любые другие теги пока сами не закроются.
				if (!$is_xml)
				{
					if (in_array($name, HTML_ELEMENTS_SPECIAL))
					{$special_opened = $name;}
						else
					{
						/*	Делается проверка родителей для текущего тега ($name). 
								$blocked_parents - если среди родителей текущего тега ($name) будет найден один из этих тегов ("неправильный" родитель), то он будет закрыт. Ищется максимально топовый.
								$stoppers - список тегов, *до* которых родители (при пути наверх) будут проверяться: если встречен - проверка прерывается.
							Используются именно in_array() и именно динамическое составление - выходит быстрее! Проверено!
						*/
						$stoppers = $blocked_parents = [];
						
						// элементы, которые не могут быть вложенными в такие же элементы
						if (in_array($name, HTML_ELEMENTS_NON_NESTED))
						{$blocked_parents[] = $name;}
						if (in_array($name, HTML_ELEMENTS_NON_PARAGRAPH))
						{$blocked_parents[] = 'p';}
						// (!) вот это не тестил
						if (in_array($name, HTML_ELEMENTS_NON_HEADER))
						{
							$blocked_parents[] = 'h1';
							$blocked_parents[] = 'h2';
							$blocked_parents[] = 'h3';
							$blocked_parents[] = 'h4';
							$blocked_parents[] = 'h5';
							$blocked_parents[] = 'h6';
						}
						switch ($name)
						{
							case 'li':
							case 'dd':
							case 'dt':
								foreach (['li', 'dd', 'dt'] as $v) $blocked_parents[] = $v;
								foreach (['ul', 'ol', 'dl', 'dir'] as $v) $stoppers[] = $v;
							break;
							case 'head':
							case 'body':
								// т.е. head будет закрыт, если он попытается быть родителем для body (и наоборот)
								$blocked_parents[] = 'head';
								$blocked_parents[] = 'body';
							break;
							case 'td':
							case 'th':
								// 'col' сюда не имеет смысла добавлять, т.к. это void-элемент
								foreach (['td', 'th', 'caption', 'colgroup', ] as $v) $blocked_parents[] = $v;
								$stoppers[] = 'table';
							break;
							case 'tr':
								// 'col' сюда не имеет смысла добавлять, т.к. это void-элемент
								foreach (['tr', 'td', 'th', 'caption', 'colgroup', ] as $v) $blocked_parents[] = $v;
								$stoppers[] = 'table';
							break;
							case 'thead':
							case 'tbody':
							case 'tfoot':
								// 'col' сюда не имеет смысла добавлять, т.к. это void-элемент
								foreach (['thead', 'tbody', 'tfoot', 'td', 'tr', 'th', 'caption', 'colgroup', ] as $v) $blocked_parents[] = $v;
								$stoppers[] = 'table';
							break;
							case 'caption':
							case 'colgroup':
								// 'col' сюда не имеет смысла добавлять, т.к. это void-элемент
								$blocked_parents[] = 'caption';
								$blocked_parents[] = 'colgroup';
								$stoppers[] = 'table';
							break;
							case 'col':
								// 'col' сюда не имеет смысла добавлять, т.к. это void-элемент
								foreach (['tr', 'td', 'th', 'caption'] as $v) $blocked_parents[] = $v;
								$stoppers[] = 'table';
							break;
						}
						
						// единственные теги, которые можно внутри h1-h6. Остальные прервут любой заголовок.
						// if (!in_array($name, ['tt', 'i', 'b', 'big', 'small', 'em', 'strong', 'dfn', 'code', 'samp', 'kbd', 'var', 'cite', 'abbr', 'acronym', 'a', 'img', 'object', 'br', 'map', 'q', 'sub', 'sup', 'span', 'bdo', 'input', 'select', 'textarea', 'label', 'button', ]))
						// {
							// foreach (['h1', 'h2', 'h3', 'h4', 'h5', 'h6', ] as $v)
							// {$blocked_parents[] = $v;}
						// }
						
						if ($blocked_parents)
						{
							$n = 0;
							$found_n = -1;
							foreach ($parent_stack as $e)
							{
								if (in_array($e->tag, $stoppers)) break;
								if (in_array($e->tag, $blocked_parents))
								{$found_n = $n;}
								$n++;
							}
							if ($found_n>=0)
							{
								$parent_stack = array_slice($parent_stack, $found_n+1);
								$curr_parent = ($parent_stack?reset($parent_stack):$this);
							}
						}
					}
				}
				
				$obj = new html();
				$obj->tag = $name;
				$obj->tag_block = $tag_block;
				$obj->parent = $curr_parent;
				$obj->parent->children[] = $obj;
				
				if ($name=='?xml') $is_xml = true;
				
				if (($is_xml && $name=='?xml') || (!$is_xml && (in_array($name, HTML_ELEMENTS_VOID) || preg_match('#(\s|<\w+)/\s*>#', $tag_block))))
				{
					// тег, "не-имеющий-закрывающего"
				}
					else
				{
					// обычный тег
					$curr_parent = $obj;
					array_unshift($parent_stack, $curr_parent); // добавить в начало массива
				}
			}
				else
			{
				// тег закрылся (из тех, что ранее были открыты)
				if (!$is_xml && in_array($name, HTML_ELEMENTS_SPECIAL))
				{$special_opened = '';}
				$n = 0;
				$was_table = $found = false;
				// уходим вверх по списку в поисках уровня, который этот тег пытается закрыть
				foreach ($parent_stack as $e)
				{
					if ($e->tag===$name)
					{
						$parent_stack = array_slice($parent_stack, $n+1);
						$curr_parent = ($parent_stack?reset($parent_stack):$this);
						$e->closer = $tag_block;
						$found = true;
						break;
					}
					elseif ($e->tag=='table')
					{
						// а также внутри div'а нельзя закрыть вышестоящие теги, кроме таблички (работает, но так лучше не делать)
						//  || ($e->tag=='div' && $name!='table') 
					
						// внутри таблички нельзя закрыть вышестоящие теги
						break;
					}
					$n++;
				}
				// закрылся не открывавшийся тег - делаем текстовым узлом
				if (!$found)
				{
					$x_obj = new html();
					$x_obj->tag = '#text';
					$x_obj->tag_block = $tag_block;
					$x_obj->parent = $curr_parent;
					$x_obj->parent->children[] = $x_obj;	
				}
			}
		}
		$curr_parent = ($parent_stack?reset($parent_stack):$this);
		if (!$comment)
		{$this->try_add_text_node($html, $last_opened_or_closed_tag_offset, strlen($html), $curr_parent);}
		// вывернуть содержимое, если заменяется outerHTML
		if (!$only_inner)
		{$this->replace($this->children);}
			else
		{$this->invalidate();}
	}
	
	protected function calc_offsets_recurs($tag, &$offset, &$res)
	{
		$tag->offset = $offset;
		$offset += strlen($tag->tag_block);
		$res .= $tag->tag_block;
		foreach ($tag->children as $tag2)
		{$this->calc_offsets_recurs($tag2, $offset, $res);}
		$offset += strlen($tag->closer);
		$res .= $tag->closer;
	}
	
	protected function iterate_recurs(&$stack, &$rewind, $callback)
	{
		// с использованием рекурсии работает на 20% быстрее, чем если от нее избавиться
		$level = count($stack)-1;
		do {
			if (is_int($rewind) && $rewind < $level && $level > 0) return;
			foreach ($this->children as $child)
			{
				// $stack - это родители текущего элемента ($child) без него самого
				$c = ['level'=>$level, 'stack'=>&$stack, ];
				$res = $callback($child, $c);
				if ($res) return true;
				$rewind = $c['rewind_level'];
				if (is_int($rewind)) continue 2;
				$stack[] = $child;
				if (!$c['skip'] && $child->iterate_recurs($stack, $rewind, $callback))
				{return true;}
				array_pop($stack);
			}
			break;
		} while (true);
	}
		
	// protected function iterate_recurs_old($node, $callback)
	// {
		// без рекурсии работает медленнее! (как ни странно)
		// $stack = [['node' => $node, ], ];
		// while ($stack)
		// {
			// $level = count($stack)-1;
			// $cur = &$stack[$level];
			// if (!is_array($cur['iter']))
			// {
				// $cur['iter'] = $cur['node']->children;
				// reset($cur['iter']);
			// }
			// if ($node = current($cur['iter']))
			// {
				// $c = ['level'=>$level, 'stack'=>&$stack, ];
				// if ($res = ($callback)($node, $c))
				// {return $res;}
				// if (is_int($cl = $c['continue_level']))
				// {
					// $stack = array_slice($stack, 0, max(0, min($cl, $level)));
					// continue;
				// }
				// if (is_int($rl = $c['rewind_level']))
				// {
					// if ($rl < $level)
					// {
						// unset($cur);
						// $rl = max(0, $rl);
						// $stack = array_slice($stack, 0, $rl+1);
					// }
					// $stack[count($stack)-1]['iter'] = NULL;
					// continue;
				// }
				// if (!$c['skip'])
				// {$stack[] = ['node' => $node, ];}
				// next($cur['iter']);
			// }
				// else
			// {array_pop($stack);}
		// }
	// }
	
	protected function try_add_text_node($html, $prev_offset, $offset, $curr_parent)
	{
		if ($offset-$prev_offset <= 0) return;
		$t_obj = new html();
		$t_obj->tag = '#text';
		$t_obj->tag_block = substr($html, $prev_offset, $offset-$prev_offset);
		$t_obj->parent = $curr_parent;
		$t_obj->parent->children[] = $t_obj;
	}
}


	
/*	Абсолютизирует URL.
		$rel_url - исходный относительный (или абсолютный) URL
		$base_url - абсолютный URL страницы, где был найден исходный URL (можно с http:// или без).
	Возвращает абсолютный URL.
	Работает согласно стандартам, так же, как это делают современные браузеры. 
	Если $base_url имеет неизвестную схему URL (отличающуюся от 'http', 'https', 'ftp'), либо если она отсутствует, то она будет заменена на 'http'.
	Если $rel_url содержит email-адрес или другую неизвестную схему URL (javascript, mailto, skype, итд), то функция вернет $base_url без изменений.
	А именно, поддерживаются:
		- пустой относительный URL (означает текущий адрес)
		- использование схемы URL из базового URL (когда $rel_url начинается на "//")
		- относительные в рамках текущей папки
		- относительные (содержащие "./" и "/./"), означает адрес текущей директории
		- относительные с переходом вверх по папке (содержат "../")
		- относительные от корня (начинаются на "/")
		- относительные от знака вопроса (начинаются на "?")
		- относительные от хеша (начинаются на "#")
	Функция хорошо протестирована.
*/
function url_abs($rel_url, $base_url)
{
	$rel_url = trim($rel_url);
	if (!preg_match('#^(https?|ftp)://#i', $base_url))
	{$base_url = 'http://'.$base_url;}
	if (preg_match('#^//[\w\-]+\.[\w\-]+#i', $rel_url))
	{$rel_url = parse_url($base_url, PHP_URL_SCHEME).':'.$rel_url;}
	if (!strlen($rel_url))
	{return $base_url;}
	if (preg_match('#^(https?|ftp)://#i', $rel_url))
	{return $rel_url;}
	if (preg_match('#^[a-z]+:#i', $rel_url))
	{return $base_url;}
	if (in_array($rel_url[0], ['?', '#']))
	{return reset(explode($rel_url[0], $base_url, 2)).$rel_url;}
	$p = parse_url($base_url);
	$pp = (($rel_url[0]=='/')?'':preg_replace('#/[^/]*$#', '', $p['path']));
	$abs = $p['host'].$pp.'/'.$rel_url;
	if (!preg_match('#^(https?|ftp)$#i', $p['scheme']))
	{$p['scheme'] = 'http';}
	if (preg_match('#^(.*?)([\?\#].*)$#s', $abs, $m))
	{$abs = $m[1];}
	do {$abs = preg_replace(['#(/\.?/)#', '#/(?!\.\.)[^/]+/\.\./#'], '/', $abs, -1, $n);}
	while ($n>0);
	do {$abs = preg_replace('#/\.\./#', '/', $abs, -1, $n);}
	while ($n>0);
	$abs .= $m[2];
	$s = $p['scheme'].'://'.$abs;
	return $s;
}

/*	Заменить "голые" домены и URL на содержимое, задаваемое функцией, либо на пустую строку.
	Ищет очень точно URLы и домены. Неправильные домены игнорирует.
	Учитывает newTLD домены и Punycode-домены. Учитывает домены с точкой на конце.
	Поможет очистить строку от *текстовых* внешних ссылок и упоминаний сторонних доменов.
		$s - строка
		$func - функция вида: function($url){ ... }
			Должна вернуть строку, которой будет заменен найденный URL.
			Если не задана, то домены будут просто удалены.
	Внимание! Замены не производится в следующих случаях:
		- URL находящиеся внутри HTML-атрибутов: "href", "src", "srcset", "action", "data", "poster", "cite"
		- URL находящиеся внутри CSS-конструкции: url("...")  либо  url(...)
		- URL заключенные в некоторый HTML-тег, т.е. находящиеся внутри (определяются эвристически)
*/
function url_replace($s, $func = NULL)
{
	// + полное доменное имя не может быть длиннее 255 символов
	$r = '#(?<=[^\w\.\-]|^)((https?:)?//)?[a-z\d\-]{1,63}(\.[a-z\d\-]{1,63}){0,5}\.(?!aac|ai|aif|apk|arj|asp|aspx|atom|avi|bak|bat|bin|bmp|cab|cda|cer|cfg|cfm|cgi|class|cpl|cpp|cs|css|csv|cur|dat|db|dbf|deb|dll|dmg|dmp|doc|drv|ejs|eot|eps|exe|flv|fnt|fon|gif|gz|htm|icns|ico|img|ini|iso|jad|jar|java|jpeg|jpg|js|json|jsp|key|lnk|log|mdb|mid|midi|mkv|mov|mpa|mpeg|mpg|msi|odf|odp|ods|odt|ogg|otf|part|pdf|php|pkg|pls|png|pps|ppt|pptx|psd|py|rar|rm|rpm|rss|rtf|sav|sql|svg|svgz|swf|swift|sys|tar|tex|tgz|tif|tmp|toast|ttf|txt|vb|vcd|vob|wav|wbmp|webm|webp|wks|wma|wmv|woff|wpd|wpl|wps|wsf|xhtml|xlr|xls|xml|zip)(xn--[a-z\d\-]{1,63}|[a-z]{2,11})(:\d+)?((?=[^\w\.\-]|$)[^\[\]<>"\']*?(?=$|[^a-z\-\d/\.])|(?=\.$|\.\s|\.\.))#ui';
	if (!preg_match_all($r, $s, $m, PREG_OFFSET_CAPTURE)) return $s;
	$m = $m[0];
	if (!$func) $func = function(){return;};
	foreach ($m as &$mm)
	{
		$x = max(0, $mm[1]-4000);
		$q = substr($s, $x, $mm[1]-$x);
		$q2 = substr($s, $mm[1]+strlen($mm[0]), 4000);
		$host = parse_url('http://'.preg_replace(['#^https?:#i', '#^//#'], '', $mm[0]), PHP_URL_HOST);
		if (strlen($host)>255 || 
			preg_match('#(=["\']|(\s(href|src|srcset|action|data|poster|cite)=|\burl\()["\']?)[^"\'<>\(\)\n]*$#i', $q) ||
			(preg_match('#>\s*$#', $q) && preg_match('#^\s*</#', $q2))
		) continue;
		$mm['func'] = $func($mm[0]);
	}
	unset($mm);
	$prev = 0; $res = '';
	foreach ($m as &$mm)
	{
		$res .= substr($s, $prev, $mm[1]-$prev).(array_key_exists('func', $mm)?$mm['func']:$mm[0]);
		$prev = $mm[1]+strlen($mm[0]);
	}
	unset($mm);
	$res .= substr($s, $prev);
	return $res;
}

/*	Скачивает контент по указанному URL.
	Использует CURL, а если его нет, то file_get_contents().
		$url - ссылка для запроса
		$allow_404 - возвращать содержимое даже для страниц с кодом ответа 404 (если отключено, то будет возвращать NULL).
		$timeout - таймаут запроса
		$referer - реферер для перехода
		$headers - массив доп. HTTP заголовков
	Возвращает массив вида:
		['исходный код страницы', 'содержимое HTTP-заголовка Content-Type', 'текст ошибки']
*/
function cu_download($url, $allow_404 = true, $timeout = 20, $referer = NULL, $headers = [])
{
	$url = preg_replace('/#.*/', '', $url);
	if (!function_exists('curl_init'))
	{
		$res = @file_get_contents($url, false, stream_context_create(['http'=>compact('timeout'), ]));
		if ($res===FALSE)
		{
			$err = error_get_last();
			$err = $err['message'];
			$ct = [];
		}
			else
		{
			$ct = $http_response_header;
			if (!$ct) $ct = [];
			$ct = preg_grep('#^content-type:#i', $ct);
			if ($ct = reset($ct))
			{$ct = rtrim(preg_replace('#^[^:]+:\s*#', '', $ct), "\r\n");}
		}
	}
	elseif ($ch = curl_init())
	{
		@curl_setopt_array($ch, [
			CURLOPT_URL => $url,
			CURLOPT_REFERER => (($referer!==NULL)?$referer:$url),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_TIMEOUT => $timeout,
			CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:85.0) Gecko/20100101 Firefox/85.0',
			CURLOPT_HEADER => 1,
			CURLOPT_HTTPHEADER => array_merge([
				'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8', 
				'Accept-Language: en-us,en;q=0.5',
			], $headers),
			CURLOPT_ENCODING => 'gzip, deflate',
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
			// CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0, // требует curl 7.38+
		]);
		$res = curl_exec($ch);
		$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if (!$allow_404 && $code==404)
		{$res = NULL;}
		$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		$ct = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
		$header = substr($res, 0, $header_size);
		$res = substr($res, $header_size);
		$error = curl_error($ch);
		// echo 'CURL ERROR: '.$err."\n\n";
		curl_close($ch);
	}
	return [$res, $ct, $error];
}

