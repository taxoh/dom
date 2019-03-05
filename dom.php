<?php
/*	Простой и быстрый HTML DOM парсер/редактор.	
	Содержит класс DOM-узла (html), а также удобные функции:
	
		url_abs() - абсолютизация URL
		url_replace() - замена вашим колбеком голых доменов и URL в тексте, находящихся вне href-атрибутов
		cu_download() - скачивание страниц с приемом заголовка Content-Type
	
	Пример использования:
	
		$p = new html();
		$p->inner(file_get_contents('/tmp/somefile.html'));
		$p->iterate(function($node, $level){
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
		- класс html можно пронаследовать и что-то модифицировать или добавить свойства/методы
		- "открепленный узел" свое состояние не меняет, с ним можно продолжать работу, но стоит понимать, что он помнит своего (прежнего) родителя и находится в невалидном состоянии (значение parent у него невалидно). Сделать его валидным снова можно передав его в качестве параметра любой из функций:
			- append()
			- prepend()
			- replace()
			- replace_inner()
		- поддерживается и корректно обрабатывается операция clone
		- поддерживает красивый var_dump() для узлов (без гигантских листингов)
	
*/


// списки HTML-элементов, разделенные на группы.

// все элементы: cобраны все теги всех стандартов вплоть до HTML5 включительно, а также устаревшие и (почти все) нестандартные теги
define('HTML_ELEMENTS_ALL', ['a', 'abbr', 'acronym', 'address', 'applet', 'area', 'article', 'aside', 'audio', 'b', 'base', 'basefont', 'bdi', 'bdo', 'bgsound', 'big', 'blink', 'blockquote', 'body', 'br', 'button', 'canvas', 'caption', 'center', 'cite', 'code', 'col', 'colgroup', 'command', 'data', 'datalist', 'dd', 'del', 'details', 'dfn', 'dialog', 'dir', 'div', 'dl', 'dt', 'em', 'embed', 'fieldset', 'figcaption', 'figure', 'font', 'footer', 'form', 'frame', 'frameset', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'head', 'header', 'hgroup', 'hr', 'html', 'i', 'iframe', 'img', 'input', 'ins', 'isindex', 'kbd', 'keygen', 'label', 'legend', 'li', 'link', 'main', 'map', 'mark', 'marquee', 'menu', 'menuitem', 'meta', 'meter', 'nav', 'nobr', 'noembed', 'noframes', 'noscript', 'object', 'ol', 'optgroup', 'option', 'output', 'p', 'param', 'picture', 'plaintext', 'pre', 'progress', 'q', 'rp', 'rt', 'ruby', 's', 'samp', 'script', 'section', 'select', 'small', 'source', 'span', 'strike', 'strong', 'style', 'sub', 'summary', 'sup', 'table', 'tbody', 'td', 'textarea', 'tfoot', 'th', 'thead', 'time', 'title', 'tr', 'track', 'tt', 'u', 'ul', 'var', 'video', 'wbr', 'xmp', ]);
// блочные элементы
define('HTML_ELEMENTS_BLOCK', ['address', 'article', 'aside', 'blockquote', 'center', 'dd', 'details', 'dir', 'div', 'dl', 'dt', 'embed', 'fieldset', 'figcaption', 'figure', 'footer', 'form', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hgroup', 'hr', 'isindex', 'li', 'main', 'marquee', 'nav', 'ol', 'p', 'pre', 'rt', 'section', 'summary', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr', 'ul', 'xmp', ]);
// строчные элементы
define('HTML_ELEMENTS_SPAN', ['a', 'abbr', 'acronym', 'applet', 'audio', 'b', 'bdi', 'bdo', 'big', 'blink', 'br', 'button', 'canvas', 'cite', 'code', 'command', 'data', 'del', 'dfn', 'dialog', 'em', 'figcaption', 'figure', 'font', 'i', 'iframe', 'img', 'input', 'ins', 'kbd', 'label', 'mark', 'meter', 'nobr', 'object', 'output', 'picture', 'plaintext', 'pre', 'progress', 'q', 'rp', 'ruby', 's', 'samp', 'select', 'small', 'span', 'strike', 'strong', 'sub', 'sup', 'textarea', 'time', 'tt', 'u', 'var', 'video', ]);
// информационные и логические элементы, которые однозначно нельзя отнести к строчным, либо блочным (часто невидимые).
define('HTML_ELEMENTS_INFO', ['area', 'base', 'basefont', 'bgsound', 'body', 'caption', 'col', 'colgroup', 'datalist', 'frame', 'frameset', 'head', 'html', 'keygen', 'legend', 'link', 'map', 'menu', 'menuitem', 'meta', 'noembed', 'noframes', 'noscript', 'optgroup', 'option', 'param', 'script', 'source', 'style', 'title', 'track', 'wbr']);
// "выделители" (phrase tags): жирный, курсив и прочие косметические выделялки для текста
define('HTML_ELEMENTS_MARKS', ['abbr', 'acronym', 'b', 'big', 'cite', 'code', 'del', 'dfn', 'em', 'font', 'i', 'ins', 'kbd', 'mark', 's', 'samp', 'small', 'span', 'strike', 'strong', 'sub', 'sup', 'tt', 'u', 'q', 'var', ]);
// элементы, используемые внутри (полезного) контента. Без тега "span".
define('HTML_CONTENT_TAGS', ['a', 'abbr', 'acronym', 'audio', 'b', 'blockquote', 'br', 'cite', 'code', 'dd', 'del', 'dfn', 'dl', 'dt', 'em', 'embed', 'font', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'i', 'iframe', 'img', 'ins', 'kbd', 'li', 'mark', 'object', 'ol', 'p', 'param', 'picture', 'pre', 'q', 'rp', 'rt', 'ruby', 's', 'samp', 'small', 'source', 'strike', 'strong', 'sub', 'sup', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'time', 'tr', 'track', 'tt', 'u', 'ul', 'var', 'video', ]);
// элементы, которые всегда подразумевают перенос строки (т.е. не могут собой разделять слова одного предложения)
define('HTML_LINEBREAK_TAGS', ['audio', 'blockquote', 'br', 'cite', 'code', 'dd', 'dl', 'dt', 'embed', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'iframe', 'img', 'li', 'object', 'ol', 'p', 'picture', 'pre', 'ruby', 'table', 'td', 'th', 'ul', 'video', ]);
// элементы, разрешенные спецификацией внутри <p> 
define('HTML_ELEMENTS_PHRASING', ['a', 'abbr', 'area', 'audio', 'b', 'bdi', 'bdo', 'br', 'button', 'canvas', 'cite', 'code', 'command', 'datalist', 'del', 'dfn', 'em', 'embed', 'i', 'iframe', 'img', 'input', 'ins', 'kbd', 'keygen', 'label', 'map', 'mark', 'math', 'meter', 'noscript', 'object', 'output', 'progress', 'q', 'ruby', 's', 'samp', 'script', 'select', 'small', 'span', 'strong', 'sub', 'sup', 'svg', 'textarea', 'time', 'u', 'var', 'video', 'wbr', 'text']);
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
// используются здешним парсером: теги, не имеющие закрывающих. В режиме XML этот список не учитывается.
define('HTML_ELEMENTS_VOID', ['!doctype', '?xml', 'area', 'base', 'basefont', 'bgsound', 'br', 'col', 'command', 'embed', 'frame', 'hr', 'img', 'input', 'isindex', 'keygen', 'link', 'meta', 'nextid', 'param', 'source', 'track', 'wbr', ]);
// используются здешним парсером: теги, которые будучи открытыми не воспринимают других тегов, в том числе комментарии. В режиме XML этот список не учитывается.
define('HTML_ELEMENTS_SPECIAL', ['script', 'style']);


// класс HTML-узла
class html {
	
	// свойства закомментированы ради уменьшения расхода памяти.
	// тем не менее, они могут присутствовать когда это необходимо и здесь даны описания для них.
	
	// public $tag;				// тип тега (например, 'div'). Всегда в нижнем регистре. Текстовый узел - '#text', комментарий - '#comment', корневой - NULL.
	// public $tag_block;		// открывашка от тега, например '<a href="http://..." someattr="123">'. Для узлов типа '#text' и '#comment' в этом поле хранится сам текст (либо HTML-комментарий целиком).
	// public $closer;			// закрывашка от тега, например '</a>'. Может быть пустым, когда закрывашка отсутствует (по тем или иным причинам).
	// public $parent;			// ссылка на родительский узел. Для корневого - NULL.
	// public $offset;			// числовое строковое смещение до тега в получившемся документе. Запоняется при вызове calc_offsets().
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
		
			:contains("любой текст") - регистронезависимая проверка наличия текста внутри тега (включая outerText)
			:notcontains("любой текст") - регистронезависимая проверка отсутствия текста внутри тега (включая outerText)
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
		
		А также:
			
			"!=" - (для атрибутов) возможность выборки атрибута "не равного" заданному значению
			[!...] - (для атрибутов) инверсия требования для атрибута. Т.е. срабатывать будет в прямо противоположных случаях.
			[!myattr] - (для атрибутов) как частный пример случая выше: требование отсутствия атрибута с именем "myattr".
			[!myattr~=flower] - (для атрибутов) еще один частный пример случая выше: атрибут "myattr" не должен содержать в значении фразу "flower".
			@text - (в качестве имени тега) выборка текстовых узлов
			@comment - (в качестве имени тега) выборка узлов-комментариев
			@attr_href - (в качестве имени тега, а также наряду с ".class" и "#id" суффиксами) собрать атрибуты с заданным именем. В данном случае для примера это "href" (можно любые имена). Внимание! Результат будет содержать открепленные текстовые узлы, представляющие из себя значения найденных атрибутов в декодированном виде (т.к. атрибуты сами по себе узлами не являются).
		
		Также имеются псевдоклассы и псевдоэлементы, которые в принципе невозможно прочитать/изменить без js: в таких случаях поведение будет словно соответствующая часть селектора отсутствует. Селекторы читаются согласно стандарту, поэтому можно подавать взятые прямо со стилей HTML-страниц из сети. Чтение и выполнение хорошо оттестированы и прекрасно работают во всех возможных режимах.
		
		Есть некоторые ограничения:
		
			- имена атрибутов в css-выражении (в секции требований к атрибутам) могут состоять только из символов: [\w\-]
			- псевдокласс ":not" (из стандартной css-спецификации) поддерживается ограниченно: можно только одиночные имена тегов, либо одиночные классы, либо одиночные ID, а также можно несколько через запятую. Пример: 
		
				:not(span)
				:not(.fancy)
				:not(.crazy, .fancy)
			
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
		{$ext = '|odd|even|hidden|header|first|last|(?:eq|lt|gt)\(\s*\-?\d+\s*\)|(?:not)?contains\((?:"[^"]+"|\'[^\']+\'|[^\)]+)\)';}
		$gr = '(?P<tag>(?:@?[\w\-]*|\*)(?:[#\.@][\w\-]+)*)'.
			'(?P<attrs>(?:\[.*?\])*)'.
			'(?P<pseudo>(?:\s*(?::(?:active|checked|disabled|empty|enabled|first-child|first-of-type|last-of-type|focus|hover|in-range|invalid|last-child|link|not\([\w\-#\.,\s]+\)|only-child|target|valid|visited|root|read-write|lang\([\w\-]+\)|read-only|optional|out-of-range|only-of-type'.$ext.'|nth-of-type'.$nth.'|nth-last-of-type'.$nth.'|nth-last-child'.$nth.'|nth-child'.$nth.')|::(?:after|before|first-letter|first-line|selection)))*)';
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
						switch ($v{0})
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
									$v{0} = '#';
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
					foreach (preg_split('#::?#', $xv['pseudo']) as $v)
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
					
					$fnc = function($e, $level)use($allow_recurse, &$found, $iter_c, &$req, &$need, $prev_combinator, $i_node){
						// пустой цикл - из него будем выскакивать когда не найдено
						do {
							if ($e->tag!==NULL)
							{
								if ($req['tag'] && $e->tag!=$req['tag']) continue;
								if (!$req['texcom'] && $e->tag{0}=='#') continue;
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
									$conti2 = false;
									switch ($p)
									{
										case 'first-child':
										case 'last-child':
											$z = $e->parent->children;
											if ($p=='last-child') $z = array_reverse($z);
											foreach ($z as $cc)
											{
												if ($cc->tag{0}!='#') 
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
												if ($cc->tag{0}=='#' || $cc->tag != $e->tag) continue;
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
												if ($cc->tag{0}=='#') continue;
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
											$ps = explode(',', $ps);
											foreach ($ps as $v)
											{
												$v = trim($v);
												$tn = substr($v, 1);
												$conti2 = ($e->tag==$v || ($v{0}=='#' && $a['id']==$tn));
												if (!$conti2 && $v{0}=='.')
												{
													if ($e_classes===NULL)
													{$e_classes = (isset($a['class'])?preg_split('#\s+#', $a['class']):[]);}
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
												if ($prev_combinator=='+' && $cc->tag{0}!='#') $start = false;
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
						foreach ($i_node->children as $e)
						{($fnc)($e, 0);}
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
			if (!$e->closer && $e->tag{0}!='#' && !in_array($e->tag, HTML_ELEMENTS_VOID))
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
			if ($e->tag{0}!='#' || strlen(trim($e->tag_block)))
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
	
	/*	Оставить внутри текущего элемента теги только заданных типов.
			$tags - массив имен тегов, например: ['table', 'tr', 'td']
	*/
	public function tag_filter($tags)
	{
		$qkey = 0;
		$queue = array_values($this->children);
		while ($e = $queue[$qkey++])
		{
			if ($e->tag{0}!='#' && !in_array($e->tag, $tags))
			{
				$e->remove();
				continue;
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
			if ($e->tag{0}!='#')
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
	
	// получить список детей ($this->children) текущего узла исключая текстовые
	public function children_notext()
	{
		$res = [];
		foreach ($this->children as $v)
		{if ($v->tag!=='#text') $res[] = $v;}
		return $res;
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
		
		Параметры:
		
			$callback - замыкание или имя функции. Имеет формат:
				function($node, $level, &$skip){
					// ...
				}
				, где:
					$node - очередной узел
					$level - (число) уровень вложенности (целое число от 0 и выше)
					&$skip - (bool) если поставить в true, то обход потомков данного узла произведен не будет. Тем самым можно пропускать ненужные ветви.
			
			Если колбек вернет TRUE, то обход будет полностью прекращен. При этом возвращенное колбеком значение будет передано как результат вызова функции.
		
		Если важна скорость и не важен порядок обработки, то лучше делать обход полностью вручную. Пример:
		(порядок перебора будет другой!)
		
			$qkey = 0;
			// здесь аккуратнее: если передать $node->children, то его узлы могут иметь номера не по порядку, а это чревато! (поэтому сделайте array_values())
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
	{$this->iterate_recurs($this, false, $callback);}
	
	// тоже самое что iterate(), но обход задом наперед (начиная с конца).
	public function iterate_reverse($callback)
	{$this->iterate_recurs($this, true, $callback);}
	
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
			$res = [];
			if ($this->tag{0}=='#')
			{return $res;}
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
			if ($this->tag{0}=='#' || !preg_match('#^(<[^\s<>]+\s*).*?([\s/]*>)$#s', $this->tag_block, $m))
			{return;}
			$z = [];
			foreach ($values as $k=>$v) $z[] = $k.'="'.htmlspecialchars($v).'"';
			$this->tag_block = ($z?$m[1]:rtrim($m[1])).(($z && !preg_match('#\s$#', $m[1]))?' ':'').implode(' ', $z).$m[2];
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
	
	/*	Удалить текущий узел.
		В случае корневого узла это очистит его содержимое.
		
		Удаляет текущий элемент из списка детей у родителя текущего элемента.
		(!) Текущий узел становится открепленным.
	*/
	public function remove()
	{
		if ($this->parent)
		{
			if (($num = array_search($this, $this->parent->children, true))===FALSE)
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
		Параметры:
	
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
	
	/*	Очистить содержимое тега. Сам тег остается на месте.
		
		Выполняются следующие операции:
			- очищает список детей текущего узла
			
		(!) Дочерние узлы становятся открепленными.
	*/
	public function clear()
	{
		if ($this->tag{0}=='#')
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
			return ($this->c_inner = $this->get(true));
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
			return ($this->c_outer = $this->get(false));
		}
			else
		{$this->set($html, false);}
	}
	
	/*	Получить outerHTML для списка разрозненных/сторонних узлов.
			$nodes - массив узлов
		Возвращает строку.
		Функция статическая - может быть вызвана без создания класса.
	*/
	static public function render($nodes)
	{
		$res = '';
		foreach ($nodes as $elem)
		{html::get_content($elem, $res);}
		return $res;
	}
	
	/*	Упаковать список узлов в общий (корневой) тег.
			$nodes - массив узлов
		Вернет объект узла.
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
		$res = '';
		$this->get_stripped_content($this, $res);
		return ($this->c_strip = trim(strip_tags($res))); // strip_tags() всё равно нужен
	}
	
	/*	Изменить тип тега.
		Внимание: тип не может быть изменен на '#text' или '#comment', а также на "не-имеющий-закрывающего" тега тип. Также тип нельзя менять корневому узлу.
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
		do {
			$c = $c->parent;
		} while ($c->tag!==NULL);
		return $c;
	}
		
	/*	Получить соседа слева от текущего.
		Вернет NULL если соседа нет.
		Операция медленная.
	*/
	public function prev_sibling()
	{
		foreach ($this->parent->children as $c)
		{
			if ($c===$this) return $prev;
			$prev = $c;
		}
	}
	
	/*	Получить соседа справа от текущего.
		Вернет NULL если соседа нет.
		Операция медленная.
	*/
	public function next_sibling()
	{
		foreach ($this->parent->children as $c)
		{
			if ($found) return $c;
			if ($c===$this) $found = true;
		}
	}
	
	/*	Найти узлы, находящиеся между двумя узлами, при условии что оба узла имеют общего прямого родителя и расположены в прямом порядке.
		Вернет массив узлов. Если условие не соблюдено, либо узлов нет, то массив будет пустым.
	*/
	static public function between($node1, $node2)
	{
		$res = [];
		foreach ($node1->parent->children as $c)
		{
			if ($c===$node2) break;
			if ($start) $res[] = $c;
			if ($c===$node1) $start = true;
		}
		return $res;
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
		foreach ($arr5 as $v)
		{
			list($n, $v) = $v;
			$z[$n][] = $v;
		}
		list($arr1, $arr2) = $z;
		if (count($arr1)!=count($arr2))
		{
			// if ($from_second_too && !$arr1)
			// {$arr1 = $arr2;}
			if ($arr1)
			{
				$x = reset($arr1);
				$res[] = $x->parent;
				return;
			}
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
	
	protected static function get_content($tag, &$res)
	{
		$res .= $tag->tag_block;
		foreach ($tag->children as $tag2)
		{html::get_content($tag2, $res);}
		$res .= $tag->closer;
	}
	
	protected static function get_stripped_content($tag, &$res)
	{
		if ($tag->tag==='#text')
		{$res .= $tag->tag_block;}
			else
		{
			foreach ($tag->children as $tag2)
			{html::get_stripped_content($tag2, $res);}
		}
	}
	
	protected function get($only_inner = true)
	{
		$res = '';
		if (!$this->parent || ($only_inner && $this->tag{0}!='#'))
		{
			foreach ($this->children as $elem)
			{$this->get_content($elem, $res);}
		}
			else
		{$this->get_content($this, $res);}
		return $res;
	}
	
	protected function set($html, $only_inner = true)
	{
		// вырожденная ситуация: когда корневому ставят пустой контент, то нужно добавить хотя бы один пустой текстовый узел
		if ($this->tag===NULL && ((string)$html)==='')
		{
			$x_obj = $this->new_tag();
			$x_obj->tag = '#text';
			$x_obj->tag_block = '';
			$x_obj->parent = $this;
			$x_obj->parent->children = [$x_obj];
			$this->invalidate();
			return;
		}
		$is_xml = false;
		
		$curr_parent = $this;
		if ($this->tag{0}=='#')
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
			$is_opened = ($tag_block{1}!='/');
			
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
				$comment = $this->new_tag();
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
				if (!$is_xml && in_array($name, HTML_ELEMENTS_SPECIAL))
				{$special_opened = $name;}
					else
				{
					// делается проверка родителей для текущего тега. Обнаруженный "неправильный" родитель будет закрыт.
					// ищется максимально топовый "неправильный" родитель.
					// $stoppers - список тегов, *до* которых родители (при пути наверх) будут проверяться: если встречен - проверка прерывается.
					$stoppers = $blocked_parents = [];
					switch ($name)
					{
						case 'p':
						case 'a':
						case 'option':
						case 'optgroup':
						case 'html':
							// если среди родителей у элементов этого типа попадается элемент такого же типа, то этот родитель закрывается
							$blocked_parents = [$name];
						break;
						case 'li':
						case 'dd':
						case 'dt':
							$stoppers = ['ul', 'ol', 'dl', 'dir'];
							$blocked_parents = ['li', 'dd', 'dt'];
						break;
						case 'head':
						case 'body':
							$blocked_parents = ['head', 'body']; // т.е. head будет закрыт, если он попытается быть родителем для body (и наоборот)
						break;
						case 'td':
						case 'th':
							$blocked_parents = ['td', 'th', 'caption', 'colgroup', 'col'];
							$stoppers[] = 'table';
						break;
						case 'tr':
							$blocked_parents = ['tr', 'td', 'th', 'caption', 'colgroup', 'col'];
							$stoppers[] = 'table';
						break;
						case 'thead':
						case 'tbody':
						case 'tfoot':
							$blocked_parents = ['thead', 'tbody', 'tfoot', 'td', 'tr', 'th', 'caption', 'colgroup', 'col'];
							$stoppers[] = 'table';
						break;
						case 'caption':
						case 'colgroup':
							$blocked_parents = ['caption', 'colgroup', 'col'];
							$stoppers[] = 'table';
						break;
						case 'col':
							$blocked_parents = ['col', 'tr', 'td', 'th', 'caption'];
							$stoppers[] = 'table';
						break;
					}
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
				
				$obj = $this->new_tag();
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
					$x_obj = $this->new_tag();
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
		
	protected function iterate_recurs($node, $reverse, $callback)
	{
		$stack = [['node' => $node, ], ];
		while ($stack)
		{
			$level = count($stack)-1;
			$cur = &$stack[$level];
			if (!$cur['iter'])
			{
				$z = $cur['node']->children;
				if ($reverse) $z = array_reverse($z);
				$cur['iter'] = new ArrayIterator($z);
			}
			if ($node = $cur['iter']->current())
			{
				$skip = false;
				if ($res = ($callback)($node, $level, $skip))
				{return $res;}
				$cur['iter']->next();
				if (!$skip)
				{$stack[] = ['node' => $node, ];}
			}
				else
			{array_pop($stack);}
		}
	}
	
	protected function new_tag()
	{
		$c = get_class($this);
		return new $c();
	}
	
	protected function try_add_text_node($html, $prev_offset, $offset, $curr_parent)
	{
		if ($offset-$prev_offset <= 0) return;
		$t_obj = $this->new_tag();
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
	if (in_array($rel_url{0}, ['?', '#']))
	{return reset(explode($rel_url{0}, $base_url, 2)).$rel_url;}
	$p = parse_url($base_url);
	$pp = (($rel_url{0}=='/')?'':preg_replace('#/[^/]*$#', '', $p['path']));
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
	$res .= substr($s, $prev);
	return $res;
}

/*	Скачивает контент по указанному URL.
	Использует CURL, а если его нет, то file_get_contents().
		$url - ссылка для запроса
		$allow_404 - возвращать содержимое даже для страниц с кодом ответа 404 (если отключено, то будет возвращать NULL).
		$timeout - таймаут запроса
	Возвращает массив вида:
		['исходный код страницы', 'содержимое HTTP-заголовка Content-Type', 'текст ошибки']
*/
function cu_download($url, $allow_404 = true, $timeout = 20)
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
			CURLOPT_REFERER => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_TIMEOUT => $timeout,
			CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:64.0) Gecko/20100101 Firefox/64.0',
			CURLOPT_HEADER => 1,
			CURLOPT_HTTPHEADER => [
				'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8', 
				'Accept-Language: en-us,en;q=0.5',
			],
			CURLOPT_ENCODING => 'gzip, deflate',
			CURLOPT_FOLLOWLOCATION => true,
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