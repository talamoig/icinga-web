<?php
/**
 * @author Christian Doebler <christian.doebler@netways.de>
 */
class Cronks_System_StaticContentModel extends ICINGACronksBaseModel
{

	/*
	 * API variables
	 */
	private $api = false;
	private $globalFilter = array();
	private $templateData = array();

	/*
	 * content variable
	 */
	private $content = array();

	/*
	 * XML variables
	 */
	private $dom = false;
	private $xmlData = array();

	/**
	 * class constructor
	 * @param	void
	 * @return	Cronks_System_StaticContentModel
	 * @author	Christian Doebler <christian.doebler@netways.de>
	 */
	public function __construct () {
		$this->api = AppKitFactories::getInstance()->getFactory('IcingaData');
	}	

	/**
	 * main function to generate content
	 * @param	string			$xmlFile			absolute filename of XML template
	 * @return	unknown_type
	 * @author	Christian Doebler <christian.doebler@netways.de>
	 */
	public function getContent ($xmlFile) {
		$this->getTemplateData($xmlFile);
		$content = $this->processTemplates();

		return $content;
	}

	/*
	 * XML processing
	 */

	/**
	 * loads XML template and converts its data into an array
	 * @param	string			$file				absolute filename of XML template
	 * @return	void
	 * @author	Christian Doebler <christian.doebler@netways.de>
	 */
	private function getTemplateData ($file) {
		$xml = file_get_contents($file);
		$this->dom = new DOMDocument();
		$this->dom->preserveWhiteSpace = false;
		$this->dom->loadXML($xml);

		$this->xmlData = $this->convertDom($this->dom->getElementsByTagName('template')->item(0));
	}

	/**
	 * checks whether XML node for child nodes
	 * @param	DOMElement		$element			element to check for child nodes
	 * @return	boolean								true if element has children otherwise false
	 * @author	Christian Doebler <christian.doebler@netways.de>
	 */
	private function hasChildren (DOMElement &$element) {
		$hasChildren = false;
		if ($element->hasChildNodes()) {
			foreach ($element->childNodes as $node) {
				if ($node->nodeType == XML_ELEMENT_NODE) {
					$hasChildren = true;
					break;
				}
			}
		}

		return $hasChildren;
	}

	/**
	 * converts XML into an associative array
	 * @param	DOMElement		$element			XML node to convert into associative array
	 * @return	array								converted XML data								
	 * @author	Christian Doebler <christian.doebler@netways.de>
	 */
	private function convertDom (DOMElement &$element) {
		$data = array();

		if ($element->hasChildNodes()) {
			foreach ($element->childNodes as $child) {
				if ($child->nodeType == XML_ELEMENT_NODE) {
					$index = '__BAD_INDEX__';
					if ($child->hasAttribute('name')) {
						$index = $child->getAttribute('name');
					} elseif ($child->nodeName == 'datasource') {
						$index = $child->getAttribute('id');
					} else {
						$index = $child->nodeName;
					}

					if ($this->hasChildren($child)) {
						$data[$index] = $this->convertDom($child);
					} else {
						$data[$index] = $child->textContent;
					}
				}
			}
		}

		return $data;
	}

	/*
	 * data fetching
	 */

	/**
	 * fetches data and returns it
	 * @param	string			$dataSourceId		source id to query settings
	 * @param	string			$templateId			source id for template definition
	 * @param	string			$column				column to fetch
	 * @param	array			$filter				additional filter data for query
	 * @return	mixed								retrieved data or false on error
	 * @author	Christian Doebler <christian.doebler@netways.de>
	 */
	private function fetchTemplateValues ($dataSourceId, $templateId, $column, $filter = array()) {
		$success = true;

		if (!array_key_exists($dataSourceId, $this->xmlData['datasources'])) {

			throw new Cronks_System_StaticContentModelException('fetchTemplateValues(): no id in datasource!');
			$success = false;

		} elseif (!array_key_exists($dataSourceId, $this->content[$templateId]['data'])) {

			$dataSource = $this->xmlData['datasources'][$dataSourceId];
			switch ($dataSource['source_type']) {
				case 'IcingaApi':
				default:
					$this->fetchTemplateValuesIcingaApi($dataSourceId, $templateId, $filter);
					break;
			}

		}

		if (array_key_exists($dataSourceId, $this->content[$templateId]['data'])) {
			$success = $this->content[$templateId]['data'][$dataSourceId]['data'][0][$column];
		} else {
			$success = false;
		}

		return $success;
	}

	/**
	 * fetches data via IcingaApi
	 * @param	string			$dataSourceId		source id to query settings
	 * @param	string			$templateId			source id for template definition
	 * @param	array			$additionalFilter	additional filter data for query
	 * @return	boolean								true on success, otherwise false
	 * @author	Christian Doebler <christian.doebler@netways.de>
	 */
	private function fetchTemplateValuesIcingaApi ($dataSourceId, $templateId = false, $additionalFilter = array()) {
		$success = true;

		$dataSource = $this->xmlData['datasources'][$dataSourceId];

		$apiSearch = $this->api->API()->createSearch()->setResultType(IcingaApi::RESULT_ARRAY);
		if (!array_key_exists('target', $dataSource)) {

			throw new Cronks_System_StaticContentModelException('fetchTemplateValues(): no target in datasource!');
			$success = false;

		} else {
		
			// set search target
			$apiSearch->setSearchTarget(constant($dataSource['target']));

			// set result columns
			if (array_key_exists('columns', $dataSource)) {
				$columns = explode(',', $dataSource['columns']);
				foreach ($columns as $currentColumn) {
					$apiSearch->setResultColumns(trim($currentColumn));
				}
			}

			// set search type
			if (array_key_exists('search_type', $dataSource)) {
				$apiSearch->setSearchType(constant($dataSource['search_type']));
			}

			// set search filter
			if (array_key_exists('filter', $dataSource) && array_key_exists('columns', $dataSource['filter'])) {
				foreach ($dataSource['filter'] as $filter) {
					if (!array_key_exists('column', $filter)) {
						throw new Cronks_System_StaticContentModelException('fetchTemplateValues(): no column defined in filter definition!');
						$success = false;
					}
					if ($success && !array_key_exists('value', $filter)) {
						throw new Cronks_System_StaticContentModelException('fetchTemplateValues(): no value defined in filter definition!');
						$success = false;
					}

					if ($success) {
						$filterData = array(
							trim($filter['column']),
							trim($filter['value'])
						);

						if (array_key_exists('match_type', $filter)) {
							array_push($filterData, trim($filter['match_type']));
						}

						$apiSearch->setSearchFilter(array($filterData));
					}
				}
			}

			// set additional search filter
			foreach ($this->globalFilter as $filterData) {
				$apiSearch->setSearchFilter(array($filterData));
			}

			// set additional search filter
			foreach ($additionalFilter as $filterData) {
				$apiSearch->setSearchFilter(array($filterData));
			}

			// execute query and fetch result
			if ($success) {
				// add access control to query
				$secureSearchModels = array(
					'IcingaHostgroup',
					'IcingaServicegroup',
					'IcingaHostCustomVariablePair',
					'IcingaServiceCustomVariablePair'
				);
				IcingaPrincipalTargetTool::applyApiSecurityPrincipals(
					$secureSearchModels,
					$apiSearch
				);

				// fetch data
				$apiRes = $apiSearch->fetch()->getAll();

				// set function
				if (array_key_exists('function', $dataSource)) {
					$function = $dataSource['function'];
				} else {
					$function = false;
				}

				// set result data
				$numResults = count($apiRes);
				$offset = ($numResults > 0) ? 0 : -1;

				$resultData = array(
					'data'			=> $apiRes,
					'function'		=> $function,
				);

				if ($templateId !== false) {
					if (!array_key_exists($templateId, $this->content)) {
						$this->content[$templateId] = array('data' => array());
					} elseif (!array_key_exists('data', $this->content[$templateId])) {
						$this->content[$templateId]['data'] = array();
					}
					$this->content[$templateId]['data'][$dataSourceId] = $resultData;
				}
			}

		}

		return $success;
	}

	/**
	 * applies post-processing function to fetched value
	 * @param	mixed			$value					value to post-process
	 * @param	array			$function				function definition
	 * @return	mixed									processed value
	 * @author	Christian Doebler <christian.doebler@netways.de>
	 */
	private function applyFunction ($value, array $function) {
		if (array_key_exists('name', $function)) {
			switch ($function['name']) {
				case 'round':
					if (array_key_exists('param', $function)) {
						$precision = (int)$function['param'];
					} else {
						$precision = 0;
					}
					$value = round($value, $precision);
					break;
			}
		} else {
			throw new Cronks_System_StaticContentModelException('applyFunction(): no function name defined!');
		}

		return $value;
	}

	/*
	 * template parsing
	 */

	/**
	 * calls the template generator for each template
	 * @param	void
	 * @return	string								processed content
	 * @author	Christian Doebler <christian.doebler@netways.de>
	 */
	private function processTemplates () {
		$content = null;

		if (array_key_exists('template_code', $this->xmlData)) {
			if (is_array($this->xmlData) && array_key_exists('MAIN', $this->xmlData['template_code'])) {
				$this->createTemplateContent('MAIN');
			} else {
				throw new Cronks_System_StaticContentModelException('processTemplates(): no template "MAIN" defined!');
			}
		} else {
			throw new Cronks_System_StaticContentModelException('processTemplates(): no template_code defined!');
		}

		return $this->content['MAIN']['content'];
	}

	/**
	 * generates content from template and fetched data
	 * @param	string			$tplId				id of content template
	 * @param	string			$filter				additional filter data for query
	 * @return	void
	 * @author	Christian Doebler <christian.doebler@netways.de>
	 */
	private function createTemplateContent ($tplId, $filter = false) {
		// init content array
		if ($tplId == 'MAIN' && !array_key_exists($tplId, $this->content)) {
			$this->content[$tplId] = array(
				'content'	=> null,
				'data'		=> array()
			);
		} else {
			$this->content[$tplId] = array(
				'content'	=> null,
				'data'		=> array()
			);
		}

		$content = $this->xmlData['template_code'][$tplId];

		// fetch remaining variables from template and call substitution routine
		// for variables
		$variablePattern = '/\${([A-Z0-9_\-]+):([A-Z_]+)(:[^}]+)?}/s';
		preg_match_all($variablePattern, $content, $templateVariables);
		$content = $this->substituteTemplateVariables($tplId, $templateVariables, false, $filter);

		// fetch remaining variables from template and call substitution routine
		// for processed content
		$variablePattern = '/\${([a-z0-9_\-]+)(:[^}]+)?}/s';
		preg_match_all($variablePattern, $content, $templateVariables);
		$numMatches = count($templateVariables[0]);
		for ($x = 0; $x < $numMatches; $x++) {
			if (!empty($templateVariables[2][$x])) {
				$currentFilter = trim($templateVariables[2][$x]);
				if ($tplId != 'MAIN') {
					
				}
			} else {
				$currentFilter = false;
			}

			if ($tplId == 'MAIN') {
				$this->globalFilter = array();
			} elseif (!empty($filter)) {
				$this->addToGlobalFilter($filter);
			}

			$this->createTemplateContent($templateVariables[1][$x], $currentFilter);
			$content = $this->substituteTemplateVariablesByProcessedContent(
				$templateVariables[0][$x],
				$templateVariables[1][$x],
				$content
			);
		}

		$this->content[$tplId]['content'] .= $content;
	}

	/**
	 * adds filter data to global filter to provide inheritance of filters
	 * @param	mixed			$filterRaw			filter to add to global filter as string or array
	 * @return	void
	 * @author	Christian Doebler <christian.doebler@netways.de>
	 */
	private function addToGlobalFilter ($filterRaw) {
		if (!is_array($filterRaw)) {
			$filterArr = explode(':', substr($filterRaw, 1));
			foreach ($filterArr as $currentFilter) {
				$filterTmp = explode(',', $currentFilter);
				array_push($this->globalFilter, $filterTmp);
			}
		} else {
			foreach ($filterArr as $currentFilter) {
				array_push($this->globalFilter, $currentFilter);
			}
		}
	}

	/**
	 * substitutes template variables by already processed content
	 * @param	string			$tplVar				variable to replace in content template
	 * @param	string			$tplId				id of content template
	 * @param	string			$content			content to process (optional)
	 * @return	string								processed template
	 * @author	Christian Doebler <christian.doebler@netways.de>
	 */
	private function substituteTemplateVariablesByProcessedContent ($tplVar, $tplId, $content = false) {
		if ($content === false) {
			$content = $this->xmlData['template_code'][$tplId];
		}

		$content = str_replace(
			$tplVar,
			$this->content[$tplId]['content'],
			$content
		);

		return $content;
	}

	/**
	 * substitutes template variables by fetched data
	 * @param	string			$tplId				id of content template
	 * @param	array			$templateVariables	template variables extracted via preg_match_all
	 * @param	string			$content			content to process (optional)
	 * @param	string			$filter				additional filter data for query
	 * @return	string								processed template
	 * @author	Christian Doebler <christian.doebler@netways.de>
	 */
	private function substituteTemplateVariables ($tplId, $templateVariables, $content = false, $filter = false) {
		// fetch template
		if ($content === false) {
			$content = $this->xmlData['template_code'][$tplId];
		}

		// determine number of found template variables
		$numMatches = count($templateVariables[0]);

		// replace template variables by found values
		for ($x = 0; $x < $numMatches; $x++) {
			$id = $templateVariables[1][$x];
			$column = $templateVariables[2][$x];
			$outputWrapperFunction = $templateVariables[3][$x];

			// prepare filter
			if ($filter !== false) {
				if (!is_array($filter)) {
					$filterArr = explode(':', substr($filter, 1));
					$filter = array();
					foreach ($filterArr as $currentFilter) {
						$filterTmp = explode(',', $currentFilter);
						array_push($filter, $filterTmp);
					}
				}
			} else {
				$filter = array();
			}

			// determine template values and set them
			$substitution = $this->fetchTemplateValues($id, $tplId, $column, $filter);

			// apply post-fetching function
			if (array_key_exists($id, $this->content[$tplId]['data'])) {
				$templateData = $this->content[$tplId]['data'][$id];

				// apply function
				if ($templateData['function'] !== false) {
					$substitution = $this->applyFunction(
						$substitution,
						$templateData['function']
					);
				}
			}

			// apply output wrapper
			if (!empty($outputWrapperFunction)) {
				$funcDef = explode(':', $outputWrapperFunction);
				eval("\$funcInstance = $funcDef[1]::getInstance();");

				$funcCall = str_replace('__VALUE__', $substitution, $funcDef[2]);
				eval("\$substitution = \$funcInstance->$funcCall;");
			}

			$content = str_replace(
				$templateVariables[0][$x],
				$substitution,
				$content
			);
		}

		return $content;
	}

}

class Cronks_System_StaticContentModelException extends AppKitException {}

?>