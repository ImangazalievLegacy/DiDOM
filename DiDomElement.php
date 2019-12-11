<?php 

namespace ProstoPost\DiDom;

class DiDomElement {

	public $domElement;

	public function __construct($domElement)
	{

		$this->domElement = $domElement;

	}

	public function innerText()
	{

		return $this->domElement->textContent;

	}

	public function toDocument()
	{

		$document = new DiDomDocument();

		$document->appendChild($this->domElement);

		return $document;

	}

	public function find($expression, $type = DiDomDocument::TYPE_CSS)
	{

		return $this->toDocument()->find($expression, $type);

	}

	public function has($expression, $type = DiDomDocument::TYPE_CSS)
	{

		return $this->toDocument()->has($expression, $type);

	}

	public function saveHtml()
	{

		return $this->toDocument()->saveHtml();

	}

	public function getAttribute($name)
	{

		if ($this->domElement->hasAttribute($name))
		{

			return $this->domElement->getAttribute($name);

		}
		else
		{

			return false;

		}

	}

	public function __get($name)
	{

		switch ($name) {
			case 'innerText':

				return $this->innerText();

				break;
		}

		return $this->getAttribute($name);

	}

	public function __toString()
    {

        return $this->toDocument()->saveHtml();

    }

    public function __invoke($expression)
    {

        return $this->toDocument()->find($expression);;

    }

}