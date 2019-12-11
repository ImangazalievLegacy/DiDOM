<?php 

namespace ProstoPost\DiDom;

class DiDomDocument {

	const TYPE_XPATH  = 'TYPE_XPATH';
    const TYPE_CSS    = 'TYPE_CSS';

	public $document = null;

	public function __construct($html = null, $isFile = true)
	{

        $this->document = new \DOMDocument();	

		if ($html)
		{

			if ($isFile)
			{

				$this->loadHtmlFile($html);

			}
			else
			{

				$this->loadHtml($html);

			}

		}

	}

    public function appendChild (\DOMNode $newnode)
    {

        $cloned = $newnode->cloneNode(TRUE);
        $temp =  $this->document->importNode($cloned,TRUE);

        $this->document->appendChild($temp);

    }

	public function removeNoise($html)
	{

		$noisePatterns = array(

			// strip out comments
	       "'<!--(.*?)-->'is",
	        // strip out cdata
	       "'<!\[CDATA\[(.*?)\]\]>'is",
	        // Script tags removal now preceeds style tag removal.
	        // strip out <script> tags
	       "'<\s*script[^>]*[^/]>(.*?)<\s*/\s*script\s*>'is",
	       "'<\s*script\s*>(.*?)<\s*/\s*script\s*>'is",
	        // strip out <style> tags
	       "'<\s*style[^>]*[^/]>(.*?)<\s*/\s*style\s*>'is",
	       "'<\s*style\s*>(.*?)<\s*/\s*style\s*>'is",
	        // strip out preformatted tags
	       "'<\s*(?:code)[^>]*>(.*?)<\s*/\s*(?:code)\s*>'is",
	        // strip out server side scripts
	       "'(<\?)(.*?)(\?>)'s",
	        // strip smarty scripts
	       "'(\{\w)(.*?)(\})'s",

       );

		foreach ($noisePatterns as $pattern) {
			
			preg_replace($pattern, "", $html);

		}

		return $html;

	}

	public function loadHtml($html)
	{

		libxml_use_internal_errors(true);
		libxml_disable_entity_loader(true);

		$this->document->loadHtml($html);

		libxml_clear_errors();

        libxml_disable_entity_loader(false);
        libxml_use_internal_errors(false);

	}

	public function loadHtmlFile($filepath)
	{

		$html = file_get_contents($filepath);

		$this->loadHtml($html);

	}

    public function has($expression, $type = self::TYPE_CSS)
    {

        return $this->find($expression, $type) == null ? false : true;

    }

	public function find($expression, $type = self::TYPE_CSS)
	{

		if ($type === static::TYPE_CSS) {

             $expression = static::cssToXpath($expression);

        }

        $xpath = new \DOMXPath($this->document);

        $nodeList = $xpath->query($expression);

        $elements = array();

        if ($nodeList->length > 0)
        {

            foreach ($nodeList as $node) {
            
                $elements[] = new DiDomElement($node);

            }
            
            return $elements;

        }
        
        return null;

	}

    public function saveHtml()
    {

        return $this->document->saveHTML();

    }

    public function __toString()
    {

        return $this->saveHtml();

    }

    public function __invoke($expression)
    {

        return $this->find($expression);;

    }

    public function __call($method, array $params)
    {

        if (method_exists($this->document, $method))
        {

            return $this->document->$method($params);

        }
        else
        {

            throw new \BadMethodCallException("Method [$method] does not exist.");

        }

    }

	/**
     * Transform CSS expression to XPath
     *
     * @param  string $path
     * @return string
     */
    public static function cssToXpath($path)
    {
        $path = (string) $path;
        if (strstr($path, ',')) {
            $paths       = explode(',', $path);
            $expressions = array();
            foreach ($paths as $path) {
                $xpath = static::cssToXpath(trim($path));
                if (is_string($xpath)) {
                    $expressions[] = $xpath;
                } elseif (is_array($xpath)) {
                    $expressions = array_merge($expressions, $xpath);
                }
            }
            return implode('|', $expressions);
        }

        $paths    = array('//');
        $path     = preg_replace('|\s+>\s+|', '>', $path);
        $segments = preg_split('/\s+/', $path);
        foreach ($segments as $key => $segment) {
            $pathSegment = static::_tokenize($segment);
            if (0 == $key) {
                if (0 === strpos($pathSegment, '[contains(')) {
                    $paths[0] .= '*' . ltrim($pathSegment, '*');
                } else {
                    $paths[0] .= $pathSegment;
                }
                continue;
            }
            if (0 === strpos($pathSegment, '[contains(')) {
                foreach ($paths as $pathKey => $xpath) {
                    $paths[$pathKey] .= '//*' . ltrim($pathSegment, '*');
                    $paths[]      = $xpath . $pathSegment;
                }
            } else {
                foreach ($paths as $pathKey => $xpath) {
                    $paths[$pathKey] .= '//' . $pathSegment;
                }
            }
        }

        if (1 == count($paths)) {
            return $paths[0];
        }
        return implode('|', $paths);
    }

    /**
     * Tokenize CSS expressions to XPath
     *
     * @param  string $expression
     * @return string
     */
    protected static function _tokenize($expression)
    {
        // Child selectors
        $expression = str_replace('>', '/', $expression);

        // IDs
        $expression = preg_replace('|#([a-z][a-z0-9_-]*)|i', '[@id=\'$1\']', $expression);
        $expression = preg_replace('|(?<![a-z0-9_-])(\[@id=)|i', '*$1', $expression);

        // arbitrary attribute strict equality
        $expression = preg_replace_callback(
            '|\[@?([a-z0-9_-]+)=[\'"]([^\'"]+)[\'"]\]|i',
            function ($matches) {
                return '[@' . strtolower($matches[1]) . "='" . $matches[2] . "']";
            },
            $expression
        );

        // arbitrary attribute contains full word
        $expression = preg_replace_callback(
            '|\[([a-z0-9_-]+)~=[\'"]([^\'"]+)[\'"]\]|i',
            function ($matches) {
                return "[contains(concat(' ', normalize-space(@" . strtolower($matches[1]) . "), ' '), ' "
                     . $matches[2] . " ')]";
            },
            $expression
        );

        // arbitrary attribute contains specified content
        $expression = preg_replace_callback(
            '|\[([a-z0-9_-]+)\*=[\'"]([^\'"]+)[\'"]\]|i',
            function ($matches) {
                return "[contains(@" . strtolower($matches[1]) . ", '"
                     . $matches[2] . "')]";
            },
            $expression
        );

        // Classes
        if (false === strpos($expression, "[@")) {
            $expression = preg_replace(
                '|\.([a-z][a-z0-9_-]*)|i',
                "[contains(concat(' ', normalize-space(@class), ' '), ' \$1 ')]",
                $expression
            );
        }

        /** ZF-9764 -- remove double asterisk */
        $expression = str_replace('**', '*', $expression);

        return $expression;
    }

    public function nodeListToArray($nodeList)
    {

        $array = array();

        foreach ($nodeList as $node) {
            
            $array[] = $node;

        }

        return $array;

    }

}